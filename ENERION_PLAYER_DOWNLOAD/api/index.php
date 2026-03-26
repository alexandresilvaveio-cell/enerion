<?php
/**
 * =====================================================
 * ENERION PLAYER - API REST
 * =====================================================
 * Endpoints principais da API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Revendedor.php';
require_once __DIR__ . '/CodigoDNS.php';
require_once __DIR__ . '/Dispositivo.php';

// Obter método e rota
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$path = trim($path, '/');

// Decodificar JSON do corpo da requisição
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// =====================================================
// ROTAS DE AUTENTICAÇÃO
// =====================================================

if ($path === 'auth/login-admin' && $method === 'POST') {
    $auth = new Auth();
    $result = $auth->loginAdmin($input['username'] ?? '', $input['password'] ?? '');
    respondJSON($result, $result['success'] ? 200 : 401);
}

if ($path === 'auth/login-rev' && $method === 'POST') {
    $auth = new Auth();
    $result = $auth->loginRev($input['username'] ?? '', $input['password'] ?? '');
    respondJSON($result, $result['success'] ? 200 : 401);
}

if ($path === 'auth/logout' && $method === 'POST') {
    Auth::checkSessionTimeout();
    $auth = new Auth();
    $result = $auth->logout();
    respondJSON($result, 200);
}

if ($path === 'auth/me' && $method === 'GET') {
    Auth::checkSessionTimeout();
    
    if (Auth::isAdminAuthenticated()) {
        respondSuccess([
            'user_id' => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_username'],
            'user_type' => 'admin'
        ]);
    } elseif (Auth::isRevAuthenticated()) {
        respondSuccess([
            'user_id' => $_SESSION['rev_id'],
            'username' => $_SESSION['rev_username'],
            'nome' => $_SESSION['rev_nome'],
            'user_type' => 'rev'
        ]);
    } else {
        respondError('Não autenticado', 401);
    }
}

// =====================================================
// ROTAS DE REVENDEDORES (ADMIN)
// =====================================================

if ($path === 'admin/revendedores' && $method === 'GET') {
    Auth::checkSessionTimeout();
    $revendedor = new Revendedor();
    $pagina = $_GET['pagina'] ?? 1;
    $result = $revendedor->listar($pagina);
    respondJSON($result, $result['success'] ? 200 : 403);
}

if ($path === 'admin/revendedores' && $method === 'POST') {
    Auth::checkSessionTimeout();
    $revendedor = new Revendedor();
    $result = $revendedor->criar($input);
    respondJSON($result, $result['success'] ? 201 : 400);
}

if (preg_match('/^admin\/revendedores\/(\d+)$/', $path, $matches) && $method === 'GET') {
    Auth::checkSessionTimeout();
    $rev_id = $matches[1];
    $revendedor = new Revendedor();
    $result = $revendedor->obter($rev_id);
    respondJSON($result, $result['success'] ? 200 : 404);
}

if (preg_match('/^admin\/revendedores\/(\d+)$/', $path, $matches) && $method === 'PUT') {
    Auth::checkSessionTimeout();
    $rev_id = $matches[1];
    $revendedor = new Revendedor();
    $result = $revendedor->atualizar($rev_id, $input);
    respondJSON($result, $result['success'] ? 200 : 400);
}

if (preg_match('/^admin\/revendedores\/(\d+)$/', $path, $matches) && $method === 'DELETE') {
    Auth::checkSessionTimeout();
    $rev_id = $matches[1];
    $revendedor = new Revendedor();
    $result = $revendedor->deletar($rev_id);
    respondJSON($result, $result['success'] ? 200 : 400);
}

// =====================================================
// ROTAS DE CÓDIGOS DNS
// =====================================================

if (preg_match('/^(admin|rev)\/revendedores\/(\d+)\/codigos$/', $path, $matches) && $method === 'GET') {
    Auth::checkSessionTimeout();
    $rev_id = $matches[2];
    $codigoDNS = new CodigoDNS();
    $result = $codigoDNS->listar($rev_id);
    respondJSON($result, $result['success'] ? 200 : 403);
}

if (preg_match('/^(admin|rev)\/revendedores\/(\d+)\/codigos$/', $path, $matches) && $method === 'POST') {
    Auth::checkSessionTimeout();
    $rev_id = $matches[2];
    $codigoDNS = new CodigoDNS();
    $result = $codigoDNS->criar($rev_id, $input);
    respondJSON($result, $result['success'] ? 201 : 400);
}

if (preg_match('/^admin\/codigos\/(\d+)\/status$/', $path, $matches) && $method === 'PUT') {
    Auth::checkSessionTimeout();
    $code_id = $matches[1];
    $codigoDNS = new CodigoDNS();
    $result = $codigoDNS->atualizarStatus($code_id, $input['status'] ?? '');
    respondJSON($result, $result['success'] ? 200 : 400);
}

if (preg_match('/^(admin|rev)\/codigos\/(\d+)$/', $path, $matches) && $method === 'DELETE') {
    Auth::checkSessionTimeout();
    $code_id = $matches[2];
    $codigoDNS = new CodigoDNS();
    $result = $codigoDNS->deletar($code_id);
    respondJSON($result, $result['success'] ? 200 : 400);
}

// =====================================================
// ROTAS DE DISPOSITIVOS
// =====================================================

// Ping do aplicativo (público)
if ($path === 'dispositivos/ping' && $method === 'POST') {
    $dispositivo = new Dispositivo();
    $result = $dispositivo->ping(
        $input['codigo'] ?? '',
        $input['device_id'] ?? '',
        $input['device_model'] ?? null,
        $input['device_name'] ?? null
    );
    respondJSON($result, $result['success'] ? 200 : 400);
}

// Listar dispositivos de um revendedor
if (preg_match('/^(admin|rev)\/revendedores\/(\d+)\/dispositivos$/', $path, $matches) && $method === 'GET') {
    Auth::checkSessionTimeout();
    $rev_id = $matches[2];
    $pagina = $_GET['pagina'] ?? 1;
    $dispositivo = new Dispositivo();
    $result = $dispositivo->listar($rev_id, $pagina);
    respondJSON($result, $result['success'] ? 200 : 403);
}

// Estatísticas de dispositivos
if ($path === 'admin/dispositivos/estatisticas' && $method === 'GET') {
    Auth::checkSessionTimeout();
    $dispositivo = new Dispositivo();
    $result = $dispositivo->estatisticas();
    respondJSON($result, $result['success'] ? 200 : 403);
}

if (preg_match('/^(admin|rev)\/revendedores\/(\d+)\/dispositivos\/estatisticas$/', $path, $matches) && $method === 'GET') {
    Auth::checkSessionTimeout();
    $rev_id = $matches[2];
    $dispositivo = new Dispositivo();
    $result = $dispositivo->estatisticas($rev_id);
    respondJSON($result, $result['success'] ? 200 : 403);
}

// Atualizar status de dispositivo
if (preg_match('/^admin\/dispositivos\/([a-zA-Z0-9:-]+)\/status$/', $path, $matches) && $method === 'PUT') {
    Auth::checkSessionTimeout();
    $device_id = $matches[1];
    $dispositivo = new Dispositivo();
    $result = $dispositivo->atualizarStatus($device_id, $input['status'] ?? '');
    respondJSON($result, $result['success'] ? 200 : 400);
}

// Deletar dispositivo
if (preg_match('/^admin\/dispositivos\/([a-zA-Z0-9:-]+)$/', $path, $matches) && $method === 'DELETE') {
    Auth::checkSessionTimeout();
    $device_id = $matches[1];
    $dispositivo = new Dispositivo();
    $result = $dispositivo->deletar($device_id);
    respondJSON($result, $result['success'] ? 200 : 400);
}

// =====================================================
// ROTA NÃO ENCONTRADA
// =====================================================

respondError('Rota não encontrada', 404);
?>
