<?php
/**
 * API de Logs - Admin
 * Auditoria de eventos do sistema
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
        // LISTAR LOGS
        // ============================================
        $limit = $_GET['limit'] ?? 100;
        $offset = $_GET['offset'] ?? 0;
        $tipo_filter = $_GET['tipo'] ?? null;
        
        // Validar limit
        $limit = min((int)$limit, 500);
        $offset = (int)$offset;
        
        $query = "SELECT 
                    id,
                    tipo,
                    descricao,
                    ip,
                    created_at
                  FROM logs";
        
        $params = [];
        $types = '';
        
        // Filtrar por tipo se fornecido
        if ($tipo_filter) {
            $query .= " WHERE tipo = ?";
            $params[] = $tipo_filter;
            $types .= 's';
        }
        
        $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception('Erro ao preparar query: ' . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao executar query: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $logs = [];
        
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        $stmt->close();
        
        respondSuccess($logs, 'Logs carregados com sucesso');
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
    
    $conn->close();
    
} catch (Exception $e) {
    error_log('Erro na API de logs: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao processar requisição',
        'error' => $e->getMessage()
    ]);
}
?>
