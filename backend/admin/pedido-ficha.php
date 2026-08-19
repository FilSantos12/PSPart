<?php
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDB();
$id  = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = :id");
$stmt->execute([':id' => $id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    header('Location: pedidos.php');
    exit;
}

$numeroPedido = $pedido['numero_pedido'] ?? $id;

$stmtItens = $pdo->prepare("
    SELECT ip.quantidade, pr.nome AS produto_nome, pr.codigo_interno
    FROM itens_pedido ip
    JOIN produtos pr ON pr.id = ip.produto_id
    WHERE ip.pedido_id = :id
    ORDER BY ip.id ASC
");
$stmtItens->execute([':id' => $id]);
$itens = $stmtItens->fetchAll(PDO::FETCH_ASSOC);

$stmtTracking = $pdo->prepare("
    SELECT carrier, chosen_carrier, chosen_service, shipping_price
    FROM order_tracking WHERE order_id = :oid LIMIT 1
");
$stmtTracking->execute([':oid' => (string) $id]);
$tracking = $stmtTracking->fetch(PDO::FETCH_ASSOC);

$statusLabel = match($pedido['status']) {
    'aprovado'         => 'Aprovado',
    'pendente'         => 'Pendente',
    'em_analise'       => 'Em Análise',
    'recusado'         => 'Recusado',
    'cancelado'        => 'Cancelado',
    'reembolsado'      => 'Reembolsado',
    'contestado'       => 'Contestado',
    'em_processamento' => 'Em Processamento',
    default            => ucfirst($pedido['status']),
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ficha de Separação — Pedido #<?= htmlspecialchars($numeroPedido) ?></title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; font-size: 13px; color: #222; background: #fff; padding: 24px; }
    .print-controls { margin-bottom: 18px; display: flex; gap: 8px; }
    .btn-print { background: #274185; color: #fff; border: none; padding: 8px 18px; border-radius: 6px; font-size: 13px; cursor: pointer; }
    .btn-fechar { background: #6c757d; color: #fff; border: none; padding: 8px 18px; border-radius: 6px; font-size: 13px; cursor: pointer; }
    .ficha-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 2px solid #274185; padding-bottom: 12px; }
    .ficha-empresa { font-size: 18px; font-weight: bold; color: #274185; }
    .ficha-empresa small { display: block; font-size: 11px; color: #666; font-weight: normal; margin-top: 2px; }
    .ficha-pedido { text-align: right; font-size: 12px; color: #444; line-height: 1.6; }
    .ficha-pedido strong { font-size: 16px; color: #274185; display: block; }
    .info-bar { display: flex; gap: 24px; font-size: 12px; color: #555; margin-bottom: 16px; padding: 8px 12px; background: #f4f6fb; border-radius: 6px; }
    .info-bar span strong { color: #222; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th { background: #274185; color: #fff; padding: 10px 8px; text-align: left; font-size: 12px; }
    td { padding: 9px 8px; border-bottom: 1px solid #ddd; font-size: 13px; }
    td.codigo { font-family: monospace; font-size: 12px; color: #444; }
    td.qtd { font-size: 16px; font-weight: bold; text-align: center; }
    td.check { text-align: center; font-size: 20px; color: #bbb; }
    .entrega-bloco { margin-top: 16px; padding: 10px 12px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 12px; line-height: 1.6; }
    .entrega-bloco strong { color: #274185; }
    .ficha-footer { margin-top: 20px; font-size: 11px; color: #aaa; text-align: center; border-top: 1px solid #eee; padding-top: 10px; }
    @media print {
      .print-controls { display: none !important; }
      body { padding: 0; }
    }
  </style>
</head>
<body>

<div class="print-controls">
  <button class="btn-print" onclick="window.print()">&#x1F5A8; Imprimir</button>
  <button class="btn-fechar" onclick="window.close()">Fechar</button>
</div>

<div class="ficha-header">
  <div class="ficha-empresa">
    PSPart
    <small>Partes e Peças Automação</small>
  </div>
  <div class="ficha-pedido">
    <strong>Pedido #<?= htmlspecialchars($numeroPedido) ?></strong>
    <?= date('d/m/Y H:i', strtotime($pedido['criado_em'])) ?><br>
    Status: <?= htmlspecialchars($statusLabel) ?>
  </div>
</div>

<div class="info-bar">
  <span><strong>Comprador:</strong> <?= htmlspecialchars($pedido['nome_comprador']) ?></span>
  <?php if ($pedido['cidade']): ?>
  <span><strong>Cidade/UF:</strong> <?= htmlspecialchars($pedido['cidade'] . '/' . $pedido['estado']) ?></span>
  <?php endif; ?>
  <span><strong>Total:</strong> R$ <?= number_format($pedido['total'], 2, ',', '.') ?></span>
</div>

<table>
  <thead>
    <tr>
      <th style="width:150px;">Código Interno</th>
      <th>Produto</th>
      <th style="width:70px;text-align:center;">Qtd</th>
      <th style="width:80px;text-align:center;">&#x2713; Sep.</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($itens as $item): ?>
    <tr>
      <td class="codigo"><?= $item['codigo_interno'] ? htmlspecialchars($item['codigo_interno']) : '—' ?></td>
      <td><?= htmlspecialchars($item['produto_nome']) ?></td>
      <td class="qtd"><?= (int) $item['quantidade'] ?></td>
      <td class="check">&#x25A1;</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($pedido['cep']): ?>
<div class="entrega-bloco">
  <strong>Endereço de entrega:</strong>
  <?= htmlspecialchars($pedido['endereco'] . ', ' . $pedido['numero']) ?>
  <?php if ($pedido['complemento']): ?> — <?= htmlspecialchars($pedido['complemento']) ?><?php endif; ?>,
  <?= htmlspecialchars($pedido['bairro']) ?> —
  <?= htmlspecialchars($pedido['cidade']) ?>/<?= htmlspecialchars($pedido['estado']) ?> —
  CEP <?= htmlspecialchars($pedido['cep']) ?><br>
  <strong>Telefone:</strong> <?= htmlspecialchars($pedido['telefone_comprador']) ?><br>
  <?php
  $transpEfetiva = $tracking['carrier'] ?? $tracking['chosen_carrier'] ?? null;
  $servico       = $tracking['chosen_service'] ?? null;
  $freteValor    = isset($tracking['shipping_price']) ? (float) $tracking['shipping_price'] : null;
  $transpLabel   = trim(($transpEfetiva ?? 'A definir') . ($servico ? ' — ' . $servico : ''));
  ?>
  <strong>Transportadora:</strong> <?= htmlspecialchars($transpLabel) ?>
  <?php if ($freteValor !== null): ?>
    — <strong>Frete:</strong>
    <?= $freteValor === 0.0 ? 'Grátis' : 'R$ ' . number_format($freteValor, 2, ',', '.') ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="ficha-footer">
  Gerado em <?= date('d/m/Y \à\s H:i') ?> — PSPart Admin — Uso interno
</div>

<script>
  window.addEventListener('load', () => window.print());
</script>

</body>
</html>
