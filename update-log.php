<?php
// update-log.php - Guarda logs directamente en GitHub
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');
header('Access-Control-Max-Age: 86400');

// Configuración de GitHub
define('GITHUB_OWNER', 'franciscozamoramx');
define('GITHUB_REPO', 'centro-dashboards');
define('GITHUB_BRANCH', 'main');
define('MAX_LOGS', 5000);

// Token de GitHub - SOLO VARIABLE DE ENTORNO
define('GITHUB_TOKEN', getenv('GITHUB_TOKEN') ?: '');
define('API_SECRET', getenv('API_SECRET') ?: '');

// Validar seguridad mejorada
function validateRequest() {
    // Manejo de preflight CORS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('HTTP/1.1 204 No Content');
        exit();
    }
    
    // Solo POST permitido
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        return false;
    }
    
    // Obtener datos
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos JSON inválidos']);
        return false;
    }
    
    // Verificar secreto de API
    if (!isset($data['secret']) || $data['secret'] !== API_SECRET) {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso no autorizado']);
        return false;
    }
    
    // Validar origen si está configurado
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (getenv('ALLOWED_ORIGINS')) {
        $allowed_origins = explode(',', getenv('ALLOWED_ORIGINS'));
        if (!in_array($origin, $allowed_origins) && !empty($origin)) {
            http_response_code(403);
            echo json_encode(['error' => 'Origen no permitido']);
            return false;
        }
    }
    
    return true;
}

// Función para actualizar archivo en GitHub
function updateGitHubFile($content) {
    if (empty(GITHUB_TOKEN)) {
        return ['error' => 'Token de GitHub no configurado'];
    }
    
    $fileUrl = 'https://api.github.com/repos/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/contents/logs/logs.json';
    
    // Primero, obtener el SHA del archivo actual
    $ch = curl_init($fileUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . GITHUB_TOKEN,
        'User-Agent: PHP-Script',
        'Accept: application/vnd.github.v3+json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $sha = null;
    if ($httpCode === 200) {
        $fileData = json_decode($response, true);
        $sha = $fileData['sha'];
    } elseif ($httpCode !== 404) {
        return ['error' => 'Error accediendo a GitHub: ' . $httpCode];
    }
    
    // Preparar datos para actualizar
    $data = [
        'message' => '📝 Actualizar logs - ' . date('Y-m-d H:i:s'),
        'content' => base64_encode($content),
        'branch' => GITHUB_BRANCH
    ];
    
    if ($sha) {
        $data['sha'] = $sha;
    }
    
    // Actualizar archivo
    $ch = curl_init($fileUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: token ' . GITHUB_TOKEN,
        'User-Agent: PHP-Script',
        'Accept: application/vnd.github.v3+json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 || $httpCode === 201) {
        $responseData = json_decode($response, true);
        return [
            'success' => true, 
            'sha' => $responseData['commit']['sha'] ?? null,
            'commit_url' => $responseData['commit']['html_url'] ?? null
        ];
    } else {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['message'] ?? 'Error desconocido';
        return ['error' => 'Error actualizando: ' . $httpCode . ' - ' . $errorMsg];
    }
}

// Función principal
function handleLogRequest() {
    // Obtener datos
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos JSON inválidos']);
        return;
    }
    
    // Validar datos requeridos (removemos 'secret' de los datos del log)
    $logData = $data;
    unset($logData['secret']); // No guardar el secreto en los logs
    
    if (empty($logData['user']) || empty($logData['action'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos incompletos: user y action son requeridos']);
        return;
    }
    
    // Crear entrada de log
    $logEntry = [
        'id' => uniqid(),
        'timestamp' => $logData['timestamp'] ?? date('c'),
        'user' => $logData['user'],
        'action' => $logData['action'],
        'dashboard' => $logData['dashboard'] ?? null,
        'dashboardName' => $logData['dashboardName'] ?? null,
        'deviceType' => $logData['deviceType'] ?? 'desktop',
        'screen' => $logData['screen'] ?? null,
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'language' => $logData['language'] ?? null,
        'sessionId' => $logData['sessionId'] ?? null,
        'referrer' => $logData['referrer'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'server_time' => date('c')
    ];
    
    // Obtener logs existentes de GitHub
    $fileUrl = 'https://raw.githubusercontent.com/' . GITHUB_OWNER . '/' . GITHUB_REPO . '/' . GITHUB_BRANCH . '/logs/logs.json';
    
    $ch = curl_init($fileUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: PHP-Script'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $logs = [];
    if ($httpCode === 200) {
        $logs = json_decode($response, true) ?: [];
    }
    
    // Agregar nuevo log
    $logs[] = $logEntry;
    
    // Limitar número de logs
    if (count($logs) > MAX_LOGS) {
        $logs = array_slice($logs, -MAX_LOGS);
    }
    
    // Actualizar en GitHub
    $result = updateGitHubFile(json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if (isset($result['success'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Log guardado en GitHub',
            'id' => $logEntry['id'],
            'total_logs' => count($logs),
            'commit_sha' => $result['sha'],
            'commit_url' => $result['commit_url'] ?? null
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error guardando en GitHub: ' . ($result['error'] ?? 'Desconocido')]);
    }
}

// Ejecutar
if (validateRequest()) {
    handleLogRequest();
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
}
?>