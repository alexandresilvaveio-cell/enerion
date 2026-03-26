<?php
/**
 * =====================================================
 * ENERION PLAYER - CLASSE DE GERENCIAMENTO DE DISPOSITIVOS
 * =====================================================
 * Gerencia registro e monitoramento de dispositivos conectados
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Auth.php';

class Dispositivo {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Registrar ou atualizar ping de dispositivo
     * Chamado pelo aplicativo a cada 30-60 segundos
     */
    public function ping($codigo, $device_id, $device_model = null, $device_name = null) {
        try {
            // Validar entrada
            if (empty($codigo) || empty($device_id)) {
                return [
                    'success' => false,
                    'message' => 'Código e device_id são obrigatórios'
                ];
            }
            
            // Obter informações do código
            $stmt = $this->conn->prepare("
                SELECT rc.id, rc.rev_id, r.max_apps, r.status, r.data_expiracao
                FROM rev_codes rc
                JOIN revs r ON rc.rev_id = r.id
                WHERE rc.codigo = ? AND rc.status = 'ativo'
                LIMIT 1
            ");
            
            $stmt->bind_param("s", $codigo);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Código inválido ou inativo',
                    'status' => 'BLOQUEADO'
                ];
            }
            
            $code_info = $result->fetch_assoc();
            $stmt->close();
            
            $rev_id = $code_info['rev_id'];
            $code_id = $code_info['id'];
            
            // Verificar status do revendedor
            if ($code_info['status'] !== 'ativo') {
                return [
                    'success' => false,
                    'message' => 'Revendedor inativo',
                    'status' => 'BLOQUEADO'
                ];
            }
            
            // Verificar expiração
            if ($code_info['data_expiracao'] && strtotime($code_info['data_expiracao']) < time()) {
                return [
                    'success' => false,
                    'message' => 'Revendedor expirado',
                    'status' => 'BLOQUEADO'
                ];
            }
            
            // Contar apps ativos do revendedor
            $stmt = $this->conn->prepare("
                SELECT COUNT(DISTINCT device_id) as total_apps
                FROM apps
                WHERE rev_id = ? AND status = 'ativo'
            ");
            
            $stmt->bind_param("i", $rev_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $total_apps = $result['total_apps'];
            $stmt->close();
            
            // Verificar se dispositivo já existe
            $stmt = $this->conn->prepare("SELECT id FROM apps WHERE device_id = ? LIMIT 1");
            $stmt->bind_param("s", $device_id);
            $stmt->execute();
            $device_exists = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            
            // Se é novo dispositivo, verificar limite
            if (!$device_exists && $total_apps >= $code_info['max_apps']) {
                return [
                    'success' => false,
                    'message' => 'Limite de aplicativos atingido',
                    'status' => 'BLOQUEADO',
                    'motivo' => 'LIMITE_ATINGIDO'
                ];
            }
            
            // Obter IP do cliente
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
            
            // Inserir ou atualizar dispositivo
            if ($device_exists) {
                // Atualizar ping
                $stmt = $this->conn->prepare("
                    UPDATE apps
                    SET ultimo_ping = NOW(), ip = ?, device_model = ?, device_name = ?
                    WHERE device_id = ?
                ");
                
                $stmt->bind_param("ssss", $ip, $device_model, $device_name, $device_id);
                
                if (!$stmt->execute()) {
                    $stmt->close();
                    return [
                        'success' => false,
                        'message' => 'Erro ao atualizar dispositivo'
                    ];
                }
                $stmt->close();
            } else {
                // Inserir novo dispositivo
                $stmt = $this->conn->prepare("
                    INSERT INTO apps (rev_id, rev_code_id, device_id, device_model, device_name, ip, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'ativo')
                ");
                
                $stmt->bind_param("iissss", $rev_id, $code_id, $device_id, $device_model, $device_name, $ip);
                
                if (!$stmt->execute()) {
                    $stmt->close();
                    return [
                        'success' => false,
                        'message' => 'Erro ao registrar dispositivo'
                    ];
                }
                $stmt->close();
            }
            
            return [
                'success' => true,
                'message' => 'Ping recebido com sucesso',
                'status' => 'ATIVO',
                'apps_ativos' => $total_apps + ($device_exists ? 0 : 1),
                'limite' => $code_info['max_apps']
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao processar ping: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao processar ping'
            ];
        }
    }
    
    /**
     * Listar dispositivos de um revendedor
     */
    public function listar($rev_id, $pagina = 1, $por_pagina = 20) {
        try {
            // Verificar permissão
            if (Auth::isRevAuthenticated() && $_SESSION['rev_id'] != $rev_id) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado',
                    'code' => 403
                ];
            }
            
            $offset = ($pagina - 1) * $por_pagina;
            
            // Contar total
            $result = $this->conn->query("SELECT COUNT(*) as total FROM apps WHERE rev_id = $rev_id");
            $total = $result->fetch_assoc()['total'];
            
            // Buscar dispositivos
            $stmt = $this->conn->prepare("
                SELECT 
                    a.id, a.device_id, a.device_model, a.device_name, a.ip,
                    a.status, a.ultimo_ping, a.created_at,
                    rc.codigo,
                    CASE 
                        WHEN TIMESTAMPDIFF(SECOND, a.ultimo_ping, NOW()) < ? THEN 'online'
                        ELSE 'offline'
                    END as online_status
                FROM apps a
                JOIN rev_codes rc ON a.rev_code_id = rc.id
                WHERE a.rev_id = ?
                ORDER BY a.ultimo_ping DESC
                LIMIT ? OFFSET ?
            ");
            
            $timeout = DEVICE_PING_TIMEOUT;
            $stmt->bind_param("iiii", $timeout, $rev_id, $por_pagina, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $dispositivos = [];
            while ($row = $result->fetch_assoc()) {
                $dispositivos[] = $row;
            }
            $stmt->close();
            
            return [
                'success' => true,
                'data' => $dispositivos,
                'total' => $total,
                'pagina' => $pagina,
                'por_pagina' => $por_pagina,
                'total_paginas' => ceil($total / $por_pagina)
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao listar dispositivos: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao listar dispositivos'
            ];
        }
    }
    
    /**
     * Obter estatísticas de dispositivos
     */
    public function estatisticas($rev_id = null) {
        try {
            if ($rev_id) {
                // Verificar permissão
                if (Auth::isRevAuthenticated() && $_SESSION['rev_id'] != $rev_id) {
                    return [
                        'success' => false,
                        'message' => 'Acesso negado',
                        'code' => 403
                    ];
                }
                
                $where = "WHERE a.rev_id = $rev_id";
            } else {
                // Apenas admin
                if (!Auth::isAdminAuthenticated()) {
                    return [
                        'success' => false,
                        'message' => 'Acesso negado',
                        'code' => 403
                    ];
                }
                $where = "";
            }
            
            // Total de apps
            $result = $this->conn->query("
                SELECT COUNT(DISTINCT a.id) as total FROM apps a $where
            ");
            $total_apps = $result->fetch_assoc()['total'];
            
            // Apps ativos
            $result = $this->conn->query("
                SELECT COUNT(DISTINCT a.id) as total FROM apps a $where AND a.status = 'ativo'
            ");
            $apps_ativos = $result->fetch_assoc()['total'];
            
            // Apps online
            $timeout = DEVICE_PING_TIMEOUT;
            $result = $this->conn->query("
                SELECT COUNT(DISTINCT a.id) as total FROM apps a $where 
                AND a.status = 'ativo' 
                AND TIMESTAMPDIFF(SECOND, a.ultimo_ping, NOW()) < $timeout
            ");
            $apps_online = $result->fetch_assoc()['total'];
            
            // Apps bloqueados
            $result = $this->conn->query("
                SELECT COUNT(DISTINCT a.id) as total FROM apps a $where AND a.status = 'bloqueado'
            ");
            $apps_bloqueados = $result->fetch_assoc()['total'];
            
            return [
                'success' => true,
                'data' => [
                    'total_apps' => $total_apps,
                    'apps_ativos' => $apps_ativos,
                    'apps_online' => $apps_online,
                    'apps_bloqueados' => $apps_bloqueados,
                    'apps_offline' => $apps_ativos - $apps_online
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao obter estatísticas: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao obter estatísticas'
            ];
        }
    }
    
    /**
     * Atualizar status de um dispositivo
     */
    public function atualizarStatus($device_id, $status) {
        try {
            if (!Auth::isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado',
                    'code' => 403
                ];
            }
            
            if (!in_array($status, ['ativo', 'inativo', 'bloqueado'])) {
                return [
                    'success' => false,
                    'message' => 'Status inválido'
                ];
            }
            
            $stmt = $this->conn->prepare("UPDATE apps SET status = ? WHERE device_id = ?");
            $stmt->bind_param("ss", $status, $device_id);
            
            if (!$stmt->execute()) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Erro ao atualizar dispositivo'
                ];
            }
            
            $stmt->close();
            
            // Log
            logAction('atualizar_dispositivo', Auth::getUserId(), 'admin', "Status do dispositivo atualizado para: $status (Device: $device_id)");
            
            return [
                'success' => true,
                'message' => 'Dispositivo atualizado com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao atualizar dispositivo: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao atualizar dispositivo'
            ];
        }
    }
    
    /**
     * Deletar dispositivo
     */
    public function deletar($device_id) {
        try {
            if (!Auth::isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado',
                    'code' => 403
                ];
            }
            
            $stmt = $this->conn->prepare("DELETE FROM apps WHERE device_id = ?");
            $stmt->bind_param("s", $device_id);
            
            if (!$stmt->execute()) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Erro ao deletar dispositivo'
                ];
            }
            
            $stmt->close();
            
            // Log
            logAction('deletar_dispositivo', Auth::getUserId(), 'admin', "Dispositivo deletado (Device: $device_id)");
            
            return [
                'success' => true,
                'message' => 'Dispositivo deletado com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao deletar dispositivo: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao deletar dispositivo'
            ];
        }
    }
}
?>
