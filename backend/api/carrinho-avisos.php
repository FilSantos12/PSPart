<?php
/**
 * POST /backend/api/carrinho-avisos.php
 * Avisos das linhas do drawer do carrinho. Somente leitura.
 * - avisos (Patch L): estoque parcial — parte do item vira sob encomenda.
 * - bloqueios (Patch K): item que não pode seguir para o checkout (esgotado, indisponível ou
 *   quantidade acima da pronta entrega sem excedente permitido). O drawer trava o avanço.
 *
 * Body JSON: { "cart": [{"produto_id": 1, "quantidade": 2}, ...] }
 * Resposta:  { "avisos": { "1": "Parte deste item é sob encomenda (...)..." },
 *              "bloqueios": { "2": "\"Sensor\" está esgotado e não pode ser comprado no momento." } }
 *
 * Os textos saem prontos do helper (disponibilidade_aviso_parcial() / disponibilidade_validar_item(),
 * a mesma validação do checkout) — o JS não recebe estoque, não compara quantidade com estoque e
 * não monta texto. Item sem aviso/bloqueio simplesmente não aparece no mapa.
 */

require_once __DIR__ . '/_core.php';
require_once __DIR__ . '/../helpers/disponibilidade.php';

exigir_metodo('POST');

$body = body_json();
$cart = $body['cart'] ?? [];
if (!is_array($cart) || count($cart) > 50) json_erro('Carrinho inválido.', 422);

// Só id/quantidade inteiros e positivos; quantidade limitada ao teto de sanidade (QTD_MAX_SANIDADE)
$itens = [];
foreach ($cart as $item) {
    $id  = filter_var($item['produto_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $qtd = filter_var($item['quantidade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => QTD_MAX_SANIDADE]]);
    if ($id === false || $qtd === false) json_erro('Item do carrinho inválido.', 422);
    $itens[$id] = $qtd;
}

try {
    $avisos    = [];
    $bloqueios = [];
    if ($itens) {
        $pdo  = getDB();
        $stmt = $pdo->prepare('
            SELECT nome, estoque, modo_venda, permite_venda_sem_estoque, prazo_encomenda_dias
            FROM produtos WHERE id = ? AND ativo = 1
        ');
        foreach ($itens as $id => $qtd) {
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) continue;
            $bloqueio = disponibilidade_validar_item($p['nome'], $qtd, disponibilidade_produto($p));
            if ($bloqueio !== null) {
                $bloqueios[(string) $id] = $bloqueio;
                continue;
            }
            $texto = disponibilidade_aviso_parcial($p, $qtd);
            if ($texto !== null) $avisos[(string) $id] = $texto;
        }
    }
    // Objeto JSON mesmo vazio (nunca [])
    json_ok(['avisos' => (object) $avisos, 'bloqueios' => (object) $bloqueios]);

} catch (Exception $e) {
    json_erro('Erro ao consultar disponibilidade.', 500);
}
