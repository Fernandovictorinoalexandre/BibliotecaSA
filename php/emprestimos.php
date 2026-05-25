<?php
// ═══════════════════════════════════════════════════════
// php/emprestimos.php — CRUD de Empréstimos
// GET    /php/emprestimos.php               → lista todos
// GET    /php/emprestimos.php?id=N          → busca um
// GET    /php/emprestimos.php?usuario_id=N  → por usuário
// GET    /php/emprestimos.php?status=ativo  → por status
// POST   /php/emprestimos.php               → cria
// PUT    /php/emprestimos.php?id=N          → atualiza (devolver/renovar)
// DELETE /php/emprestimos.php?id=N          → remove
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

// ── GET ──────────────────────────────────────────────────
if ($metodo === 'GET') {

    // Busca um por id
    if ($id) {
        $stmt = $pdo->prepare('
            SELECT e.*,
                   u.nome AS usuario_nome, u.email AS usuario_email,
                   l.titulo, l.autor, l.isbn, l.capa
            FROM emprestimos e
            JOIN usuarios u ON u.id = e.usuario_id
            JOIN livros   l ON l.id = e.livro_id
            WHERE e.id = ?
        ');
        $stmt->execute([$id]);
        $e = $stmt->fetch();
        $e ? responder(200, $e) : responder(404, ['erro' => 'Empréstimo não encontrado.']);
    }

    // Monta WHERE dinâmico
    $where  = ['1=1']; $params = [];

    if (!empty($_GET['usuario_id'])) { $where[] = 'e.usuario_id = ?'; $params[] = (int)$_GET['usuario_id']; }
    if (!empty($_GET['livro_id']))   { $where[] = 'e.livro_id = ?';   $params[] = (int)$_GET['livro_id']; }
    if (!empty($_GET['status']))     { $where[] = 'e.status = ?';     $params[] = $_GET['status']; }
    if (!empty($_GET['busca'])) {
        $like = '%' . $_GET['busca'] . '%';
        $where[] = '(u.nome LIKE ? OR l.titulo LIKE ? OR l.isbn LIKE ?)';
        $params = array_merge($params, [$like, $like, $like]);
    }

    // Atualiza automaticamente atrasados
    $pdo->exec("
        UPDATE emprestimos
        SET status = 'atrasado'
        WHERE status = 'ativo'
          AND data_devolucao_prevista < CURDATE()
          AND data_devolucao_real IS NULL
    ");

    $stmt = $pdo->prepare('
        SELECT e.id, e.data_emprestimo, e.data_devolucao_prevista,
               e.data_devolucao_real, e.renovacoes, e.metodo_pagamento,
               e.valor_pago, e.multa, e.multa_paga, e.status,
               u.id AS usuario_id, u.nome AS usuario_nome,
               l.id AS livro_id, l.titulo, l.autor, l.isbn, l.capa
        FROM emprestimos e
        JOIN usuarios u ON u.id = e.usuario_id
        JOIN livros   l ON l.id = e.livro_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY e.criado_em DESC
    ');
    $stmt->execute($params);
    responder(200, $stmt->fetchAll());
}

// ── POST — Criar empréstimo ──────────────────────────────
if ($metodo === 'POST') {
    $d = bodyJson();

    if (empty($d['usuario_id'])) responder(422, ['erro' => 'usuario_id obrigatório.']);
    if (empty($d['livro_id']))   responder(422, ['erro' => 'livro_id obrigatório.']);

    // Verifica livro disponível considerando quantidade de exemplares
    $livro = $pdo->prepare('SELECT id, status, quantidade FROM livros WHERE id = ?');
    $livro->execute([$d['livro_id']]);
    $l = $livro->fetch();
    if (!$l) responder(404, ['erro' => 'Livro não encontrado.']);
    if ($l['status'] === 'indisponivel') responder(409, ['erro' => 'Livro indisponível no momento.']);

    // Conta quantos exemplares estão atualmente emprestados
    $empAtivos = $pdo->prepare(
        "SELECT COUNT(*) FROM emprestimos
         WHERE livro_id = ? AND data_devolucao_real IS NULL"
    );
    $empAtivos->execute([$d['livro_id']]);
    $qtdEmprestada = (int) $empAtivos->fetchColumn();

    if ($qtdEmprestada >= (int) $l['quantidade']) {
        responder(409, ['erro' => 'Todos os exemplares deste livro estão emprestados.']);
    }

    // Verifica limite de 5 por usuário
    $ativos = $pdo->prepare(
        "SELECT COUNT(*) FROM emprestimos
         WHERE usuario_id = ? AND status IN ('ativo','atrasado') AND data_devolucao_real IS NULL"
    );
    $ativos->execute([$d['usuario_id']]);
    if ($ativos->fetchColumn() >= 5) responder(409, ['erro' => 'Usuário já possui 5 livros emprestados.']);

    // Verifica se já tem este livro
    $jatem = $pdo->prepare(
        "SELECT id FROM emprestimos
         WHERE usuario_id = ? AND livro_id = ? AND data_devolucao_real IS NULL"
    );
    $jatem->execute([$d['usuario_id'], $d['livro_id']]);
    if ($jatem->fetch()) responder(409, ['erro' => 'Usuário já tem este livro emprestado.']);

    $hoje    = date('Y-m-d');
    $prevista = date('Y-m-d', strtotime('+30 days'));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('
            INSERT INTO emprestimos
              (usuario_id, livro_id, funcionario_id, data_emprestimo,
               data_devolucao_prevista, metodo_pagamento, valor_pago, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, \'ativo\')
        ');
        $stmt->execute([
            $d['usuario_id'],
            $d['livro_id'],
            $d['funcionario_id'] ?? null,
            $d['data_emprestimo'] ?? $hoje,
            $d['data_devolucao_prevista'] ?? $prevista,
            $d['metodo_pagamento'] ?? null,
            $d['valor_pago'] ?? 15.00,
        ]);
        $novoId = $pdo->lastInsertId();

        // Atualiza status do livro baseado na quantidade emprestada
        $totalEmp = $pdo->prepare(
            "SELECT COUNT(*) FROM emprestimos WHERE livro_id = ? AND data_devolucao_real IS NULL"
        );
        $totalEmp->execute([$d['livro_id']]);
        $saindo = (int) $totalEmp->fetchColumn();
        if ($saindo >= (int) $l['quantidade']) {
            $pdo->prepare("UPDATE livros SET status = 'emprestado' WHERE id = ?")
                ->execute([$d['livro_id']]);
        }

        $pdo->commit();
        responder(201, ['mensagem' => 'Empréstimo registrado.', 'id' => (int)$novoId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        responder(500, ['erro' => 'Erro ao registrar empréstimo.']);
    }
}

// ── PUT — Atualizar (devolver / renovar / multa) ─────────
if ($metodo === 'PUT') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório.']);
    $d = bodyJson();

    $stmt = $pdo->prepare('SELECT * FROM emprestimos WHERE id = ?');
    $stmt->execute([$id]);
    $emp = $stmt->fetch();
    if (!$emp) responder(404, ['erro' => 'Empréstimo não encontrado.']);

    $pdo->beginTransaction();
    try {
        // DEVOLUÇÃO
        if (!empty($d['devolver'])) {
            $hoje  = date('Y-m-d');
            $multa = 0;
            if ($emp['status'] === 'atrasado') {
                $dias  = (int) floor((strtotime($hoje) - strtotime($emp['data_devolucao_prevista'])) / 86400);
                $multa = $dias * 1.00;
            }
            $pdo->prepare("
                UPDATE emprestimos
                SET status = 'devolvido', data_devolucao_real = ?, multa = ?, multa_paga = ?
                WHERE id = ?
            ")->execute([$hoje, $multa, $d['multa_paga'] ?? 0, $id]);

            // Libera o livro se houver exemplares disponíveis
            $livroInfo = $pdo->prepare('SELECT quantidade FROM livros WHERE id = ?');
            $livroInfo->execute([$emp['livro_id']]);
            $qtdTotal = (int) ($livroInfo->fetchColumn() ?: 0);

            $outros = $pdo->prepare(
                "SELECT COUNT(*) FROM emprestimos
                 WHERE livro_id = ? AND id != ? AND data_devolucao_real IS NULL"
            );
            $outros->execute([$emp['livro_id'], $id]);
            $qtdAindaEmprestada = (int) $outros->fetchColumn();

            if ($qtdAindaEmprestada < $qtdTotal) {
                $pdo->prepare("UPDATE livros SET status = 'disponivel' WHERE id = ?")
                    ->execute([$emp['livro_id']]);
            }
            $pdo->commit();
            responder(200, ['mensagem' => 'Devolução registrada.', 'multa' => $multa]);
        }

        // RENOVAÇÃO
        if (!empty($d['renovar'])) {
            if ($emp['renovacoes'] >= 2) responder(409, ['erro' => 'Limite de 2 renovações atingido.']);
            if ($emp['status'] === 'atrasado') responder(409, ['erro' => 'Não é possível renovar empréstimo em atraso.']);

            $novaData = date('Y-m-d', strtotime($emp['data_devolucao_prevista'] . ' +30 days'));
            $pdo->prepare("
                UPDATE emprestimos
                SET data_devolucao_prevista = ?, renovacoes = renovacoes + 1, status = 'renovado'
                WHERE id = ?
            ")->execute([$novaData, $id]);
            $pdo->commit();
            responder(200, ['mensagem' => 'Empréstimo renovado.', 'nova_data' => $novaData]);
        }

        // ATUALIZAÇÃO GERAL
        $campos = []; $params = [];
        if (!empty($d['status']))              { $campos[] = 'status = ?';              $params[] = $d['status']; }
        if (isset($d['multa']))                { $campos[] = 'multa = ?';               $params[] = $d['multa']; }
        if (isset($d['multa_paga']))           { $campos[] = 'multa_paga = ?';          $params[] = $d['multa_paga']; }
        if (!empty($d['data_devolucao_real'])) { $campos[] = 'data_devolucao_real = ?'; $params[] = $d['data_devolucao_real']; }

        if (empty($campos)) { $pdo->commit(); responder(400, ['erro' => 'Nenhum campo para atualizar.']); }
        $params[] = $id;
        $pdo->prepare('UPDATE emprestimos SET ' . implode(', ', $campos) . ' WHERE id = ?')->execute($params);
        $pdo->commit();
        responder(200, ['mensagem' => 'Empréstimo atualizado.']);

    } catch (Exception $e) {
        $pdo->rollBack();
        responder(500, ['erro' => 'Erro ao atualizar empréstimo.']);
    }
}

// ── DELETE ───────────────────────────────────────────────
if ($metodo === 'DELETE') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório.']);
    $stmt = $pdo->prepare('DELETE FROM emprestimos WHERE id = ?');
    $stmt->execute([$id]);
    $stmt->rowCount()
        ? responder(200, ['mensagem' => 'Empréstimo removido.'])
        : responder(404, ['erro' => 'Empréstimo não encontrado.']);
}

responder(405, ['erro' => 'Método não permitido.']);
