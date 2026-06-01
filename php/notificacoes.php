<?php
// ═══════════════════════════════════════════════════════
// php/notificacoes.php — Notificações de usuários
// GET  /php/notificacoes.php?usuario_id=N     → lista notificações do usuário
// POST /php/notificacoes.php                  → cria notificação (funcionário)
// PUT  /php/notificacoes.php?id=N             → marca como lida
// PUT  /php/notificacoes.php?usuario_id=N&todas=1 → marca todas como lidas
// ═══════════════════════════════════════════════════════

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/auth.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id'])         ? (int) $_GET['id']         : null;
$uid    = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : null;
$pdo    = getConexao();

// GET/PUT → usuário vê/marca as próprias; funcionário acessa qualquer uma
// POST    → só funcionário pode criar notificação
if ($metodo === 'POST') {
    exigirFuncionario();
} else {
    $sessaoUsuario = usuarioLogadoOuNull();
    $sessaoFunc    = !empty($_SESSION['funcionario_id']) ? (int)$_SESSION['funcionario_id'] : null;
    if ($sessaoUsuario === null && $sessaoFunc === null) {
        http_response_code(401);
        echo json_encode(['erro' => 'Não autenticado.']);
        exit;
    }
    // Usuário só vê/marca as próprias notificações
    if ($sessaoFunc === null && $uid !== null && $uid !== $sessaoUsuario) {
        http_response_code(403);
        echo json_encode(['erro' => 'Acesso negado.']);
        exit;
    }
}

function bodyJson(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}
function responder(int $status, mixed $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── GET — Lista notificações do usuário ──────────────────
if ($metodo === 'GET') {
    if (!$uid) responder(400, ['erro' => 'usuario_id obrigatório.']);

    $soNaoLidas = isset($_GET['nao_lidas']) ? 1 : 0;
    $where = 'n.usuario_id = ?';
    $params = [$uid];
    if ($soNaoLidas) {
        $where .= ' AND n.lida = 0';
    }

    $stmt = $pdo->prepare("
        SELECT n.id, n.tipo, n.titulo, n.mensagem, n.lida, n.criado_em,
               n.emprestimo_id,
               e.data_devolucao_prevista,
               l.titulo AS livro_titulo
        FROM notificacoes n
        LEFT JOIN emprestimos e ON e.id = n.emprestimo_id
        LEFT JOIN livros      l ON l.id = e.livro_id
        WHERE {$where}
        ORDER BY n.criado_em DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    responder(200, $stmt->fetchAll());
}

// ── POST — Criar notificação (funcionário envia ao usuário) ──
if ($metodo === 'POST') {
    $d = bodyJson();

    if (empty($d['usuario_id'])) responder(422, ['erro' => 'usuario_id obrigatório.']);
    if (empty($d['mensagem']))   responder(422, ['erro' => 'mensagem obrigatória.']);

    // Verifica se usuário existe
    $chk = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
    $chk->execute([$d['usuario_id']]);
    if (!$chk->fetch()) responder(404, ['erro' => 'Usuário não encontrado.']);

    $tipo      = $d['tipo']          ?? 'atraso';
    $titulo    = $d['titulo']        ?? 'Aviso da Biblioteca';
    $empId     = $d['emprestimo_id'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO notificacoes (usuario_id, emprestimo_id, tipo, titulo, mensagem)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$d['usuario_id'], $empId, $tipo, $titulo, $d['mensagem']]);

    responder(201, [
        'mensagem' => 'Notificação enviada.',
        'id'       => (int) $pdo->lastInsertId(),
    ]);
}

// ── PUT — Marcar como lida ───────────────────────────────
if ($metodo === 'PUT') {
    // Marcar todas de um usuário
    if ($uid && isset($_GET['todas'])) {
        $pdo->prepare('UPDATE notificacoes SET lida = 1 WHERE usuario_id = ?')->execute([$uid]);
        responder(200, ['mensagem' => 'Todas as notificações marcadas como lidas.']);
    }

    // Marcar uma específica
    if (!$id) responder(400, ['erro' => 'id obrigatório.']);
    $pdo->prepare('UPDATE notificacoes SET lida = 1 WHERE id = ?')->execute([$id]);
    responder(200, ['mensagem' => 'Notificação marcada como lida.']);
}

responder(405, ['erro' => 'Método não permitido.']);
