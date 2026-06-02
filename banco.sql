-- ═══════════════════════════════════════════════════════════════
--  Estação Literária — Banco de Dados Completo
--  Versão final — gerado em 2026
-- ───────────────────────────────────────────────────────────────
--  Como usar:
--    1. Abra o phpMyAdmin (http://localhost:9999/phpmyadmin)
--    2. Clique em "SQL" e cole este arquivo inteiro
--    3. Execute
--
--  Credenciais dos dados de teste:
--    Usuários     → senha: usuario123
--    Funcionários → senha: func123
--    Admin        → senha: admin123
-- ═══════════════════════════════════════════════════════════════

-- ── Cria e seleciona o banco ────────────────────────────────────
CREATE DATABASE IF NOT EXISTS estacao_literaria
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE estacao_literaria;

-- ── Desativa verificação de FK durante carga ─────────────────────
SET FOREIGN_KEY_CHECKS = 0;


-- ════════════════════════════════════════════════════════════════
--  TABELAS
-- ════════════════════════════════════════════════════════════════

-- ── 1. USUÁRIOS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
  id            INT          AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  senha         VARCHAR(255) NOT NULL,
  data_nasc     DATE         DEFAULT NULL,
  status        ENUM('ativo','inativo','suspenso') DEFAULT 'ativo',
  foto_perfil   LONGTEXT     DEFAULT NULL,          -- base64 ou URL
  criado_em     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. REGISTRO DE CONTAS DESATIVADAS ────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios_inativos (
  id            INT       AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT       NOT NULL UNIQUE,
  nome          VARCHAR(120),
  email         VARCHAR(150),
  senha         VARCHAR(255),
  data_nasc     DATE,
  criado_em     TIMESTAMP DEFAULT NULL,
  desativado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. FUNCIONÁRIOS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS funcionarios (
  id            INT         AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  senha         VARCHAR(255) NOT NULL,
  cargo         VARCHAR(80)  DEFAULT 'Bibliotecário',
  matricula     VARCHAR(20)  NOT NULL UNIQUE,
  status        ENUM('ativo','folga','suspenso') DEFAULT 'ativo',
  criado_em     TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. LIVROS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS livros (
  id              INT          AUTO_INCREMENT PRIMARY KEY,
  titulo          VARCHAR(200) NOT NULL,
  autor           VARCHAR(120) NOT NULL,
  isbn            VARCHAR(13)  NOT NULL UNIQUE,
  editora         VARCHAR(120) DEFAULT NULL,
  categoria       VARCHAR(80)  DEFAULT 'Geral',
  capa            LONGTEXT     DEFAULT NULL,   -- base64 (upload do PC) ou URL
  descricao       TEXT         DEFAULT NULL,
  paginas         SMALLINT     DEFAULT NULL,
  data_publicacao DATE         DEFAULT NULL,
  quantidade      INT          DEFAULT 1,
  status          ENUM('disponivel','emprestado','indisponivel') DEFAULT 'disponivel',
  criado_em       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. EMPRÉSTIMOS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS emprestimos (
  id                      INT           AUTO_INCREMENT PRIMARY KEY,
  usuario_id              INT           NOT NULL,
  livro_id                INT           NOT NULL,
  funcionario_id          INT           DEFAULT NULL,
  data_emprestimo         DATE          NOT NULL,
  data_devolucao_prevista DATE          NOT NULL,
  data_devolucao_real     DATE          DEFAULT NULL,
  renovacoes              INT           DEFAULT 0,
  metodo_pagamento        VARCHAR(30)   DEFAULT NULL,
  valor_pago              DECIMAL(8,2)  DEFAULT 15.00,
  multa                   DECIMAL(8,2)  DEFAULT 0.00,
  multa_paga              TINYINT(1)    DEFAULT 0,
  status                  ENUM('ativo','devolvido','atrasado','renovado') DEFAULT 'ativo',
  criado_em               TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  atualizado_em           TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id)     REFERENCES usuarios(id)     ON DELETE RESTRICT,
  FOREIGN KEY (livro_id)       REFERENCES livros(id)       ON DELETE RESTRICT,
  FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. TENTATIVAS DE LOGIN (bloqueio por IP) ──────────────────────
--   Máx. 5 tentativas incorretas → bloqueio de 15 minutos
--   Reseta automaticamente após login bem-sucedido
CREATE TABLE IF NOT EXISTS login_tentativas (
  id            INT        AUTO_INCREMENT PRIMARY KEY,
  ip            VARCHAR(45) NOT NULL,
  tipo          ENUM('usuario','funcionario') NOT NULL,
  tentativas    TINYINT    NOT NULL DEFAULT 1,
  bloqueado_ate DATETIME   DEFAULT NULL,
  ultima_tent   DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ip_tipo (ip, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════
--  ÍNDICES
-- ════════════════════════════════════════════════════════════════

CREATE INDEX IF NOT EXISTS idx_emp_usuario  ON emprestimos (usuario_id);
CREATE INDEX IF NOT EXISTS idx_emp_livro    ON emprestimos (livro_id);
CREATE INDEX IF NOT EXISTS idx_emp_status   ON emprestimos (status);
CREATE INDEX IF NOT EXISTS idx_liv_status   ON livros (status);
CREATE INDEX IF NOT EXISTS idx_liv_cat      ON livros (categoria);
CREATE INDEX IF NOT EXISTS idx_ltent_ip     ON login_tentativas (ip);


-- ════════════════════════════════════════════════════════════════
--  DADOS DE TESTE
-- ════════════════════════════════════════════════════════════════

-- ── Funcionários ─────────────────────────────────────────────────
--   admin@biblioteca.com  → admin123
--   demais                → func123
INSERT IGNORE INTO funcionarios (nome, email, senha, cargo, matricula, status) VALUES
('Administrador',  'admin@biblioteca.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'ADM0001', 'ativo'),
('Marina Souza',   'marina@biblioteca.com',   '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Bibliotecário', 'BIB0002', 'ativo'),
('Carlos Eduardo', 'carlos@biblioteca.com',   '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Assistente',    'BIB0003', 'ativo'),
('Fernanda Lima',  'fernanda@biblioteca.com', '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Bibliotecário', 'BIB0004', 'folga'),
('Rafael Mendes',  'rafael@biblioteca.com',   '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Estagiário',    'BIB0005', 'ativo');

-- ── Usuários ──────────────────────────────────────────────────────
--   Todos com senha: usuario123
INSERT IGNORE INTO usuarios (nome, email, senha, data_nasc, status) VALUES
('Ana Clara Oliveira', 'ana@email.com',     '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '2000-03-15', 'ativo'),
('Bruno Ferreira',     'bruno@email.com',   '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '1998-07-22', 'ativo'),
('Camila Santos',      'camila@email.com',  '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '2001-11-05', 'ativo'),
('Diego Almeida',      'diego@email.com',   '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '1995-01-30', 'ativo'),
('Eduarda Martins',    'eduarda@email.com', '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '2003-09-18', 'ativo');

-- ── Livros ────────────────────────────────────────────────────────
INSERT IGNORE INTO livros (titulo, autor, isbn, editora, categoria, capa, descricao, data_publicacao, quantidade, status) VALUES
(
  'O Senhor dos Anéis',
  'J.R.R. Tolkien',
  '9788533613379',
  'HarperCollins',
  'Fantasia',
  'https://covers.openlibrary.org/b/isbn/9788533613379-L.jpg',
  'A épica jornada de Frodo Bolseiro para destruir o Um Anel e salvar a Terra-média das trevas de Sauron.',
  '1954-07-29', 3, 'disponivel'
),
(
  'Dom Casmurro',
  'Machado de Assis',
  '9788535910254',
  'Penguin Companhia',
  'Clássico',
  'https://covers.openlibrary.org/b/isbn/9788535910254-L.jpg',
  'Bentinho narra sua vida e o amor por Capitu, em um dos romances mais debatidos da literatura brasileira.',
  '1899-01-01', 2, 'disponivel'
),
(
  'Duna',
  'Frank Herbert',
  '9788576572008',
  'Aleph',
  'Ficção Científica',
  'https://covers.openlibrary.org/b/isbn/9788576572008-L.jpg',
  'Em um futuro distante, Paul Atreides lidera uma revolta no planeta desértico de Arrakis, fonte da especiaria mais valiosa do universo.',
  '1965-08-01', 2, 'disponivel'
),
(
  'A Revolução dos Bichos',
  'George Orwell',
  '9788535914849',
  'Companhia das Letras',
  'Clássico',
  'https://covers.openlibrary.org/b/isbn/9788535914849-L.jpg',
  'Uma sátira política brilhante em que animais de uma fazenda se rebelam contra seus donos humanos.',
  '1945-08-17', 4, 'disponivel'
),
(
  'Neuromancer',
  'William Gibson',
  '9788576572435',
  'Aleph',
  'Ficção Científica',
  'https://covers.openlibrary.org/b/isbn/9788576572435-L.jpg',
  'O romance fundador do cyberpunk que definiu a visão moderna da realidade virtual e do ciberespaço.',
  '1984-07-01', 2, 'disponivel'
),
(
  'O Hobbit',
  'J.R.R. Tolkien',
  '9788533613125',
  'HarperCollins',
  'Fantasia',
  'https://covers.openlibrary.org/b/isbn/9788533613125-L.jpg',
  'Bilbo Bolseiro é arrastado para uma aventura inesperada com treze anões e o mago Gandalf em busca de um tesouro guardado por um dragão.',
  '1937-09-21', 3, 'disponivel'
),
(
  '1984',
  'George Orwell',
  '9788535909555',
  'Companhia das Letras',
  'Ficção Científica',
  'https://covers.openlibrary.org/b/isbn/9788535909555-L.jpg',
  'Winston Smith vive em uma sociedade totalitária controlada pelo Grande Irmão, onde o passado é reescrito e o pensamento independente é crime.',
  '1949-06-08', 3, 'disponivel'
),
(
  'Memórias Póstumas de Brás Cubas',
  'Machado de Assis',
  '9788535902785',
  'Penguin Companhia',
  'Clássico',
  'https://covers.openlibrary.org/b/isbn/9788535902785-L.jpg',
  'Narrado por um defunto autor, este romance inaugura o Realismo brasileiro com ironia e profundidade psicológica incomparáveis.',
  '1881-01-01', 2, 'disponivel'
),
(
  'O Código Da Vinci',
  'Dan Brown',
  '9788575421932',
  'Sextante',
  'Mistério',
  'https://covers.openlibrary.org/b/isbn/9788575421932-L.jpg',
  'O simbologista Robert Langdon é arrastado para uma investigação que envolve sociedades secretas, arte e os maiores mistérios do Vaticano.',
  '2003-03-18', 2, 'disponivel'
),
(
  'O Pequeno Príncipe',
  'Antoine de Saint-Exupéry',
  '9788532522085',
  'Geração Editorial',
  'Infantil',
  'https://covers.openlibrary.org/b/isbn/9788532522085-L.jpg',
  'Um piloto preso no deserto encontra um misterioso menino de outro planeta. Uma fábula poética sobre amizade, amor e o sentido da vida.',
  '1943-04-06', 5, 'disponivel'
);



-- Atualiza número de páginas dos livros de teste
UPDATE livros SET paginas = 1178 WHERE titulo = 'O Senhor dos Anéis';
UPDATE livros SET paginas = 256 WHERE titulo = 'Dom Casmurro';
UPDATE livros SET paginas = 680 WHERE titulo = 'Duna';
UPDATE livros SET paginas = 120 WHERE titulo = 'A Revolução dos Bichos';
UPDATE livros SET paginas = 368 WHERE titulo = 'Neuromancer';
UPDATE livros SET paginas = 310 WHERE titulo = 'O Hobbit';
UPDATE livros SET paginas = 328 WHERE titulo = '1984';
UPDATE livros SET paginas = 240 WHERE titulo = 'Memórias Póstumas de Brás Cubas';
UPDATE livros SET paginas = 480 WHERE titulo = 'O Código Da Vinci';
UPDATE livros SET paginas = 96 WHERE titulo = 'O Pequeno Príncipe';

-- ── Reativa verificação de FK ────────────────────────────────────
SET FOREIGN_KEY_CHECKS = 1;

-- ════════════════════════════════════════════════════════════════
--  FIM DO SCRIPT
-- ════════════════════════════════════════════════════════════════

-- Adiciona coluna de número de páginas aos livros
ALTER TABLE livros ADD COLUMN IF NOT EXISTS paginas SMALLINT DEFAULT NULL;

-- ════════════════════════════════════════════════════════════════
--  ATUALIZAÇÃO — OPERAÇÃO INATIVAR EXEMPLARES (v2)
-- ════════════════════════════════════════════════════════════════
--  A tela FuncionarioLivro agora usa PATCH /php/livros.php?id=N
--  com { "acao": "inativar", "quantidade": N } para reduzir o
--  estoque de um livro sem excluí-lo do banco.
--
--  Regra aplicada no PHP (livros.php):
--    quantidade_nova = quantidade_atual - N
--    status = (quantidade_nova = 0) ? 'indisponivel' : 'disponivel'
--
--  Nenhuma coluna nova é necessária — a tabela livros já possui
--  'quantidade' e 'status' para suportar essa operação.
-- ════════════════════════════════════════════════════════════════

-- FIM
