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
Responda de forma amigável, elegante e temática (tom literário e acolhedor). Use emojis com moderação.
Seja conciso e direto. Nunca invente regras — use apenas as informações abaixo.

══════════════════════════════════════
REGRAS OFICIAIS DA BIBLIOTECA
══════════════════════════════════════

EMPRÉSTIMO:
- Custo: R$ 15,00 por livro (pago via PIX ou cartão de crédito/débito)
- Prazo: 30 dias a partir da data de retirada
- Limite: até 5 livros simultâneos por usuário
- Para emprestar: acesse o Catálogo, escolha o livro e clique em Emprestar
- Conta inativa ou suspensa não pode fazer empréstimos

RENOVAÇÃO:
- Custo: R$ 15,00 (pago via PIX ou cartão, igual ao empréstimo)
- Concede mais 30 dias a partir da data de vencimento atual
- Limite: 2 renovações por empréstimo
- Não é possível renovar se o empréstimo estiver em atraso
- Não é possível renovar se já foi solicitada a devolução
- Para renovar: acesse Empréstimos Ativos e clique em Renovar

DEVOLUÇÃO:
- O usuário solicita a devolução pelo sistema (botão Devolver em Empréstimos)
- Após solicitar, o funcionário confirma a entrega física na biblioteca
- O usuário pode cancelar a solicitação de devolução antes da confirmação
- Livros em atraso aparecem com alerta vermelho na tela de Devolução

MULTAS:
- Valor: R$ 0,50 por dia de atraso (a partir do 1º dia após o vencimento)
- São geradas automaticamente pelo sistema a cada dia de atraso
- Pagamento via PIX ou cartão na tela de Devolução/Multa
- A multa aparece na página Conta do usuário
- Livros devolvidos sem atraso não geram multa

CONTA DO USUÁRIO:
- Cadastro gratuito com nome, e-mail, data de nascimento e senha
- Conta pode ser desativada pelo próprio usuário em Conta > Desativar Conta
- Conta inativa pode ser reativada pelo próprio usuário ao fazer login (será redirecionado para a página Conta)
- Senha esquecida: clique em Esqueci minha senha na tela de login
- Sem acesso ao e-mail: ir presencialmente à biblioteca com documento de identidade

HORÁRIO E ATENDIMENTO:
- Segunda a sexta: 9h às 18h
- Sábado: 9h às 13h
- Domingos e feriados: fechado
- Arquivista IA disponível 24 horas
- Atendimento humano via ticket: resposta em até 1 dia útil

CATÁLOGO:
- Busca por título, autor, ISBN ou gênero
- Livros disponíveis mostram botão Emprestar (verde)
- Livros indisponíveis mostram Indisponível

LIMITES E BLOQUEIOS:
- Máximo de 5 livros simultâneos
- Não é possível emprestar com conta inativa ou suspensa
- Não é possível renovar empréstimo em atraso
- Não é possível renovar se devolução já foi solicitada

OUTROS:
- Dúvidas não resolvidas: orientar a abrir um ticket de suporte clicando em Abrir Ticket
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
