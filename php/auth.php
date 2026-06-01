<?php
// ═══════════════════════════════════════════════════════
// php/auth.php — Verificação de sessão server-side
//
// Use no início de cada endpoint protegido:
//   require_once __DIR__ . '/auth.php';
//   exigirUsuario();   // para rotas de leitor
//   exigirFuncionario(); // para rotas de funcionário
// ═══════════════════════════════════════════════════════

if (session_status() === PHP_SESSION_NONE) {
    // Sessão segura: cookie HttpOnly, SameSite=Strict, Secure em HTTPS
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

/**
 * Exige sessão de usuário (leitor) válida.
 * Retorna 401 e encerra se não estiver autenticado.
 */
function exigirUsuario(): int {
    if (empty($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Não autenticado. Faça login para continuar.']);
        exit;
    }
    return (int) $_SESSION['usuario_id'];
}

/**
 * Exige sessão de funcionário válida.
 * Retorna 401 e encerra se não estiver autenticado como funcionário.
 */
function exigirFuncionario(): int {
    if (empty($_SESSION['funcionario_id'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Acesso restrito a funcionários. Faça login.']);
        exit;
    }
    return (int) $_SESSION['funcionario_id'];
}

/**
 * Retorna o ID do usuário logado ou null se não autenticado.
 * Use quando a rota é pública mas o comportamento muda se logado.
 */
function usuarioLogadoOuNull(): ?int {
    return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
}
