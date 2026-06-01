<?php
// ═══════════════════════════════════════════════════════
// php/gemini.php — Proxy para API do Gemini
// A chave API é lida do arquivo .env (nunca exposta ao cliente)
// ═══════════════════════════════════════════════════════

require_once __DIR__ . '/env.php';

header('Content-Type: application/json');

$API_KEY = getEnv('GEMINI_API_KEY');

if (empty($API_KEY)) {
    http_response_code(500);
    echo json_encode(['erro' => 'Chave da API não configurada. Verifique o arquivo .env.']);
    exit;
}

$dados    = json_decode(file_get_contents('php://input'), true);
$pergunta = trim($dados['pergunta'] ?? '');

if (empty($pergunta)) {
    http_response_code(422);
    echo json_encode(['erro' => 'Pergunta não informada.']);
    exit;
}

$contexto = "
Você é o Arquivista da Estação Literária, assistente de suporte de uma biblioteca online.

Responda de forma amigável, elegante e temática. Use emojis com moderação.

REGRAS OFICIAIS DA BIBLIOTECA (use sempre estas informações):

EMPRÉSTIMO:
- Custo: R$ 15,00 por livro (pago via PIX ou cartão de crédito/débito)
- Prazo: 30 dias
- Limite: até 5 livros simultâneos
- Bloqueio: se o usuário tiver 2 ou mais livros em atraso, não pode fazer novos empréstimos

DEVOLUÇÃO:
- O usuário clica no botão de devolução no sistema
- Após clicar, tem 1 dia para entregar o livro fisicamente na biblioteca
- Se não entregar no prazo de 1 dia, o empréstimo é reativado automaticamente e uma multa é cobrada
- O mesmo se aplica a livros já em atraso

MULTAS:
- São geradas por atraso (passar dos 30 dias) ou por não entregar o livro após clicar em devolver
- Pagamento via PIX ou cartão
- Com 2 ou mais livros em atraso, novos empréstimos ficam bloqueados

OUTROS:
- Dúvidas não resolvidas: orientar a abrir um ticket de suporte
- Atendimento humano: segunda a sexta, 9h às 18h
";

$url  = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $API_KEY;
$body = [
    'contents' => [[
        'parts' => [[
            'text' => $contexto . "\n\nUsuário: " . $pergunta,
        ]],
    ]],
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($body),
    CURLOPT_TIMEOUT        => 20,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response) {
    http_response_code(502);
    echo json_encode(['erro' => 'Não foi possível conectar ao serviço de IA.']);
    exit;
}

$resultado = json_decode($response, true);
$resposta  = $resultado['candidates'][0]['content']['parts'][0]['text']
             ?? 'Não consegui acessar os arquivos da estação.';

echo json_encode(['resposta' => $resposta]);
