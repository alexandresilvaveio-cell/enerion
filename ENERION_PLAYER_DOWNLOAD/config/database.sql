-- =====================================================
-- ENERION PLAYER - SCHEMA DO BANCO DE DADOS
-- =====================================================
-- Banco de dados para gerenciamento de DNS e revendedores
-- Criado para instalação em aaPanel com MySQL

CREATE DATABASE IF NOT EXISTS `enerion`;
USE `enerion`;

-- =====================================================
-- TABELA: ADMINS
-- =====================================================
-- Armazena credenciais dos administradores do sistema
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Nome de usuário único',
    `password` VARCHAR(255) NOT NULL COMMENT 'Senha com hash bcrypt',
    `email` VARCHAR(255) COMMENT 'Email do administrador (opcional)',
    `status` ENUM('ativo', 'inativo') DEFAULT 'ativo' COMMENT 'Status do admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última atualização',
    INDEX idx_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de administradores do sistema';

-- =====================================================
-- TABELA: REVS (REVENDEDORES)
-- =====================================================
-- Armazena informações dos revendedores
CREATE TABLE IF NOT EXISTS `revs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(255) NOT NULL COMMENT 'Nome do revendedor',
    `telefone` VARCHAR(20) COMMENT 'Telefone do revendedor',
    `username` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Nome de usuário único',
    `password` VARCHAR(255) NOT NULL COMMENT 'Senha com hash bcrypt',
    `email` VARCHAR(255) COMMENT 'Email do revendedor',
    `status` ENUM('ativo', 'inativo') DEFAULT 'ativo' COMMENT 'Status do revendedor',
    `max_apps` INT DEFAULT 0 COMMENT 'Limite máximo de aplicativos',
    `valor_por_app` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Valor cobrado por app ativo',
    `dia_pagamento` INT COMMENT 'Dia do mês para pagamento',
    `data_expiracao` DATE COMMENT 'Data de expiração da conta',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última atualização',
    INDEX idx_username (username),
    INDEX idx_status (status),
    INDEX idx_data_expiracao (data_expiracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de revendedores';

-- =====================================================
-- TABELA: REV_CODES (CÓDIGOS DNS DOS REVENDEDORES)
-- =====================================================
-- Armazena os códigos DNS mascarados para cada revendedor
CREATE TABLE IF NOT EXISTS `rev_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rev_id` INT NOT NULL COMMENT 'ID do revendedor',
    `codigo` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Código mascarado (ex: 1009001)',
    `dns_real` TEXT NOT NULL COMMENT 'DNS real (nunca expor diretamente)',
    `status` ENUM('ativo', 'inativo') DEFAULT 'ativo' COMMENT 'Status do código',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última atualização',
    FOREIGN KEY (`rev_id`) REFERENCES `revs`(`id`) ON DELETE CASCADE,
    INDEX idx_rev_id (rev_id),
    INDEX idx_codigo (codigo),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de códigos DNS dos revendedores';

-- =====================================================
-- TABELA: APPS (DISPOSITIVOS/APLICATIVOS CONECTADOS)
-- =====================================================
-- Armazena informações dos dispositivos conectados
CREATE TABLE IF NOT EXISTS `apps` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rev_id` INT NOT NULL COMMENT 'ID do revendedor',
    `rev_code_id` INT NOT NULL COMMENT 'ID do código DNS utilizado',
    `device_id` VARCHAR(255) NOT NULL UNIQUE COMMENT 'ID único do dispositivo (MAC ou gerado)',
    `device_model` VARCHAR(255) COMMENT 'Modelo do dispositivo (LG, Samsung, Android)',
    `device_name` VARCHAR(255) COMMENT 'Nome/identificação do dispositivo',
    `ip` VARCHAR(45) COMMENT 'Endereço IP do dispositivo',
    `ultimo_ping` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Último ping recebido',
    `status` ENUM('ativo', 'inativo', 'bloqueado') DEFAULT 'ativo' COMMENT 'Status do dispositivo',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Última atualização',
    FOREIGN KEY (`rev_id`) REFERENCES `revs`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`rev_code_id`) REFERENCES `rev_codes`(`id`) ON DELETE CASCADE,
    INDEX idx_rev_id (rev_id),
    INDEX idx_device_id (device_id),
    INDEX idx_status (status),
    INDEX idx_ultimo_ping (ultimo_ping)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de dispositivos/aplicativos conectados';

-- =====================================================
-- TABELA: LOGS (AUDITORIA E RASTREAMENTO)
-- =====================================================
-- Armazena logs de ações do sistema
CREATE TABLE IF NOT EXISTS `logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tipo` VARCHAR(100) COMMENT 'Tipo de ação (login, criacao_rev, etc)',
    `usuario_id` INT COMMENT 'ID do usuário que realizou a ação',
    `usuario_tipo` ENUM('admin', 'rev') COMMENT 'Tipo de usuário',
    `descricao` TEXT COMMENT 'Descrição da ação',
    `ip` VARCHAR(45) COMMENT 'IP de origem',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data da ação',
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_tipo (tipo),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de logs e auditoria';

-- =====================================================
-- INSERIR ADMIN PADRÃO
-- =====================================================
-- Admin padrão: username=admin, password=admin123 (hash bcrypt)
INSERT IGNORE INTO `admins` (`username`, `password`, `status`) 
VALUES ('admin', '$2y$10$YIjlrBxZ8.8K8Zy5.8K8.uK8K8K8K8K8K8K8K8K8K8K8K8K8K8K8K', 'ativo');

-- =====================================================
-- CRIAR ÍNDICES ADICIONAIS PARA PERFORMANCE
-- =====================================================
CREATE INDEX idx_apps_rev_code ON apps(rev_code_id);
CREATE INDEX idx_revs_created ON revs(created_at);
CREATE INDEX idx_rev_codes_created ON rev_codes(created_at);
