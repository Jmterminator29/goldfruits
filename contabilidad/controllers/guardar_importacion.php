<?php
session_start();

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if ($data && isset($data['datos'])) {
    // Limpiamos datos previos
    unset($_SESSION['importacion_asistencia']);
    
    // Guardar los nuevos datos procesados por el DNI limpio
    $_SESSION['importacion_asistencia'] = $data['datos'];
    $_SESSION['mensaje_importacion'] = "ÉXITO: Se sincronizaron " . count($data['datos']) . " colaboradores.";
    
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se recibieron datos válidos']);
}
?>