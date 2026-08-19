<?php
/**
 * Helpers de envio de e-mail (PHP mail() nativo)
 * Logs gravados em logs/emails.log para diagnóstico
 */

function _enviarEmail(string $para, string $assunto, string $corpo): bool {
    $logDir  = __DIR__ . '/../../logs';
    $logFile = $logDir . '/emails.log';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);

    // Em localhost, mail() trava o servidor PHP single-threaded por 30-60s tentando SMTP
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
        file_put_contents($logFile,
            date('Y-m-d H:i:s') . " | DEV-SKIP | Para: {$para} | Assunto: {$assunto}\n",
            FILE_APPEND | LOCK_EX
        );
        return false;
    }

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: PSPart <noreply@pspart.com.br>\r\n";
    $headers .= "Reply-To: contato@pspart.com.br\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $ok = @mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $corpo, $headers);

    $status = $ok ? 'OK' : 'FALHOU';
    file_put_contents($logFile,
        date('Y-m-d H:i:s') . " | {$status} | Para: {$para} | Assunto: {$assunto}\n",
        FILE_APPEND | LOCK_EX
    );

    return $ok;
}

function _statusLabel(string $status): string {
    return match($status) {
        'aprovado'         => 'Pagamento Aprovado',
        'pendente'         => 'Aguardando Pagamento',
        'em_analise'       => 'Em Análise',
        'recusado'         => 'Pagamento Recusado',
        'cancelado'        => 'Pedido Cancelado',
        'reembolsado'      => 'Reembolsado',
        'contestado'       => 'Em Contestação',
        'em_processamento' => 'Em Processamento',
        'enviado'          => 'Enviado',
        default            => ucfirst($status),
    };
}

function _statusCor(string $status): string {
    return match($status) {
        'aprovado'         => '#1a7a3c',
        'pendente'         => '#b8860b',
        'em_analise'       => '#0d6efd',
        'recusado',
        'cancelado'        => '#c0392b',
        'em_processamento' => '#6f42c1',
        'enviado'          => '#0d6efd',
        default            => '#555',
    };
}

function _layoutEmail(string $titulo, string $conteudo): string {
    return <<<HTML
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
    <body style="margin:0;padding:0;background:#f4f6fb;font-family:Arial,sans-serif;font-size:14px;color:#222;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:30px 0;">
        <tr><td align="center">
          <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
            <!-- Header -->
            <tr>
              <td style="background:#274185;padding:28px 32px;text-align:center;">
                <span style="color:#fff;font-size:22px;font-weight:bold;letter-spacing:1px;">PSPart</span>
                <br><span style="color:#b0bfe8;font-size:12px;">Partes e Peças Automação</span>
              </td>
            </tr>
            <!-- Título -->
            <tr>
              <td style="padding:28px 32px 0 32px;">
                <h2 style="margin:0 0 6px 0;color:#274185;font-size:18px;">{$titulo}</h2>
                <hr style="border:none;border-top:1px solid #eee;margin:16px 0;">
              </td>
            </tr>
            <!-- Conteúdo -->
            <tr>
              <td style="padding:0 32px 28px 32px;">
                {$conteudo}
              </td>
            </tr>
            <!-- Footer -->
            <tr>
              <td style="background:#f4f6fb;padding:16px 32px;text-align:center;font-size:11px;color:#999;">
                PSPart - Partes e Peças Automação &nbsp;|&nbsp; filipe@pentasis.com.br<br>
                Este e-mail foi gerado automaticamente, não é necessário responder.
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}

/**
 * E-mail 1: Pedido recebido (disparado ao criar o pedido)
 */
