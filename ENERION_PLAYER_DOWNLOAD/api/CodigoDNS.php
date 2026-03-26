<?php
/**
 * =====================================================
 * ENERION PLAYER - CLASSE DE GERENCIAMENTO DE CÓDIGOS DNS
 * =====================================================
 * Gerencia códigos DNS mascarados para revendedores
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Auth.php';

class CodigoDNS {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Criar novo código DNS
     */
    public function criar($rev_id, $dados) {
        try {
            // Verificar permissão
            if (Auth::isRevAuthenticated() && $_SESSION['rev_id'] != $rev_id) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado',
                    'code' => 403
                ];
            }
            
            // Validar dados
            if (empty($dados['codigo'] ?? null) || empty($dados['dns_real'] ?? null)) {
                return [
                    'success' => false,
                    'message' => 'Código e DNS são obrigatórios'
                ];
            }
            
            // Verificar limite de códigos (máximo 5 por revendedor)
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM rev_codes WHERE rev_id = ?");
            $stmt->bind_param("i", $rev_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($result['total'] >= 5) {
                return [
                    'success' => false,
                    'message' => 'Limite de 5 códigos DNS por revendedor atingido'
                ];
            }
            
            // Verificar se código já existe
            $stmt = $this->conn->prepare("SELECT id FROM rev_codes WHERE codigo = ? LIMIT 1");
            $stmt->bind_param("s", $dados['codigo']);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Este código já está em uso'
                ];
            }
            $stmt->close();
            
            // Preparar dados
            $codigo = $dados['codigo'];
            $dns_real = $dados['dns_real'];
            
            // Inserir código
            $stmt = $this->conn->prepare("
                INSERT INTO rev_codes (rev_id, codigo, dns_real, status)
                VALUES (?, ?, ?, 'ativo')
            ");
            
            $stmt->bind_param("iss", $rev_id, $codigo, $dns_real);
            
            if (!$stmt->execute()) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Erro ao criar código DNS'
                ];
            }
            
            $code_id = $this->conn->insert_id;
            $stmt->close();
            
            // Log
            logAction('criar_codigo_dns', Auth::getUserId(), Auth::getUserType(), "Código DNS criado: $codigo (ID: $code_id)");
            
            return [
                'success' => true,
                'message' => 'Código DNS criado com sucesso',
                'code_id' => $code_id,
                'codigo' => $codigo
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao criar código DNS: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao criar código DNS'
            ];
        }
    }
    
    /**
     * Listar códigos DNS de um revendedor
     */
    public function listar($rev_id) {
        try {
            // Verificar permissão
            if (Auth::isRevAuthenticated() && $_SESSION['rev_id'] != $rev_id) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado',
                    'code' => 403
                ];
            }
            
            $stmt = $this->conn->prepare("
                SELECT 
                    rc.id, rc.rev_id, rc.codigo, rc.status, rc.created_at,
                    COUNT(DISTINCT a.id) as apps_conectados
                FROM rev_codes rc
                LEFT JOIN apps a ON rc.id = a.rev_code_id AND a.status = 'ativo'
                WHERE rc.rev_id = ?
                GROUP BY rc.id
                ORDER BY rc.created_at DESC
            ");
            
            $stmt->bind_param("i", $rev_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $codigos = [];
            while ($row = $result->fetch_assoc()) {
                // Nunca expor DNS real
                unset($row['dns_real']);
                $codigos[] = $row;
            }
            $stmt->close();
            
            return [
                'success' => true,
                'data' => $codigos
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao listar códigos DNS: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao listar códigos DNS'
            ];
        }
    }
    
    /**
     * Obter DNS real (apenas para validação interna)
     */
    public function obterDNSReal($codigo) {
        try {
            $stmt = $this->conn->prepare("SELECT dns_real, status FROM rev_codes WHERE codigo = ? AND status = 'ativo' LIMIT 1");
            $stmt->bind_param("s", $codigo);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                return null;
            }
            
            $row = $result->fetch_assoc();
            $stmt->close();
            
            return $row['dns_real'];
            
        } catch (Exception $e) {
            error_log('Erro ao obter DNS real: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Atualizar status do código DNS
     */
    public function atualizarStatus($code_id, $status) {
        try {
            if (!Auth::isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado',
                    'code' => 403
                ];
            }
            
            if (!in_array($status, ['ativo', 'inativo'])) {
                return [
                    'success' => false,
                    'message' => 'Status inválido'
                ];
            }
            
            $stmt = $this->conn->prepare("UPDATE rev_codes SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $code_id);
            
            if (!$stmt->execute()) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Erro ao atualizar código DNS'
                ];
            }
            
            $stmt->close();
            
            // Log
            logAction('atualizar_codigo_dns', Auth::getUserId(), 'admin', "Status do código atualizado para: $status (ID: $code_id)");
            
            return [
                'success' => true,
                'message' => 'Código DNS atualizado com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao atualizar código DNS: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao atualizar código DNS'
            ];
        }
    }
    
    /**
     * Deletar código DNS
     */
    public function deletar($code_id) {
        try {
            // Verificar permissão
            $stmt = $this->conn->prepare("SELECT rev_id FROM rev_codes WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $code_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Código DNS não encontrado'
                ];
            }
            
            $row = $result->fetch_assoc();
            $rev_id = $row['rev_id'];
            $stmt->close();
            
            // Verificar permissão
            if (Auth::isRevAuthenticated() && $_SESSION['rev_id'] != $rev_id) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado',
                    'code' => 403
                ];
            }
            
            // Deletar código
            $stmt = $this->conn->prepare("DELETE FROM rev_codes WHERE id = ?");
            $stmt->bind_param("i", $code_id);
            
            if (!$stmt->execute()) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Erro ao deletar código DNS'
                ];
            }
            
            $stmt->close();
            
            // Log
            logAction('deletar_codigo_dns', Auth::getUserId(), Auth::getUserType(), "Código DNS deletado (ID: $code_id)");
            
            return [
                'success' => true,
                'message' => 'Código DNS deletado com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao deletar código DNS: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao deletar código DNS'
            ];
        }
    }
}
?>
