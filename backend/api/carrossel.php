<?php
/**
 * GET /backend/api/carrossel.php
 * Retorna as campanhas ativas E vigentes (hoje dentro de [data_inicio, data_fim],
 * tratando nulls como aberto), ordenadas por `ordem`. Cálculo de vigência é
 * sempre server-side — nunca confiado do client.
 */
require_once __DIR__ . '/_core.php';

exigir_metodo('GET');

$pdo  = getDB();
$hoje = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT id, titulo, arquivo, link_destino
    FROM carrossel_campanhas
    WHERE ativo = 1
      AND (data_inicio IS NULL OR data_inicio <= :hoje1)
      AND (data_fim    IS NULL OR data_fim    >= :hoje2)
    ORDER BY ordem ASC, id ASC
");
$stmt->execute([':hoje1' => $hoje, ':hoje2' => $hoje]);

json_ok($stmt->fetchAll());