function emailPedidoCriado(array $pedido, array $itens, string $token): void {
    $baseUrl    = defined('MP_BASE_URL') ? MP_BASE_URL : '';
    $linkAcomp  = $baseUrl . '/acompanhar.html?pedido=' . $pedido['id'] . '&token=' . $token;
    $statusLabel = _statusLabel($pedido['status'] ?? 'pendente');
    $statusCor   = _statusCor($pedido['status'] ?? 'pendente');

    $linhasItens = '';
    foreach ($itens as $item) {
        $subtotal     = number_format($item['preco_unitario'] * $item['quantidade'], 2, ',', '.');
        $preco        = number_format($item['preco_unitario'], 2, ',', '.');
        $linhasItens .= <<<HTML
        <tr>
          <td style="padding:8px 0;border-bottom:1px solid #eee;">{$item['produto_nome']}</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:center;">{$item['quantidade']}</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">R$ {$preco}</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">R$ {$subtotal}</td>
        </tr>
        HTML;
    }

    $total   = number_format($pedido['total'], 2, ',', '.');
    $nome    = htmlspecialchars($pedido['nome_comprador']);
    $pedidoId = $pedido['numero_pedido'] ?? $pedido['id'];
    $criado   = $pedido['criado_em'] ?? date('d/m/Y H:i');

    $endereco = implode(', ', array_filter([
        ($pedido['endereco'] ?? '') . ($pedido['numero'] ? ', ' . $pedido['numero'] : ''),
        $pedido['complemento'] ?? '',
        $pedido['bairro'] ?? '',
        ($pedido['cidade'] ?? '') . ($pedido['estado'] ? '/' . $pedido['estado'] : ''),
        $pedido['cep'] ?? '',
    ]));

    $conteudo = <<<HTML
    <p>Olá, <strong>{$nome}</strong>!</p>
    <p>Seu pedido foi recebido com sucesso. Assim que o pagamento for confirmado, você receberá outro e-mail.</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;">
      <tr>
        <td style="padding:12px;background:#f4f6fb;border-radius:8px;">
          <strong>Pedido:</strong> #{$pedidoId} &nbsp;&nbsp;
          <strong>Data:</strong> {$criado} &nbsp;&nbsp;
          <strong>Status:</strong> <span style="color:{$statusCor};font-weight:bold;">{$statusLabel}</span>
        </td>
      </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;font-size:13px;">
      <tr style="background:#f4f6fb;">
        <th style="padding:8px 0;text-align:left;">Produto</th>
        <th style="padding:8px 0;text-align:center;">Qtd</th>
        <th style="padding:8px 0;text-align:right;">Preço unit.</th>
        <th style="padding:8px 0;text-align:right;">Subtotal</th>
      </tr>
      {$linhasItens}
      <tr>
        <td colspan="3" style="padding:10px 0;text-align:right;font-weight:bold;">Total:</td>
        <td style="padding:10px 0;text-align:right;font-weight:bold;color:#274185;">R$ {$total}</td>
      </tr>
    </table>

    <p style="font-size:13px;color:#555;margin:4px 0;"><strong>Endereço de entrega:</strong> {$endereco}</p>

    <div style="margin:24px 0;text-align:center;">
      <a href="{$linkAcomp}" style="background:#274185;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:bold;">
        Acompanhar Pedido
      </a>
    </div>

    <p style="font-size:12px;color:#999;">Se o botão não funcionar, acesse: <a href="{$linkAcomp}">{$linkAcomp}</a></p>
    HTML;

    _enviarEmail(
        $pedido['email_comprador'],
        "Pedido #{$pedidoId} recebido — PSPart",
        _layoutEmail("Pedido #{$pedidoId} Recebido!", $conteudo)
    );
}

/**
 * E-mail 3: Pedido enviado pelos Correios (disparado pelo admin ao inserir código de rastreio)
 */
