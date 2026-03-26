-- =====================================================
-- ENERION PLAYER - SCHEMA DE LICENCIAMENTO
-- Sistema de Ativação e Controle de Dispositivos
-- Otimizado para 500.000+ dispositivos simultâneos
-- =====================================================

-- Usar banco de dados
USE `enerion`;

-- =====================================================
-- TABELA: CODIGOS (Códigos de Ativação)
-- =====================================================
-- Armazena códigos de ativação gerados pelos revendedores
CREATE TABLE IF NOT EXISTS `codigos` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Código único de ativação',
    `rev_id` INT NOT NULL COMMENT 'ID do revendedor',
    `status` ENUM('ativo', 'inativo', 'expirado') DEFAULT 'ativo' COMMENT 'Status do código',
    `data_criacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    `data_expiracao` DATE COMMENT 'Data de expiração',
    `max_dispositivos` INT DEFAULT 5 COMMENT 'Limite de dispositivos',
    `dispositivos_ativos` INT DEFAULT 0 COMMENT 'Contador de dispositivos ativos',
    `ultimo_uso` TIMESTAMP NULL COMMENT 'Último uso do código',
    
    INDEX idx_codigo (codigo),
    INDEX idx_rev_id (rev_id),
    INDEX idx_status (status),
    INDEX idx_data_expiracao (data_expiracao),
    FOREIGN KEY (rev_id) REFERENCES revs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
  COMMENT='Tabela de códigos de ativação' ROW_FORMAT=COMPRESSED;

-- =====================================================
-- TABELA: DISPOSITIVOS (Dispositivos Ativados)
-- =====================================================
-- Armazena informações dos dispositivos ativados
-- Otimizada para consultas rápidas
CREATE TABLE IF NOT EXISTS `dispositivos` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `device_id` VARCHAR(100) NOT NULL UNIQUE COMMENT 'ID único do dispositivo (MAC/UUID)',
    `codigo_id` BIGINT NOT NULL COMMENT 'ID do código de ativação',
    `rev_id` INT NOT NULL COMMENT 'ID do revendedor (desnormalizado para performance)',
    `modelo` VARCHAR(100) COMMENT 'Modelo da TV (Samsung, LG, etc)',
    `plataforma` ENUM('webos', 'tizen', 'android', 'roku', 'outro') DEFAULT 'outro' COMMENT 'Plataforma da TV',
    `app_version` VARCHAR(20) COMMENT 'Versão do app instalado',
    `ip_address` VARCHAR(45) COMMENT 'IP do dispositivo (IPv4 ou IPv6)',
    `status` ENUM('ativo', 'inativo', 'bloqueado') DEFAULT 'ativo' COMMENT 'Status do dispositivo',
    `data_ativacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de ativação',
    `data_ultimo_ping` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Último ping recebido',
    `online` TINYINT(1) DEFAULT 1 COMMENT 'Status online (1=sim, 0=não)',
    
    -- Índices para performance máxima
    UNIQUE KEY uk_device_id (device_id),
    INDEX idx_codigo_id (codigo_id),
    INDEX idx_rev_id (rev_id),
    INDEX idx_status (status),
    INDEX idx_online (online),
    INDEX idx_data_ultimo_ping (data_ultimo_ping),
    INDEX idx_plataforma (plataforma),
    
    -- Foreign keys
    FOREIGN KEY (codigo_id) REFERENCES codigos(id) ON DELETE CASCADE,
    FOREIGN KEY (rev_id) REFERENCES revs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
  COMMENT='Tabela de dispositivos ativados' ROW_FORMAT=COMPRESSED;

-- =====================================================
-- TABELA: ATIVACOES_LOG (Log de Ativações)
-- =====================================================
-- Registra todas as ativações para auditoria
CREATE TABLE IF NOT EXISTS `ativacoes_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `device_id` VARCHAR(100) NOT NULL COMMENT 'ID do dispositivo',
    `codigo` VARCHAR(50) NOT NULL COMMENT 'Código usado',
    `rev_id` INT NOT NULL COMMENT 'ID do revendedor',
    `ip_address` VARCHAR(45) COMMENT 'IP da requisição',
    `modelo` VARCHAR(100) COMMENT 'Modelo da TV',
    `resultado` ENUM('sucesso', 'erro_codigo', 'erro_limite', 'erro_expirado', 'erro_outro') DEFAULT 'sucesso',
    `mensagem_erro` TEXT COMMENT 'Mensagem de erro (se houver)',
    `data_ativacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora da ativação',
    
    INDEX idx_device_id (device_id),
    INDEX idx_codigo (codigo),
    INDEX idx_rev_id (rev_id),
    INDEX idx_resultado (resultado),
    INDEX idx_data_ativacao (data_ativacao),
    FOREIGN KEY (rev_id) REFERENCES revs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
  COMMENT='Log de ativações de dispositivos' ROW_FORMAT=COMPRESSED;

