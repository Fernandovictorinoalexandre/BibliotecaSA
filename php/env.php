<?php
// ═══════════════════════════════════════════════════════
// php/env.php — Carrega variáveis do arquivo .env
//
// O .env deve ficar FORA da pasta pública (htdocs).
// Caminho padrão para XAMPP: C:\xampp\estacao.env
// Ajuste ENV_PATH abaixo se mover o arquivo.
// ═══════════════════════════════════════════════════════

define('ENV_PATH', __DIR__ . '/../../.env');   // dois níveis acima de /php → raiz do projeto
                                                 // No XAMPP isso aponta para htdocs/Estacao-refatorado/.env
                                                 // Para segurança máxima mude para: 'C:/xampp/estacao.env'

function getEnv(string $chave, string $padrao = ''): string {
    static $vars = null;

    if ($vars === null) {
        $vars = [];
        $caminho = ENV_PATH;

        if (!file_exists($caminho)) {
            return $padrao;
        }

        $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            // ignora comentários
            if ($linha === '' || $linha[0] === '#') continue;
            if (!str_contains($linha, '=')) continue;

            [$nome, $valor] = explode('=', $linha, 2);
            $vars[trim($nome)] = trim($valor);
        }
    }

    return $vars[$chave] ?? $padrao;
}
