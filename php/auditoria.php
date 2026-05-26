<?php
// ═══════════════════════════════════════════════════════
// auditoria.php — Log de ações dos funcionários
// GET    /php/auditoria.php                → lista (com filtros)
// POST   /php/auditoria.php               → registra (interno)
// ═══════════════════════════════════════════════════════
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once 'conexao.php';

function responder(int $status, mixed $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bodyJson(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

$pdo    = getConexao();
$metodo = $_SERVER['REQUEST_METHOD'];

// ── Garante que a tabela existe ───────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS auditoria (
        id              INT          AUTO_INCREMENT PRIMARY KEY,
        funcionario_id  INT          NOT NULL,
        funcionario_nome VARCHAR(120),
        acao            VARCHAR(80)  NOT NULL,
        entidade        VARCHAR(40),
        entidade_id     INT,
        detalhe         TEXT,
        ip              VARCHAR(45),
        criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_aud_func ON auditoria (funcionario_id)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_aud_acao ON auditoria (acao)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_aud_data ON auditoria (criado_em)");

// ── GET — Listar com filtros ──────────────────────────
if ($metodo === 'GET') {
    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['funcionario_id'])) {
        $where[]  = 'a.funcionario_id = ?';
        $params[] = (int) $_GET['funcionario_id'];
    }
    if (!empty($_GET['acao'])) {
        $where[]  = 'a.acao = ?';
        $params[] = $_GET['acao'];
    }
    if (!empty($_GET['entidade'])) {
        $where[]  = 'a.entidade = ?';
        $params[] = $_GET['entidade'];
    }
    if (!empty($_GET['data_ini'])) {
        $where[]  = 'DATE(a.criado_em) >= ?';
        $params[] = $_GET['data_ini'];
    }
    if (!empty($_GET['data_fim'])) {
        $where[]  = 'DATE(a.criado_em) <= ?';
        $params[] = $_GET['data_fim'];
    }
    if (!empty($_GET['busca'])) {
        $like     = '%' . $_GET['busca'] . '%';
        $where[]  = '(a.detalhe LIKE ? OR a.funcionario_nome LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }

    $limit  = min((int) ($_GET['limit'] ?? 100), 500);
    $offset = (int) ($_GET['offset'] ?? 0);

    $sql = "
        SELECT  a.id, a.funcionario_id, a.funcionario_nome,
                a.acao, a.entidade, a.entidade_id,
                a.detalhe, a.ip,
                a.criado_em
        FROM    auditoria a
        WHERE   " . implode(' AND ', $where) . "
        ORDER BY a.criado_em DESC
        LIMIT   $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Total sem paginação
    $sqlCount = "SELECT COUNT(*) FROM auditoria a WHERE " . implode(' AND ', $where);
    $stmtC    = $pdo->prepare($sqlCount);
    $stmtC->execute($params);
    $total    = (int) $stmtC->fetchColumn();

    responder(200, ['total' => $total, 'registros' => $rows]);
}

// ── POST — Registrar log ──────────────────────────────
if ($metodo === 'POST') {
    $d = bodyJson();
    if (empty($d['funcionario_id'])) responder(422, ['erro' => 'funcionario_id obrigatório.']);
    if (empty($d['acao']))           responder(422, ['erro' => 'acao obrigatória.']);

    // Busca nome do funcionário se não vier
    $nome = $d['funcionario_nome'] ?? null;
    if (!$nome) {
        $s = $pdo->prepare('SELECT nome FROM funcionarios WHERE id = ?');
        $s->execute([$d['funcionario_id']]);
        $nome = $s->fetchColumn() ?: 'Desconhecido';
    }

    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

    $pdo->prepare("
        INSERT INTO auditoria
            (funcionario_id, funcionario_nome, acao, entidade, entidade_id, detalhe, ip)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        (int)   $d['funcionario_id'],
                $nome,
                $d['acao'],
                $d['entidade']    ?? null,
        isset($d['entidade_id']) ? (int)$d['entidade_id'] : null,
                $d['detalhe']     ?? null,
                $ip,
    ]);

    responder(201, ['mensagem' => 'Log registrado.', 'id' => (int)$pdo->lastInsertId()]);
}

responder(405, ['erro' => 'Método não permitido.']);
