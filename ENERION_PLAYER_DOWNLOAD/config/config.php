<?php
/**
 * =====================================================
 * ENERION PLAYER - CONFIGURAÇÃO DO SISTEMA
 * =====================================================
 * Arquivo de configuração centralizado para o banco de dados
 * e constantes do sistema
 */

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'enerion_user');
define('DB_PASS', 'marabo01');
define('DB_NAME', 'enerion');
define('DB_CHARSET', 'utf8mb4');

// Configurações do Sistema
define('SYSTEM_NAME', 'Enerion Player');
define('SYSTEM_VERSION', '1.0.0');
define('SYSTEM_URL', 'https://enerionplayer.brujah.xyz');
define('SYSTEM_TIMEZONE', 'America/Sao_Paulo');

// Configurações de Segurança
define('JWT_SECRET', 'enerion_player_jwt_secret_2026_producao');
define('SESSION_TIMEOUT', 3600); // 1 hora em segundos
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos em segundos

// Configurações de CORS
define('ALLOWED_ORIGINS', [
    'https://enerionplayer.brujah.xyz',
    'http://localhost:3000',
    'http://localhost'
]);

// Configurações de Logging
define('LOG_ENABLED', true);
define('LOG_FILE', __DIR__ . '/../logs/system.log');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// Configurações de API
define('API_RATE_LIMIT', 100); // Requisições por minuto
define('API_TIMEOUT', 30); // Segundos

// Configurações de Dispositivos
define('DEVICE_PING_TIMEOUT', 120); // Segundos para considerar offline
define('MAX_DEVICES_PER_REV', 200); // Limite padrão de dispositivos

// Configurações de Email (opcional, para notificações futuras)
define('MAIL_ENABLED', false); // Ativar quando configurar SMTP
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USER', 'seu_email@gmail.com');
define('MAIL_PASS', 'sua_senha');
define('MAIL_FROM', 'noreply@enerionplayer.brujah.xyz');

// Função para conectar ao banco de dados
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception('Erro de conexão: ' . $conn->connect_error);
        }
        
        $conn->set_charset(DB_CHARSET);
        return $conn;
    } catch (Exception $e) {
        error_log('Erro ao conectar ao banco de dados: ' . $e->getMessage());
        die('Erro ao conectar ao banco de dados. Por favor, tente novamente mais tarde.');
    }
}

// Função para logging
function logAction($tipo, $usuario_id, $usuario_tipo, $descricao, $ip = null) {
    if (!LOG_ENABLED) return;
    
    $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? 'desconhecido';
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("INSERT INTO logs (tipo, usuario_id, usuario_tipo, descricao, ip) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisss", $tipo, $usuario_id, $usuario_tipo, $descricao, $ip);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// Definir timezone
date_default_timezone_set(SYSTEM_TIMEZONE);

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Habilitar CORS
header('Access-Control-Allow-Origin: ' . (in_array($_SERVER['HTTP_ORIGIN'] ?? '', ALLOWED_ORIGINS) ? $_SERVER['HTTP_ORIGIN'] : ALLOWED_ORIGINS[0]));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Tratar requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Função para responder com JSON
function respondJSON($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Função para responder com erro
function respondError($message, $statusCode = 400, $details = null) {
    $response = [
        'success' => false,
        'message' => $message,
        'error' => true
    ];
    
    if ($details) {
        $response['details'] = $details;
    }
    
    respondJSON($response, $statusCode);
}

// Função para responder com sucesso
function respondSuccess($data = null, $message = 'Operação realizada com sucesso', $statusCode = 200) {
    $response = [
        'success' => true,
        'message' => $message,
        'error' => false
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    respondJSON($response, $statusCode);
}
?>
