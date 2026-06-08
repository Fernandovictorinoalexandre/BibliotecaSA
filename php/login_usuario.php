<?php
// ═══════════════════════════════════════════════════════
// php/login_usuario.php — Autenticação de Usuário
// POST → { email, senha }
// Bloqueio: 5 tentativas falhas → 15 minutos de espera
// ═══════════════════════════════════════════════════════

require_once __DIR__ . '/conexao.php';

const MAX_TENTATIVAS = 5;
const BLOQUEIO_MIN   = 1;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

$email = trim($body['email'] ?? '');
$senha = $body['senha']       ?? '';

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
$ip  = trim(explode(',', $ip)[0]); // pega só o primeiro IP se vier lista

// ── Verifica bloqueio por IP ─────────────────────────────
$chkBlq = $pdo->prepare(
    "SELECT tentativas, bloqueado_ate FROM login_tentativas
     WHERE ip = ? AND tipo = 'usuario' LIMIT 1"
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
            'erro'           => "Muitas tentativas incorretas. Aguarde {$restam} minuto(s) para tentar novamente.",
            'bloqueado'      => true,
            'minutos_restantes' => $restam,
        ]);
        exit;
    }

    // Bloqueio expirou — reseta o contador
    $pdo->prepare(
        "UPDATE login_tentativas SET tentativas = 0, bloqueado_ate = NULL
         WHERE ip = ? AND tipo = 'usuario'"
    )->execute([$ip]);
    $registro['tentativas'] = 0;
}

// ── Busca usuário ────────────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT id, nome, email, senha, status, foto_perfil
     FROM usuarios WHERE email = ? LIMIT 1'
);
$stmt->execute([strtolower($email)]);
$usuario = $stmt->fetch();

// ── Verifica credenciais ─────────────────────────────────
$credenciaisOk = $usuario && password_verify($senha, $usuario['senha']);

if (!$credenciaisOk) {
    // Incrementa tentativas
    $tentativasAtuais = ($registro['tentativas'] ?? 0) + 1;
    $novoBloqueio     = null;
    $msg              = 'E-mail ou senha incorretos.';

    if ($tentativasAtuais >= MAX_TENTATIVAS) {
        $novoBloqueio = (new DateTime())->modify('+' . BLOQUEIO_MIN . ' minutes')
                                        ->format('Y-m-d H:i:s');
        $msg = "Conta bloqueada após " . MAX_TENTATIVAS . " tentativas. Aguarde " . BLOQUEIO_MIN . " minutos.";
    } else {
        $msg = "E-mail ou senha incorretos.";
    }

    $pdo->prepare(
        "INSERT INTO login_tentativas (ip, tipo, tentativas, bloqueado_ate, ultima_tent)
         VALUES (?, 'usuario', ?, ?, NOW())
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
    "DELETE FROM login_tentativas WHERE ip = ? AND tipo = 'usuario'"
)->execute([$ip]);

if ($usuario['status'] === 'suspenso') {
    http_response_code(403);
    echo json_encode(['erro' => 'Conta suspensa. Entre em contato com a biblioteca.']);
    exit;
}

session_start();
$_SESSION['usuario_id']    = $usuario['id'];
$_SESSION['usuario_nome']  = $usuario['nome'];
$_SESSION['usuario_email'] = $usuario['email'];

http_response_code(200);
echo json_encode([
    'mensagem' => $usuario['status'] === 'inativo'
        ? 'Conta desativada. Acesse sua conta para reativá-la.'
        : 'Login realizado com sucesso.',
    'usuario'  => [
        'id'          => $usuario['id'],
        'nome'        => $usuario['nome'],
        'email'       => $usuario['email'],
        'foto_perfil' => $usuario['foto_perfil'],
        'status'      => $usuario['status'],
    ],
]);
