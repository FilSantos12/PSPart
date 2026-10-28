<?php
/**
 * POST /backend/api/agente.php
 * Endpoint do agente conversacional de compras.
 *
 * Body JSON: { "mensagem": string, "historico": array }
 * Resposta:  { "resposta": string } ou { "erro": string }
 *
 * Em AGENTE_MODO_MOCK = true não chama a Anthropic (custo zero).
 */

require_once __DIR__ . '/_core.php';
require_once __DIR__ . '/../config/loja.php';
require_once __DIR__ . '/../config/melhorenvio.php';
require_once __DIR__ . '/../helpers/status.php';
require_once __DIR__ . '/../helpers/MelhorEnvio.php';
require_once __DIR__ . '/../agente/ferramentas.php';

exigir_metodo('POST');

define('AGENTE_LOG', __DIR__ . '/../../logs/agente.log');
define('AGENTE_MSG_MAX_CHARS',   2000);
define('AGENTE_HISTORICO_MAX',   20);    // máximo de mensagens no histórico
define('AGENTE_SYSTEM_PROMPT',
    'Você é o assistente de compras da loja. Responda sempre em português do Brasil, de forma breve e cordial.' . "\n" .
    'Use SOMENTE as ferramentas disponíveis para obter dados sobre produtos, frete e pedidos.' . "\n" .
    'NUNCA invente preço, estoque, prazo, valor de frete ou status de pedido — se não vier de uma ferramenta, diga que não tem essa informação.' . "\n" .
    'Você não finaliza compras, não altera pedidos e não emite etiquetas: apenas informa e orienta. Direcione ao checkout quando fizer sentido, mas quem conclui a compra é o cliente.' . "\n" .
    'O frete informado é uma estimativa de um produto; o valor final do carrinho é confirmado no checkout.' . "\n" .
    'A loja tem dois mercados: Brasil (campo "preco", em reais, com frete e compra pelo site) e Exportação (campo "preco_exportacao", em USD na condição indicada, ou "Sob consulta"; exportação é só por cotação, via "Solicitar Orçamento", nunca pelo carrinho). Ao informar preço, apresente os dois mercados; se o cliente indicar o mercado, priorize o dele. Nunca converta reais em dólares.' . "\n" .
    'Para consultar um pedido, o cliente precisa informar o número e o e-mail (ou o token de acompanhamento). Sem isso, peça a verificação — nunca mostre dados sem confirmar titularidade.' . "\n" .
    'Se uma ferramenta não retornar resultado, seja honesto sobre isso.'
);

// ── Helpers internos ──────────────────────────────────────────────────────────

function agente_log(string $linha): void
{
    $dir = dirname(AGENTE_LOG);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(AGENTE_LOG, '[' . date('Y-m-d H:i:s') . '] ' . $linha . "\n", FILE_APPEND | LOCK_EX);
}

function agente_fallback(string $detalhe = ''): never
{
    if ($detalhe) agente_log('ERRO ' . $detalhe);
    json_ok(['resposta' => 'Assistente indisponível no momento. Tente novamente em breve.']);
}

// ── Camada de transporte (Anthropic ou Groq) ──────────────────────────────────

function api_post(array $body): array
{
    return AGENTE_PROVEDOR === 'groq'
        ? groq_post($body)
        : anthropic_post($body);
}

function anthropic_post(array $body): array
{
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => AGENTE_TIMEOUT,
        CURLOPT_HTTPHEADER     => [
            'x-api-key: '         . ANTHROPIC_API_KEY,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
            'User-Agent: PSPart-Agente/1.0 (filipe@pentasis.com.br)',
        ],
    ]);

    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $err) agente_fallback('cURL Anthropic: ' . $err);

    $data = json_decode($raw, true);
    if (!is_array($data)) agente_fallback('JSON inválido da Anthropic (HTTP ' . $code . ')');

    return ['code' => $code, 'data' => $data];
}

