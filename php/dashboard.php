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

// Atualiza status atrasados
$pdo->exec("
    UPDATE emprestimos
    SET status = 'atrasado'
    WHERE status = 'ativo'
      AND data_devolucao_prevista < CURDATE()
      AND data_devolucao_real IS NULL
");

// Contagens gerais
$totalLivros     = $pdo->query('SELECT COUNT(*) FROM livros')->fetchColumn();
$totalUsuarios   = $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
$totalFuncionarios = $pdo->query('SELECT COUNT(*) FROM funcionarios')->fetchColumn();

// Empréstimos
$empAtivos    = $pdo->query("SELECT COUNT(*) FROM emprestimos WHERE status IN ('ativo','renovado') AND data_devolucao_real IS NULL")->fetchColumn();
$empAtrasados = $pdo->query("SELECT COUNT(*) FROM emprestimos WHERE status = 'atrasado'")->fetchColumn();
$empHoje      = $pdo->query("SELECT COUNT(*) FROM emprestimos WHERE DATE(criado_em) = CURDATE()")->fetchColumn();
$empMes       = $pdo->query("SELECT COUNT(*) FROM emprestimos WHERE MONTH(criado_em) = MONTH(CURDATE()) AND YEAR(criado_em) = YEAR(CURDATE())")->fetchColumn();

// Multas
$multaTotal   = $pdo->query('SELECT COALESCE(SUM(multa),0) FROM emprestimos WHERE multa_paga = 0')->fetchColumn();

// Livros disponíveis vs emprestados
$livrosDisp = $pdo->query("SELECT COUNT(*) FROM livros WHERE status = 'disponivel'")->fetchColumn();
$livrosEmp  = $pdo->query("SELECT COUNT(*) FROM livros WHERE status = 'emprestado'")->fetchColumn();

// Empréstimos recentes (últimos 5)
$recentes = $pdo->query("
    SELECT e.id, e.data_emprestimo, e.data_devolucao_prevista, e.status,
           u.nome AS usuario, l.titulo AS livro
    FROM emprestimos e
    JOIN usuarios u ON u.id = e.usuario_id
    JOIN livros   l ON l.id = e.livro_id
    ORDER BY e.criado_em DESC LIMIT 5
")->fetchAll();

// Livros mais emprestados (top 5)
$maisEmprestados = $pdo->query("
    SELECT l.titulo, l.autor, l.capa, COUNT(e.id) AS total
    FROM emprestimos e
    JOIN livros l ON l.id = e.livro_id
    GROUP BY l.id
    ORDER BY total DESC LIMIT 5
")->fetchAll();

http_response_code(200);
echo json_encode([
    'livros'           => (int) $totalLivros,
    'livros_disponiveis'=> (int) $livrosDisp,
    'livros_emprestados'=> (int) $livrosEmp,
    'usuarios'         => (int) $totalUsuarios,
    'funcionarios'     => (int) $totalFuncionarios,
    'emprestimos_ativos'=> (int) $empAtivos,
    'emprestimos_atrasados' => (int) $empAtrasados,
    'emprestimos_hoje' => (int) $empHoje,
    'emprestimos_mes'  => (int) $empMes,
    'multa_pendente'   => (float) $multaTotal,
    'recentes'         => $recentes,
    'mais_emprestados' => $maisEmprestados,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
