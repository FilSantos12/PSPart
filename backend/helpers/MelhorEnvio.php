<?php
/**
 * Serviço Melhor Envio — encapsula chamadas à API e renovação de token.
 *
 * Fonte única do token: tabela melhorenvio_auth (banco SQLite).
 * Renovação proativa se o token expira em menos de 1 dia.
 */

require_once __DIR__ . '/../config/database.php';

class MelhorEnvio
{
    private string $baseUrl;
    private string $userAgent;
    private string $clientId;
    private string $clientSecret;
    private string $token = '';
    private string $refreshToken = '';

    public function __construct()
    {
        $this->baseUrl      = MELHORENVIO_BASE_URL;
        $this->userAgent    = MELHORENVIO_USER_AGENT;
        $this->clientId     = MELHORENVIO_CLIENT_ID;
        $this->clientSecret = MELHORENVIO_CLIENT_SECRET;
    }

    // ── Público ───────────────────────────────────────────────────────────────

    /**
     * Faz uma chamada autenticada à API (GET ou POST) com retry automático em 401.
     * Usado pelo serviço de etiquetas (shipment.php).
     *
     * @param string     $method  'GET' ou 'POST'
     * @param string     $path    Ex: '/api/v2/me/cart'
     * @param array|null $payload Body JSON para POST; null para GET
     * @return array ['code' => int, 'data' => array|null, 'body' => string]
     */
    public function request(string $method, string $path, ?array $payload = null): array
    {
        $this->token = $this->getValidToken();
        $resp        = $this->_request($method, $this->baseUrl . $path, $payload);

        if ($resp['code'] === 401) {
            if (!$this->_renovarToken()) {
                throw new RuntimeException(
                    'Token do Melhor Envio expirado e renovação falhou. Reautorize em Admin → Integrações.'
                );
            }
            $resp = $this->_request($method, $this->baseUrl . $path, $payload);
        }

        return $resp;
    }

    /**
     * Calcula frete para um produto e CEP de destino.
     *
     * @param array  $produto    Linha do banco com id, preco, peso, largura, altura, comprimento
     * @param string $cepDestino 8 dígitos numéricos
     * @return array Serviços normalizados [{id, nome, transportadora, logo, preco, prazo_min, prazo_max}]
     * @throws RuntimeException em falha irrecuperável
     */
    public function calcularFrete(array $produto, string $cepDestino): array
    {
        $this->token = $this->getValidToken();

        $payload = [
            'from'     => ['postal_code' => LOJA_CEP_ORIGEM],
            'to'       => ['postal_code' => $cepDestino],
            'products' => [[
                'id'              => (string) $produto['id'],
                'width'           => (float)  $produto['largura'],
                'height'          => (float)  $produto['altura'],
                'length'          => (float)  $produto['comprimento'],
                'weight'          => (float)  $produto['peso'],
                'insurance_value' => (float)  $produto['preco'],
                'quantity'        => 1,
            ]],
            'options'  => ['receipt' => false, 'own_hand' => false],
        ];

        return $this->_cotarFrete($payload);
    }

    /**
     * Calcula frete agregado para múltiplos produtos (carrinho) e CEP de destino.
     * Mesma chamada /shipment/calculate, um "product" por item do carrinho — cada um
     * com a quantidade real (ao contrário de calcularFrete(), que chumba quantity=1
     * por ser sempre item único; aqui a quantidade importa para o cálculo agregado).
     *
     * @param array  $carrinho   Lista de ['produto' => linha do banco, 'quantidade' => int]
     * @param string $cepDestino 8 dígitos numéricos
     * @return array Serviços normalizados [{id, nome, transportadora, logo, preco, prazo_min, prazo_max}]
     * @throws RuntimeException em falha irrecuperável
     */
    public function calcularFreteCarrinho(array $carrinho, string $cepDestino): array
    {
        $this->token = $this->getValidToken();

        $products = array_map(function (array $item): array {
            $produto = $item['produto'];
            return [
                'id'              => (string) $produto['id'],
                'width'           => (float)  $produto['largura'],
                'height'          => (float)  $produto['altura'],
                'length'          => (float)  $produto['comprimento'],
                'weight'          => (float)  $produto['peso'],
                'insurance_value' => (float)  $produto['preco'],
                'quantity'        => (int)    $item['quantidade'],
            ];
        }, $carrinho);

        $payload = [
            'from'     => ['postal_code' => LOJA_CEP_ORIGEM],
            'to'       => ['postal_code' => $cepDestino],
            'products' => $products,
            'options'  => ['receipt' => false, 'own_hand' => false],
        ];

        return $this->_cotarFrete($payload);
    }

