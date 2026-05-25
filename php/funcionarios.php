<?php
// ═══════════════════════════════════════════════════════
// php/funcionarios.php — CRUD de Funcionários
// GET    /php/funcionarios.php          → lista todos
// GET    /php/funcionarios.php?id=N     → busca um
// POST   /php/funcionarios.php          → cria
// PUT    /php/funcionarios.php?id=N     → atualiza
// DELETE /php/funcionarios.php?id=N     → remove
// ═══════════════════════════════════════════════════════

require_once __DIR__ . '/conexao.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$pdo    = getConexao();

function bodyJson(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}
function responder(int $status, mixed $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
function validar(array $d, bool $novo = true): ?string {
    if ($novo && empty($d['nome']))      return 'Campo "nome" obrigatório.';
    if ($novo && empty($d['email']))     return 'Campo "email" obrigatório.';
    if ($novo && empty($d['senha']))     return 'Campo "senha" obrigatório.';
    if ($novo && empty($d['matricula'])) return 'Campo "matricula" obrigatório.';
    if (!empty($d['email']) && !filter_var($d['email'], FILTER_VALIDATE_EMAIL))
        return 'E-mail inválido.';
    if (!empty($d['senha']) && strlen($d['senha']) < 6)
        return 'Senha deve ter ao menos 6 caracteres.';
    return null;
}

// ── GET ──────────────────────────────────────────────────
if ($metodo === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare(
            'SELECT id, nome, email, cargo, matricula, status, criado_em
             FROM funcionarios WHERE id = ?'
        );
        $stmt->execute([$id]);
        $f = $stmt->fetch();
        $f ? responder(200, $f) : responder(404, ['erro' => 'Funcionário não encontrado.']);
    }
    $busca = $_GET['busca'] ?? '';
    if ($busca) {
        $stmt = $pdo->prepare(
            'SELECT id, nome, email, cargo, matricula, status, criado_em
             FROM funcionarios WHERE nome LIKE ? OR email LIKE ? OR matricula LIKE ?
             ORDER BY nome'
        );
        $like = "%$busca%";
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $pdo->query(
            'SELECT id, nome, email, cargo, matricula, status, criado_em
             FROM funcionarios ORDER BY nome'
        );
    }
    responder(200, $stmt->fetchAll());
}

// ── POST ─────────────────────────────────────────────────
if ($metodo === 'POST') {
    $d = bodyJson();
    $err = validar($d);
    if ($err) responder(422, ['erro' => $err]);

    $chk = $pdo->prepare('SELECT id FROM funcionarios WHERE email = ?');
    $chk->execute([$d['email']]);
    if ($chk->fetch()) responder(409, ['erro' => 'E-mail já cadastrado.']);

    $chkM = $pdo->prepare('SELECT id FROM funcionarios WHERE matricula = ?');
    $chkM->execute([$d['matricula']]);
    if ($chkM->fetch()) responder(409, ['erro' => 'Matrícula já cadastrada.']);

    $stmt = $pdo->prepare(
        'INSERT INTO funcionarios (nome, email, senha, cargo, matricula, status)
         VALUES (:nome, :email, :senha, :cargo, :matricula, :status)'
    );
    $stmt->execute([
        ':nome'      => trim($d['nome']),
        ':email'     => strtolower(trim($d['email'])),
        ':senha'     => password_hash($d['senha'], PASSWORD_BCRYPT),
        ':cargo'     => $d['cargo']     ?? 'Bibliotecário',
        ':matricula' => strtoupper(trim($d['matricula'])),
        ':status'    => $d['status']    ?? 'ativo',
    ]);
    responder(201, ['mensagem' => 'Funcionário cadastrado.', 'id' => (int) $pdo->lastInsertId()]);
}

// ── PUT ──────────────────────────────────────────────────
if ($metodo === 'PUT') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório.']);
    $d   = bodyJson();
    $err = validar($d, false);
    if ($err) responder(422, ['erro' => $err]);

    $chk = $pdo->prepare('SELECT id FROM funcionarios WHERE id = ?');
    $chk->execute([$id]);
    if (!$chk->fetch()) responder(404, ['erro' => 'Funcionário não encontrado.']);

    if (!empty($d['email'])) {
        $chk2 = $pdo->prepare('SELECT id FROM funcionarios WHERE email = ? AND id != ?');
        $chk2->execute([$d['email'], $id]);
        if ($chk2->fetch()) responder(409, ['erro' => 'E-mail já em uso.']);
    }

    $campos = []; $params = [];
    if (!empty($d['nome']))      { $campos[] = 'nome = ?';      $params[] = trim($d['nome']); }
    if (!empty($d['email']))     { $campos[] = 'email = ?';     $params[] = strtolower(trim($d['email'])); }
    if (!empty($d['senha']))     { $campos[] = 'senha = ?';     $params[] = password_hash($d['senha'], PASSWORD_BCRYPT); }
    if (!empty($d['cargo']))     { $campos[] = 'cargo = ?';     $params[] = $d['cargo']; }
    if (!empty($d['matricula'])) { $campos[] = 'matricula = ?'; $params[] = strtoupper(trim($d['matricula'])); }
    if (!empty($d['status']))    { $campos[] = 'status = ?';    $params[] = $d['status']; }

    if (empty($campos)) responder(400, ['erro' => 'Nenhum campo para atualizar.']);
    $params[] = $id;
    $pdo->prepare('UPDATE funcionarios SET ' . implode(', ', $campos) . ' WHERE id = ?')->execute($params);
    responder(200, ['mensagem' => 'Funcionário atualizado.']);
}

// ── DELETE ───────────────────────────────────────────────
if ($metodo === 'DELETE') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório.']);
    $chk = $pdo->prepare('SELECT id FROM emprestimos WHERE funcionario_id = ? LIMIT 1');
    $chk->execute([$id]);
    if ($chk->fetch()) responder(409, ['erro' => 'Funcionário possui empréstimos vinculados.']);

    $stmt = $pdo->prepare('DELETE FROM funcionarios WHERE id = ?');
    $stmt->execute([$id]);
    $stmt->rowCount()
        ? responder(200, ['mensagem' => 'Funcionário removido.'])
        : responder(404, ['erro' => 'Funcionário não encontrado.']);
}

responder(405, ['erro' => 'Método não permitido.']);
