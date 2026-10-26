<?php
/**
 * GET /backend/api/produtos.php
 * Retorna todos os produtos ativos com imagens e data sheet.
 */

require_once __DIR__ . '/_core.php';
require_once __DIR__ . '/../helpers/disponibilidade.php';

exigir_metodo('GET');

try {
    $pdo  = getDB();
    $stmt = $pdo->query("
        SELECT id, nome, descricao, preco, categoria, imagem, estoque, codigo_interno, datasheet, especificacao_tecnica,
               modo_venda, permite_venda_sem_estoque, prazo_encomenda_dias
        FROM produtos
        WHERE ativo = 1
        ORDER BY id ASC
    ");

    $produtos = $stmt->fetchAll();

    // Busca todas as imagens em uma única query (evita N+1)
    $allImgs = $pdo->query("
        SELECT produto_id, caminho, ordem, principal
        FROM produto_imagens
        ORDER BY produto_id ASC, ordem ASC, id ASC
    ")->fetchAll();

    $imgsByProduct = [];
    foreach ($allImgs as $img) {
        $pid = (int) $img['produto_id'];
        $imgsByProduct[$pid][] = [
            'caminho'   => $img['caminho'],
            'ordem'     => (int) $img['ordem'],
            'principal' => (int) $img['principal'],
        ];
    }

    foreach ($produtos as &$p) {
        $p['id']      = (int)   $p['id'];
        $p['preco']   = (float) $p['preco'];
        $p['estoque'] = (int)   $p['estoque'];
        $p['imagens'] = $imgsByProduct[$p['id']] ?? [];
        // Disponibilidade já resolvida no servidor — o front só renderiza, nunca recompõe a matriz
        $p['disponibilidade'] = disponibilidade_produto($p);
        unset($p['modo_venda'], $p['permite_venda_sem_estoque'], $p['prazo_encomenda_dias']);
    }

    json_ok($produtos);

} catch (Exception $e) {
    json_erro('Erro ao buscar produtos.', 500);
}
