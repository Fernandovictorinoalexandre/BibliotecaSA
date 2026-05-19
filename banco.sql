-- ═══════════════════════════════════════════════════════
-- Estação Literária — Banco de Dados
-- Execute no MySQL Workbench
-- ═══════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS estacao_literaria
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE estacao_literaria;

-- ── TABELA DE USUÁRIOS ──────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
  id           INT          AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(120) NOT NULL,
  email        VARCHAR(150) NOT NULL UNIQUE,
  senha        VARCHAR(255) NOT NULL,          -- bcrypt hash
  data_nasc    DATE,
  status       ENUM('ativo','suspenso')        DEFAULT 'ativo',
  criado_em    TIMESTAMP                       DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP                      DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── TABELA DE LIVROS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS livros (
  id             INT          AUTO_INCREMENT PRIMARY KEY,
  titulo         VARCHAR(200) NOT NULL,
  autor          VARCHAR(120) NOT NULL,
  isbn           VARCHAR(13)  NOT NULL UNIQUE,
  editora        VARCHAR(120),
  data_publicacao DATE,
  quantidade     INT          DEFAULT 1,
  status         ENUM('disponivel','emprestado','indisponivel') DEFAULT 'disponivel',
  criado_em      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  atualizado_em  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
