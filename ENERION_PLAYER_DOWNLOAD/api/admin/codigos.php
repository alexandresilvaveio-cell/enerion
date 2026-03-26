<?php
/**
 * API de Códigos DNS - Admin
 * Gerencia códigos de ativação
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
    $headers = getallheaders();
    
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token não fornecido']);
        exit;
    }
    
    $auth_header = $headers['Authorization'];
    $token = str_replace('Bearer ', '', $auth_header);
    
    if (empty($token)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit;
    }
    
    return $token;
}

// Função para gerar código único
function gerarCodigo($conn) {
    do {
        $codigo = strtoupper(substr(md5(uniqid()), 0, 8));
        $check = $conn->prepare("SELECT id FROM rev_codes WHERE codigo = ?");
        $check->bind_param("s", $codigo);
        $check->execute();
    } while ($check->get_result()->num_rows > 0);
    
    return $codigo;
}

// Verificar autenticação
verificarAutenticacao();

try {
    $conn = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // ============================================
        // LISTAR CÓDIGOS DNS
        // ============================================
        $query = "SELECT 
                    c.id,
                    c.codigo,
                    c.rev_id,
                    r.nome as rev_nome,
                    c.status,
                    COUNT(DISTINCT a.id) as devices_count,
                    c.created_at
                  FROM rev_codes c
                  LEFT JOIN revs r ON c.rev_id = r.id
                  LEFT JOIN apps a ON c.id = a.codigo_id
                  GROUP BY c.id
                  ORDER BY c.created_at DESC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Erro ao consultar códigos: ' . $conn->error);
        }
        
        $codigos = [];
        while ($row = $result->fetch_assoc()) {
            $codigos[] = $row;
        }
        
        respondSuccess($codigos, 'Códigos carregados com sucesso');
        
    } elseif ($method === 'POST') {
        // ============================================
        // CRIAR CÓDIGO DNS
        // ============================================
        $input = json_decode(file_get_contents('php://input'), true);
        
        $rev_id = $input['rev_id'] ?? '';
        $status = $input['status'] ?? 'ativo';
        
        if (!$rev_id) {
            respondError('Revendedor é obrigatório', 400);
        }
        
        // Verificar se revendedor existe
        $check_stmt = $conn->prepare("SELECT id FROM revs WHERE id = ?");
        $check_stmt->bind_param("i", $rev_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            respondError('Revendedor não encontrado', 404);
        }
        $check_stmt->close();
        
        // Gerar código único
        $codigo = gerarCodigo($conn);
        
        // Inserir código
        $insert_stmt = $conn->prepare(
            "INSERT INTO rev_codes (codigo, rev_id, status, created_at) 
             VALUES (?, ?, ?, NOW())"
        );
        
        if (!$insert_stmt) {
            throw new Exception('Erro ao preparar insert: ' . $conn->error);
        }
        
        $insert_stmt->bind_param("sis", $codigo, $rev_id, $status);
        
        if (!$insert_stmt->execute()) {
            throw new Exception('Erro ao criar código: ' . $insert_stmt->error);
        }
        
        $codigo_id = $conn->insert_id;
        $insert_stmt->close();
        
        // Registrar log
        $log_stmt = $conn->prepare(
            "INSERT INTO logs (tipo, descricao, ip, created_at) VALUES (?, ?, ?, NOW())"
        );
        
        if ($log_stmt) {
            $tipo = 'CREATE_CODIGO';
            $descricao = "Código DNS $codigo criado";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
            $log_stmt->bind_param("sss", $tipo, $descricao, $ip);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        respondSuccess(['id' => $codigo_id, 'codigo' => $codigo], 'Código criado com sucesso', 201);
        
    } elseif ($method === 'DELETE') {
        // ============================================
        // DELETAR CÓDIGO DNS
        // ============================================
        $codigo_id = $_GET['id'] ?? null;
        
        if (!$codigo_id) {
            respondError('ID do código não fornecido', 400);
        }
        
        // Obter código
        $get_stmt = $conn->prepare("SELECT codigo FROM rev_codes WHERE id = ?");
        if (!$get_stmt) {
            throw new Exception('Erro ao preparar query: ' . $conn->error);
        }
        
        $get_stmt->bind_param("i", $codigo_id);
        $get_stmt->execute();
        $get_result = $get_stmt->get_result();
        $codigo_row = $get_result->fetch_assoc();
        $get_stmt->close();
        
        if (!$codigo_row) {
            respondError('Código não encontrado', 404);
        }
        
        // Deletar código
        $delete_stmt = $conn->prepare("DELETE FROM rev_codes WHERE id = ?");
        if (!$delete_stmt) {
            throw new Exception('Erro ao preparar delete: ' . $conn->error);
        }
        
        $delete_stmt->bind_param("i", $codigo_id);
        
        if (!$delete_stmt->execute()) {
            throw new Exception('Erro ao deletar código: ' . $delete_stmt->error);
        }
        
        $delete_stmt->close();
        
        // Registrar log
        $log_stmt = $conn->prepare(
            "INSERT INTO logs (tipo, descricao, ip, created_at) VALUES (?, ?, ?, NOW())"
        );
        
        if ($log_stmt) {
            $tipo = 'DELETE_CODIGO';
            $descricao = "Código DNS " . $codigo_row['codigo'] . " deletado";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
            $log_stmt->bind_param("sss", $tipo, $descricao, $ip);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        respondSuccess(null, 'Código deletado com sucesso');
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log('Erro na API de códigos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao processar requisição',
        'error' => $e->getMessage()
    ]);
}
?>