    /**
     * Envia o payload de cotação ao Melhor Envio com retry automático em 401.
     * Compartilhado por calcularFrete() (item único) e calcularFreteCarrinho() (agregado).
     */
    private function _cotarFrete(array $payload): array
    {
        $resp = $this->_post('/api/v2/me/shipment/calculate', $payload);

        if ($resp['code'] === 401) {
            if (!$this->_renovarToken()) {
                throw new RuntimeException(
                    'Token do Melhor Envio expirado e renovação falhou. Reautorize em Admin → Integrações.'
                );
            }
            $resp = $this->_post('/api/v2/me/shipment/calculate', $payload);
        }

        if ($resp['code'] !== 200) {
            throw new RuntimeException('Erro na API do Melhor Envio (HTTP ' . $resp['code'] . ').');
        }

        $servicos = json_decode($resp['body'], true);
        if (!is_array($servicos)) {
            throw new RuntimeException('Resposta inesperada da API do Melhor Envio.');
        }

        return $this->_normalizar($servicos);
    }

    /**
     * Retorna um access_token válido, renovando proativamente se expira em < 1 dia.
     * Fonte única: tabela melhorenvio_auth.
     * @throws RuntimeException se não houver token conectado ou renovação falhar.
     */
    public function getValidToken(): string
    {
        $row = $this->_lerDoBanco();

        if (!$row || empty($row['access_token'])) {
            throw new RuntimeException('integracao_nao_conectada');
        }

        if (!empty($row['requer_reautorizacao'])) {
            throw new RuntimeException('integracao_nao_conectada');
        }

        $this->token        = $row['access_token'];
        $this->refreshToken = $row['refresh_token'] ?? '';

        // Renovação proativa se expira em menos de 1 dia
        if (!empty($row['expires_at'])) {
            $expira = strtotime($row['expires_at']);
            if ($expira !== false && ($expira - time()) < 86400) {
                if (!$this->_renovarToken()) {
                    throw new RuntimeException(
                        'Token do Melhor Envio prestes a expirar e renovação falhou. Reautorize em Admin → Integrações.'
                    );
                }
            }
        }

        return $this->token;
    }

    /**
     * Retorna status da integração para o painel admin.
     * @return array { status, dias_restantes, mensagem, updated_at }
     */
    public static function getStatus(): array
    {
        try {
            $row = getDB()->query("SELECT * FROM melhorenvio_auth WHERE id = 1")->fetch();
        } catch (Exception $e) {
            return [
                'status'         => 'sem_tabela',
                'dias_restantes' => null,
                'mensagem'       => 'Execute migrate_melhorenvio_auth.php antes de continuar.',
                'updated_at'     => null,
            ];
        }

        $updated = $row['updated_at'] ?? null;

        if (!$row || empty($row['access_token'])) {
            return [
                'status'         => 'nao_configurado',
                'dias_restantes' => null,
                'mensagem'       => 'Integração não configurada. Clique em "Conectar" para autorizar.',
                'updated_at'     => $updated,
            ];
        }

        if (!empty($row['requer_reautorizacao'])) {
            return [
                'status'         => 'requer_reautorizacao',
                'dias_restantes' => null,
                'mensagem'       => 'Reautorização necessária — o refresh token expirou.',
                'updated_at'     => $updated,
            ];
        }

        if (empty($row['expires_at'])) {
            return [
                'status'         => 'ok',
                'dias_restantes' => null,
                'mensagem'       => 'Conectado (validade desconhecida).',
                'updated_at'     => $updated,
            ];
        }

        $expira = strtotime($row['expires_at']);
        $dias   = (int) ceil(($expira - time()) / 86400);

        if ($dias <= 0) {
            return [
                'status'         => 'expirado',
                'dias_restantes' => 0,
                'mensagem'       => 'Token expirado. Reconecte para restaurar o cálculo de frete.',
                'updated_at'     => $updated,
            ];
        }

        if ($dias <= 7) {
            return [
                'status'         => 'expira_em_breve',
                'dias_restantes' => $dias,
                'mensagem'       => "Token expira em {$dias} dia(s). Reconecte em breve.",
                'updated_at'     => $updated,
            ];
        }

        return [
            'status'         => 'ok',
            'dias_restantes' => $dias,
            'mensagem'       => "Conectado. Token válido por mais {$dias} dias.",
            'updated_at'     => $updated,
        ];
    }