function emailPedidoEnviado(array $pedido, array $itens, string $token, string $codigoRastreio): void {
    $baseUrl      = defined('MP_BASE_URL') ? MP_BASE_URL : '';
    $linkAcomp    = $baseUrl . '/acompanhar.html?pedido=' . $pedido['id'] . '&token=' . $token;
    $linkRastreio = 'https://rastreamento.correios.com.br/app/index.php?objeto=' . urlencode($codigoRastreio);
    $total        = number_format($pedido['total'], 2, ',', '.');
    $nome         = htmlspecialchars($pedido['nome_comprador']);
    $pedidoId     = $pedido['numero_pedido'] ?? $pedido['id'];

    $linhasItens = '';
    foreach ($itens as $item) {
        $subtotal     = number_format($item['preco_unitario'] * $item['quantidade'], 2, ',', '.');
        $preco        = number_format($item['preco_unitario'], 2, ',', '.');
        $linhasItens .= <<<HTML
        <tr>
          <td style="padding:8px 0;border-bottom:1px solid #eee;">{$item['produto_nome']}</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:center;">{$item['quantidade']}</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">R$ {$preco}</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">R$ {$subtotal}</td>
        </tr>
        HTML;
    }

    $conteudo = <<<HTML
    <p>Olá, <strong>{$nome}</strong>!</p>
    <p>
      <span style="font-size:32px;">📦</span><br>
      Seu pedido foi <strong style="color:#0d6efd;">enviado</strong>! Você pode acompanhar a entrega pelos Correios.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;">
      <tr>
        <td style="padding:16px;background:#f0f4ff;border-radius:8px;text-align:center;">
          <div style="font-size:12px;color:#555;margin-bottom:6px;">CÓDIGO DE RASTREIO</div>
          <div style="font-size:20px;font-weight:bold;letter-spacing:2px;color:#274185;">{$codigoRastreio}</div>
          <div style="margin-top:12px;">
            <a href="{$linkRastreio}" style="background:#274185;color:#fff;padding:8px 20px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:bold;">
              Rastrear nos Correios
            </a>
          </div>
        </td>
      </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;font-size:13px;">
      <tr style="background:#f4f6fb;">
        <th style="padding:8px 0;text-align:left;">Produto</th>
        <th style="padding:8px 0;text-align:center;">Qtd</th>
        <th style="padding:8px 0;text-align:right;">Preço unit.</th>
        <th style="padding:8px 0;text-align:right;">Subtotal</th>
      </tr>
      {$linhasItens}
      <tr>
        <td colspan="3" style="padding:10px 0;text-align:right;font-weight:bold;">Total:</td>
        <td style="padding:10px 0;text-align:right;font-weight:bold;color:#274185;">R$ {$total}</td>
      </tr>
    </table>

    <div style="margin:24px 0;text-align:center;">
      <a href="{$linkAcomp}" style="background:#274185;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:bold;">
        Acompanhar Pedido
      </a>
    </div>

    <p style="font-size:12px;color:#999;">Se o botão não funcionar, acesse: <a href="{$linkAcomp}">{$linkAcomp}</a></p>
    HTML;

    _enviarEmail(
        $pedido['email_comprador'],
        "Seu pedido #{$pedidoId} foi enviado! — PSPart",
        _layoutEmail("Pedido #{$pedidoId} Enviado! 📦", $conteudo)
    );
}

/**
 * E-mail interno: Ficha de Separação (para estoque/separação — não vai ao comprador)
 */
function emailFichaSeparacao(array $pedido, array $itens): bool {
    $destinatario = defined('EMAIL_SEPARACAO_INTERNA') ? EMAIL_SEPARACAO_INTERNA : 'filipe@pentasis.com.br';
    $pedidoId     = $pedido['numero_pedido'] ?? $pedido['id'];
    $data         = date('d/m/Y H:i', strtotime($pedido['criado_em']));
    $status       = _statusLabel($pedido['status'] ?? 'pendente');
    $nome         = htmlspecialchars($pedido['nome_comprador']);
    $cidadeUf     = htmlspecialchars(($pedido['cidade'] ?? '') . ($pedido['estado'] ? '/' . $pedido['estado'] : ''));

    $linhasItens = '';
    foreach ($itens as $item) {
        $codigo    = htmlspecialchars($item['codigo_interno'] ?: '—');
        $nomeProd  = htmlspecialchars($item['produto_nome']);
        $qtd       = (int) $item['quantidade'];
        $linhasItens .= <<<HTML
        <tr>
          <td style="padding:8px;border-bottom:1px solid #eee;font-family:monospace;font-size:12px;color:#444;">{$codigo}</td>
          <td style="padding:8px;border-bottom:1px solid #eee;">{$nomeProd}</td>
          <td style="padding:8px;border-bottom:1px solid #eee;text-align:center;font-weight:bold;font-size:15px;">{$qtd}</td>
        </tr>
        HTML;
    }

    $conteudo = <<<HTML
    <p><strong>Ficha de Separação interna — não encaminhar ao cliente.</strong></p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0;font-size:12px;">
      <tr>
        <td style="padding:10px;background:#f4f6fb;border-radius:8px;">
          <strong>Pedido:</strong> #{$pedidoId} &nbsp;&nbsp;
          <strong>Data:</strong> {$data} &nbsp;&nbsp;
          <strong>Status:</strong> {$status}
        </td>
      </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;font-size:13px;">
      <tr style="background:#274185;">
        <th style="padding:10px;text-align:left;color:#fff;">Código Interno</th>
        <th style="padding:10px;text-align:left;color:#fff;">Produto</th>
        <th style="padding:10px;text-align:center;color:#fff;">Qtd</th>
      </tr>
      {$linhasItens}
    </table>

    <p style="font-size:13px;color:#555;margin:4px 0;">
      <strong>Comprador:</strong> {$nome} &nbsp;|&nbsp; <strong>Cidade/UF:</strong> {$cidadeUf}
    </p>
    HTML;

    return _enviarEmail(
        $destinatario,
        "Ficha de Separação — Pedido #{$pedidoId}",
        _layoutEmail("Ficha de Separação — Pedido #{$pedidoId}", $conteudo)
    );
}

