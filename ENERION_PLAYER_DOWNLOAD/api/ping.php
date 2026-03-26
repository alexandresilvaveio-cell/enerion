<?php
/**
 * Enerion Player - Endpoint de Ping
 * 
 * POST /api/ping.php
 * 
 * Atualiza status de dispositivo (online/offline)
 * Otimizado para <100ms de latência
 * 
 * Request:
 * {
 *   "device_id": "TV-ABC-123"
 * }
 * 
 * Response:
 * {
 *   "status": "ok",
 *   "message": "Ping recebido"
 * }
 */

// Incluir configuração e classes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Tratar OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'erro',
        'message' => 'Método não permitido'
    ]);
    exit;
}

// Função para responder rapidamente
function respond($status, $message, $data = []) {
    $response = [
        'status' => $status,
        'message' => $message,
        'timestamp' => time()
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    http_response_code($status === 'ok' ? 200 : 400);
    echo json_encode($response);
    exit;
}

try {
    // Obter dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        respond('erro', 'Dados inválidos');
    }
    
    // Validar campo obrigatório
    $device_id = trim($input['device_id'] ?? '');
    
    if (strlen($device_id) < 3 || strlen($device_id) > 100) {
        respond('erro', 'Device ID inválido');
    }
    
    // Obter IP do cliente
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Conectar ao banco
    $db = Database::getInstance();
    
    // ===== ETAPA 1: Buscar dispositivo =====
    $dispositivo = $db->fetchOne(
        "SELECT id, rev_id, status FROM dispositivos WHERE device_id = ? LIMIT 1",
        [$device_id]
    );
    
    if (!$dispositivo) {
        respond('erro', 'Dispositivo não encontrado');
    }
    
    $dispositivo_id = $dispositivo['id'];
    $rev_id = $dispositivo['rev_id'];
    
    // ===== ETAPA 2: Atualizar status do dispositivo =====
    $db->update('dispositivos', [
        'ip_address' => $ip_address,
        'data_ultimo_ping' => date('Y-m-d H:i:s'),
        'online' => 1
    ], 'id = ?', [$dispositivo_id]);
    
    // ===== ETAPA 3: Registrar ping no log (opcional, pode ser assíncrono) =====
    // Para performance, isso pode ser feito de forma assíncrona
    // Aqui fazemos de forma síncrona para simplicidade
    $db->insert('pings_log', [
        'device_id' => $device_id,
        'rev_id' => $rev_id,
        'ip_address' => $ip_address,
        'data_ping' => date('Y-m-d H:i:s')
    ]);
    
    // ===== RESPOSTA DE SUCESSO =====
    respond('ok', 'Ping recebido com sucesso', [
        'device_id' => $device_id,
        'online' => true
    ]);
    
} catch (Exception $e) {
    // Log de erro
    error_log('Erro em ping.php: ' . $e->getMessage());
    
    respond('erro', 'Erro ao processar ping');
}

?>