    // ── Privados ──────────────────────────────────────────────────────────────

    private function _normalizar(array $servicos): array
    {
        $resultado = [];

        foreach ($servicos as $s) {
            if (!empty($s['error'])) continue;

            $preco = isset($s['custom_price'])
                ? (float) $s['custom_price']
                : (float) ($s['price'] ?? 0);

            $range    = $s['custom_delivery_range'] ?? $s['delivery_range'] ?? null;
            $prazoRef = isset($s['custom_delivery_time'])
                ? (int) $s['custom_delivery_time']
                : (int) ($s['delivery_time'] ?? 0);

            $prazoMin = $range ? (int) $range['min'] : $prazoRef;
            $prazoMax = $range ? (int) $range['max'] : $prazoRef;

            $resultado[] = [
                'id'             => $s['id'],
                'nome'           => $s['name']               ?? '',
                'transportadora' => $s['company']['name']    ?? '',
                'logo'           => $s['company']['picture'] ?? '',
                'preco'          => $preco,
                'prazo_min'      => $prazoMin,
                'prazo_max'      => $prazoMax,
            ];
        }

        usort($resultado, function ($a, $b) {
            if ($a['preco'] !== $b['preco']) return $a['preco'] <=> $b['preco'];
            return $a['prazo_min'] <=> $b['prazo_min'];
        });

        return $resultado;
    }

    private function _request(string $method, string $url, ?array $payload = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
                'User-Agent: ' . $this->userAgent,
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS,
                $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : '{}'
            );
        }

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Falha de conexão com o Melhor Envio: ' . $err);
        }

        return ['code' => $code, 'data' => json_decode($body, true), 'body' => $body];
    }

    private function _post(string $caminho, array $payload): array
    {
        $ch = curl_init($this->baseUrl . $caminho);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
                'User-Agent: ' . $this->userAgent,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Falha de conexão com o Melhor Envio: ' . $err);
        }

        return ['code' => $code, 'body' => $body];
    }

    private function _renovarToken(): bool
    {
        if ($this->refreshToken === '') return false;

        $ch = curl_init($this->baseUrl . '/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: ' . $this->userAgent,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'scope'         => MELHORENVIO_SCOPES,
            ]),
        ]);

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || $body === false) {
            $this->_marcarRequerReautorizacao();
            return false;
        }

        $data = json_decode($body, true);
        if (empty($data['access_token'])) {
            $this->_marcarRequerReautorizacao();
            return false;
        }

        $expiresIn = (int) ($data['expires_in'] ?? 2592000);
        $this->_salvarTokenNoBanco(
            $data['access_token'],
            $data['refresh_token'] ?? $this->refreshToken,
            $expiresIn
        );
        return true;
    }

    private function _lerDoBanco(): array|false
    {
        try {
            return getDB()->query("SELECT * FROM melhorenvio_auth WHERE id = 1")->fetch();
        } catch (Exception $e) {
            return false;
        }
    }

    private function _salvarTokenNoBanco(string $token, string $refreshToken, int $expiresIn): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
        $now       = date('Y-m-d H:i:s');

        getDB()->prepare("
            INSERT INTO melhorenvio_auth (id, access_token, refresh_token, expires_at, requer_reautorizacao, updated_at)
            VALUES (1, :at, :rt, :ea, 0, :now)
            ON CONFLICT(id) DO UPDATE SET
                access_token         = :at,
                refresh_token        = :rt,
                expires_at           = :ea,
                requer_reautorizacao = 0,
                updated_at           = :now
        ")->execute([':at' => $token, ':rt' => $refreshToken, ':ea' => $expiresAt, ':now' => $now]);

        $this->token        = $token;
        $this->refreshToken = $refreshToken;
    }

    private function _marcarRequerReautorizacao(): void
    {
        try {
            getDB()->prepare("
                UPDATE melhorenvio_auth SET requer_reautorizacao = 1, updated_at = :now WHERE id = 1
            ")->execute([':now' => date('Y-m-d H:i:s')]);
        } catch (Exception $e) {}
    }

}