-- =====================================================
-- TABELA: PINGS_LOG (Log de Pings)
-- =====================================================
-- Registra pings para monitoramento
CREATE TABLE IF NOT EXISTS `pings_log` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `device_id` VARCHAR(100) NOT NULL COMMENT 'ID do dispositivo',
    `rev_id` INT NOT NULL COMMENT 'ID do revendedor',
    `ip_address` VARCHAR(45) COMMENT 'IP da requisição',
    `data_ping` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora do ping',
    
    INDEX idx_device_id (device_id),
    INDEX idx_rev_id (rev_id),
    INDEX idx_data_ping (data_ping),
    FOREIGN KEY (rev_id) REFERENCES revs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
  COMMENT='Log de pings de dispositivos' ROW_FORMAT=COMPRESSED;

-- =====================================================
-- TABELA: ESTATISTICAS_REVENDEDOR (Cache de Estatísticas)
-- =====================================================
-- Cache para performance de dashboards
CREATE TABLE IF NOT EXISTS `estatisticas_revendedor` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rev_id` INT NOT NULL UNIQUE COMMENT 'ID do revendedor',
    `total_codigos` INT DEFAULT 0 COMMENT 'Total de códigos criados',
    `codigos_ativos` INT DEFAULT 0 COMMENT 'Códigos ainda ativos',
    `total_dispositivos` INT DEFAULT 0 COMMENT 'Total de dispositivos ativados',
    `dispositivos_online` INT DEFAULT 0 COMMENT 'Dispositivos online agora',
    `data_atualizacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (rev_id) REFERENCES revs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
  COMMENT='Cache de estatísticas por revendedor';

-- =====================================================
-- VIEWS (Para queries otimizadas)
-- =====================================================

-- View: Dispositivos Ativos por Revendedor
CREATE OR REPLACE VIEW vw_dispositivos_ativos AS
SELECT 
    d.id,
    d.device_id,
    d.rev_id,
    d.modelo,
    d.plataforma,
    d.ip_address,
    d.status,
    d.data_ativacao,
    d.data_ultimo_ping,
    d.online,
    c.codigo,
    r.nome as revendedor_nome
FROM dispositivos d
INNER JOIN codigos c ON d.codigo_id = c.id
INNER JOIN revs r ON d.rev_id = r.id
WHERE d.status = 'ativo' AND c.status = 'ativo';

-- View: Resumo de Ativações
CREATE OR REPLACE VIEW vw_resumo_ativacoes AS
SELECT 
    DATE(al.data_ativacao) as data,
    al.rev_id,
    r.nome as revendedor_nome,
    COUNT(*) as total_ativacoes,
    SUM(CASE WHEN al.resultado = 'sucesso' THEN 1 ELSE 0 END) as sucessos,
    SUM(CASE WHEN al.resultado != 'sucesso' THEN 1 ELSE 0 END) as erros
FROM ativacoes_log al
INNER JOIN revs r ON al.rev_id = r.id
GROUP BY DATE(al.data_ativacao), al.rev_id;

-- =====================================================
-- STORED PROCEDURES (Para operações complexas)
-- =====================================================

-- Procedure: Ativar Dispositivo
DELIMITER //

