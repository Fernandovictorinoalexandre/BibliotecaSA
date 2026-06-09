<?php
// ═══════════════════════════════════════════════════════
// php/logout.php — Encerrar sessão
// POST /php/logout.php → destrói a sessão ativa
// ═══════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

session_start();
session_unset();
session_destroy();

http_response_code(200);
echo json_encode(['mensagem' => 'Logout realizado com sucesso.']);