/**
 * E-mail 2: Pagamento aprovado (disparado pelo webhook)
 */
function emailPagamentoAprovado(array $pedido, array $itens, string $token): void {
    $baseUrl   = defined('MP_BASE_URL') ? MP_BASE_URL : '';
    $linkAcomp = $baseUrl . '/acompanhar.html?pedido=' . $pedido['id'] . '&token=' . $token;
    $total     = number_format($pedido['total'], 2, ',', '.');
    $nome      = htmlspecialchars($pedido['nome_comprador']);
    $pedidoId  = $pedido['numero_pedido'] ?? $pedido['id'];

    $linhasItens = '';
    foreach ($itens as $item) {
        $subtotal     = number_format($item['preco_unitario'] * $item['quantidade'], 2, ',', '.');
        $preco        = number_format($item['preco_unitario'], 2, ',', '.');
        $linhasItens .= <<<HTML
        <tr>
          <td style="padding:8px 0;border-bottom:1px solid #eee;">{$item['produto_nome']}</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:center;">{$item['quantidade']}</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">R$ {$preco}</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;">R$ {$subtotal}</td>
        </tr>
        HTML;
    }

    $conteudo = <<<HTML
    <p>Olá, <strong>{$nome}</strong>!</p>
    <p>
      <span style="font-size:32px;">✅</span><br>
      Seu pagamento foi <strong style="color:#1a7a3c;">aprovado</strong>! Estamos preparando seu pedido.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;font-size:13px;">
      <tr style="background:#f4f6fb;">
        <th style="padding:8px 0;text-align:left;">Produto</th>
        <th style="padding:8px 0;text-align:center;">Qtd</th>
        <th style="padding:8px 0;text-align:right;">Preço unit.</th>
        <th style="padding:8px 0;text-align:right;">Subtotal</th>
      </tr>
      {$linhasItens}
      <tr>
        <td colspan="3" style="padding:10px 0;text-align:right;font-weight:bold;">Total pago:</td>
        <td style="padding:10px 0;text-align:right;font-weight:bold;color:#1a7a3c;">R$ {$total}</td>
      </tr>
    </table>

    <p style="font-size:13px;color:#555;">Em breve entraremos em contato para combinar o envio.</p>

    <div style="margin:24px 0;text-align:center;">
      <a href="{$linkAcomp}" style="background:#274185;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:bold;">
        Acompanhar Pedido
      </a>
    </div>

    <p style="font-size:12px;color:#999;">Se o botão não funcionar, acesse: <a href="{$linkAcomp}">{$linkAcomp}</a></p>
    HTML;

    _enviarEmail(
        $pedido['email_comprador'],
        "Pagamento aprovado — Pedido #{$pedidoId} PSPart",
        _layoutEmail("Pagamento Aprovado! 🎉", $conteudo)
    );
}
