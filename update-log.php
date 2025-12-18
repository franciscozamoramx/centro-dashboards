<?php
// update-log.php - Guarda logs directamente en GitHub
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

// Configuración de GitHub
define('GITHUB_OWNER', 'franciscozamoramx');
define('GITHUB_REPO', 'centro-dashboards');
define('GITHUB_BRANCH', 'main');
define('MAX_LOGS', 5000);

// Token de GitHub (debes cambiarlo por tu token)
define('GITHUB_TOKEN', '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');

// Validar seguridad
function validateRequest() {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('HTTP/1.1 200 OK');
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }
    
    // Validar origen (opcional)
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed_origins = [
        'https://franciscozamoramx.github.io',
        'http://localhost'
    ];
    
    if (!in_array($origin, $allowed_origins) && !empty($origin)) {
        return false;
    }
    
    return true;
}

// Función para actualizar archivo en GitHub
function updateGitHubFile($content) {
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
        return ['success' => true, 'sha' => json_decode($response, true)['commit']['sha']];
    } else {
        return ['error' => 'Error actualizando: ' . $httpCode . ' - ' . $response];
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
    
    // Validar datos requeridos
    if (empty($data['user']) || empty($data['action'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos incompletos: user y action son requeridos']);
        return;
    }
    
    // Crear entrada de log
    $logEntry = [
        'id' => uniqid(),
        'timestamp' => $data['timestamp'] ?? date('c'),
        'user' => $data['user'],
        'action' => $data['action'],
        'dashboard' => $data['dashboard'] ?? null,
        'dashboardName' => $data['dashboardName'] ?? null,
        'deviceType' => $data['deviceType'] ?? 'desktop',
        'screen' => $data['screen'] ?? null,
        'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'language' => $data['language'] ?? null,
        'sessionId' => $data['sessionId'] ?? null,
        'referrer' => $data['referrer'] ?? null,
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
            'commit_sha' => $result['sha']
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