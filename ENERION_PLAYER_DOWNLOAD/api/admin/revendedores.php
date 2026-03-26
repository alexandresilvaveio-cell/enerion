<?php
/**
 * API de Revendedores - Admin
 * Gerencia CRUD de revendedores
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Tratar requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../../config/config.php';

// Função para verificar autenticação
function verificarAutenticacao() {
    // Verificar header Authorization
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token não fornecido']);
        exit;
    }
    
    // Extrair token
    $auth_header = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $auth_header);
    
    // Validar token (por enquanto apenas verificar se não está vazio)
    // Em produção, validar contra JWT ou sessão
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit;
    }
    
    return $token;
}

// Verificar autenticação
verificarAutenticacao();

try {
    $conn = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // ============================================
        // LISTAR REVENDEDORES
        // ============================================
        $query = "SELECT 
                    r.id, 
                    r.nome, 
                    r.username, 
                    r.email, 
                    r.telefone, 
                    r.status,
                    COUNT(DISTINCT a.id) as apps_count,
                    COUNT(DISTINCT c.id) as codigos_count
                  FROM revs r
                  LEFT JOIN apps a ON r.id = a.rev_id
                  LEFT JOIN rev_codes c ON r.id = c.rev_id
                  GROUP BY r.id
                  ORDER BY r.created_at DESC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Erro ao consultar revendedores: ' . $conn->error);
        }
        
        $revendedores = [];
        while ($row = $result->fetch_assoc()) {
            $revendedores[] = $row;
        }
        
        respondSuccess($revendedores, 'Revendedores carregados com sucesso');
        
    } elseif ($method === 'POST') {
        // ============================================
        // CRIAR REVENDEDOR
        // ============================================
        $input = json_decode(file_get_contents('php://input'), true);
        
        $nome = $input['nome'] ?? '';
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        $telefone = $input['telefone'] ?? '';
        $email = $input['email'] ?? '';
        $max_apps = $input['max_apps'] ?? 100;
        $status = $input['status'] ?? 'ativo';
        
        // Validar campos obrigatórios
        if (!$nome || !$username || !$password) {
            respondError('Nome, usuário e senha são obrigatórios', 400);
        }
        
        // Verificar se usuário já existe
        $check_stmt = $conn->prepare("SELECT id FROM revs WHERE username = ?");
        if (!$check_stmt) {
            throw new Exception('Erro ao preparar query: ' . $conn->error);
        }
        
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            respondError('Este usuário já existe', 400);
        }
        $check_stmt->close();
        
        // Hash da senha
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        // Inserir revendedor
        $insert_stmt = $conn->prepare(
            "INSERT INTO revs (nome, username, password, email, telefone, max_apps, status, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        
        if (!$insert_stmt) {
            throw new Exception('Erro ao preparar insert: ' . $conn->error);
        }
        
        $insert_stmt->bind_param("sssssds", $nome, $username, $password_hash, $email, $telefone, $max_apps, $status);
        
        if (!$insert_stmt->execute()) {
            throw new Exception('Erro ao criar revendedor: ' . $insert_stmt->error);
        }
        
        $rev_id = $conn->insert_id;
        $insert_stmt->close();
        
        // Registrar log
        $log_stmt = $conn->prepare(
            "INSERT INTO logs (tipo, descricao, ip, created_at) VALUES (?, ?, ?, NOW())"
        );
        
        if ($log_stmt) {
            $tipo = 'CREATE_REV';
            $descricao = "Revendedor $nome criado";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
            $log_stmt->bind_param("sss", $tipo, $descricao, $ip);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        respondSuccess(['id' => $rev_id], 'Revendedor criado com sucesso', 201);
        
    } elseif ($method === 'DELETE') {
        // ============================================
        // DELETAR REVENDEDOR
        // ============================================
        $rev_id = $_GET['id'] ?? null;
        
        if (!$rev_id) {
            respondError('ID do revendedor não fornecido', 400);
        }
        
        // Obter nome do revendedor
        $get_stmt = $conn->prepare("SELECT nome FROM revs WHERE id = ?");
        if (!$get_stmt) {
            throw new Exception('Erro ao preparar query: ' . $conn->error);
        }
        
        $get_stmt->bind_param("i", $rev_id);
        $get_stmt->execute();
        $get_result = $get_stmt->get_result();
        $rev = $get_result->fetch_assoc();
        $get_stmt->close();
        
        if (!$rev) {
            respondError('Revendedor não encontrado', 404);
        }
        
        // Deletar revendedor
        $delete_stmt = $conn->prepare("DELETE FROM revs WHERE id = ?");
        if (!$delete_stmt) {
            throw new Exception('Erro ao preparar delete: ' . $conn->error);
        }
        
        $delete_stmt->bind_param("i", $rev_id);
        
        if (!$delete_stmt->execute()) {
            throw new Exception('Erro ao deletar revendedor: ' . $delete_stmt->error);
        }
        
        $delete_stmt->close();
        
        // Registrar log
        $log_stmt = $conn->prepare(
            "INSERT INTO logs (tipo, descricao, ip, created_at) VALUES (?, ?, ?, NOW())"
        );
        
        if ($log_stmt) {
            $tipo = 'DELETE_REV';
            $descricao = "Revendedor " . $rev['nome'] . " deletado";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
            $log_stmt->bind_param("sss", $tipo, $descricao, $ip);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        respondSuccess(null, 'Revendedor deletado com sucesso');
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log('Erro na API de revendedores: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao processar requisição',
        'error' => $e->getMessage()
    ]);
}
?>