CREATE PROCEDURE sp_ativar_dispositivo(
    IN p_codigo VARCHAR(50),
    IN p_device_id VARCHAR(100),
    IN p_modelo VARCHAR(100),
    IN p_plataforma VARCHAR(20),
    IN p_app_version VARCHAR(20),
    IN p_ip_address VARCHAR(45),
    OUT p_status VARCHAR(20),
    OUT p_mensagem VARCHAR(255),
    OUT p_dns VARCHAR(255)
)
BEGIN
    DECLARE v_codigo_id BIGINT;
    DECLARE v_rev_id INT;
    DECLARE v_max_dispositivos INT;
    DECLARE v_dispositivos_ativos INT;
    DECLARE v_data_expiracao DATE;
    DECLARE v_codigo_status VARCHAR(20);
    
    -- Inicializar variáveis
    SET p_status = 'erro';
    SET p_mensagem = 'Erro desconhecido';
    SET p_dns = '';
    
    START TRANSACTION;
    
    -- 1. Validar código
    SELECT id, rev_id, status, data_expiracao, max_dispositivos, dispositivos_ativos
    INTO v_codigo_id, v_rev_id, v_codigo_status, v_data_expiracao, v_max_dispositivos, v_dispositivos_ativos
    FROM codigos
    WHERE codigo = p_codigo
    LIMIT 1;
    
    -- Se código não existe
    IF v_codigo_id IS NULL THEN
        SET p_status = 'erro';
        SET p_mensagem = 'Código inválido';
        ROLLBACK;
        LEAVE;
    END IF;
    
    -- Se código está inativo
    IF v_codigo_status != 'ativo' THEN
        SET p_status = 'erro';
        SET p_mensagem = 'Código inativo ou expirado';
        ROLLBACK;
        LEAVE;
    END IF;
    
    -- Se código expirou
    IF v_data_expiracao IS NOT NULL AND v_data_expiracao < CURDATE() THEN
        UPDATE codigos SET status = 'expirado' WHERE id = v_codigo_id;
        SET p_status = 'erro';
        SET p_mensagem = 'Código expirado';
        ROLLBACK;
        LEAVE;
    END IF;
    
    -- 2. Verificar limite de dispositivos
    IF v_dispositivos_ativos >= v_max_dispositivos THEN
        SET p_status = 'erro';
        SET p_mensagem = 'Limite de dispositivos atingido';
        ROLLBACK;
        LEAVE;
    END IF;
    
    -- 3. Verificar se dispositivo já existe
    IF EXISTS (SELECT 1 FROM dispositivos WHERE device_id = p_device_id AND status = 'ativo') THEN
        -- Atualizar dispositivo existente
        UPDATE dispositivos
        SET 
            ip_address = p_ip_address,
            data_ultimo_ping = NOW(),
            online = 1
        WHERE device_id = p_device_id;
    ELSE
        -- Criar novo dispositivo
        INSERT INTO dispositivos (
            device_id, codigo_id, rev_id, modelo, plataforma, 
            app_version, ip_address, status, online
        ) VALUES (
            p_device_id, v_codigo_id, v_rev_id, p_modelo, p_plataforma,
            p_app_version, p_ip_address, 'ativo', 1
        );
        
        -- Incrementar contador de dispositivos ativos
        UPDATE codigos
        SET dispositivos_ativos = dispositivos_ativos + 1,
            ultimo_uso = NOW()
        WHERE id = v_codigo_id;
    END IF;
    
    -- 4. Registrar ativação no log
    INSERT INTO ativacoes_log (
        device_id, codigo, rev_id, ip_address, modelo, resultado
    ) VALUES (
        p_device_id, p_codigo, v_rev_id, p_ip_address, p_modelo, 'sucesso'
    );
    
    -- 5. Atualizar estatísticas
    UPDATE estatisticas_revendedor
    SET 
        dispositivos_online = (SELECT COUNT(*) FROM dispositivos WHERE rev_id = v_rev_id AND online = 1),
        data_atualizacao = NOW()
    WHERE rev_id = v_rev_id;
    
    -- Retornar sucesso
    SET p_status = 'ok';
    SET p_mensagem = 'Dispositivo ativado com sucesso';
    SET p_dns = 'http://servidoriptv.com'; -- Retornar DNS do servidor IPTV
    
    COMMIT;
    
END //

DELIMITER ;

-- =====================================================
-- ÍNDICES ADICIONAIS (Performance)
-- =====================================================

-- Índices compostos para queries comuns
ALTER TABLE dispositivos ADD INDEX idx_rev_status (rev_id, status);
ALTER TABLE dispositivos ADD INDEX idx_codigo_status (codigo_id, status);
ALTER TABLE codigos ADD INDEX idx_rev_status (rev_id, status);

-- =====================================================
-- PARTICIONAMENTO (Para tabelas grandes)
-- =====================================================

-- Particionar tabela de logs por data (opcional, para 500k+ dispositivos)
-- ALTER TABLE ativacoes_log PARTITION BY RANGE (YEAR(data_ativacao)) (
--     PARTITION p2024 VALUES LESS THAN (2025),
--     PARTITION p2025 VALUES LESS THAN (2026),
--     PARTITION p2026 VALUES LESS THAN (2027),
--     PARTITION pmax VALUES LESS THAN MAXVALUE
-- );

-- =====================================================
-- DADOS INICIAIS
-- =====================================================

-- Inserir alguns códigos de exemplo
INSERT INTO codigos (codigo, rev_id, status, data_expiracao, max_dispositivos) VALUES
('ENERION001', 1, 'ativo', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 5),
('ENERION002', 1, 'ativo', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 10),
('ENERION003', 1, 'ativo', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 3);

-- =====================================================
-- ESTATÍSTICAS
-- =====================================================

-- Analisar tabelas para otimização
ANALYZE TABLE codigos;
ANALYZE TABLE dispositivos;
ANALYZE TABLE ativacoes_log;
ANALYZE TABLE pings_log;

-- =====================================================
-- FIM DO SCHEMA
-- =====================================================
