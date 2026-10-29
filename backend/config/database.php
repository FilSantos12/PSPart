<?php

define('DB_PATH', __DIR__ . '/../../database.db');

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // Códigos estendidos (Patch T): errorInfo[1] distingue UNIQUE (2067) de FK (787) etc.
        // Pdo\Sqlite existe a partir do PHP 8.4; a constante PDO::SQLITE_* é a de 8.1–8.3
        $pdo->setAttribute(
            class_exists(\Pdo\Sqlite::class) ? \Pdo\Sqlite::ATTR_EXTENDED_RESULT_CODES : PDO::SQLITE_ATTR_EXTENDED_RESULT_CODES,
            true
        );
        $pdo->exec('PRAGMA foreign_keys = ON;');
    }

    return $pdo;
}
