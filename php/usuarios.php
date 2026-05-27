<?php
// ═══════════════════════════════════════════════════════
// php/usuarios.php — CRUD de Usuários
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
            'SELECT u.id, u.nome, u.email, u.data_nasc, u.status, u.criado_em,
                    COUNT(CASE WHEN e.data_devolucao_real IS NULL THEN 1 END) AS emprestimos_ativos
             FROM usuarios u
             LEFT JOIN emprestimos e ON e.usuario_id = u.id
             WHERE u.nome LIKE ? OR u.email LIKE ?
             GROUP BY u.id
             ORDER BY u.nome'
        );
        $like = "%$busca%";
        $stmt->execute([$like, $like]);
    } else {
        $stmt = $pdo->query(
            'SELECT u.id, u.nome, u.email, u.data_nasc, u.status, u.criado_em,
                    COUNT(CASE WHEN e.data_devolucao_real IS NULL THEN 1 END) AS emprestimos_ativos
             FROM usuarios u
             LEFT JOIN emprestimos e ON e.usuario_id = u.id
             GROUP BY u.id
             ORDER BY u.nome'
        );
    }
    responder(200, $stmt->fetchAll());
}

// ── POST (CRIAR) ─────────────────────────────────────────

if ($metodo === 'POST') {
    $d   = bodyJson();
    $err = validarUsuario($d);
    if ($err) responder(422, ['erro' => $err]);

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
    $err = validarUsuario($d, false);
    if ($err) responder(422, ['erro' => $err]);

    $chk = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
    $chk->execute([$id]);
    if (!$chk->fetch()) responder(404, ['erro' => 'Usuário não encontrado.']);

    if (!empty($d['email'])) {
        $chk2 = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ?');
        $chk2->execute([$d['email'], $id]);
        if ($chk2->fetch()) responder(409, ['erro' => 'E-mail já usado por outro usuário.']);
    }

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

// ✅ ALTERADO: DELETE agora DESATIVA ao invés de excluir ──

if ($metodo === 'DELETE') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório para desativação.']);

    $d     = bodyJson();
    $senha = $d['senha'] ?? '';

    if (empty($senha)) responder(422, ['erro' => 'Senha obrigatória para desativar a conta.']);

    // Verifica senha
    $chk = $pdo->prepare('SELECT id, nome, email, senha, data_nasc, status, criado_em FROM usuarios WHERE id = ?');
    $chk->execute([$id]);
    $usuario = $chk->fetch();

    if (!$usuario) responder(404, ['erro' => 'Usuário não encontrado.']);

    if ($usuario['status'] === 'inativo') {
        responder(409, ['erro' => 'Esta conta já está desativada.']);
    }

    if (!password_verify($senha, $usuario['senha'])) {
        responder(401, ['erro' => 'Senha incorreta. Tente novamente.']);
    }

    // Bloqueia se tiver empréstimos ativos
    $emp = $pdo->prepare("SELECT COUNT(*) FROM emprestimos WHERE usuario_id = ? AND status IN ('ativo','atrasado') AND data_devolucao_real IS NULL");
    $emp->execute([$id]);
    if ($emp->fetchColumn() > 0) {
        responder(409, ['erro' => 'Você possui livros em aberto. Devolva-os antes de desativar a conta.']);
    }

    // Inicia transação: atualiza status e copia para usuarios_inativos
    $pdo->beginTransaction();
    try {
        // 1. Marca como inativo na tabela principal
        $upd = $pdo->prepare("UPDATE usuarios SET status = 'inativo' WHERE id = ?");
        $upd->execute([$id]);

        // 2. Copia para a tabela de inativos
        $ins = $pdo->prepare(
            'INSERT INTO usuarios_inativos (usuario_id, nome, email, senha, data_nasc, criado_em, desativado_em)
             VALUES (:usuario_id, :nome, :email, :senha, :data_nasc, :criado_em, NOW())
             ON DUPLICATE KEY UPDATE desativado_em = NOW()'
        );
        $ins->execute([
            ':usuario_id' => $usuario['id'],
            ':nome'       => $usuario['nome'],
            ':email'      => $usuario['email'],
            ':senha'      => $usuario['senha'],
            ':data_nasc'  => $usuario['data_nasc'],
            ':criado_em'  => $usuario['criado_em'],
        ]);

        $pdo->commit();
        responder(200, ['mensagem' => 'Conta desativada com sucesso. Entre em contato com a biblioteca para reativá-la.']);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        responder(500, ['erro' => 'Erro interno ao desativar a conta. Tente novamente.']);
    }
}

responder(405, ['erro' => 'Método não permitido.']);