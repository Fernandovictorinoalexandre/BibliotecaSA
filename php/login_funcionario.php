<?php
// ═══════════════════════════════════════════════════════
// php/login_funcionario.php — Autenticação de Funcionário
// POST /php/login_funcionario.php → { email, senha }
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
$senha = $body['senha'] ?? '';

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
    'SELECT id, nome, email, cargo, matricula, senha, status
     FROM funcionarios WHERE email = ? LIMIT 1'
);
$stmt->execute([strtolower($email)]);
$func = $stmt->fetch();

if (!$func) {
    http_response_code(401);
    echo json_encode(['erro' => 'E-mail ou senha incorretos.']);
    exit;
}

if ($func['status'] === 'suspenso') {
    http_response_code(403);
    echo json_encode(['erro' => 'Conta suspensa. Entre em contato com a administração.']);
    exit;
}

if (!password_verify($senha, $func['senha'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'E-mail ou senha incorretos.']);
    exit;
}

session_start();
$_SESSION['funcionario_id']       = $func['id'];
$_SESSION['funcionario_nome']     = $func['nome'];
$_SESSION['funcionario_email']    = $func['email'];
$_SESSION['funcionario_cargo']    = $func['cargo'];
$_SESSION['funcionario_matricula']= $func['matricula'];

http_response_code(200);
echo json_encode([
    'mensagem'     => 'Login realizado com sucesso.',
    'funcionario'  => [
        'id'       => $func['id'],
        'nome'     => $func['nome'],
        'email'    => $func['email'],
        'cargo'    => $func['cargo'],
        'matricula'=> $func['matricula'],
    ],
]);
