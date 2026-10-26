<?php
/**
 * Fonte única da disponibilidade de produto (Patch I).
 *
 * Toda decisão de "pode comprar?", rótulo e cor do badge sai daqui — API da loja,
 * checkout (pedido-carrinho.php / pedidos.php), listagem e preview do admin.
 * O JS só renderiza o objeto já resolvido; nunca recompõe a matriz.
 */

const LIMIAR_ULTIMAS_UNIDADES = 3;

// Valores aceitos em produtos.modo_venda → rótulo exibido no admin
const MODOS_VENDA = [
    'automatico'           => 'Automático (pelo estoque)',
    'sempre_sob_encomenda' => 'Sempre sob encomenda',
    'indisponivel'         => 'Indisponível',
];

// Contato comercial citado na mensagem de excedente — espelha a seção #contato do
// index.html e as constantes EMPRESA_* do script.js (o PHP não enxerga as do JS).
const DISPONIBILIDADE_CONTATO_EMAIL    = 'vendas@pentasis.com.br';
const DISPONIBILIDADE_CONTATO_TELEFONE = '(15) 3263-9494';

/**
 * Resolve a disponibilidade de um produto a partir das colunas do banco.
 *
 * @param array $p Linha de produtos (estoque, modo_venda, permite_venda_sem_estoque, prazo_encomenda_dias)
 * @return array{status:string,label:string,badge_class:string,prazo_dias:?int,prazo_texto:string,
 *               pode_comprar:bool,permite_excedente:bool,estoque_disponivel:int}
 */
function disponibilidade_produto(array $p): array
{
    $estoque = max(0, (int) ($p['estoque'] ?? 0));  // NULL → 0
    $modo    = $p['modo_venda'] ?? 'automatico';
    $permite = !empty($p['permite_venda_sem_estoque']);
    $prazo   = (isset($p['prazo_encomenda_dias']) && (int) $p['prazo_encomenda_dias'] > 0)
        ? (int) $p['prazo_encomenda_dias']
        : null;

    // Prazo nunca é inventado: sem valor cadastrado, o cliente é orientado a consultar
    $prazoTexto = $prazo === null
        ? 'consulte prazo'
        : '~' . $prazo . ' ' . ($prazo === 1 ? 'dia útil' : 'dias úteis');
    $labelEncomenda = $prazo === null
        ? 'Sob encomenda — consulte prazo'
        : 'Sob encomenda — prazo ' . $prazoTexto;

    $base = [
        'prazo_dias'         => $prazo,
        'prazo_texto'        => $prazoTexto,
        'estoque_disponivel' => $estoque,
    ];

    if ($modo === 'sempre_sob_encomenda') {
        return $base + [
            'status' => 'sob_encomenda', 'label' => $labelEncomenda, 'badge_class' => 'stock-badge--warn',
            'pode_comprar' => true, 'permite_excedente' => true,
        ];
    }

    // 'indisponivel' — e qualquer valor fora da lista (falha fechada, nunca libera venda)
    if ($modo !== 'automatico') {
        return $base + [
            'status' => 'indisponivel', 'label' => 'Indisponível', 'badge_class' => 'stock-badge--req',
            'pode_comprar' => false, 'permite_excedente' => false,
        ];
    }

    if ($estoque > LIMIAR_ULTIMAS_UNIDADES) {
        return $base + [
            'status' => 'pronta_entrega', 'label' => "Pronta entrega ($estoque un.)", 'badge_class' => 'stock-badge--ok',
            'pode_comprar' => true, 'permite_excedente' => $permite,
        ];
    }

    if ($estoque > 0) {
        return $base + [
            'status' => 'ultimas_unidades', 'label' => "Pronta entrega — últimas $estoque un.", 'badge_class' => 'stock-badge--warn',
            'pode_comprar' => true, 'permite_excedente' => $permite,
        ];
    }

    if ($permite) {
        return $base + [
            'status' => 'sob_encomenda', 'label' => $labelEncomenda, 'badge_class' => 'stock-badge--warn',
            'pode_comprar' => true, 'permite_excedente' => true,
        ];
    }

    return $base + [
        'status' => 'esgotado', 'label' => 'Esgotado', 'badge_class' => 'stock-badge--req',
        'pode_comprar' => false, 'permite_excedente' => false,
    ];
}

/**
 * Valida uma quantidade pedida contra a disponibilidade. Retorna a mensagem de bloqueio
 * (identificando a peça) ou null quando a compra pode seguir.
 */
function disponibilidade_validar_item(string $nome, int $quantidade, array $disp): ?string
{
    if (!$disp['pode_comprar']) {
        $label = mb_strtolower(mb_substr($disp['label'], 0, 1)) . mb_substr($disp['label'], 1);
        return "\"{$nome}\" está {$label} e não pode ser comprado no momento.";
    }

    if (!$disp['permite_excedente'] && $quantidade > $disp['estoque_disponivel']) {
        return "Temos {$disp['estoque_disponivel']} un. de \"{$nome}\" em pronta entrega. "
             . "Ajuste a quantidade ou fale com o comercial para o restante: "
             . DISPONIBILIDADE_CONTATO_EMAIL . ' · ' . DISPONIBILIDADE_CONTATO_TELEFONE . '.';
    }

    return null;
}

/**
 * Lê e valida os campos de disponibilidade vindos do formulário do admin.
 * modo_venda é conferido contra MODOS_VENDA aqui no servidor, não só no <select>.
 *
 * @return array{modo_venda:string,permite_venda_sem_estoque:int,prazo_encomenda_dias:?int}
 */
function disponibilidade_dados_post(array $post, array &$erros): array
{
    $modo = trim((string) ($post['modo_venda'] ?? ''));
    if (!array_key_exists($modo, MODOS_VENDA)) {
        $erros[] = 'Modo de venda inválido.';
        $modo = 'automatico';
    }

    // Checkbox só tem efeito em 'automatico' — fora dele não fica estado escondido gravado
    $permite = ($modo === 'automatico' && !empty($post['permite_venda_sem_estoque'])) ? 1 : 0;

    $prazoRaw = trim((string) ($post['prazo_encomenda_dias'] ?? ''));
    $prazo    = null;
    if ($prazoRaw !== '') {
        if (!ctype_digit($prazoRaw) || (int) $prazoRaw < 1 || (int) $prazoRaw > 365) {
            $erros[] = 'Prazo sob encomenda inválido (dias úteis entre 1 e 365, ou vazio para "consulte prazo").';
        } else {
            $prazo = (int) $prazoRaw;
        }
    }

    return [
        'modo_venda'                => $modo,
        'permite_venda_sem_estoque' => $permite,
        'prazo_encomenda_dias'      => $prazo,
    ];
}
