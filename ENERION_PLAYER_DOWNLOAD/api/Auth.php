<?php
/**
 * =====================================================
 * ENERION PLAYER - CLASSE DE AUTENTICAÇÃO
 * =====================================================
 * Gerencia login, logout e validação de sessões
 */

require_once __DIR__ . '/../config/config.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Fazer login como Admin
     */
    public function loginAdmin($username, $password) {
        try {
            // Validar entrada
            if (empty($username) || empty($password)) {
                return [
                    'success' => false,
                    'message' => 'Usuário e senha são obrigatórios'
                ];
            }
            
            // Buscar admin
            $stmt = $this->conn->prepare("SELECT id, username, password, status FROM admins WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                logAction('login_falho', null, 'admin', 'Usuário não encontrado: ' . $username);
                return [
                    'success' => false,
                    'message' => 'Usuário ou senha incorretos'
                ];
            }
            
            $admin = $result->fetch_assoc();
            $stmt->close();
            
            // Verificar status
            if ($admin['status'] !== 'ativo') {
                logAction('login_bloqueado', $admin['id'], 'admin', 'Conta inativa');
                return [
                    'success' => false,
                    'message' => 'Sua conta está inativa'
                ];
            }
            
            // Verificar senha
            if (!password_verify($password, $admin['password'])) {
                logAction('login_falho', $admin['id'], 'admin', 'Senha incorreta');
                return [
                    'success' => false,
                    'message' => 'Usuário ou senha incorretos'
                ];
            }
            
            // Criar sessão
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['user_type'] = 'admin';
            $_SESSION['login_time'] = time();
            
            // Log de sucesso
            logAction('login_sucesso', $admin['id'], 'admin', 'Login realizado');
            
            return [
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'admin_id' => $admin['id'],
                'username' => $admin['username']
            ];
            
        } catch (Exception $e) {
            error_log('Erro no login do admin: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao realizar login'
            ];
        }
    }
    
    /**
     * Fazer login como Revendedor
     */
    public function loginRev($username, $password) {
        try {
            // Validar entrada
            if (empty($username) || empty($password)) {
                return [
                    'success' => false,
                    'message' => 'Usuário e senha são obrigatórios'
                ];
            }
            
            // Buscar revendedor
            $stmt = $this->conn->prepare("SELECT id, username, password, status, nome, max_apps, data_expiracao FROM revs WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                logAction('login_falho', null, 'rev', 'Revendedor não encontrado: ' . $username);
                return [
                    'success' => false,
                    'message' => 'Usuário ou senha incorretos'
                ];
            }
            
            $rev = $result->fetch_assoc();
            $stmt->close();
            
            // Verificar status
            if ($rev['status'] !== 'ativo') {
                logAction('login_bloqueado', $rev['id'], 'rev', 'Conta inativa');
                return [
                    'success' => false,
                    'message' => 'Sua conta está inativa'
                ];
            }
            
            // Verificar expiração
            if ($rev['data_expiracao'] && strtotime($rev['data_expiracao']) < time()) {
                logAction('login_expirado', $rev['id'], 'rev', 'Conta expirada');
                return [
                    'success' => false,
                    'message' => 'Sua conta expirou'
                ];
            }
            
            // Verificar senha
            if (!password_verify($password, $rev['password'])) {
                logAction('login_falho', $rev['id'], 'rev', 'Senha incorreta');
                return [
                    'success' => false,
                    'message' => 'Usuário ou senha incorretos'
                ];
            }
            
            // Criar sessão
            $_SESSION['rev_id'] = $rev['id'];
            $_SESSION['rev_username'] = $rev['username'];
            $_SESSION['rev_nome'] = $rev['nome'];
            $_SESSION['user_type'] = 'rev';
            $_SESSION['login_time'] = time();
            
            // Log de sucesso
            logAction('login_sucesso', $rev['id'], 'rev', 'Login realizado');
            
            return [
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'rev_id' => $rev['id'],
                'username' => $rev['username'],
                'nome' => $rev['nome'],
                'max_apps' => $rev['max_apps']
            ];
            
        } catch (Exception $e) {
            error_log('Erro no login do revendedor: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao realizar login'
            ];
        }
    }
    
    /**
     * Fazer logout
     */
    public function logout() {
        try {
            $user_type = $_SESSION['user_type'] ?? null;
            $user_id = $_SESSION['admin_id'] ?? $_SESSION['rev_id'] ?? null;
            
            if ($user_id) {
                logAction('logout', $user_id, $user_type, 'Logout realizado');
            }
            
            session_destroy();
            
            return [
                'success' => true,
                'message' => 'Logout realizado com sucesso'
            ];
            
        } catch (Exception $e) {
            error_log('Erro no logout: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao fazer logout'
            ];
        }
    }
    
    /**
     * Verificar se está autenticado como Admin
     */
    public static function isAdminAuthenticated() {
        return isset($_SESSION['admin_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
    }
    
    /**
     * Verificar se está autenticado como Revendedor
     */
    public static function isRevAuthenticated() {
        return isset($_SESSION['rev_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'rev';
    }
    
    /**
     * Verificar se está autenticado (Admin ou Revendedor)
     */
    public static function isAuthenticated() {
        return self::isAdminAuthenticated() || self::isRevAuthenticated();
    }
    
    /**
     * Obter ID do usuário autenticado
     */
    public static function getUserId() {
        if (self::isAdminAuthenticated()) {
            return $_SESSION['admin_id'];
        } elseif (self::isRevAuthenticated()) {
            return $_SESSION['rev_id'];
        }
        return null;
    }
    
    /**
     * Obter tipo de usuário autenticado
     */
    public static function getUserType() {
        return $_SESSION['user_type'] ?? null;
    }
    
    /**
     * Verificar timeout de sessão
     */
    public static function checkSessionTimeout() {
        if (isset($_SESSION['login_time'])) {
            if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
                session_destroy();
                return false;
            }
            // Renovar tempo de sessão
            $_SESSION['login_time'] = time();
            return true;
        }
        return false;
    }
    
    /**
     * Hash de senha usando bcrypt
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }
    
    /**
     * Gerar token JWT simples
     */
    public static function generateToken($data) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($data);
        $signature = hash_hmac('sha256', base64_encode($header) . '.' . base64_encode($payload), JWT_SECRET);
        
        return base64_encode($header) . '.' . base64_encode($payload) . '.' . base64_encode($signature);
    }
    
    /**
     * Validar token JWT simples
     */
    public static function validateToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        $signature = hash_hmac('sha256', $parts[0] . '.' . $parts[1], JWT_SECRET);
        
        if (!hash_equals(base64_encode($signature), $parts[2])) {
            return false;
        }
        
        $payload = json_decode(base64_decode($parts[1]), true);
        return $payload;
    }
}
?>