// Traduz request Anthropic → OpenAI, envia ao Groq, traduz resposta de volta.
// O loop principal nunca sabe que falou com o Groq.
// Retry automático em tool_use_failed (modelo gerou chamada mal formatada).
function groq_post(array $body, int $tentativa = 1): array
{
    $openaiBody = _anthropic_para_openai($body);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($openaiBody, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => AGENTE_TIMEOUT,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . GROQ_API_KEY,
            'content-type: application/json',
            'User-Agent: PSPart-Agente/1.0 (filipe@pentasis.com.br)',
        ],
    ]);

    $raw  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $err) agente_fallback('cURL Groq: ' . $err);

    $openaiData = json_decode($raw, true);
    if (!is_array($openaiData)) agente_fallback('JSON inválido do Groq (HTTP ' . $code . ')');

    // Modelo gerou tool call mal formatada — tenta uma segunda vez
    if ($code === 400
        && ($openaiData['error']['code'] ?? '') === 'tool_use_failed'
        && $tentativa < 2
    ) {
        agente_log('RETRY tool_use_failed (tentativa ' . $tentativa . ')');
        return groq_post($body, $tentativa + 1);
    }

    return ['code' => $code, 'data' => _openai_para_anthropic($openaiData)];
}

// Converte body no formato Anthropic para formato OpenAI (Groq).
function _anthropic_para_openai(array $body): array
{
    // Tools: input_schema → parameters, envolve em type:function
    $tools = [];
    foreach ($body['tools'] ?? [] as $tool) {
        $tools[] = [
            'type'     => 'function',
            'function' => [
                'name'        => $tool['name'],
                'description' => $tool['description'] ?? '',
                'parameters'  => $tool['input_schema'] ?? ['type' => 'object', 'properties' => []],
            ],
        ];
    }

    // System vira primeira mensagem
    $messages = [];
    if (!empty($body['system'])) {
        $messages[] = ['role' => 'system', 'content' => $body['system']];
    }

    foreach ($body['messages'] ?? [] as $msg) {
        $role    = $msg['role'];
        $content = $msg['content'];

        // Mensagem simples de texto
        if (is_string($content)) {
            $messages[] = ['role' => $role, 'content' => $content];
            continue;
        }

        if ($role === 'assistant') {
            // Blocos do assistente: text + tool_use → content + tool_calls
            $textos    = [];
            $toolCalls = [];
            foreach ($content as $bloco) {
                if ($bloco['type'] === 'text') {
                    $textos[] = $bloco['text'];
                } elseif ($bloco['type'] === 'tool_use') {
                    $toolCalls[] = [
                        'id'       => $bloco['id'],
                        'type'     => 'function',
                        'function' => [
                            'name'      => $bloco['name'],
                            'arguments' => json_encode($bloco['input'] ?? [], JSON_UNESCAPED_UNICODE),
                        ],
                    ];
                }
            }
            $m = ['role' => 'assistant', 'content' => implode("\n", $textos) ?: null];
            if ($toolCalls) $m['tool_calls'] = $toolCalls;
            $messages[] = $m;

        } elseif ($role === 'user') {
            // Blocos tool_result → mensagens separadas com role:tool
            $toolResults = array_filter($content, fn($b) => ($b['type'] ?? '') === 'tool_result');
            if ($toolResults) {
                foreach ($toolResults as $bloco) {
                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $bloco['tool_use_id'],
                        'content'      => is_array($bloco['content'])
                            ? json_encode($bloco['content'], JSON_UNESCAPED_UNICODE)
                            : (string) $bloco['content'],
                    ];
                }
            } else {
                // Blocos de texto no user
                $textos = implode("\n", array_map(fn($b) => $b['text'] ?? '', array_filter($content, fn($b) => ($b['type'] ?? '') === 'text')));
                if ($textos !== '') $messages[] = ['role' => 'user', 'content' => $textos];
            }
        }
    }

    $result = [
        'model'       => GROQ_MODELO,
        'max_tokens'  => $body['max_tokens'] ?? AGENTE_MAX_TOKENS,
        'messages'    => $messages,
    ];
    if ($tools) $result['tools'] = $tools;

    return $result;
}

// Converte resposta OpenAI (Groq) de volta para formato Anthropic.
function _openai_para_anthropic(array $data): array
{
    if (empty($data['choices'][0])) return $data; // erro passado para o handler

    $message      = $data['choices'][0]['message']       ?? [];
    $finishReason = $data['choices'][0]['finish_reason']  ?? 'stop';
    $content      = [];

    if (!empty($message['content'])) {
        $content[] = ['type' => 'text', 'text' => $message['content']];
    }

    foreach ($message['tool_calls'] ?? [] as $tc) {
        $input     = json_decode($tc['function']['arguments'] ?? '{}', true) ?? [];
        $content[] = [
            'type'  => 'tool_use',
            'id'    => $tc['id'],
            'name'  => $tc['function']['name'],
            'input' => $input,
        ];
    }

    return [
        'content'     => $content,
        'stop_reason' => $finishReason === 'tool_calls' ? 'tool_use' : 'end_turn',
    ];
}

