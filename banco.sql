-- ═══════════════════════════════════════════════════════════════
--  Estação Literária — Banco de Dados
--  Como usar:
--    1. Abra o phpMyAdmin (http://localhost:9999/phpmyadmin)
--    2. Clique em "SQL" e cole este arquivo inteiro
--    3. Execute
--
--  Credenciais dos dados de teste:
--    Usuários     → senha: usuario123
--    Funcionários → senha: func123
-- ═══════════════════════════════════════════════════════════════

DROP DATABASE IF EXISTS estacao_literaria;
CREATE DATABASE estacao_literaria
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE estacao_literaria;

SET FOREIGN_KEY_CHECKS = 0;

-- ════════════════════════════════════════════════════════════════
--  TABELAS
-- ════════════════════════════════════════════════════════════════

-- ── 1. USUÁRIOS ──────────────────────────────────────────────────
CREATE TABLE usuarios (
  id            INT          AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  senha         VARCHAR(255) NOT NULL,
  data_nasc     DATE         DEFAULT NULL,
  cpf           CHAR(11)     DEFAULT NULL UNIQUE,
  telefone      VARCHAR(11)  DEFAULT NULL,
  status        ENUM('ativo','inativo','suspenso') DEFAULT 'ativo',
  foto_perfil   LONGTEXT     DEFAULT NULL,
  criado_em     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. CONTAS DESATIVADAS ────────────────────────────────────────
CREATE TABLE usuarios_inativos (
  id            INT          AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT          NOT NULL UNIQUE,
  nome          VARCHAR(120),
  email         VARCHAR(150),
  senha         VARCHAR(255),
  data_nasc     DATE,
  criado_em     TIMESTAMP    DEFAULT NULL,
  desativado_em TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. FUNCIONÁRIOS ──────────────────────────────────────────────
CREATE TABLE funcionarios (
  id            INT          AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  senha         VARCHAR(255) NOT NULL,
  cargo         VARCHAR(80)  DEFAULT 'Bibliotecário',
  matricula     VARCHAR(20)  NOT NULL UNIQUE,
  status        ENUM('ativo','inativo','folga','suspenso') DEFAULT 'ativo',
  criado_em     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. LIVROS ────────────────────────────────────────────────────
CREATE TABLE livros (
  id              INT          AUTO_INCREMENT PRIMARY KEY,
  titulo          VARCHAR(200) NOT NULL,
  autor           VARCHAR(120) NOT NULL,
  isbn            VARCHAR(13)  NOT NULL UNIQUE,
  editora         VARCHAR(120) DEFAULT NULL,
  categoria       VARCHAR(80)  DEFAULT 'Geral',
  paginas         SMALLINT     DEFAULT NULL,
  data_publicacao DATE         DEFAULT NULL,
  quantidade      INT          DEFAULT 1,
  status          ENUM('disponivel','emprestado','indisponivel') DEFAULT 'disponivel',
  capa            LONGTEXT     DEFAULT NULL,
  descricao       TEXT         DEFAULT NULL,
  criado_em       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  atualizado_em   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. EMPRÉSTIMOS ───────────────────────────────────────────────
CREATE TABLE emprestimos (
  id                      INT          AUTO_INCREMENT PRIMARY KEY,
  usuario_id              INT          NOT NULL,
  livro_id                INT          NOT NULL,
  funcionario_id          INT          DEFAULT NULL,
  data_emprestimo         DATE         NOT NULL,
  data_devolucao_prevista DATE         NOT NULL,
  data_devolucao_real     DATE         DEFAULT NULL,
  renovacoes              INT          DEFAULT 0,
  metodo_pagamento        VARCHAR(30)  DEFAULT NULL,
  valor_pago              DECIMAL(8,2) DEFAULT 15.00,
  multa                   DECIMAL(8,2) DEFAULT 0.00,
  multa_paga              TINYINT(1)   DEFAULT 0,
  status                  ENUM('ativo','devolvido','atrasado','renovado','aguardando_devolucao') DEFAULT 'ativo',
  criado_em               TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  atualizado_em           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id)     REFERENCES usuarios(id)     ON DELETE RESTRICT,
  FOREIGN KEY (livro_id)       REFERENCES livros(id)       ON DELETE RESTRICT,
  FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. NOTIFICAÇÕES ──────────────────────────────────────────────
CREATE TABLE notificacoes (
  id            INT          AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT          NOT NULL,
  emprestimo_id INT          DEFAULT NULL,
  tipo          VARCHAR(30)  DEFAULT 'aviso',
  titulo        VARCHAR(120) NOT NULL,
  mensagem      TEXT         NOT NULL,
  lida          TINYINT(1)   DEFAULT 0,
  criado_em     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id)    REFERENCES usuarios(id)    ON DELETE CASCADE,
  FOREIGN KEY (emprestimo_id) REFERENCES emprestimos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. TENTATIVAS DE LOGIN ───────────────────────────────────────
CREATE TABLE login_tentativas (
  id            INT          AUTO_INCREMENT PRIMARY KEY,
  ip            VARCHAR(45)  NOT NULL,
  tipo          ENUM('usuario','funcionario') NOT NULL,
  tentativas    TINYINT      NOT NULL DEFAULT 1,
  bloqueado_ate DATETIME     DEFAULT NULL,
  ultima_tent   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_ip_tipo (ip, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ════════════════════════════════════════════════════════════════
--  ÍNDICES
-- ════════════════════════════════════════════════════════════════

CREATE INDEX idx_emp_usuario ON emprestimos (usuario_id);
CREATE INDEX idx_emp_livro   ON emprestimos (livro_id);
CREATE INDEX idx_emp_status  ON emprestimos (status);
CREATE INDEX idx_liv_status  ON livros (status);
CREATE INDEX idx_liv_cat     ON livros (categoria);
CREATE INDEX idx_ltent_ip    ON login_tentativas (ip);
CREATE INDEX idx_notif_uid   ON notificacoes (usuario_id);


-- ════════════════════════════════════════════════════════════════
--  DADOS DE TESTE
-- ════════════════════════════════════════════════════════════════

-- ── Funcionários (senha: func123) ────────────────────────────────
INSERT INTO funcionarios (nome, email, senha, cargo, matricula, status) VALUES
('Administrador',  'admin@biblioteca.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'ADM0001', 'ativo'),
('Marina Souza',   'marina@biblioteca.com',   '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Bibliotecário', 'BIB0002', 'ativo'),
('Carlos Eduardo', 'carlos@biblioteca.com',   '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Assistente',    'BIB0003', 'ativo'),
('Fernanda Lima',  'fernanda@biblioteca.com', '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Bibliotecário', 'BIB0004', 'folga'),
('Rafael Mendes',  'rafael@biblioteca.com',   '$2b$10$RBdqCMtq9XsykzfDg0tVHeQ/xhpI3x2zKCzDZw1WnlaMe2ReosEDa', 'Estagiário',    'BIB0005', 'ativo');

-- ── Usuários (senha: usuario123) ─────────────────────────────────
INSERT INTO usuarios (nome, email, senha, data_nasc, status) VALUES
('Ana Clara Oliveira', 'ana@email.com',     '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '2000-03-15', 'ativo'),
('Bruno Ferreira',     'bruno@email.com',   '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '1998-07-22', 'ativo'),
('Camila Santos',      'camila@email.com',  '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '2001-11-05', 'ativo'),
('Diego Almeida',      'diego@email.com',   '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '1995-01-30', 'ativo'),
('Eduarda Martins',    'eduarda@email.com', '$2b$10$/owMWy/1/TyIZZR4AKJPcOKRv1sFGmOR8WC6aDjFYhMyFnOF8Rsw.', '2003-09-18', 'ativo');

-- ── Livros ────────────────────────────────────────────────────────
INSERT INTO livros (titulo, autor, isbn, editora, categoria, paginas, data_publicacao, quantidade, status, capa, descricao) VALUES
(
  'O Senhor dos Anéis',
  'J.R.R. Tolkien',
  '9788533613379',
  'HarperCollins',
  'Fantasia',
  1178,
  '1954-07-29',
  3, 'disponivel',
  'https://covers.openlibrary.org/b/isbn/9788533613379-L.jpg',
  'A épica jornada de Frodo Bolseiro para destruir o Um Anel e salvar a Terra-média das trevas de Sauron.'
),
(
  'Dom Casmurro',
  'Machado de Assis',
  '9788535910254',
  'Penguin Companhia',
  'Clássico',
  256,
  '1899-01-01',
  2, 'disponivel',
  'https://covers.openlibrary.org/b/isbn/9788535910254-L.jpg',
  'Bentinho narra sua vida e o amor por Capitu, em um dos romances mais debatidos da literatura brasileira.'
),
(
  'Duna',
  'Frank Herbert',
  '9788576572008',
  'Aleph',
  'Ficção Científica',
  680,
  '1965-08-01',
  2, 'disponivel',
  'https://covers.openlibrary.org/b/isbn/9788576572008-L.jpg',
  'Em um futuro distante, Paul Atreides lidera uma revolta no planeta desértico de Arrakis, fonte da especiaria mais valiosa do universo.'
),
(
  'A Revolução dos Bichos',
  'George Orwell',
  '9788535914849',
  'Companhia das Letras',
  'Clássico',
  120,
  '1945-08-17',
  4, 'disponivel',
  'https://covers.openlibrary.org/b/isbn/9788535914849-L.jpg',
  'Uma sátira política brilhante em que animais de uma fazenda se rebelam contra seus donos humanos.'
),
(
  'Neuromancer',
  'William Gibson',
  '9788576572435',
  'Aleph',
  'Ficção Científica',
  368,
  '1984-07-01',
  2, 'disponivel',
  'https://covers.openlibrary.org/b/isbn/9788576572435-L.jpg',
  'O romance fundador do cyberpunk que definiu a visão moderna da realidade virtual e do ciberespaço.'
),
(
  'O Hobbit',
  'J.R.R. Tolkien',
  '9788533613125',
  'HarperCollins',
  'Fantasia',
  310,
  '1937-09-21',
  3, 'disponivel',
  'https://covers.openlibrary.org/b/isbn/9788533613125-L.jpg',
  'Bilbo Bolseiro é arrastado para uma aventura inesperada com treze anões e o mago Gandalf em busca de um tesouro guardado por um dragão.'
),
(
  '1984',
  'George Orwell',
  '9788535909555',
  'Companhia das Letras',
  'Ficção Científica',
  328,
  '1949-06-08',
  3, 'disponivel',
  'https://covers.openlibrary.org/b/isbn/9788535909555-L.jpg',
  'Winston Smith vive em uma sociedade totalitária controlada pelo Grande Irmão, onde o passado é reescrito e o pensamento independente é crime.'
),
(
  'Memórias Póstumas de Brás Cubas',
  'Machado de Assis',
  '9788535902785',
  'Penguin Companhia',
  'Clássico',
  240,
  '1881-01-01',
  2, 'disponivel',
  'https://covers.openlibrary.org/b/isbn/9788535902785-L.jpg',
  'Narrado por um defunto autor, este romance inaugura o Realismo brasileiro com ironia e profundidade psicológica incomparáveis.'
),
(
  'O Código Da Vinci',
  'Dan Brown',
  '9788575421932',
  'Sextante',
  'Mistério',
  480,
  '2003-03-18',
  2, 'disponivel',
  'https://covers.openlibrary.org/b/isbn/9788575421932-L.jpg',
  'O simbologista Robert Langdon é arrastado para uma investigação que envolve sociedades secretas, arte e os maiores mistérios do Vaticano.'
),
(
  'O Pequeno Príncipe',
  'Antoine de Saint-Exupéry',
  '9788532522085',
  'Geração Editorial',
  'Infantil',
  96,
  '1943-04-06',
  5, 'disponivel',
  'https://covers.openlibrary.org/b/isbn/9788532522085-L.jpg',
  'Um piloto preso no deserto encontra um misterioso menino de outro planeta. Uma fábula poética sobre amizade, amor e o sentido da vida.'
);

SET FOREIGN_KEY_CHECKS = 1;

-- ════════════════════════════════════════════════════════════════
--  FIM
-- ════════════════════════════════════════════════════════════════

-- Adiciona status 'aguardando_devolucao' ao ENUM de emprestimos
ALTER TABLE emprestimos MODIFY COLUMN status ENUM('ativo','devolvido','atrasado','renovado','aguardando_devolucao') DEFAULT 'ativo';
