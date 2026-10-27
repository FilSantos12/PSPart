<?php
/**
 * POST /backend/api/carrinho-avisos.php
 * Avisos de estoque parcial das linhas do drawer do carrinho (Patch L). Somente leitura.
 *
 * Body JSON: { "cart": [{"produto_id": 1, "quantidade": 2}, ...] }
 * Resposta:  { "avisos": { "1": "Parte deste item é sob encomenda (...)..." } }
 *
 * O texto sai pronto de disponibilidade_aviso_parcial() — o JS não recebe estoque, não compara
 * quantidade com estoque e não monta texto. Item sem aviso simplesmente não aparece no mapa.
 */

require_once __DIR__ . '/_core.php';
require_once __DIR__ . '/../helpers/disponibilidade.php';

exigir_metodo('POST');

$body = body_json();
$cart = $body['cart'] ?? [];
if (!is_array($cart) || count($cart) > 50) json_erro('Carrinho inválido.', 422);

// Só id/quantidade inteiros e positivos; quantidade limitada ao teto de volume do carrinho
$itens = [];
foreach ($cart as $item) {
    $id  = filter_var($item['produto_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $qtd = filter_var($item['quantidade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => QTD_LIMITE_VOLUME]]);
    if ($id === false || $qtd === false) json_erro('Item do carrinho inválido.', 422);
    $itens[$id] = $qtd;
}

try {
    $avisos = [];
    if ($itens) {
        $pdo  = getDB();
        $stmt = $pdo->prepare('
            SELECT estoque, modo_venda, permite_venda_sem_estoque, prazo_encomenda_dias
            FROM produtos WHERE id = ? AND ativo = 1
        ');
        foreach ($itens as $id => $qtd) {
            $stmt->execute([$id]);
            $p = $stmt->fetch();
            if (!$p) continue;
            $texto = disponibilidade_aviso_parcial($p, $qtd);
            if ($texto !== null) $avisos[(string) $id] = $texto;
        }
    }
    // Objeto JSON mesmo vazio (nunca [])
    json_ok(['avisos' => (object) $avisos]);

} catch (Exception $e) {
    json_erro('Erro ao consultar disponibilidade.', 500);
}
