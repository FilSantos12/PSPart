<?php
/**
 * POST /backend/admin/ajax-disponibilidade.php — preview ao vivo do badge de disponibilidade
 * no formulário de produto. Roda o MESMO helper da loja (disponibilidade_produto()); o JS do
 * admin só exibe label_admin/badge_class devolvidos, nunca compõe o rótulo.
 *
 * Body JSON: { estoque, modo_venda, permite_venda_sem_estoque, prazo_encomenda_dias,
 *              exige_consulta, qtd_consulta }   — devolve também texto_consulta (Patch N)
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../helpers/disponibilidade.php';

header('Content-Type: application/json; charset=UTF-8');

function ajaxOk(mixed $data = null): void {
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function ajaxErro(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'erro' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') ajaxErro('Método não permitido.', 405);

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) ajaxErro('JSON inválido.');

// Mesma validação do POST do formulário — preview nunca mostra um estado que o salvar recusaria
$erros = [];
$disp  = disponibilidade_dados_post($body, $erros);
$estoqueRaw = $body['estoque'] ?? '';
if (!is_numeric($estoqueRaw) || $estoqueRaw < 0) $erros[] = 'Estoque inválido.';
if ($erros) ajaxErro(implode(' ', $erros), 422);

$r = disponibilidade_produto(['estoque' => (int) $estoqueRaw] + $disp);
ajaxOk(['status' => $r['status'], 'label_admin' => $r['label_admin'], 'badge_class' => $r['badge_class'], 'pode_comprar' => $r['pode_comprar'],
        'texto_consulta' => $r['texto_consulta']]);
