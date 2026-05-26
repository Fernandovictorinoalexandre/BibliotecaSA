<?php
// ═══════════════════════════════════════════════════════
// php/conexao.php — Conexão com o banco de dados
// ═══════════════════════════════════════════════════════

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // altere para seu usuário MySQL
define('DB_PASS', '');           // altere para sua senha MySQL
define('DB_NAME', 'estacao_literaria');
define('DB_PORT', 3306);

function getConexao(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT
             . ";dbname=" . DB_NAME . ";charset=utf8mb4";

        $opcoes = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opcoes);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['erro' => 'Falha na conexão com o banco de dados.']);
            exit;
        }
    }

    return $pdo;
}

// Garante que a resposta será sempre JSON
header('Content-Type: application/json; charset=utf-8');

// Permite requisições do mesmo domínio (ajuste se usar domínio separado)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
