<?php
// ═══════════════════════════════════════════════════════
// php/dashboard.php — Estatísticas para o Painel
// GET /php/dashboard.php → retorna contagens e resumos
// ═══════════════════════════════════════════════════════

require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

$pdo = getConexao();

// ── Atualiza empréstimos para "atrasado" automaticamente ─────────
$pdo->exec("
    UPDATE emprestimos
    SET status = 'atrasado'
    WHERE status IN ('ativo','renovado')
      AND data_devolucao_prevista < CURDATE()
      AND data_devolucao_real IS NULL
");

// ── LIVROS ────────────────────────────────────────────────────────
// Total de EXEMPLARES (soma das quantidades cadastradas)
$totalExemplares = $pdo->query(
    "SELECT COALESCE(SUM(quantidade), 0) FROM livros"
)->fetchColumn();

// Total de TÍTULOS distintos
$totalTitulos = $pdo->query(
    "SELECT COUNT(*) FROM livros"
)->fetchColumn();

// Exemplares disponíveis = livros com status 'disponivel'
// (quantidade real livre = quantidade - empréstimos ativos daquele livro)
$exemplDisp = $pdo->query("
    SELECT COALESCE(SUM(
        l.quantidade - COALESCE(e_ativos.total, 0)
    ), 0)
    FROM livros l
    LEFT JOIN (
        SELECT livro_id, COUNT(*) AS total
        FROM emprestimos
        WHERE status IN ('ativo','atrasado','renovado')
          AND data_devolucao_real IS NULL
        GROUP BY livro_id
    ) e_ativos ON e_ativos.livro_id = l.id
")->fetchColumn();

// Exemplares atualmente emprestados (empréstimos em aberto)
$exemplEmprestados = $pdo->query("
    SELECT COUNT(*)
    FROM emprestimos
    WHERE status IN ('ativo','atrasado','renovado')
      AND data_devolucao_real IS NULL
")->fetchColumn();

// ── USUÁRIOS ──────────────────────────────────────────────────────
$totalUsuarios  = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$usuariosAtivos = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'ativo'")->fetchColumn();

// ── FUNCIONÁRIOS ──────────────────────────────────────────────────
$totalFuncionarios = $pdo->query("SELECT COUNT(*) FROM funcionarios")->fetchColumn();

// ── EMPRÉSTIMOS ───────────────────────────────────────────────────
$empAtivos    = $pdo->query("
    SELECT COUNT(*) FROM emprestimos
    WHERE status IN ('ativo','renovado')
      AND data_devolucao_real IS NULL
")->fetchColumn();

$empAtrasados = $pdo->query("
    SELECT COUNT(*) FROM emprestimos WHERE status = 'atrasado'
")->fetchColumn();

$empHoje = $pdo->query("
    SELECT COUNT(*) FROM emprestimos
    WHERE DATE(criado_em) = CURDATE()
")->fetchColumn();

$empMes = $pdo->query("
    SELECT COUNT(*) FROM emprestimos
    WHERE MONTH(criado_em) = MONTH(CURDATE())
      AND YEAR(criado_em)  = YEAR(CURDATE())
")->fetchColumn();

$empDevolvidos = $pdo->query("
    SELECT COUNT(*) FROM emprestimos WHERE status = 'devolvido'
")->fetchColumn();

// ── MULTAS ────────────────────────────────────────────────────────
$multaPendente = $pdo->query("
    SELECT COALESCE(SUM(multa), 0) FROM emprestimos WHERE multa_paga = 0 AND multa > 0
")->fetchColumn();

$multaRecebida = $pdo->query("
    SELECT COALESCE(SUM(multa), 0) FROM emprestimos WHERE multa_paga = 1
")->fetchColumn();

// ── RECEITA DO MÊS ───────────────────────────────────────────────
$receitaMes = $pdo->query("
    SELECT COALESCE(SUM(valor_pago), 0) FROM emprestimos
    WHERE MONTH(criado_em) = MONTH(CURDATE())
      AND YEAR(criado_em)  = YEAR(CURDATE())
")->fetchColumn();

// ── EMPRÉSTIMOS RECENTES (últimos 8) ─────────────────────────────
$recentes = $pdo->query("
    SELECT e.id,
           e.data_emprestimo,
           e.data_devolucao_prevista,
           e.data_devolucao_real,
           e.status,
           e.multa,
           u.nome  AS usuario,
           l.titulo AS livro
    FROM emprestimos e
    JOIN usuarios u ON u.id = e.usuario_id
    JOIN livros   l ON l.id = e.livro_id
    ORDER BY e.criado_em DESC
    LIMIT 8
")->fetchAll();

// ── LIVROS MAIS EMPRESTADOS (top 5) ──────────────────────────────
$maisEmprestados = $pdo->query("
    SELECT l.titulo, l.autor, COUNT(e.id) AS total
    FROM emprestimos e
    JOIN livros l ON l.id = e.livro_id
    GROUP BY l.id
    ORDER BY total DESC
    LIMIT 5
")->fetchAll();

http_response_code(200);
echo json_encode([
    // Livros
    'livros'                => (int)   $totalExemplares,
    'livros_titulos'        => (int)   $totalTitulos,
    'livros_disponiveis'    => (int)   $exemplDisp,
    'livros_emprestados'    => (int)   $exemplEmprestados,

    // Usuários
    'usuarios'              => (int)   $totalUsuarios,
    'usuarios_ativos'       => (int)   $usuariosAtivos,

    // Funcionários
    'funcionarios'          => (int)   $totalFuncionarios,

    // Empréstimos
    'emprestimos_ativos'    => (int)   $empAtivos,
    'emprestimos_atrasados' => (int)   $empAtrasados,
    'emprestimos_hoje'      => (int)   $empHoje,
    'emprestimos_mes'       => (int)   $empMes,
    'emprestimos_devolvidos'=> (int)   $empDevolvidos,

    // Financeiro
    'multa_pendente'        => (float) $multaPendente,
    'multa_recebida'        => (float) $multaRecebida,
    'receita_mes'           => (float) $receitaMes,

    // Listas
    'recentes'              => $recentes,
    'mais_emprestados'      => $maisEmprestados,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
