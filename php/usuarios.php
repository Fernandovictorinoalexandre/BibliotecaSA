<?php
// ═══════════════════════════════════════════════════════
// php/usuarios.php — CRUD de Usuários
// Rotas:
//   GET    /php/usuarios.php          → lista todos
//   GET    /php/usuarios.php?id=N     → busca um
//   POST   /php/usuarios.php          → cria
//   PUT    /php/usuarios.php?id=N     → atualiza
//   DELETE /php/usuarios.php?id=N     → remove
// ═══════════════════════════════════════════════════════

require_once __DIR__ . '/conexao.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$pdo    = getConexao();

// ── Helpers ─────────────────────────────────────────────

function bodyJson(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

function responder(int $status, mixed $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function validarUsuario(array $d, bool $novo = true): ?string {
    if ($novo && empty($d['nome']))   return 'Campo "nome" obrigatório.';
    if ($novo && empty($d['email']))  return 'Campo "email" obrigatório.';
    if ($novo && empty($d['senha']))  return 'Campo "senha" obrigatório.';
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
            'SELECT id, nome, email, data_nasc, status, criado_em, atualizado_em
             FROM usuarios WHERE id = ?'
        );
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();
        $usuario
            ? responder(200, $usuario)
            : responder(404, ['erro' => 'Usuário não encontrado.']);
    }

    $busca = $_GET['busca'] ?? '';
    if ($busca) {
        $stmt = $pdo->prepare(
            'SELECT id, nome, email, data_nasc, status, criado_em
             FROM usuarios
             WHERE nome LIKE ? OR email LIKE ?
             ORDER BY nome'
        );
        $like = "%$busca%";
        $stmt->execute([$like, $like]);
    } else {
        $stmt = $pdo->query(
            'SELECT id, nome, email, data_nasc, status, criado_em
             FROM usuarios ORDER BY nome'
        );
    }
    responder(200, $stmt->fetchAll());
}

// ── POST (CRIAR) ─────────────────────────────────────────

if ($metodo === 'POST') {
    $d   = bodyJson();
    $err = validarUsuario($d);
    if ($err) responder(422, ['erro' => $err]);

    // Verifica e-mail duplicado
    $chk = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $chk->execute([$d['email']]);
    if ($chk->fetch()) responder(409, ['erro' => 'E-mail já cadastrado.']);

    $hash = password_hash($d['senha'], PASSWORD_BCRYPT);

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nome, email, senha, data_nasc, status)
         VALUES (:nome, :email, :senha, :data_nasc, :status)'
    );
    $stmt->execute([
        ':nome'      => trim($d['nome']),
        ':email'     => strtolower(trim($d['email'])),
        ':senha'     => $hash,
        ':data_nasc' => $d['data_nasc'] ?? null,
        ':status'    => $d['status']    ?? 'ativo',
    ]);

    responder(201, [
        'mensagem' => 'Usuário cadastrado com sucesso.',
        'id'       => (int) $pdo->lastInsertId(),
    ]);
}

// ── PUT (ATUALIZAR) ──────────────────────────────────────

if ($metodo === 'PUT') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório para atualização.']);

    $d   = bodyJson();
    $err = validarUsuario($d, false);   // campos opcionais na edição
    if ($err) responder(422, ['erro' => $err]);

    // Verifica existência
    $chk = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
    $chk->execute([$id]);
    if (!$chk->fetch()) responder(404, ['erro' => 'Usuário não encontrado.']);

    // Verifica e-mail duplicado (outro usuário)
    if (!empty($d['email'])) {
        $chk2 = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ?');
        $chk2->execute([$d['email'], $id]);
        if ($chk2->fetch()) responder(409, ['erro' => 'E-mail já usado por outro usuário.']);
    }

    // Monta SET dinamicamente (atualiza apenas campos enviados)
    $campos = [];
    $params = [];

    if (!empty($d['nome']))      { $campos[] = 'nome = ?';      $params[] = trim($d['nome']); }
    if (!empty($d['email']))     { $campos[] = 'email = ?';     $params[] = strtolower(trim($d['email'])); }
    if (!empty($d['senha']))     { $campos[] = 'senha = ?';     $params[] = password_hash($d['senha'], PASSWORD_BCRYPT); }
    if (isset($d['data_nasc']))  { $campos[] = 'data_nasc = ?'; $params[] = $d['data_nasc'] ?: null; }
    if (!empty($d['status']))    { $campos[] = 'status = ?';    $params[] = $d['status']; }

    if (empty($campos)) responder(400, ['erro' => 'Nenhum campo para atualizar.']);

    $params[] = $id;
    $stmt = $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = ?');
    $stmt->execute($params);

    responder(200, ['mensagem' => 'Usuário atualizado com sucesso.']);
}

// ── DELETE ───────────────────────────────────────────────

if ($metodo === 'DELETE') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório para exclusão.']);

    $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);

    $stmt->rowCount()
        ? responder(200, ['mensagem' => 'Usuário removido com sucesso.'])
        : responder(404, ['erro' => 'Usuário não encontrado.']);
}

responder(405, ['erro' => 'Método não permitido.']);