// ── Entrada ───────────────────────────────────────────────────────────────────

$body     = body_json();
$mensagem = trim((string) ($body['mensagem'] ?? ''));
$historico = is_array($body['historico'] ?? null) ? $body['historico'] : [];

if ($mensagem === '') json_erro('mensagem é obrigatória.', 422);

// Sanitização leve
$mensagem  = mb_substr($mensagem, 0, AGENTE_MSG_MAX_CHARS);
$historico = array_slice($historico, -AGENTE_HISTORICO_MAX);

// ── Modo mock ─────────────────────────────────────────────────────────────────

if (AGENTE_MODO_MOCK) {
    agente_log('MOCK-SKIP msg="' . mb_substr($mensagem, 0, 100) . '"');
    json_ok(['resposta' => '[MOCK] Recebi sua mensagem. Ferramentas ativas: buscar_produtos, calcular_frete, consultar_pedido.']);
}

// ── Chamada real ──────────────────────────────────────────────────────────────

$chaveAtiva = AGENTE_PROVEDOR === 'groq' ? GROQ_API_KEY : ANTHROPIC_API_KEY;
if (empty($chaveAtiva)) {
    agente_fallback(strtoupper(AGENTE_PROVEDOR) . '_API_KEY não configurada');
}

$messages = array_merge($historico, [['role' => 'user', 'content' => $mensagem]]);

$requestBody = [
    'model'      => AGENTE_MODELO,
    'max_tokens' => AGENTE_MAX_TOKENS,
    'system'     => AGENTE_SYSTEM_PROMPT,
    'tools'      => getDefinicoesFerramentas(),
    'messages'   => $messages,
];

$toolsUsadas = [];
$turnos      = 0;

while ($turnos < AGENTE_MAX_TURNOS_TOOLUSE) {
    $resp = api_post($requestBody);

    if ($resp['code'] !== 200) {
        agente_fallback('HTTP ' . $resp['code'] . ' (' . AGENTE_PROVEDOR . '): ' . json_encode($resp['data']));
    }

    $data       = $resp['data'];
    $stopReason = $data['stop_reason'] ?? '';
    $content    = $data['content']     ?? [];

    if ($stopReason === 'tool_use') {
        // Anexa resposta do assistente
        $requestBody['messages'][] = ['role' => 'assistant', 'content' => $content];

        // Executa cada tool e monta a mensagem de resultados
        $toolResults = [];
        foreach ($content as $bloco) {
            if (($bloco['type'] ?? '') !== 'tool_use') continue;

            $toolName  = $bloco['name']  ?? '';
            $toolInput = $bloco['input'] ?? [];
            $toolUseId = $bloco['id']    ?? '';

            $toolsUsadas[] = $toolName;
            $resultado     = executarFerramenta($toolName, $toolInput);

            $toolResults[] = [
                'type'        => 'tool_result',
                'tool_use_id' => $toolUseId,
                'content'     => json_encode($resultado, JSON_UNESCAPED_UNICODE),
            ];
        }

        $requestBody['messages'][] = ['role' => 'user', 'content' => $toolResults];
        $turnos++;
        continue;
    }

    // end_turn — extrair blocos de texto
    $textos = [];
    foreach ($content as $bloco) {
        if (($bloco['type'] ?? '') === 'text') {
            $textos[] = trim($bloco['text'] ?? '');
        }
    }

    $resposta = implode("\n\n", array_filter($textos));
    if ($resposta === '') $resposta = 'Não consegui formular uma resposta. Tente novamente.';

    agente_log(
        'OK tools=[' . implode(',', $toolsUsadas) . '] '
        . 'msg="' . mb_substr($mensagem, 0, 80) . '"'
    );

    json_ok(['resposta' => $resposta]);
}

// Trava de segurança: excedeu AGENTE_MAX_TURNOS_TOOLUSE
agente_log('AVISO loop de tool use excedeu ' . AGENTE_MAX_TURNOS_TOOLUSE . ' turnos');
json_ok(['resposta' => 'Não consegui obter a informação a tempo. Tente reformular a pergunta.']);
