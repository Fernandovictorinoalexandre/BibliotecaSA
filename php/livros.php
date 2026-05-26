<?php
// ═══════════════════════════════════════════════════════
// php/livros.php — CRUD de Livros
// Rotas:
//   GET    /php/livros.php            → lista todos
//   GET    /php/livros.php?id=N       → busca um
//   POST   /php/livros.php            → cria
//   PUT    /php/livros.php?id=N       → atualiza
//   DELETE /php/livros.php?id=N       → remove
// ═══════════════════════════════════════════════════════

require_once __DIR__ . '/conexao.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$busca  = isset($_GET['busca']) ? trim($_GET['busca']) : '';
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

function validarLivro(array $d, bool $novo = true): ?string {
    if ($novo && empty($d['titulo'])) return 'Campo "titulo" obrigatório.';
    if ($novo && empty($d['autor']))  return 'Campo "autor" obrigatório.';
    if ($novo && empty($d['isbn']))   return 'Campo "isbn" obrigatório.';
    if (!empty($d['isbn'])) {
        $isbn = preg_replace('/\D/', '', $d['isbn']);
        if (!in_array(strlen($isbn), [10, 13]))
            return 'ISBN deve ter 10 ou 13 dígitos.';
    }
    if (isset($d['quantidade']) && (int)$d['quantidade'] < 0)
        return 'Quantidade não pode ser negativa.';
    return null;
}

// ── GET ──────────────────────────────────────────────────

if ($metodo === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM livros WHERE id = ?');
        $stmt->execute([$id]);
        $livro = $stmt->fetch();
        $livro
            ? responder(200, $livro)
            : responder(404, ['erro' => 'Livro não encontrado.']);
    }

    if ($busca) {
        $stmt = $pdo->prepare(
            'SELECT l.*,
                    (l.quantidade - COALESCE((SELECT COUNT(*) FROM emprestimos e
                     WHERE e.livro_id = l.id AND e.data_devolucao_real IS NULL), 0)) AS quantidade_disponivel
             FROM livros l
             WHERE l.titulo LIKE ? OR l.autor LIKE ? OR l.isbn LIKE ?
             ORDER BY l.titulo'
        );
        $like = "%$busca%";
        $stmt->execute([$like, $like, $like]);
    } else {
        $stmt = $pdo->query(
            'SELECT l.*,
                    (l.quantidade - COALESCE((SELECT COUNT(*) FROM emprestimos e
                     WHERE e.livro_id = l.id AND e.data_devolucao_real IS NULL), 0)) AS quantidade_disponivel
             FROM livros l
             ORDER BY l.titulo'
        );
    }
    responder(200, $stmt->fetchAll());
}

// ── POST (CRIAR) ─────────────────────────────────────────

if ($metodo === 'POST') {
    $d   = bodyJson();
    $err = validarLivro($d);
    if ($err) responder(422, ['erro' => $err]);

    // ISBN sem caracteres não-numéricos
    $isbn = preg_replace('/\D/', '', $d['isbn']);

    // Verifica ISBN duplicado
    $chk = $pdo->prepare('SELECT id FROM livros WHERE isbn = ?');
    $chk->execute([$isbn]);
    if ($chk->fetch()) responder(409, ['erro' => 'ISBN já cadastrado.']);

    $stmt = $pdo->prepare(
        'INSERT INTO livros (titulo, autor, isbn, editora, data_publicacao, quantidade, status, capa, descricao, categoria)
         VALUES (:titulo, :autor, :isbn, :editora, :data_publicacao, :quantidade, :status, :capa, :descricao, :categoria)'
    );
    $stmt->execute([
        ':titulo'          => trim($d['titulo']),
        ':autor'           => trim($d['autor']),
        ':isbn'            => $isbn,
        ':editora'         => isset($d['editora'])         ? trim($d['editora'])  : null,
        ':data_publicacao' => $d['data_publicacao']        ?? null,
        ':quantidade'      => isset($d['quantidade'])      ? (int)$d['quantidade'] : 1,
        ':status'          => $d['status']                 ?? 'disponivel',
        ':capa'            => isset($d['capa'])            ? trim($d['capa'])     : null,
        ':descricao'       => isset($d['descricao'])       ? trim($d['descricao']): null,
        ':categoria'       => isset($d['categoria'])       ? trim($d['categoria']): 'Geral',
    ]);

    responder(201, [
        'mensagem' => 'Livro cadastrado com sucesso.',
        'id'       => (int) $pdo->lastInsertId(),
    ]);
}

// ── PUT (ATUALIZAR) ──────────────────────────────────────

if ($metodo === 'PUT') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório para atualização.']);

    $d   = bodyJson();
    $err = validarLivro($d, false);
    if ($err) responder(422, ['erro' => $err]);

    // Verifica existência
    $chk = $pdo->prepare('SELECT id FROM livros WHERE id = ?');
    $chk->execute([$id]);
    if (!$chk->fetch()) responder(404, ['erro' => 'Livro não encontrado.']);

    // Verifica ISBN duplicado (outro livro)
    if (!empty($d['isbn'])) {
        $isbn = preg_replace('/\D/', '', $d['isbn']);
        $chk2 = $pdo->prepare('SELECT id FROM livros WHERE isbn = ? AND id != ?');
        $chk2->execute([$isbn, $id]);
        if ($chk2->fetch()) responder(409, ['erro' => 'ISBN já usado por outro livro.']);
        $d['isbn'] = $isbn;
    }

    // Monta SET dinamicamente
    $campos = [];
    $params = [];

    if (!empty($d['titulo']))          { $campos[] = 'titulo = ?';          $params[] = trim($d['titulo']); }
    if (!empty($d['autor']))           { $campos[] = 'autor = ?';           $params[] = trim($d['autor']); }
    if (!empty($d['isbn']))            { $campos[] = 'isbn = ?';            $params[] = $d['isbn']; }
    if (isset($d['editora']))          { $campos[] = 'editora = ?';         $params[] = trim($d['editora']); }
    if (isset($d['data_publicacao']))  { $campos[] = 'data_publicacao = ?'; $params[] = $d['data_publicacao'] ?: null; }
    if (isset($d['quantidade']))       { $campos[] = 'quantidade = ?';      $params[] = (int)$d['quantidade']; }
    if (!empty($d['status']))          { $campos[] = 'status = ?';          $params[] = $d['status']; }
    if (isset($d['capa']))             { $campos[] = 'capa = ?';            $params[] = trim($d['capa']) ?: null; }
    if (isset($d['descricao']))        { $campos[] = 'descricao = ?';       $params[] = trim($d['descricao']) ?: null; }
    if (!empty($d['categoria']))       { $campos[] = 'categoria = ?';       $params[] = trim($d['categoria']); }

    if (empty($campos)) responder(400, ['erro' => 'Nenhum campo para atualizar.']);

    $params[] = $id;
    $stmt = $pdo->prepare('UPDATE livros SET ' . implode(', ', $campos) . ' WHERE id = ?');
    $stmt->execute($params);

    responder(200, ['mensagem' => 'Livro atualizado com sucesso.']);
}

// ── DELETE ───────────────────────────────────────────────

if ($metodo === 'DELETE') {
    if (!$id) responder(400, ['erro' => 'ID obrigatório para exclusão.']);

    $stmt = $pdo->prepare('DELETE FROM livros WHERE id = ?');
    $stmt->execute([$id]);

    $stmt->rowCount()
        ? responder(200, ['mensagem' => 'Livro removido com sucesso.'])
        : responder(404, ['erro' => 'Livro não encontrado.']);
}

responder(405, ['erro' => 'Método não permitido.']);
