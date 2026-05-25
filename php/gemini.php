<?php

header("Content-Type: application/json");

$API_KEY = "AIzaSyBGm8dN7uTSzZHs_VISuaQJPK_mEZq1e1I";

$dados = json_decode(file_get_contents("php://input"), true);

$pergunta = $dados["pergunta"] ?? "";

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

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=".$API_KEY;

$body = [
  "contents" => [
    [
      "parts" => [
        [
          "text" => $contexto . "\n\nUsuário: " . $pergunta
        ]
      ]
    ]
  ]
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/json"
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

$response = curl_exec($ch);

curl_close($ch);

$resultado = json_decode($response, true);

$resposta =
$resultado["candidates"][0]["content"]["parts"][0]["text"]
?? "Não consegui acessar os arquivos da estação.";

echo json_encode([
  "resposta" => $resposta
]);