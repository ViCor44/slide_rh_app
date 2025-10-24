-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 24-Out-2025 às 00:43
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `slide_app`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `avaliacao_metricas`
--

CREATE TABLE `avaliacao_metricas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome_metrica` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `ativa` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `avaliacao_metricas`
--

INSERT INTO `avaliacao_metricas` (`id`, `nome_metrica`, `descricao`, `ativa`) VALUES
(1, 'Comunicação', 'Clareza, escuta ativa e capacidade de partilhar informação.', 1),
(2, 'Trabalho em Equipa', 'Colaboração com colegas e contribuição para um ambiente positivo.', 1),
(3, 'Proatividade e Iniciativa', 'Capacidade de antecipar necessidades e agir sem supervisão constante.', 1),
(4, 'Resolução de Problemas', 'Habilidade para identificar, analisar e resolver desafios de forma eficaz.', 1),
(5, 'Qualidade do Trabalho', 'Atenção ao detalhe e entrega de trabalho com excelência.', 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `avaliacao_respostas`
--

CREATE TABLE `avaliacao_respostas` (
  `id` int(10) UNSIGNED NOT NULL,
  `avaliacao_id` int(10) UNSIGNED NOT NULL,
  `metrica_id` int(10) UNSIGNED NOT NULL,
  `pontuacao` tinyint(4) NOT NULL,
  `comentarios` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `avaliacao_respostas`
--

INSERT INTO `avaliacao_respostas` (`id`, `avaliacao_id`, `metrica_id`, `pontuacao`, `comentarios`) VALUES
(1, 1, 1, 4, ''),
(2, 1, 2, 4, ''),
(3, 1, 3, 3, ''),
(4, 1, 4, 4, ''),
(5, 1, 5, 4, ''),
(6, 2, 1, 3, ''),
(7, 2, 2, 3, ''),
(8, 2, 3, 3, ''),
(9, 2, 4, 4, ''),
(10, 2, 5, 4, '');

-- --------------------------------------------------------

--
-- Estrutura da tabela `avaliacoes`
--

CREATE TABLE `avaliacoes` (
  `id` int(10) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `avaliador_user_id` int(10) UNSIGNED DEFAULT NULL,
  `periodo` varchar(100) NOT NULL,
  `data_avaliacao` date NOT NULL,
  `comentarios_finais` text DEFAULT NULL,
  `objetivos_futuros` text DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Concluída'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `avaliacoes`
--

INSERT INTO `avaliacoes` (`id`, `funcionario_id`, `avaliador_user_id`, `periodo`, `data_avaliacao`, `comentarios_finais`, `objetivos_futuros`, `status`) VALUES
(1, 5, 6, 'Outubro', '2025-10-24', '', '', 'Concluída'),
(2, 5, 7, 'Outubro', '2025-10-24', '', '', 'Concluída');

-- --------------------------------------------------------

--
-- Estrutura da tabela `funcionarios`
--

CREATE TABLE `funcionarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `numero_funcionario` varchar(20) DEFAULT NULL,
  `nome_completo` varchar(255) NOT NULL,
  `email_corporativo` varchar(255) DEFAULT NULL,
  `cargo` varchar(100) NOT NULL,
  `departamento` varchar(100) NOT NULL,
  `foto_path` varchar(255) DEFAULT NULL,
  `nfc_card_id` varchar(50) DEFAULT NULL,
  `data_contratacao` date NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `funcionarios`
--

INSERT INTO `funcionarios` (`id`, `numero_funcionario`, `nome_completo`, `email_corporativo`, `cargo`, `departamento`, `foto_path`, `nfc_card_id`, `data_contratacao`, `ativo`, `created_at`, `updated_at`) VALUES
(1, '101', 'Sofia Alves', 'sofia.alves@slideapp.pt', 'Diretora Geral', 'Administração', '1.jpg', NULL, '2020-01-15', 1, '2025-10-21 19:47:12', '2025-10-22 20:36:12'),
(2, '205', 'Ricardo Mendes', 'ricardo.mendes@slideapp.pt', 'Técnico de RH', 'Recursos Humanos', '2.jpg', NULL, '2021-03-22', 1, '2025-10-21 19:47:12', '2025-10-22 20:36:12'),
(3, '310', 'Joana Pinto', 'joana.pinto@slideapp.pt', 'Chefe de Equipa', 'Piscinas', '3.jpg', NULL, '2019-05-10', 1, '2025-10-21 19:47:12', '2025-10-22 20:36:12'),
(4, '311', 'Carlos Martins', 'carlos.martins@slideapp.pt', 'Nadador Salvador', 'Piscinas', '4.jpg', NULL, '2022-06-01', 1, '2025-10-21 19:47:12', '2025-10-22 20:36:12'),
(5, '452', 'Ana Pereira', 'ana.pereira@slideapp.pt', 'Operadora de Caixa', 'Restauração', NULL, NULL, '2023-04-12', 1, '2025-10-21 19:47:12', '2025-10-21 19:47:12'),
(6, '999', 'Laura Neves', 'laura.neves@email.com', 'Chefe de Equipa', 'Restauração', NULL, NULL, '2025-10-21', 1, '2025-10-21 19:47:12', '2025-10-22 18:08:57');

-- --------------------------------------------------------

--
-- Estrutura da tabela `funcionarios_dados_pessoais`
--

CREATE TABLE `funcionarios_dados_pessoais` (
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `nif` varchar(9) DEFAULT NULL,
  `nss` varchar(11) DEFAULT NULL,
  `cartao_cidadao` varchar(20) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `telemovel` varchar(20) DEFAULT NULL,
  `morada_completa` text DEFAULT NULL,
  `iban` varchar(25) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `funcionarios_dados_pessoais`
--

INSERT INTO `funcionarios_dados_pessoais` (`funcionario_id`, `nif`, `nss`, `cartao_cidadao`, `data_nascimento`, `telemovel`, `morada_completa`, `iban`) VALUES
(1, '234567890', NULL, NULL, NULL, '912345678', NULL, NULL),
(3, '345678901', NULL, NULL, NULL, '923456789', NULL, NULL),
(4, '456789012', NULL, NULL, NULL, '934567890', NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `funcionario_documentos`
--

CREATE TABLE `funcionario_documentos` (
  `id` int(10) UNSIGNED NOT NULL,
  `funcionario_id` int(10) UNSIGNED NOT NULL,
  `nome_ficheiro_original` varchar(255) NOT NULL,
  `path_ficheiro_armazenado` varchar(255) NOT NULL,
  `tipo_documento` varchar(100) DEFAULT NULL,
  `uploaded_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `logs`
--

CREATE TABLE `logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `level` varchar(20) NOT NULL COMMENT 'Ex: INFO, WARNING, SECURITY',
  `event_type` varchar(50) NOT NULL COMMENT 'Ex: LOGIN_SUCCESS, EMPLOYEE_UPDATE',
  `message` varchar(255) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID do utilizador que realizou a ação',
  `ip_address` varchar(45) DEFAULT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Dados extra, como o ID do registo afetado' CHECK (json_valid(`context`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `logs`
--

INSERT INTO `logs` (`id`, `level`, `event_type`, `message`, `user_id`, `ip_address`, `context`, `created_at`) VALUES
(1, 'INFO', 'LOGIN_SUCCESS', 'Utilizador \'admin@slideapp.pt\' logado com sucesso.', 6, '::1', NULL, '2025-10-23 20:49:31'),
(2, 'INFO', 'EMPLOYEE_UPDATE', 'O funcionário \'Ana Pereira\' (ID: 5) foi atualizado.', 6, '::1', '{\"record_id\":5}', '2025-10-23 20:50:32'),
(3, 'INFO', 'LOGIN_SUCCESS', 'Utilizador \'sofia.alves@slideapp.pt\' logado com sucesso.', 1, '::1', NULL, '2025-10-23 20:51:37'),
(4, 'WARNING', 'LOGIN_FAILURE', 'Tentativa de login falhada para o email \'gyvubu@gmail.com\'.', NULL, '::1', NULL, '2025-10-23 20:52:40'),
(5, 'INFO', 'LOGIN_SUCCESS', 'Utilizador \'admin@slideapp.pt\' fez login com sucesso.', 6, '::1', NULL, '2025-10-23 20:52:44'),
(6, 'INFO', 'LOGIN_SUCCESS', 'Utilizador \'admin@slideapp.pt\' fez login com sucesso.', 6, '::1', NULL, '2025-10-23 21:39:18'),
(7, 'INFO', 'EVALUATION_CREATED', 'Nova avaliação para o funcionário \'Ana Pereira\' (ID: 5) foi submetida.', 6, '::1', NULL, '2025-10-23 22:20:25'),
(8, 'INFO', 'LOGIN_SUCCESS', 'Utilizador \'ricardo.mendes@slideapp.pt\' fez login com sucesso.', 2, '::1', NULL, '2025-10-23 22:29:55'),
(9, 'INFO', 'LOGIN_SUCCESS', 'Utilizador \'joana.pinto@slideapp.pt\' fez login com sucesso.', 3, '::1', NULL, '2025-10-23 22:30:46'),
(10, 'INFO', 'LOGIN_SUCCESS', 'Utilizador \'admin@slideapp.pt\' fez login com sucesso.', 6, '::1', NULL, '2025-10-23 22:31:31'),
(11, 'INFO', 'LOGIN_SUCCESS', 'Utilizador \'laura.neves@email.com\' fez login com sucesso.', 7, '::1', NULL, '2025-10-23 22:32:00'),
(12, 'INFO', 'EVALUATION_CREATED', 'Nova avaliação para o funcionário \'Ana Pereira\' (ID: 5) foi submetida.', 7, '::1', NULL, '2025-10-23 22:32:46');

-- --------------------------------------------------------

--
-- Estrutura da tabela `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome_role` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `roles`
--

INSERT INTO `roles` (`id`, `nome_role`) VALUES
(1, 'Admin'),
(4, 'Funcionário'),
(3, 'Manager'),
(2, 'Recursos Humanos'),
(5, 'Supervisor');

-- --------------------------------------------------------

--
-- Estrutura da tabela `utilizadores`
--

CREATE TABLE `utilizadores` (
  `id` int(10) UNSIGNED NOT NULL,
  `nome` varchar(255) NOT NULL,
  `funcionario_id` int(10) UNSIGNED DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `google_authenticator_secret` varchar(255) DEFAULT NULL,
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Extraindo dados da tabela `utilizadores`
--

INSERT INTO `utilizadores` (`id`, `nome`, `funcionario_id`, `email`, `password_hash`, `google_authenticator_secret`, `role_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Sofia Alves', 1, 'sofia.alves@slideapp.pt', '$2y$10$lTDNseVm5FFBBC54aXOeUer6tEosMRkJVoSS81I.PExD67LjUqtWC', NULL, 1, 1, '2025-10-21 19:47:12', '2025-10-21 20:30:34'),
(2, 'Ricardo Mendes', 2, 'ricardo.mendes@slideapp.pt', '$2y$10$lTDNseVm5FFBBC54aXOeUer6tEosMRkJVoSS81I.PExD67LjUqtWC', NULL, 2, 1, '2025-10-21 19:47:12', '2025-10-21 20:30:42'),
(3, 'Joana Pinto', 3, 'joana.pinto@slideapp.pt', '$2y$10$lTDNseVm5FFBBC54aXOeUer6tEosMRkJVoSS81I.PExD67LjUqtWC', NULL, 3, 1, '2025-10-21 19:47:12', '2025-10-21 20:30:54'),
(4, 'Carlos Martins', 4, 'carlos.martins@slideapp.pt', '$2y$10$lTDNseVm5FFBBC54aXOeUer6tEosMRkJVoSS81I.PExD67LjUqtWC', NULL, 4, 1, '2025-10-21 19:47:12', '2025-10-21 20:31:03'),
(5, 'Ana Pereira', 5, 'ana.pereira@slideapp.pt', '$2y$10$lTDNseVm5FFBBC54aXOeUer6tEosMRkJVoSS81I.PExD67LjUqtWC', NULL, 4, 1, '2025-10-21 19:47:12', '2025-10-21 20:31:12'),
(6, 'SysAdmin', NULL, 'admin@slideapp.pt', '$2y$10$lTDNseVm5FFBBC54aXOeUer6tEosMRkJVoSS81I.PExD67LjUqtWC', NULL, 1, 1, '2025-10-21 19:47:12', '2025-10-21 20:26:58'),
(7, 'Laura Neves', 6, 'laura.neves@email.com', '$2y$10$lTDNseVm5FFBBC54aXOeUer6tEosMRkJVoSS81I.PExD67LjUqtWC', NULL, 3, 1, '2025-10-21 19:47:12', '2025-10-22 18:10:07');

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `avaliacao_metricas`
--
ALTER TABLE `avaliacao_metricas`
  ADD PRIMARY KEY (`id`);

--
-- Índices para tabela `avaliacao_respostas`
--
ALTER TABLE `avaliacao_respostas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_resposta_avaliacao` (`avaliacao_id`),
  ADD KEY `fk_resposta_metrica` (`metrica_id`);

--
-- Índices para tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_avaliacao_funcionario` (`funcionario_id`),
  ADD KEY `fk_avaliacao_avaliador` (`avaliador_user_id`);

--
-- Índices para tabela `funcionarios`
--
ALTER TABLE `funcionarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_funcionario` (`numero_funcionario`),
  ADD UNIQUE KEY `email_corporativo` (`email_corporativo`),
  ADD UNIQUE KEY `nfc_card_id` (`nfc_card_id`);

--
-- Índices para tabela `funcionarios_dados_pessoais`
--
ALTER TABLE `funcionarios_dados_pessoais`
  ADD PRIMARY KEY (`funcionario_id`),
  ADD UNIQUE KEY `nif` (`nif`),
  ADD UNIQUE KEY `nss` (`nss`),
  ADD UNIQUE KEY `cartao_cidadao` (`cartao_cidadao`);

--
-- Índices para tabela `funcionario_documentos`
--
ALTER TABLE `funcionario_documentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `path_ficheiro_armazenado` (`path_ficheiro_armazenado`),
  ADD KEY `fk_documento_funcionario` (`funcionario_id`),
  ADD KEY `fk_documento_uploader` (`uploaded_by_user_id`);

--
-- Índices para tabela `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_level` (`level`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Índices para tabela `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome_role` (`nome_role`);

--
-- Índices para tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_utilizador_role_final` (`role_id`),
  ADD KEY `fk_utilizador_funcionario_final` (`funcionario_id`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `avaliacao_metricas`
--
ALTER TABLE `avaliacao_metricas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `avaliacao_respostas`
--
ALTER TABLE `avaliacao_respostas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `funcionarios`
--
ALTER TABLE `funcionarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `funcionario_documentos`
--
ALTER TABLE `funcionario_documentos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de tabela `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `avaliacao_respostas`
--
ALTER TABLE `avaliacao_respostas`
  ADD CONSTRAINT `fk_resposta_avaliacao` FOREIGN KEY (`avaliacao_id`) REFERENCES `avaliacoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_resposta_metrica` FOREIGN KEY (`metrica_id`) REFERENCES `avaliacao_metricas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD CONSTRAINT `fk_avaliacao_avaliador` FOREIGN KEY (`avaliador_user_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_avaliacao_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `funcionarios_dados_pessoais`
--
ALTER TABLE `funcionarios_dados_pessoais`
  ADD CONSTRAINT `fk_dados_pessoais_funcionario_final` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `funcionario_documentos`
--
ALTER TABLE `funcionario_documentos`
  ADD CONSTRAINT `fk_documento_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_documento_uploader` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `utilizadores` (`id`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `utilizadores`
--
ALTER TABLE `utilizadores`
  ADD CONSTRAINT `fk_utilizador_funcionario_final` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_utilizador_role_final` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
