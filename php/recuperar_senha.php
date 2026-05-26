<?php
// ═══════════════════════════════════════════════════════
// php/recuperar_senha.php — Redefinir senha
// POST /php/recuperar_senha.php → { email, nova_senha }
// Funciona para usuarios E funcionarios
// ═══════════════════════════════════════════════════════
 
require_once __DIR__ . '/conexao.php';
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}
 
$body  = json_decode(file_get_contents('php://input'), true);
$email = trim($body['email'] ?? '');
$senha = $body['nova_senha'] ?? '';
 
if (empty($email))      { http_response_code(422); echo json_encode(['erro' => 'E-mail obrigatório.']); exit; }
if (empty($senha))      { http_response_code(422); echo json_encode(['erro' => 'Nova senha obrigatória.']); exit; }
if (strlen($senha) < 6) { http_response_code(422); echo json_encode(['erro' => 'Senha deve ter ao menos 6 caracteres.']); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(422); echo json_encode(['erro' => 'E-mail inválido.']); exit; }
 
$pdo   = getConexao();
$email = strtolower($email);
 
// Busca primeiro em usuarios
$stmt = $pdo->prepare('SELECT id, "usuario" AS tabela FROM usuarios WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();
 
// Se não achou, busca em funcionarios
if (!$user) {
    $stmt = $pdo->prepare('SELECT id, "funcionario" AS tabela FROM funcionarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
}
 
if (!$user) {
    http_response_code(404);
    echo json_encode(['erro' => 'E-mail não encontrado.']);
    exit;
}
 
$hash   = password_hash($senha, PASSWORD_BCRYPT);
$tabela = $user['tabela'] === 'funcionario' ? 'funcionarios' : 'usuarios';
$pdo->prepare("UPDATE {$tabela} SET senha = ? WHERE id = ?")
    ->execute([$hash, $user['id']]);
 
http_response_code(200);
echo json_encode(['mensagem' => 'Senha atualizada com sucesso.', 'tipo' => $user['tabela']]);
 