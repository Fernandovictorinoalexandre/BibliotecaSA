-- ═══════════════════════════════════════════════════════
-- Estação Literária — Banco de Dados Completo + Dados de Teste
-- Execute no phpMyAdmin ou MySQL Workbench
-- Senha dos usuários:     usuario123
-- Senha dos funcionários: func123
-- Senha do admin:         admin123
-- ═══════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS estacao_literaria
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE estacao_literaria;

-- ── TABELA DE USUÁRIOS ──────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
  id            INT          AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  senha         VARCHAR(255) NOT NULL,
  data_nasc     DATE,
  status        ENUM('ativo','suspenso') DEFAULT 'ativo',
  criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TABELA DE FUNCIONÁRIOS ──────────────────────────────
CREATE TABLE IF NOT EXISTS funcionarios (
  id            INT          AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  senha         VARCHAR(255) NOT NULL,
  cargo         VARCHAR(80)  DEFAULT 'Bibliotecário',
  matricula     VARCHAR(20)  NOT NULL UNIQUE,
  status        ENUM('ativo','folga','suspenso') DEFAULT 'ativo',
  criado_em     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TABELA DE LIVROS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS livros (
  id              INT          AUTO_INCREMENT PRIMARY KEY,
  titulo          VARCHAR(200) NOT NULL,
  autor           VARCHAR(120) NOT NULL,
  isbn            VARCHAR(13)  NOT NULL UNIQUE,
  editora         VARCHAR(120),
  categoria       VARCHAR(80)  DEFAULT 'Geral',
  capa            VARCHAR(500),
  descricao       TEXT,
  data_publicacao DATE,
  quantidade      INT          DEFAULT 1,
  status          ENUM('disponivel','emprestado','indisponivel') DEFAULT 'disponivel',
  criado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── TABELA DE EMPRÉSTIMOS ───────────────────────────────
CREATE TABLE IF NOT EXISTS emprestimos (
  id                      INT          AUTO_INCREMENT PRIMARY KEY,
  usuario_id              INT          NOT NULL,
  livro_id                INT          NOT NULL,
  funcionario_id          INT,
  data_emprestimo         DATE         NOT NULL,
  data_devolucao_prevista DATE         NOT NULL,
  data_devolucao_real     DATE,
  renovacoes              INT          DEFAULT 0,
  metodo_pagamento        VARCHAR(30),
  valor_pago              DECIMAL(8,2) DEFAULT 15.00,
  multa                   DECIMAL(8,2) DEFAULT 0.00,
  multa_paga              TINYINT(1)   DEFAULT 0,
  status                  ENUM('ativo','devolvido','atrasado','renovado') DEFAULT 'ativo',
  criado_em               TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id)     REFERENCES usuarios(id)     ON DELETE RESTRICT,
  FOREIGN KEY (livro_id)       REFERENCES livros(id)       ON DELETE RESTRICT,
  FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── ÍNDICES ─────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_emp_usuario ON emprestimos (usuario_id);
CREATE INDEX IF NOT EXISTS idx_emp_livro   ON emprestimos (livro_id);
CREATE INDEX IF NOT EXISTS idx_emp_status  ON emprestimos (status);
CREATE INDEX IF NOT EXISTS idx_liv_status  ON livros (status);
CREATE INDEX IF NOT EXISTS idx_liv_cat     ON livros (categoria);

-- ════════════════════════════════════════════════════════
-- DADOS DE TESTE
-- ════════════════════════════════════════════════════════

-- ── FUNCIONÁRIOS (senha: func123) ───────────────────────
-- Admin padrão (senha: admin123)
INSERT IGNORE INTO funcionarios (nome, email, senha, cargo, matricula, status) VALUES
('Administrador',    'admin@biblioteca.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'ADM0001', 'ativo'),
('Marina Souza',     'marina@biblioteca.com',   '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Bibliotecário', 'BIB0002', 'ativo'),
('Carlos Eduardo',   'carlos@biblioteca.com',   '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Assistente',    'BIB0003', 'ativo'),
('Fernanda Lima',    'fernanda@biblioteca.com', '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Bibliotecário', 'BIB0004', 'folga'),
('Rafael Mendes',    'rafael@biblioteca.com',   '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Estagiário',    'BIB0005', 'ativo');

-- ── USUÁRIOS (senha: usuario123) ────────────────────────
INSERT IGNORE INTO usuarios (nome, email, senha, data_nasc, status) VALUES
('Ana Clara Oliveira',  'ana@email.com',      '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '2000-03-15', 'ativo'),
('Bruno Ferreira',      'bruno@email.com',    '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '1998-07-22', 'ativo'),
('Camila Santos',       'camila@email.com',   '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '2001-11-05', 'ativo'),
('Diego Almeida',       'diego@email.com',    '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '1995-01-30', 'ativo'),
('Eduarda Martins',     'eduarda@email.com',  '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '2003-09-18', 'ativo');

-- ── LIVROS ──────────────────────────────────────────────
INSERT IGNORE INTO livros (titulo, autor, isbn, editora, categoria, capa, descricao, data_publicacao, quantidade, status) VALUES
(
  'O Senhor dos Anéis',
  'J.R.R. Tolkien',
  '9788533613379',
  'HarperCollins',
  'Fantasia',
  'https://covers.openlibrary.org/b/isbn/9788533613379-L.jpg',
  'A épica jornada de Frodo Bolseiro para destruir o Um Anel e salvar a Terra-média das trevas de Sauron.',
  '1954-07-29',
  3,
  'disponivel'
),
(
  'Dom Casmurro',
  'Machado de Assis',
  '9788535910254',
  'Penguin Companhia',
  'Clássico',
  'https://covers.openlibrary.org/b/isbn/9788535910254-L.jpg',
  'Bentinho narra sua vida e o amor por Capitu, em um dos romances mais debatidos da literatura brasileira.',
  '1899-01-01',
  2,
  'disponivel'
),
(
  'Duna',
  'Frank Herbert',
  '9788576572008',
  'Aleph',
  'Ficção Científica',
  'https://covers.openlibrary.org/b/isbn/9788576572008-L.jpg',
  'Em um futuro distante, Paul Atreides lidera uma revolta no planeta desértico de Arrakis, fonte da especiaria mais valiosa do universo.',
  '1965-08-01',
  2,
  'disponivel'
),
(
  'A Revolução dos Bichos',
  'George Orwell',
  '9788535914849',
  'Companhia das Letras',
  'Clássico',
  'https://covers.openlibrary.org/b/isbn/9788535914849-L.jpg',
  'Uma sátira política brilhante em que animais de uma fazenda se rebelam contra seus donos humanos.',
  '1945-08-17',
  4,
  'disponivel'
),
(
  'Neuromancer',
  'William Gibson',
  '9788576572435',
  'Aleph',
  'Ficção Científica',
  'https://covers.openlibrary.org/b/isbn/9788576572435-L.jpg',
  'O romance fundador do cyberpunk que definiu a visão moderna da realidade virtual e do ciberespaço.',
  '1984-07-01',
  2,
  'disponivel'
);


-- ── TABELA DE AUDITORIA ─────────────────────────────────
CREATE TABLE IF NOT EXISTS auditoria (
  id               INT          AUTO_INCREMENT PRIMARY KEY,
  funcionario_id   INT          NOT NULL,
  funcionario_nome VARCHAR(120),
  acao             VARCHAR(80)  NOT NULL,
  entidade         VARCHAR(40),
  entidade_id      INT,
  detalhe          TEXT,
  ip               VARCHAR(45),
  criado_em        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_aud_func ON auditoria (funcionario_id);
CREATE INDEX IF NOT EXISTS idx_aud_acao ON auditoria (acao);
CREATE INDEX IF NOT EXISTS idx_aud_data ON auditoria (criado_em);
