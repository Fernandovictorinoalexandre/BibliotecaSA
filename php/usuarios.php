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
            'SELECT id, nome, email, data_nasc, cpf, telefone, status, foto_perfil, criado_em, atualizado_em
             FROM usuarios WHERE id = ?'
        );
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();
        $usuario
            ? responder(200, $usuario)
            : responder(404, ['erro' => 'Usuário não encontrado.']);
    }

    // Retorna lista de inativos
    if (!empty($_GET['inativos'])) {
        $stmt = $pdo->query(
            "SELECT ui.usuario_id AS id, ui.nome, ui.email, ui.inativado_em
             FROM usuarios_inativos ui
             JOIN usuarios u ON u.id = ui.usuario_id
             WHERE u.status = 'inativo'
             ORDER BY ui.inativado_em DESC"
        );
        responder(200, $stmt->fetchAll());
    }

    $busca = $_GET['busca'] ?? '';
    if ($busca) {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.nome, u.email, u.data_nasc, u.status, u.criado_em,
                    COUNT(CASE WHEN e.data_devolucao_real IS NULL THEN 1 END) AS emprestimos_ativos
             FROM usuarios u
             LEFT JOIN emprestimos e ON e.usuario_id = u.id
             WHERE u.status != 'inativo' AND (u.nome LIKE ? OR u.email LIKE ?)
             GROUP BY u.id
             ORDER BY u.nome"
        );
        $like = "%$busca%";
        $stmt->execute([$like, $like]);
    } else {
        $stmt = $pdo->query(
            "SELECT u.id, u.nome, u.email, u.data_nasc, u.status, u.criado_em,
                    COUNT(CASE WHEN e.data_devolucao_real IS NULL THEN 1 END) AS emprestimos_ativos
             FROM usuarios u
             LEFT JOIN emprestimos e ON e.usuario_id = u.id
             WHERE u.status != 'inativo'
             GROUP BY u.id
             ORDER BY u.nome"
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
        'INSERT INTO usuarios (nome, email, senha, data_nasc, cpf, telefone, status)
         VALUES (:nome, :email, :senha, :data_nasc, :cpf, :telefone, :status)'
    );
    $stmt->execute([
        ':nome'      => trim($d['nome']),
        ':email'     => strtolower(trim($d['email'])),
        ':senha'     => $hash,
        ':data_nasc' => $d['data_nasc'] ?? null,
        ':cpf'       => isset($d['cpf'])      ? preg_replace('/\D/', '', $d['cpf'])      : null,
        ':telefone'  => isset($d['telefone']) ? preg_replace('/\D/', '', $d['telefone']) : null,
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

    if (!empty($d['nome']))      { $campos[] = 'nome = ?';        $params[] = trim($d['nome']); }
    if (!empty($d['email']))     { $campos[] = 'email = ?';       $params[] = strtolower(trim($d['email'])); }
    if (!empty($d['senha']))     { $campos[] = 'senha = ?';       $params[] = password_hash($d['senha'], PASSWORD_BCRYPT); }
    if (isset($d['data_nasc']))  { $campos[] = 'data_nasc = ?';   $params[] = $d['data_nasc'] ?: null; }
    if (!empty($d['status']))    { $campos[] = 'status = ?';      $params[] = $d['status']; }
    if (array_key_exists('foto_perfil', $d)) { $campos[] = 'foto_perfil = ?'; $params[] = $d['foto_perfil'] ?: null; }

    if (empty($campos)) responder(400, ['erro' => 'Nenhum campo para atualizar.']);

    $pdo->beginTransaction();
    try {
        $params[] = $id;
        $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = ?')->execute($params);

        // Se estiver reativando a conta, limpa o registro de inativo
        if (!empty($d['status']) && $d['status'] === 'ativo') {
            $pdo->prepare('DELETE FROM usuarios_inativos WHERE usuario_id = ?')->execute([$id]);
        }

        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        responder(500, ['erro' => 'Erro ao atualizar usuário.']);
    }

    responder(200, ['mensagem' => 'Usuário atualizado com sucesso.']);
}

// ── DELETE (INATIVAR CONTA) ───────────────────────────────

if ($metodo === 'DELETE') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório para inativação.']);

    // Busca usuário
    $chk = $pdo->prepare('SELECT id, nome, email, senha, data_nasc, status, criado_em FROM usuarios WHERE id = ?');
    $chk->execute([$id]);
    $usuario = $chk->fetch();

    if (!$usuario) responder(404, ['erro' => 'Usuário não encontrado.']);

    if ($usuario['status'] === 'inativo') {
        responder(409, ['erro' => 'Esta conta já está inativa.']);
    }

<<<<<<< HEAD
    // Bloqueia se tiver QUALQUER empréstimo não devolvido (qualquer status ativo)
    $emp = $pdo->prepare("SELECT COUNT(*) FROM emprestimos WHERE usuario_id = ? AND status IN ('ativo','atrasado','renovado','aguardando_devolucao') AND data_devolucao_real IS NULL");
    $emp->execute([$id]);
    if ($emp->fetchColumn() > 0) {
        responder(409, ['erro' => 'Usuário possui livros não devolvidos. Devolva todos os livros antes de inativar a conta.']);
=======
    // Bloqueia só se tiver empréstimos ativos ou atrasados SEM solicitação de devolução
    $emp = $pdo->prepare("SELECT COUNT(*) FROM emprestimos WHERE usuario_id = ? AND status IN ('ativo','atrasado') AND data_devolucao_real IS NULL");
    $emp->execute([$id]);
    if ($emp->fetchColumn() > 0) {
        responder(409, ['erro' => 'Você possui livros em aberto. Solicite a devolução de todos os livros antes de desativar a conta.']);
>>>>>>> ad88522de953afc72096d265041dd2c97e48cd82
    }

    // Desativa o usuário e registra no histórico
    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("UPDATE usuarios SET status = 'inativo' WHERE id = ?");
        $upd->execute([$id]);

        // Registra na tabela de histórico de inativações
        $ins = $pdo->prepare(
            'INSERT INTO usuarios_inativos
               (usuario_id, nome, email, cpf, telefone, data_nasc, motivo, inativado_por, criado_em, inativado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE inativado_em = NOW(), motivo = VALUES(motivo)'
        );
        $ins->execute([
            $usuario['id'],
            $usuario['nome'],
            $usuario['email'],
            $usuario['cpf']      ?? null,
            $usuario['telefone'] ?? null,
            $usuario['data_nasc'],
            $d['motivo']         ?? null,
            $d['inativado_por']  ?? null,
            $usuario['criado_em'],
        ]);

        $pdo->commit();
        responder(200, ['mensagem' => 'Usuário inativado com sucesso.']);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        responder(500, ['erro' => 'Erro interno ao inativar o usuário. Tente novamente.']);
    }
}

// ── PATCH (REATIVAR) ─────────────────────────────────────
if ($metodo === 'PATCH') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório.']);

    $chk = $pdo->prepare('SELECT id, status FROM usuarios WHERE id = ?');
    $chk->execute([$id]);
    $u = $chk->fetch();
    if (!$u) responder(404, ['erro' => 'Usuário não encontrado.']);
    if ($u['status'] !== 'inativo') responder(409, ['erro' => 'Usuário já está ativo.']);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE usuarios SET status = 'ativo' WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM usuarios_inativos WHERE usuario_id = ?")->execute([$id]);
        $pdo->commit();
        responder(200, ['mensagem' => 'Usuário reativado com sucesso.']);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        responder(500, ['erro' => 'Erro ao reativar usuário.']);
    }
}

responder(405, ['erro' => 'Método não permitido.']);