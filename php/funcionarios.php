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
    if (!empty($_GET['inativos'])) {
        $stmt = $pdo->query(
            "SELECT fi.funcionario_id AS id, fi.nome, fi.email, fi.cargo, fi.matricula, fi.inativado_em
             FROM funcionarios_inativos fi
             JOIN funcionarios f ON f.id = fi.funcionario_id
             WHERE f.status = 'inativo'
             ORDER BY fi.inativado_em DESC"
        );
        responder(200, $stmt->fetchAll());
    }

    $busca = $_GET['busca'] ?? '';
    if ($busca) {
        $stmt = $pdo->prepare(
            "SELECT id, nome, email, cargo, matricula, status, criado_em
             FROM funcionarios WHERE status != 'inativo' AND (nome LIKE ? OR email LIKE ? OR matricula LIKE ?)
             ORDER BY nome"
        );
        $like = "%$busca%";
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $pdo->query(
            "SELECT id, nome, email, cargo, matricula, status, criado_em
             FROM funcionarios WHERE status != 'inativo' ORDER BY nome"
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
    registrarLog($pdo, [
        'funcionario_id' => $d['funcionario_id'] ?? $id,
        'acao' => 'FUNCIONARIO_EDITADO',
        'entidade' => 'funcionarios', 'entidade_id' => $id,
        'detalhe' => "Funcionário #{$id} editado",
    ]);
    responder(200, ['mensagem' => 'Funcionário atualizado.']);
}

// ── DELETE (INATIVAR) ────────────────────────────────────
if ($metodo === 'DELETE') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório.']);

    $chk = $pdo->prepare('SELECT id, nome, email, cargo, matricula, status, criado_em FROM funcionarios WHERE id = ?');
    $chk->execute([$id]);
    $func = $chk->fetch();
    if (!$func) responder(404, ['erro' => 'Funcionário não encontrado.']);
    if ($func['status'] === 'inativo') responder(409, ['erro' => 'Funcionário já está inativo.']);

    $d = bodyJson();

    // Impede que o funcionário inative a si próprio
    $funcLogadoId = isset($d['func_logado_id']) ? (int) $d['func_logado_id'] : null;
    if ($funcLogadoId && $funcLogadoId === $id) {
        responder(403, ['erro' => 'Você não pode inativar sua própria conta.']);
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE funcionarios SET status = 'inativo' WHERE id = ?")->execute([$id]);

        // Registra no histórico de inativações
        $pdo->prepare(
            'INSERT INTO funcionarios_inativos
               (funcionario_id, nome, email, cargo, matricula, motivo, inativado_por, criado_em, inativado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE inativado_em = NOW(), motivo = VALUES(motivo)'
        )->execute([
            $func['id'],
            $func['nome'],
            $func['email'],
            $func['cargo'],
            $func['matricula'],
            $d['motivo']        ?? null,
            $d['inativado_por'] ?? null,
            $func['criado_em'],
        ]);

        $pdo->commit();
        responder(200, ['mensagem' => 'Funcionário inativado com sucesso.']);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        responder(500, ['erro' => 'Erro ao inativar funcionário.']);
    }
}

// ── PATCH (REATIVAR) ─────────────────────────────────────
if ($metodo === 'PATCH') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório.']);

    $chk = $pdo->prepare('SELECT id, status FROM funcionarios WHERE id = ?');
    $chk->execute([$id]);
    $f = $chk->fetch();
    if (!$f) responder(404, ['erro' => 'Funcionário não encontrado.']);
    if ($f['status'] !== 'inativo') responder(409, ['erro' => 'Funcionário já está ativo.']);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE funcionarios SET status = 'ativo' WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM funcionarios_inativos WHERE funcionario_id = ?")->execute([$id]);
        $pdo->commit();
        responder(200, ['mensagem' => 'Funcionário reativado com sucesso.']);
    } catch (\Throwable $e) {
        $pdo->rollBack();
        responder(500, ['erro' => 'Erro ao reativar funcionário.']);
    }
}

responder(405, ['erro' => 'Método não permitido.']);
