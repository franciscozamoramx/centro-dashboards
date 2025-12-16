<?php
// update-log.php - Guarda logs en GitHub via PHP
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Content-Type: application/json');

// Configuración
$LOG_FILE = 'logs.json';
$MAX_LOGS = 1000;
$ALLOWED_IPS = ['127.0.0.1']; // Opcional: restringir IPs

// Validar seguridad básica
function validateRequest() {
    // Puedes agregar validaciones aquí
    // 1. Verificar hash de seguridad
    // 2. Validar formato de datos
    // 3. Rate limiting
    return true;
}

// Función principal
function handleLogRequest() {
    global $LOG_FILE, $MAX_LOGS;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
        return;
    }
    
    // Obtener datos del log
    $logData = json_decode($_POST['log'] ?? '{}', true);
    $user = $_POST['user'] ?? 'unknown';
    $timestamp = $_POST['timestamp'] ?? date('c');
    
    if (empty($logData) || empty($user)) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos incompletos']);
        return;
    }
    
    // Validar datos básicos
    if (!isset($logData['action']) || !isset($logData['user'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos inválidos']);
        return;
    }
    
    // Cargar logs existentes
    $logs = [];
    if (file_exists($LOG_FILE)) {
        $content = file_get_contents($LOG_FILE);
        $logs = json_decode($content, true) ?: [];
    }
    
    // Agregar nuevo log
    $logEntry = [
        'id' => uniqid(),
        'timestamp' => $timestamp,
        'user' => $user,
        'action' => $logData['action'],
        'dashboard' => $logData['dashboardName'] ?? null,
        'device' => $logData['deviceType'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'data' => $logData // Guardar todos los datos originales
    ];
    
    array_push($logs, $logEntry);
    
    // Limitar número de logs
    if (count($logs) > $MAX_LOGS) {
        $logs = array_slice($logs, -$MAX_LOGS);
    }
    
    // Guardar archivo
    if (file_put_contents($LOG_FILE, json_encode($logs, JSON_PRETTY_PRINT))) {
        echo json_encode([
            'success' => true,
            'message' => 'Log guardado',
            'count' => count($logs),
            'id' => $logEntry['id']
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error guardando log']);
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
