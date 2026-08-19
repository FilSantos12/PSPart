<?php
/**
 * backend/agente/ferramentas.php
 * Definições das ferramentas read-only do agente de compras.
 * Somente SELECT — nenhuma escrita no banco.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/status.php';

// MelhorEnvio e config são incluídos pelo endpoint agente.php antes deste arquivo.

// ── Definições das ferramentas (formato Anthropic) ────────────────────────────

function getDefinicoesFerramentas(): array
{
    return [
        [
            'name'         => 'buscar_produtos',
            'description'  => 'Busca produtos ativos no catálogo por termo (nome, descrição, categoria ou código). Use para dúvidas sobre o que a loja vende, preço e disponibilidade.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'termo'  => ['type' => 'string',  'description' => 'Texto buscado pelo cliente'],
                    'limite' => ['description' => 'Máximo de resultados (padrão 5, número inteiro)'],
                ],
                'required'   => ['termo'],
            ],
        ],
        [
            'name'         => 'calcular_frete',
            'description'  => 'Estima o frete de UM produto para um CEP. Apenas estimativa informativa; o valor final do carrinho é confirmado no checkout.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'produto_id'  => ['type' => 'string', 'description' => 'ID do produto'],
                    'cep_destino' => ['type' => 'string', 'description' => 'CEP de destino (somente dígitos)'],
                ],
                'required'   => ['produto_id', 'cep_destino'],
            ],
        ],
        [
            'name'         => 'consultar_pedido',
            'description'  => 'Consulta status e rastreio de um pedido. Exige verificação de titularidade (e-mail ou token de acompanhamento).',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'id_pedido'            => ['type' => 'string', 'description' => 'Número do pedido informado pelo cliente (formato AAAAMM+sequencial, ex.: 202608000)'],
                    'email_cliente'        => ['type' => 'string', 'description' => 'E-mail usado na compra (para conferir titularidade)'],
                    'token_acompanhamento' => ['type' => 'string', 'description' => 'Token de acompanhamento, alternativa ao e-mail'],
                ],
                'required'   => ['id_pedido'],
            ],
        ],
    ];
}

// ── Dispatcher ────────────────────────────────────────────────────────────────

function executarFerramenta(string $nome, array $input): array
{
    switch ($nome) {
        case 'buscar_produtos':
            return buscarProdutos(
                (string) ($input['termo']  ?? ''),
                isset($input['limite']) ? (int) $input['limite'] : 5
            );

        case 'calcular_frete':
            return calcularFreteAgente(
                (string) ($input['produto_id']  ?? ''),
                (string) ($input['cep_destino'] ?? '')
            );

        case 'consultar_pedido':
            return consultarPedido(
                (string) ($input['id_pedido']            ?? ''),
                isset($input['email_cliente'])        ? (string) $input['email_cliente']        : null,
                isset($input['token_acompanhamento']) ? (string) $input['token_acompanhamento'] : null
            );

        default:
            return ['erro' => 'Ferramenta desconhecida: ' . $nome];
    }
}

// ── Implementações ────────────────────────────────────────────────────────────

function buscarProdutos(string $termo, int $limite = 5): array
{
    if ($termo === '') {
        return ['encontrado' => false, 'mensagem' => 'Termo de busca vazio.'];
    }

    $limite = max(1, min($limite, AGENTE_BUSCA_LIMITE_MAX));
    $t      = '%' . $termo . '%';

    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT id, nome, preco, estoque, categoria, codigo_interno
        FROM produtos
        WHERE ativo = 1
          AND (nome LIKE :t OR descricao LIKE :t OR categoria LIKE :t OR codigo_interno LIKE :t)
        LIMIT :lim
    ");
    $stmt->bindValue(':t',   $t,      PDO::PARAM_STR);
    $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        return ['encontrado' => false, 'mensagem' => 'Nenhum produto encontrado para "' . $termo . '".'];
    }

    $produtos = [];
    foreach ($rows as $row) {
        $produtos[] = [
            'id'              => (int)   $row['id'],
            'nome'            => $row['nome'],
            'preco'           => (float) $row['preco'],
            'estoque'         => (int)   $row['estoque'],
            'disponivel'      => ((int) $row['estoque']) > 0,
            'categoria'       => $row['categoria'],
            'codigo_interno'  => $row['codigo_interno'] ?? null,
        ];
    }

    return ['encontrado' => true, 'total' => count($produtos), 'produtos' => $produtos];
}

function calcularFreteAgente(string $produtoId, string $cepDestino): array
{
    $cep = preg_replace('/\D/', '', $cepDestino);
    if (strlen($cep) !== 8) {
        return ['disponivel' => false, 'motivo' => 'CEP inválido. Informe 8 dígitos.'];
    }

    $id = (int) $produtoId;
    if ($id <= 0) {
        return ['disponivel' => false, 'motivo' => 'ID de produto inválido.'];
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT id, nome, preco, peso, largura, altura, comprimento
        FROM produtos
        WHERE id = :id AND ativo = 1
    ");
    $stmt->execute([':id' => $id]);
    $produto = $stmt->fetch();

    if (!$produto) {
        return ['disponivel' => false, 'motivo' => 'Produto não encontrado.'];
    }

    if ((float) $produto['peso'] <= 0) {
        return ['disponivel' => false, 'motivo' => 'Dimensões do produto não cadastradas.'];
    }

    try {
        $me       = new MelhorEnvio();
        $servicos = $me->calcularFrete($produto, $cep);

        return [
            'disponivel'   => true,
            'produto_nome' => $produto['nome'],
            'cep'          => substr($cep, 0, 5) . '-' . substr($cep, 5),
            'servicos'     => $servicos,
        ];
    } catch (RuntimeException $e) {
        return ['disponivel' => false, 'motivo' => 'Frete indisponível no momento.'];
    } catch (Exception $e) {
        return ['disponivel' => false, 'motivo' => 'Frete indisponível no momento.'];
    }
}

function consultarPedido(string $idPedido, ?string $emailCliente, ?string $token): array
{
    $idPedido = trim($idPedido);
    if ($idPedido === '') {
        return ['encontrado' => false, 'mensagem' => 'Número de pedido inválido.'];
    }

    $pdo = getDB();

    // Aceita o número de pedido exibido ao cliente (AAAAMM+sequencial) e,
    // por compatibilidade, o ID interno legado (pedidos anteriores à numeração).
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE numero_pedido = :num OR id = :id LIMIT 1");
    $stmt->execute([
        ':num' => $idPedido,
        ':id'  => ctype_digit($idPedido) ? (int) $idPedido : 0,
    ]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        return ['encontrado' => false, 'mensagem' => 'Não localizei um pedido com esses dados.'];
    }

    // Guarda de titularidade: exige e-mail OU token
    $emailOk = $emailCliente !== null
        && mb_strtolower(trim($emailCliente)) === mb_strtolower(trim($pedido['email_comprador']));

    $tokenOk = $token !== null
        && !empty($pedido['token_acompanhamento'])
        && hash_equals((string) $pedido['token_acompanhamento'], (string) $token);

    if (!$emailOk && !$tokenOk) {
        return [
            'verificacao_necessaria' => true,
            'mensagem'               => 'Para consultar o pedido, informe o e-mail usado na compra ou o token de acompanhamento.',
        ];
    }

    // Busca rastreamento
    $stmtT = $pdo->prepare("SELECT * FROM order_tracking WHERE order_id = :oid LIMIT 1");
    $stmtT->execute([':oid' => (string) $pedido['id']]);
    $tracking = $stmtT->fetch() ?: null;

    $status = derivarStatusPedido($pedido, $tracking);

    // Retornar apenas o mínimo necessário — sem PII (sem e-mail, telefone, endereço)
    return [
        'encontrado'        => true,
        'pedido_id'         => $pedido['numero_pedido'] ?? $pedido['id'],
        'total'             => (float) $pedido['total'],
        'criado_em'         => $pedido['criado_em'],
        'status_pagamento'  => $status['status_pagamento'],
        'status_envio'      => $status['status_envio'],
        'status_envio_label'=> $status['status_envio_label'],
        'codigo_rastreio'   => $status['codigo_rastreio'],
        'tracking_url'      => $status['tracking_url'],
        'carrier'           => $status['carrier'],
        'prazo_dias'        => $status['prazo'],
        'exibir_rastreio'   => $status['exibir_rastreio'],
    ];
}
