<?php
/**
 * migrate_carrossel_campanhas.php
 * Cria a tabela carrossel_campanhas (Feature D — carrossel de campanhas no storefront).
 * Idempotente — pode rodar mais de uma vez sem erro. Execute via CLI:
 *   php migrations/migrate_carrossel_campanhas.php
 */

require_once __DIR__ . '/../backend/config/database.php';

try {
    $db = getDB();

    $db->exec("
        CREATE TABLE IF NOT EXISTS carrossel_campanhas (
            id            INTEGER  PRIMARY KEY AUTOINCREMENT,
            titulo        TEXT     NOT NULL,
            arquivo       TEXT     NOT NULL,
            link_destino  TEXT     DEFAULT NULL,
            ordem         INTEGER  NOT NULL DEFAULT 0,
            ativo         INTEGER  NOT NULL DEFAULT 1,
            data_inicio   TEXT     DEFAULT NULL,
            data_fim      TEXT     DEFAULT NULL,
            created_at    TEXT     NOT NULL,
            updated_at    TEXT     NOT NULL
        )
    ");

    echo "Tabela carrossel_campanhas criada/verificada com sucesso.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo 'Erro: ' . $e->getMessage() . "\n";
}
