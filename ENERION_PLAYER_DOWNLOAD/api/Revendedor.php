<?php
/**
 * =====================================================
 * ENERION PLAYER - CLASSE DE GERENCIAMENTO DE REVENDEDORES
 * =====================================================
 * Gerencia CRUD de revendedores e seus códigos DNS
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Auth.php';

class Revendedor {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Criar novo revendedor (apenas Admin)
     */
    public function criar($dados) {
        try {
            if (!Auth::isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado. Apenas administradores podem criar revendedores.',
                    'code' => 403
                ];
            }
            
            // Validar dados obrigatórios
            $campos_obrigatorios = ['nome', 'username', 'password', 'telefone'];
            foreach ($campos_obrigatorios as $campo) {
                if (empty($dados[$campo] ?? null)) {
                    return [
                        'success' => false,
                        'message' => "Campo obrigatório ausente: $campo"
                    ];
                }
            }
            
            // Verificar se username já existe
            $stmt = $this->conn->prepare("SELECT id FROM revs WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $dados['username']);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Este nome de usuário já está em uso'
                ];
            }
            $stmt->close();
            
            // Preparar dados
            $nome = $dados['nome'];
            $telefone = $dados['telefone'];
            $username = $dados['username'];
            $password = Auth::hashPassword($dados['password']);
            $email = $dados['email'] ?? null;
            $max_apps = $dados['max_apps'] ?? 100;
            $valor_por_app = $dados['valor_por_app'] ?? 0.00;
            $dia_pagamento = $dados['dia_pagamento'] ?? 1;
            $data_expiracao = $dados['data_expiracao'] ?? null;
            
            // Inserir revendedor
            $stmt = $this->conn->prepare("
                INSERT INTO revs (nome, telefone, username, password, email, max_apps, valor_por_app, dia_pagamento, data_expiracao, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ativo')
            ");
            
            $stmt->bind_param("sssssidis", $nome, $telefone, $username, $password, $email, $max_apps, $valor_por_app, $dia_pagamento, $data_expiracao);
            
            if (!$stmt->execute()) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Erro ao criar revendedor: ' . $this->conn->error
                ];
            }
            
            $rev_id = $this->conn->insert_id;
            $stmt->close();
            
            // Log
            logAction('criar_revendedor', Auth::getUserId(), 'admin', "Revendedor criado: $username (ID: $rev_id)");
            
            return [
                'success' => true,
                'message' => 'Revendedor criado com sucesso',
                'rev_id' => $rev_id,
                'username' => $username
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao criar revendedor: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao criar revendedor'
            ];
        }
    }
    
    /**
     * Listar todos os revendedores (apenas Admin)
     */
    public function listar($pagina = 1, $por_pagina = 20) {
        try {
            if (!Auth::isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado',
                    'code' => 403
                ];
            }
            
            $offset = ($pagina - 1) * $por_pagina;
            
            // Contar total
            $result = $this->conn->query("SELECT COUNT(*) as total FROM revs");
            $total = $result->fetch_assoc()['total'];
            
            // Buscar revendedores
            $stmt = $this->conn->prepare("
                SELECT 
                    r.id, r.nome, r.telefone, r.username, r.email, r.status,
                    r.max_apps, r.valor_por_app, r.dia_pagamento, r.data_expiracao,
                    r.created_at,
                    COUNT(rc.id) as total_codigos,
                    COUNT(DISTINCT a.id) as apps_ativos
                FROM revs r
                LEFT JOIN rev_codes rc ON r.id = rc.rev_id
                LEFT JOIN apps a ON r.id = a.rev_id AND a.status = 'ativo'
                GROUP BY r.id
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->bind_param("ii", $por_pagina, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $revendedores = [];
            while ($row = $result->fetch_assoc()) {
                $revendedores[] = $row;
            }
            $stmt->close();
            
            return [
                'success' => true,
                'data' => $revendedores,
                'total' => $total,
                'pagina' => $pagina,
                'por_pagina' => $por_pagina,
                'total_paginas' => ceil($total / $por_pagina)
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao listar revendedores: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao listar revendedores'
            ];
        }
    }
    
    /**
     * Obter detalhes de um revendedor
     */
    public function obter($rev_id) {
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
                    r.id, r.nome, r.telefone, r.username, r.email, r.status,
                    r.max_apps, r.valor_por_app, r.dia_pagamento, r.data_expiracao,
                    r.created_at, r.updated_at,
                    COUNT(rc.id) as total_codigos,
                    COUNT(DISTINCT a.id) as apps_ativos
                FROM revs r
                LEFT JOIN rev_codes rc ON r.id = rc.rev_id
                LEFT JOIN apps a ON r.id = a.rev_id AND a.status = 'ativo'
                WHERE r.id = ?
                GROUP BY r.id
            ");
            
            $stmt->bind_param("i", $rev_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Revendedor não encontrado'
                ];
            }
            
            $revendedor = $result->fetch_assoc();
            $stmt->close();
            
            return [
                'success' => true,
                'data' => $revendedor
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao obter revendedor: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao obter revendedor'
            ];
        }
    }
    
    /**
     * Atualizar revendedor (apenas Admin)
     */
    public function atualizar($rev_id, $dados) {
        try {
            if (!Auth::isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado',
                    'code' => 403
                ];
            }
            
            // Verificar se revendedor existe
            $stmt = $this->conn->prepare("SELECT id FROM revs WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $rev_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Revendedor não encontrado'
                ];
            }
            $stmt->close();
            
            // Preparar atualização
            $campos = [];
            $tipos = '';
            $valores = [];
            
            $campos_permitidos = ['nome', 'telefone', 'email', 'status', 'max_apps', 'valor_por_app', 'dia_pagamento', 'data_expiracao'];
            
            foreach ($campos_permitidos as $campo) {
                if (isset($dados[$campo])) {
                    $campos[] = "$campo = ?";
                    $valores[] = $dados[$campo];
                    
                    if ($campo === 'max_apps') {
                        $tipos .= 'i';
                    } elseif ($campo === 'valor_por_app') {
                        $tipos .= 'd';
                    } elseif ($campo === 'dia_pagamento') {
                        $tipos .= 'i';
                    } else {
                        $tipos .= 's';
                    }
                }
            }
            
            if (empty($campos)) {
                return [
                    'success' => false,
                    'message' => 'Nenhum campo para atualizar'
                ];
            }
            
            $valores[] = $rev_id;
            $tipos .= 'i';
            
            $sql = "UPDATE revs SET " . implode(', ', $campos) . " WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($tipos, ...$valores);
            
            if (!$stmt->execute()) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Erro ao atualizar revendedor'
                ];
            }
            
            $stmt->close();
            
            // Log
            logAction('atualizar_revendedor', Auth::getUserId(), 'admin', "Revendedor atualizado: ID $rev_id");
            
            return [
                'success' => true,
                'message' => 'Revendedor atualizado com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao atualizar revendedor: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao atualizar revendedor'
            ];
        }
    }
    
    /**
     * Deletar revendedor (apenas Admin)
     */
    public function deletar($rev_id) {
        try {
            if (!Auth::isAdminAuthenticated()) {
                return [
                    'success' => false,
                    'message' => 'Acesso negado',
                    'code' => 403
                ];
            }
            
            // Verificar se revendedor existe
            $stmt = $this->conn->prepare("SELECT username FROM revs WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $rev_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Revendedor não encontrado'
                ];
            }
            
            $rev = $result->fetch_assoc();
            $stmt->close();
            
            // Deletar revendedor (cascata deleta códigos e apps)
            $stmt = $this->conn->prepare("DELETE FROM revs WHERE id = ?");
            $stmt->bind_param("i", $rev_id);
            
            if (!$stmt->execute()) {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'Erro ao deletar revendedor'
                ];
            }
            
            $stmt->close();
            
            // Log
            logAction('deletar_revendedor', Auth::getUserId(), 'admin', "Revendedor deletado: {$rev['username']} (ID: $rev_id)");
            
            return [
                'success' => true,
                'message' => 'Revendedor deletado com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log('Erro ao deletar revendedor: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao deletar revendedor'
            ];
        }
    }
}
?>
