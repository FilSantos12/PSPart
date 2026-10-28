<?php
/**
 * Fonte única da disponibilidade de produto (Patch I).
 *
 * Toda decisão de "pode comprar?", rótulo e cor do badge sai daqui — API da loja,
 * checkout (pedido-carrinho.php / pedidos.php), listagem e preview do admin.
 * O JS só renderiza o objeto já resolvido; nunca recompõe a matriz.
 */

const LIMIAR_ULTIMAS_UNIDADES = 3;

// Teto TÉCNICO de quantidade por SKU (Patch N) — sanidade, não regra comercial. Travado no
// servidor (frete_carrinho.php sobre a quantidade agregada, pedidos.php, carrinho-avisos.php).
// Espelha QTD_MAX_SANIDADE do script.js (o PHP não enxerga as do JS).
const QTD_MAX_SANIDADE = 99;

// Valor sugerido no admin para produtos.qtd_consulta (limiar COMERCIAL, só informativo —
// não limita a compra). Usado somente pelo partial _disponibilidade-campos.php.
const QTD_CONSULTA_SUGERIDA = 10;

// Valores aceitos em produtos.modo_venda → rótulo exibido no admin
const MODOS_VENDA = [
    'automatico'           => 'Automático (pelo estoque)',
    'sempre_sob_encomenda' => 'Sempre sob encomenda',
    'indisponivel'         => 'Indisponível',
];

// Condição de entrega do preço de exportação (Patch J) — constante do SISTEMA, não campo por
// produto. Os preços em preco_export_usd são cadastrados nesta condição: trocar o Incoterm exige
// revisar todos eles. Espelha INCOTERM_PADRAO do script.js (o PHP não enxerga as do JS).
const INCOTERM_PADRAO = 'EXW Boituva';

// Contato comercial citado na mensagem de excedente — espelha a seção #contato do
// index.html e as constantes EMPRESA_* do script.js (o PHP não enxerga as do JS).
const DISPONIBILIDADE_CONTATO_EMAIL    = 'vendas@pentasis.com.br';
const DISPONIBILIDADE_CONTATO_TELEFONE = '(15) 3263-9494';

/**
 * Resolve a disponibilidade de um produto a partir das colunas do banco.
 *
 * @param array $p Linha de produtos (estoque, modo_venda, permite_venda_sem_estoque, prazo_encomenda_dias)
 * 'label' é a versão do cliente (sem quantidade de estoque); 'label_admin' é a do admin (com a
 * quantidade). 'qtd_exibivel' é o único número de estoque que pode chegar ao cliente — só em
 * ultimas_unidades (urgência legítima), null nos demais. 'estoque_disponivel' é de uso interno
 * (checkout, aviso parcial) e nunca vai ao front — a API remove o campo.
 *
 * 'qtd_consulta' / 'texto_consulta' / 'faixas_consulta' (Patch N): limiar COMERCIAL por produto,
 * só informativo — não bloqueia a compra. Todos null quando produtos.qtd_consulta é NULL.
 *
 * 'preco_export' / 'preco_export_label' (Patch J): segunda lista de preço (USD, INCOTERM_PADRAO).
 * Nunca conversão de câmbio — sem preco_export_usd cadastrado, o produto é "Sob consulta".
 *
 * @return array{status:string,label:string,label_admin:string,qtd_exibivel:?int,badge_class:string,
 *               prazo_dias:?int,prazo_texto:string,pode_comprar:bool,permite_excedente:bool,
 *               estoque_disponivel:int,qtd_consulta:?int,texto_consulta:?string,
 *               faixas_consulta:?array{ate:string,acima:string,acima_acao:string},
 *               preco_export:?string,preco_export_label:string}
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
        'qtd_exibivel'       => null,
        'prazo_dias'         => $prazo,
        'prazo_texto'        => $prazoTexto,
        'estoque_disponivel' => $estoque,
    ] + disponibilidade_consulta($p) + disponibilidade_preco_export($p);

    if ($modo === 'sempre_sob_encomenda') {
        return $base + [
            'status' => 'sob_encomenda', 'label' => $labelEncomenda, 'label_admin' => $labelEncomenda,
            'badge_class' => 'stock-badge--warn', 'pode_comprar' => true, 'permite_excedente' => true,
        ];
    }

    // 'indisponivel' — e qualquer valor fora da lista (falha fechada, nunca libera venda)
    if ($modo !== 'automatico') {
        return $base + [
            'status' => 'indisponivel', 'label' => 'Indisponível', 'label_admin' => 'Indisponível',
            'badge_class' => 'stock-badge--req', 'pode_comprar' => false, 'permite_excedente' => false,
        ];
    }

    if ($estoque > LIMIAR_ULTIMAS_UNIDADES) {
        return $base + [
            'status' => 'pronta_entrega', 'label' => 'Pronta entrega', 'label_admin' => "Pronta entrega ($estoque un.)",
            'badge_class' => 'stock-badge--ok', 'pode_comprar' => true, 'permite_excedente' => $permite,
        ];
    }

    if ($estoque > 0) {
        $labelUltimas = "Pronta entrega — últimas $estoque un.";
        return ['qtd_exibivel' => $estoque] + $base + [
            'status' => 'ultimas_unidades', 'label' => $labelUltimas, 'label_admin' => $labelUltimas,
            'badge_class' => 'stock-badge--warn', 'pode_comprar' => true, 'permite_excedente' => $permite,
        ];
    }

    if ($permite) {
        return $base + [
            'status' => 'sob_encomenda', 'label' => $labelEncomenda, 'label_admin' => $labelEncomenda,
            'badge_class' => 'stock-badge--warn', 'pode_comprar' => true, 'permite_excedente' => true,
        ];
    }

    return $base + [
        'status' => 'esgotado', 'label' => 'Esgotado', 'label_admin' => 'Esgotado',
        'badge_class' => 'stock-badge--req', 'pode_comprar' => false, 'permite_excedente' => false,
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
        return disponibilidade_msg_excedente($nome);
    }

    return null;
}

/**
 * Mensagem de excedente não permitido. Também usada pela baixa condicional do checkout
 * (Patch K) quando outro pedido leva o estoque entre a validação e a transação.
 * Sem quantidade de estoque: a mensagem chega ao cliente (HTTP 422 do checkout).
 */
