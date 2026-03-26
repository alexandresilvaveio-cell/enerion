<?php
/**
 * API de Dispositivos - Admin
 * Monitora e gerencia dispositivos
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

// Verificar autenticação
verificarAutenticacao();

try {
    $conn = getDBConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // ============================================
        // LISTAR DISPOSITIVOS
        // ============================================
        
        // Timeout para considerar offline (120 segundos)
        $timeout = 120;
        
        $query = "SELECT 
                    a.id,
                    a.device_id,
                    a.rev_id,
                    r.nome as rev_nome,
                    a.ip_address,
                    a.last_ping,
                    CASE 
                        WHEN (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(a.last_ping)) < $timeout THEN 'online'
                        ELSE 'offline'
                    END as status
                  FROM apps a
                  LEFT JOIN revs r ON a.rev_id = r.id
                  ORDER BY a.last_ping DESC";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception('Erro ao consultar dispositivos: ' . $conn->error);
        }
        
        $dispositivos = [];
        while ($row = $result->fetch_assoc()) {
            $dispositivos[] = $row;
        }
        
        respondSuccess($dispositivos, 'Dispositivos carregados com sucesso');
        
    } elseif ($method === 'POST') {
        // ============================================
        // CRIAR DISPOSITIVO (se necessário)
        // ============================================
        $input = json_decode(file_get_contents('php://input'), true);
        
        $device_id = $input['device_id'] ?? '';
        $rev_id = $input['rev_id'] ?? '';
        $ip_address = $input['ip_address'] ?? '';
        
        if (!$device_id || !$rev_id) {
            respondError('Device ID e Revendedor são obrigatórios', 400);
        }
        
        // Verificar se dispositivo já existe
        $check_stmt = $conn->prepare("SELECT id FROM apps WHERE device_id = ?");
        $check_stmt->bind_param("s", $device_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            respondError('Este dispositivo já existe', 400);
        }
        $check_stmt->close();
        
        // Inserir dispositivo
        $insert_stmt = $conn->prepare(
            "INSERT INTO apps (device_id, rev_id, ip_address, last_ping, created_at) 
             VALUES (?, ?, ?, NOW(), NOW())"
        );
        
        $insert_stmt->bind_param("sis", $device_id, $rev_id, $ip_address);
        
        if (!$insert_stmt->execute()) {
            throw new Exception('Erro ao criar dispositivo: ' . $insert_stmt->error);
        }
        
        $device_app_id = $conn->insert_id;
        $insert_stmt->close();
        
        respondSuccess(['id' => $device_app_id], 'Dispositivo criado com sucesso', 201);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log('Erro na API de dispositivos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao processar requisição',
        'error' => $e->getMessage()
    ]);
}
?>
