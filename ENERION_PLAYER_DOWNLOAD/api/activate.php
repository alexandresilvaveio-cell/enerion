<?php
/**
 * Enerion Player - Endpoint de Ativação
 * 
 * POST /api/activate.php
 * 
 * Ativa dispositivos (TV) usando código de licença
 * Otimizado para <200ms de latência
 * 
 * Request:
 * {
 *   "codigo": "ENERION001",
 *   "device_id": "TV-ABC-123",
 *   "modelo": "Samsung 55\"",
 *   "plataforma": "tizen",
 *   "app_version": "1.0"
 * }
 * 
 * Response:
 * {
 *   "status": "ok",
 *   "dns": "http://servidoriptv.com",
 *   "message": "Dispositivo ativado com sucesso"
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
    
    // Validar campos obrigatórios
    $codigo = trim($input['codigo'] ?? '');
    $device_id = trim($input['device_id'] ?? '');
    $modelo = trim($input['modelo'] ?? '');
    $plataforma = trim($input['plataforma'] ?? 'outro');
    $app_version = trim($input['app_version'] ?? '1.0');
    
    // Validar comprimento
    if (strlen($codigo) < 3 || strlen($codigo) > 50) {
        respond('erro', 'Código inválido');
    }
    
    if (strlen($device_id) < 3 || strlen($device_id) > 100) {
        respond('erro', 'Device ID inválido');
    }
    
    // Obter IP do cliente
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Conectar ao banco
    $db = Database::getInstance();
    
    // ===== ETAPA 1: Validar código =====
    $codigo_data = $db->fetchOne(
        "SELECT id, rev_id, status, data_expiracao, max_dispositivos, dispositivos_ativos 
         FROM codigos 
         WHERE codigo = ? 
         LIMIT 1",
        [$codigo]
    );
    
    if (!$codigo_data) {
        // Log de erro
        $db->insert('ativacoes_log', [
            'device_id' => $device_id,
            'codigo' => $codigo,
            'rev_id' => 0,
            'ip_address' => $ip_address,
            'modelo' => $modelo,
            'resultado' => 'erro_codigo',
            'mensagem_erro' => 'Código não encontrado'
        ]);
        
        respond('erro', 'Código inválido ou não encontrado');
    }
    
    $codigo_id = $codigo_data['id'];
    $rev_id = $codigo_data['rev_id'];
    $status_codigo = $codigo_data['status'];
    $data_expiracao = $codigo_data['data_expiracao'];
    $max_dispositivos = $codigo_data['max_dispositivos'];
    $dispositivos_ativos = $codigo_data['dispositivos_ativos'];
    
    // ===== ETAPA 2: Validar status do código =====
    if ($status_codigo !== 'ativo') {
        $db->insert('ativacoes_log', [
            'device_id' => $device_id,
            'codigo' => $codigo,
            'rev_id' => $rev_id,
            'ip_address' => $ip_address,
            'modelo' => $modelo,
            'resultado' => 'erro_expirado',
            'mensagem_erro' => 'Código inativo ou expirado'
        ]);
        
        respond('erro', 'Código inativo ou expirado');
    }
    
    // ===== ETAPA 3: Validar expiração =====
    if ($data_expiracao && strtotime($data_expiracao) < time()) {
        // Marcar como expirado
        $db->update('codigos', ['status' => 'expirado'], 'id = ?', [$codigo_id]);
        
        $db->insert('ativacoes_log', [
            'device_id' => $device_id,
            'codigo' => $codigo,
            'rev_id' => $rev_id,
            'ip_address' => $ip_address,
            'modelo' => $modelo,
            'resultado' => 'erro_expirado',
            'mensagem_erro' => 'Código expirado'
        ]);
        
        respond('erro', 'Código expirado');
    }
    
    // ===== ETAPA 4: Verificar limite de dispositivos =====
    if ($dispositivos_ativos >= $max_dispositivos) {
        $db->insert('ativacoes_log', [
            'device_id' => $device_id,
            'codigo' => $codigo,
            'rev_id' => $rev_id,
            'ip_address' => $ip_address,
            'modelo' => $modelo,
            'resultado' => 'erro_limite',
            'mensagem_erro' => 'Limite de dispositivos atingido'
        ]);
        
        respond('erro', 'Limite de dispositivos atingido para este código');
    }
    
    // ===== ETAPA 5: Verificar se dispositivo já existe =====
    $dispositivo_existente = $db->fetchOne(
        "SELECT id, status FROM dispositivos WHERE device_id = ? LIMIT 1",
        [$device_id]
    );
    
    if ($dispositivo_existente) {
        // Atualizar dispositivo existente
        $db->update('dispositivos', [
            'ip_address' => $ip_address,
            'data_ultimo_ping' => date('Y-m-d H:i:s'),
            'online' => 1,
            'app_version' => $app_version
        ], 'id = ?', [$dispositivo_existente['id']]);
    } else {
        // Criar novo dispositivo
        $db->insert('dispositivos', [
            'device_id' => $device_id,
            'codigo_id' => $codigo_id,
            'rev_id' => $rev_id,
            'modelo' => $modelo,
            'plataforma' => $plataforma,
            'app_version' => $app_version,
            'ip_address' => $ip_address,
            'status' => 'ativo',
            'online' => 1,
            'data_ativacao' => date('Y-m-d H:i:s'),
            'data_ultimo_ping' => date('Y-m-d H:i:s')
        ]);
        
        // Incrementar contador de dispositivos ativos
        $db->update('codigos', [
            'dispositivos_ativos' => $dispositivos_ativos + 1,
            'ultimo_uso' => date('Y-m-d H:i:s')
        ], 'id = ?', [$codigo_id]);
    }
    
    // ===== ETAPA 6: Registrar ativação no log =====
    $db->insert('ativacoes_log', [
        'device_id' => $device_id,
        'codigo' => $codigo,
        'rev_id' => $rev_id,
        'ip_address' => $ip_address,
        'modelo' => $modelo,
        'resultado' => 'sucesso'
    ]);
    
    // ===== ETAPA 7: Atualizar estatísticas (cache) =====
    $total_online = $db->count(
        'dispositivos',
        'rev_id = ? AND online = 1',
        [$rev_id]
    );
    
    $db->update('estatisticas_revendedor', [
        'dispositivos_online' => $total_online,
        'data_atualizacao' => date('Y-m-d H:i:s')
    ], 'rev_id = ?', [$rev_id]);
    
    // ===== RESPOSTA DE SUCESSO =====
    respond('ok', 'Dispositivo ativado com sucesso', [
        'dns' => 'http://servidoriptv.com',
        'device_id' => $device_id,
        'codigo' => $codigo,
        'modelo' => $modelo
    ]);
    
} catch (Exception $e) {
    // Log de erro
    error_log('Erro em activate.php: ' . $e->getMessage());
    
    respond('erro', 'Erro ao processar ativação');
}

?>