function disponibilidade_msg_excedente(string $nome): string
{
    return "A quantidade pedida de \"{$nome}\" está acima do disponível em pronta entrega. "
         . "Reduza a quantidade ou fale com o comercial: "
         . DISPONIBILIDADE_CONTATO_EMAIL . ' · ' . DISPONIBILIDADE_CONTATO_TELEFONE . '.';
}

/**
 * Texto do aviso de estoque parcial do drawer: a quantidade pedida passa do estoque em pronta
 * entrega e o excedente é permitido (vira sob encomenda). Nenhum número de estoque no texto.
 * Null quando não há parcial (tudo em pronta entrega, tudo sob encomenda ou não comprável).
 *
 * @param array $p Linha de produtos (mesmas colunas de disponibilidade_produto())
 */
function disponibilidade_aviso_parcial(array $p, int $quantidade): ?string
{
    $disp = disponibilidade_produto($p);
    $parcial = $disp['permite_excedente']
        && in_array($disp['status'], ['pronta_entrega', 'ultimas_unidades'], true)
        && $quantidade > $disp['estoque_disponivel'];
    if (!$parcial) return null;

    $prazo = $disp['prazo_dias'] === null ? 'consulte o prazo' : $disp['prazo_texto'];
    return "Parte deste item é sob encomenda ({$prazo}). O restante tem pronta entrega.";
}

/**
 * Limiar de consulta comercial (Patch N) — textos do card ('texto_consulta') e das linhas da
 * tabela de faixas do modal ('faixas_consulta'). Fonte única desses textos; o JS só renderiza.
 */
function disponibilidade_consulta(array $p): array
{
    $n = (isset($p['qtd_consulta']) && (int) $p['qtd_consulta'] >= 1) ? (int) $p['qtd_consulta'] : null;
    if ($n === null) {
        return ['qtd_consulta' => null, 'texto_consulta' => null, 'faixas_consulta' => null];
    }
    return [
        'qtd_consulta'    => $n,
        'texto_consulta'  => "Acima de {$n} un.: consulte o comercial",
        'faixas_consulta' => [
            'ate'        => $n === 1 ? '1 un.' : "1 a {$n} un.",
            'acima'      => "Acima de {$n} un.",
            'acima_acao' => 'Consulte o comercial',
        ],
    ];
}

/**
 * Preço de exportação (Patch J) — SEGUNDA LISTA DE PREÇO em USD, cadastrada pelo admin na condição
 * INCOTERM_PADRAO. Nunca é derivada do preço em reais (sem câmbio). Fonte única destes textos.
 *
 * @return array{preco_export:?string,preco_export_label:string}
 */
function disponibilidade_preco_export(array $p): array
{
    $usd = (isset($p['preco_export_usd']) && is_numeric($p['preco_export_usd']))
        ? (float) $p['preco_export_usd']
        : null;
    $valor = $usd === null ? null : 'US$ ' . number_format($usd, 2, ',', '.');
    return [
        'preco_export'       => $valor,
        'preco_export_label' => ($valor ?? 'Sob consulta') . ' · ' . INCOTERM_PADRAO,
    ];
}

/**
 * Lê e valida o preço de exportação vindo do formulário do admin (Patch J). Devolve o valor a
 * gravar (null = vazio, "Sob consulta") e acrescenta erro se não for numérico ≥ 0.
 * Validado aqui no servidor, não só no <input>.
 */
function preco_export_dados_post(array $post, array &$erros): ?float
{
    $raw = trim((string) ($post['preco_export_usd'] ?? ''));
    if ($raw === '') return null;
    if (!is_numeric($raw) || (float) $raw < 0) {
        $erros[] = 'Preço de exportação (USD) inválido (número maior ou igual a zero, ou vazio para "Sob consulta").';
        return null;
    }
    return round((float) $raw, 2);
}

/**
 * Lê e valida os campos de disponibilidade vindos do formulário do admin.
 * modo_venda é conferido contra MODOS_VENDA aqui no servidor, não só no <select>.
 *
 * @return array{modo_venda:string,permite_venda_sem_estoque:int,prazo_encomenda_dias:?int,qtd_consulta:?int}
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

    // Limiar de consulta (Patch N): checkbox desmarcado grava NULL — o número escondido é ignorado
    $consulta = null;
    if (!empty($post['exige_consulta'])) {
        $consultaRaw = trim((string) ($post['qtd_consulta'] ?? ''));
        if (!ctype_digit($consultaRaw) || (int) $consultaRaw < 1 || (int) $consultaRaw > QTD_MAX_SANIDADE) {
            $erros[] = 'Quantidade para consulta inválida (inteiro entre 1 e ' . QTD_MAX_SANIDADE . ').';
        } else {
            $consulta = (int) $consultaRaw;
        }
    }

    return [
        'modo_venda'                => $modo,
        'permite_venda_sem_estoque' => $permite,
        'prazo_encomenda_dias'      => $prazo,
        'qtd_consulta'              => $consulta,
    ];
}
