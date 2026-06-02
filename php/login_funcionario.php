<?php
// ═══════════════════════════════════════════════════════
// php/login_funcionario.php — Autenticação de Funcionário
// POST → { email, senha }
// Bloqueio: 5 tentativas falhas → 15 minutos de espera
// ═══════════════════════════════════════════════════════

require_once __DIR__ . '/conexao.php';

const MAX_TENTATIVAS = 5;
const BLOQUEIO_MIN   = 15;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$email = trim($body['email'] ?? '');
$senha = $body['senha'] ?? '';

if (empty($email) || empty($senha)) {
    http_response_code(422);
    echo json_encode(['erro' => 'E-mail e senha são obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['erro' => 'E-mail inválido.']);
    exit;
}

$pdo = getConexao();
$ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip  = trim(explode(',', $ip)[0]);

// ── Verifica bloqueio por IP ─────────────────────────────
$chkBlq = $pdo->prepare(
    "SELECT tentativas, bloqueado_ate FROM login_tentativas
     WHERE ip = ? AND tipo = 'funcionario' LIMIT 1"
);
$chkBlq->execute([$ip]);
$registro = $chkBlq->fetch();

if ($registro && $registro['bloqueado_ate'] !== null) {
    $bloqueadoAte = new DateTime($registro['bloqueado_ate']);
    $agora        = new DateTime();

    if ($agora < $bloqueadoAte) {
        $restam = ceil(($bloqueadoAte->getTimestamp() - $agora->getTimestamp()) / 60);
        http_response_code(429);
        echo json_encode([
            'erro'              => "Muitas tentativas incorretas. Aguarde {$restam} minuto(s) para tentar novamente.",
            'bloqueado'         => true,
            'minutos_restantes' => $restam,
        ]);
        exit;
    }

    // Bloqueio expirou — reseta
    $pdo->prepare(
        "UPDATE login_tentativas SET tentativas = 0, bloqueado_ate = NULL
         WHERE ip = ? AND tipo = 'funcionario'"
    )->execute([$ip]);
    $registro['tentativas'] = 0;
}

// ── Busca funcionário ────────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT id, nome, email, cargo, matricula, senha, status
     FROM funcionarios WHERE email = ? LIMIT 1'
);
$stmt->execute([strtolower($email)]);
$func = $stmt->fetch();

// ── Verifica credenciais ─────────────────────────────────
$credenciaisOk = $func && password_verify($senha, $func['senha']);

if (!$credenciaisOk) {
    $tentativasAtuais = ($registro['tentativas'] ?? 0) + 1;
    $novoBloqueio     = null;
    $msg              = 'E-mail ou senha incorretos.';

    if ($tentativasAtuais >= MAX_TENTATIVAS) {
        $novoBloqueio = (new DateTime())->modify('+' . BLOQUEIO_MIN . ' minutes')
                                        ->format('Y-m-d H:i:s');
        $msg = "Conta bloqueada após " . MAX_TENTATIVAS . " tentativas. Aguarde " . BLOQUEIO_MIN . " minutos.";
    } else {
        $restam = MAX_TENTATIVAS - $tentativasAtuais;
        $msg    = "E-mail ou senha incorretos. Você tem mais {$restam} tentativa(s) antes do bloqueio.";
    }

    $pdo->prepare(
        "INSERT INTO login_tentativas (ip, tipo, tentativas, bloqueado_ate, ultima_tent)
         VALUES (?, 'funcionario', ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
           tentativas    = ?,
           bloqueado_ate = ?,
           ultima_tent   = NOW()"
    )->execute([$ip, $tentativasAtuais, $novoBloqueio, $tentativasAtuais, $novoBloqueio]);

    $code = $novoBloqueio ? 429 : 401;
    http_response_code($code);
    echo json_encode([
        'erro'       => $msg,
        'bloqueado'  => (bool) $novoBloqueio,
        'tentativas' => $tentativasAtuais,
        'max'        => MAX_TENTATIVAS,
    ]);
    exit;
}

// ── Login bem-sucedido — zera contador ──────────────────
$pdo->prepare(
    "DELETE FROM login_tentativas WHERE ip = ? AND tipo = 'funcionario'"
)->execute([$ip]);

if ($func['status'] === 'suspenso') {
    http_response_code(403);
    echo json_encode(['erro' => 'Conta suspensa. Entre em contato com a administração.']);
    exit;
}

session_start();
$_SESSION['funcionario_id']        = $func['id'];
$_SESSION['funcionario_nome']      = $func['nome'];
$_SESSION['funcionario_email']     = $func['email'];
$_SESSION['funcionario_cargo']     = $func['cargo'];
$_SESSION['funcionario_matricula'] = $func['matricula'];

http_response_code(200);
echo json_encode([
    'mensagem'    => 'Login realizado com sucesso.',
    'funcionario' => [
        'id'       => $func['id'],
        'nome'     => $func['nome'],
        'email'    => $func['email'],
        'cargo'    => $func['cargo'],
        'matricula'=> $func['matricula'],
    ],
]);
