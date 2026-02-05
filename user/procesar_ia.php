<?php
// procesar_ia.php
// CORREGIDO: Sin 'proveedor_comercial'
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
session_start();

if (file_exists('auth.php')) {
    require_once '../includes/auth.php';
} else {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Sesión expirada. Recarga la página.']);
        exit;
    }
}

require_once '../includes/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$pregunta_usuario = $input['pregunta'] ?? '';

if (empty($pregunta_usuario)) {
    echo json_encode(['error' => 'Por favor escribe una pregunta.']);
    exit;
}

try {
    // CONSULTA ARREGLADA
    $sql = "SELECT codigo_unico,
                   proveedor AS proveedor_mostrar,
                   fecha_registro, total_kilos_neto, importe_total_fruta, estado,
                   total_cat1, total_cat2, total_rastrojo,
                   conductor, placa,
                   cosecha_personas, subtotal_cosecha,
                   cargadores_personas, subtotal_cargadores,
                   inspectores_personas, subtotal_inspectores
            FROM acopios_cabecera 
            ORDER BY fecha_registro DESC LIMIT 10";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $contexto_bd = "DATOS OPERATIVOS DEL SISTEMA GOLDFRUITS (Últimos 10 registros):\n";
    
    if(count($datos) > 0){
        foreach ($datos as $d) {
            $fecha = date('d/m H:i', strtotime($d['fecha_registro']));
            $contexto_bd .= "🔴 [{$fecha}] PROVEEDOR: {$d['proveedor_mostrar']} | Estado: {$d['estado']}\n";
            $contexto_bd .= "   Transporte: Chofer {$d['conductor']} (Placa: {$d['placa']})\n";
            $contexto_bd .= "   Carga: Neto {$d['total_kilos_neto']}kg (Cat1: {$d['total_cat1']}, Cat2: {$d['total_cat2']}, Rastrojo: {$d['total_rastrojo']})\n";
            $contexto_bd .= "   Personal de Campo:\n";
            $contexto_bd .= "     - Cosecha: {$d['cosecha_personas']} pers. (Costo: S/ {$d['subtotal_cosecha']})\n";
            $contexto_bd .= "     - Cargadores: {$d['cargadores_personas']} pers. (Costo: S/ {$d['subtotal_cargadores']})\n";
            $contexto_bd .= "     - Inspectores: {$d['inspectores_personas']} pers. (Costo: S/ {$d['subtotal_inspectores']})\n";
            $contexto_bd .= "   💰 PAGO TOTAL FRUTA: S/ {$d['importe_total_fruta']}\n";
            $contexto_bd .= "   --------------------------------------------------\n";
        }
    } else {
        $contexto_bd .= "No hay registros recientes.\n";
    }

    $apiKey = "AIzaSyBPmnLsLzu6IVhHOUllCUoma7_n8cFf2d8"; 
    $modelo = "gemini-2.5-flash"; 
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/$modelo:generateContent?key=" . $apiKey;

    $prompt_final = "Eres el asistente operativo experto de 'GoldFruits'. \n" .
                    "Datos disponibles:\n" .
                    "=====================\n" . $contexto_bd . "=====================\n" .
                    "Pregunta: " . $pregunta_usuario . "\n" .
                    "Instrucciones: \n" .
                    "1. Responde basándote estrictamente en los datos.\n" .
                    "2. Usa negritas (**) para resaltar nombres, kilos y montos.";

    $payload = ["contents" => [["parts" => [["text" => $prompt_final]]]]];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); 

    $response = curl_exec($ch);
    
    if(curl_errno($ch)){
        throw new Exception("Error de conexión: " . curl_error($ch));
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json_res = json_decode($response, true);
    
    if ($httpCode != 200) {
        $msg = $json_res['error']['message'] ?? 'Error desconocido';
        echo json_encode(['error' => "Error $httpCode: $msg"]);
        exit;
    }

    if (isset($json_res['candidates'][0]['content']['parts'][0]['text'])) {
        echo json_encode(['respuesta' => $json_res['candidates'][0]['content']['parts'][0]['text']]);
    } else {
        echo json_encode(['error' => 'La IA no generó respuesta.']);
    }

} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>