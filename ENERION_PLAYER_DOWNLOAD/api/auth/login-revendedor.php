<?php
/**
 * Enerion Player - API de Login do Revendedor
 * 
 * Endpoint: POST /api/auth/login-revendedor
 * 
 * Parâmetros:
 *   - username (string): Nome de usuário do revendedor
 *   - password (string): Senha do revendedor
 * 
 * Resposta de Sucesso (200):
 * {
 *   "success": true,
 *   "message": "Login realizado com sucesso",
 *   "revendedor": {
 *     "id": 1,
 *     "username": "revendedor1",
 *     "nome": "Revendedor 1",
 *     "email": "revendedor1@example.com",
 *     "status": "ativo"
 *   },
 *   "token": "jwt_token_aqui"
 * }
 * 
 * Resposta de Erro (401):
 * {
 *   "success": false,
 *   "message": "Usuário ou senha inválidos"
 * }
 */

// Incluir configuração
require_once __DIR__ . '/../config/config.php';

// Definir header JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit;
}

// Obter dados JSON
$input = json_decode(file_get_contents('php://input'), true);

// Validar entrada
if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username e password são obrigatórios'
    ]);
    exit;
}

$username = trim($input['username']);
$password = trim($input['password']);

// Validar campos vazios
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username e password não podem estar vazios'
    ]);
    exit;
}

// Validar comprimento mínimo
if (strlen($username) < 3 || strlen($password) < 3) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username e password devem ter no mínimo 3 caracteres'
    ]);
    exit;
}

try {
    // Conectar ao banco de dados
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Verificar conexão
    if ($conn->connect_error) {
        throw new Exception('Erro de conexão: ' . $conn->connect_error);
    }
    
    // Preparar query
    $stmt = $conn->prepare('SELECT id, username, password, nome, email, status FROM revs WHERE username = ? LIMIT 1');
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar query: ' . $conn->error);
    }
    
    // Bind parameters
    $stmt->bind_param('s', $username);
    
    // Executar query
    if (!$stmt->execute()) {
        throw new Exception('Erro ao executar query: ' . $stmt->error);
    }
    
    // Obter resultado
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Usuário ou senha inválidos'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    // Obter dados do revendedor
    $revendedor = $result->fetch_assoc();
    
    // Verificar se está ativo
    if ($revendedor['status'] !== 'ativo') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Sua conta está inativa. Entre em contato com o administrador.'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    // Verificar senha com bcrypt
    if (!password_verify($password, $revendedor['password'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Usuário ou senha inválidos'
        ]);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    // Gerar token JWT (opcional, mas recomendado)
    $token = generateJWT([
        'id' => $revendedor['id'],
        'username' => $revendedor['username'],
        'type' => 'revendedor',
        'iat' => time(),
        'exp' => time() + (24 * 60 * 60) // Válido por 24 horas
    ]);
    
    // Atualizar último login
    $update_stmt = $conn->prepare('UPDATE revs SET ultimo_login = NOW() WHERE id = ?');
    if ($update_stmt) {
        $update_stmt->bind_param('i', $revendedor['id']);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    // Registrar login no log
    $log_stmt = $conn->prepare('INSERT INTO logs (tipo, descricao, usuario_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)');
    if ($log_stmt) {
        $tipo = 'LOGIN_REVENDEDOR';
        $descricao = 'Revendedor ' . $revendedor['username'] . ' fez login';
        $usuario_id = $revendedor['id'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $log_stmt->bind_param('ssiss', $tipo, $descricao, $usuario_id, $ip_address, $user_agent);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    // Fechar conexões
    $stmt->close();
    $conn->close();
    
    // Resposta de sucesso
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Login realizado com sucesso',
        'revendedor' => [
            'id' => (int)$revendedor['id'],
            'username' => $revendedor['username'],
            'nome' => $revendedor['nome'],
            'email' => $revendedor['email'],
            'status' => $revendedor['status']
        ],
        'token' => $token
    ]);
    
} catch (Exception $e) {
    // Log de erro
    error_log('Erro em login-revendedor.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao processar login. Tente novamente.'
    ]);
}

/**
 * Gerar token JWT
 * 
 * @param array $payload Dados do token
 * @return string Token JWT
 */
function generateJWT($payload) {
    $header = [
        'alg' => 'HS256',
        'typ' => 'JWT'
    ];
    
    $header_encoded = base64_encode(json_encode($header));
    $payload_encoded = base64_encode(json_encode($payload));
    
    $signature = hash_hmac(
        'sha256',
        $header_encoded . '.' . $payload_encoded,
        JWT_SECRET,
        true
    );
    $signature_encoded = base64_encode($signature);
    
    return $header_encoded . '.' . $payload_encoded . '.' . $signature_encoded;
}

?>
