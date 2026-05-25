<?php
// ═══════════════════════════════════════════════════════
// php/login_usuario.php — Autenticação de Usuário
// POST /php/login_usuario.php → { email, senha }
// ═══════════════════════════════════════════════════════

require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$email = trim($body['email'] ?? '');
$senha = $body['senha']       ?? '';

if (empty($email) || empty($senha)) {
    http_response_code(422);
    echo json_encode(['erro' => 'E-mail e senha são obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['erro' => 'E-mail inválido.']);
    exit;
}

$pdo  = getConexao();
$stmt = $pdo->prepare(
    'SELECT id, nome, email, senha, status
     FROM usuarios WHERE email = ? LIMIT 1'
);
$stmt->execute([strtolower($email)]);
$usuario = $stmt->fetch();

if (!$usuario) {
    http_response_code(401);
    echo json_encode(['erro' => 'E-mail ou senha incorretos.']);
    exit;
}

if ($usuario['status'] === 'suspenso') {
    http_response_code(403);
    echo json_encode(['erro' => 'Conta suspensa. Entre em contato com a biblioteca.']);
    exit;
}

if (!password_verify($senha, $usuario['senha'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'E-mail ou senha incorretos.']);
    exit;
}

session_start();
$_SESSION['usuario_id']    = $usuario['id'];
$_SESSION['usuario_nome']  = $usuario['nome'];
$_SESSION['usuario_email'] = $usuario['email'];

http_response_code(200);
echo json_encode([
    'mensagem' => 'Login realizado com sucesso.',
    'usuario'  => [
        'id'    => $usuario['id'],
        'nome'  => $usuario['nome'],
        'email' => $usuario['email'],
    ],
]);