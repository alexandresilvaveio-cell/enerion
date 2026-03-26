<?php
/**
 * API de Estatísticas - Admin
 * Retorna estatísticas do sistema
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
    
    // ============================================
    // TOTAL DE REVENDEDORES
    // ============================================
    $result = $conn->query("SELECT COUNT(*) as count FROM revs");
    if (!$result) {
        throw new Exception('Erro ao contar revendedores: ' . $conn->error);
    }
    $row = $result->fetch_assoc();
    $total_revs = $row['count'] ?? 0;
    
    // ============================================
    // DISPOSITIVOS ONLINE
    // ============================================
    $timeout = 120;
    $result = $conn->query(
        "SELECT COUNT(*) as count FROM apps 
         WHERE (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(last_ping)) < $timeout"
    );
    if (!$result) {
        throw new Exception('Erro ao contar dispositivos: ' . $conn->error);
    }
    $row = $result->fetch_assoc();
    $devices_online = $row['count'] ?? 0;
    
    // ============================================
    // CÓDIGOS ATIVOS
    // ============================================
    $result = $conn->query("SELECT COUNT(*) as count FROM rev_codes WHERE status = 'ativo'");
    if (!$result) {
        throw new Exception('Erro ao contar códigos: ' . $conn->error);
    }
    $row = $result->fetch_assoc();
    $codigos_ativos = $row['count'] ?? 0;
    
    // ============================================
    // EVENTOS HOJE
    // ============================================
    $today = date('Y-m-d');
    $result = $conn->query(
        "SELECT COUNT(*) as count FROM logs 
         WHERE DATE(created_at) = ?"
    );
    
    if ($result) {
        $row = $result->fetch_assoc();
        $eventos_hoje = $row['count'] ?? 0;
    } else {
        // Fallback se prepared statement falhar
        $result = $conn->query(
            "SELECT COUNT(*) as count FROM logs 
             WHERE DATE(created_at) = '$today'"
        );
        $row = $result->fetch_assoc();
        $eventos_hoje = $row['count'] ?? 0;
    }
    
    $stats = [
        'total_revs' => (int)$total_revs,
        'devices_online' => (int)$devices_online,
        'codigos_ativos' => (int)$codigos_ativos,
        'eventos_hoje' => (int)$eventos_hoje
    ];
    
    respondSuccess($stats, 'Estatísticas carregadas com sucesso');
    
    $conn->close();
    
} catch (Exception $e) {
    error_log('Erro na API de estatísticas: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao processar requisição',
        'error' => $e->getMessage()
    ]);
}
?>
