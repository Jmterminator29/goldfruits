<?php
// procesar_ia.php
// VERSIÓN MAESTRA: Incluye Personal, Transporte, Categorías y Totales.
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
session_start();

// 1. Verificación de Seguridad
if (file_exists('auth.php')) {
    require_once 'auth.php';
} else {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Sesión expirada. Recarga la página.']);
        exit;
    }
}

require_once 'db_connect.php';

// 2. Recibir datos del usuario
$input = json_decode(file_get_contents('php://input'), true);
$pregunta_usuario = $input['pregunta'] ?? '';

if (empty($pregunta_usuario)) {
    echo json_encode(['error' => 'Por favor escribe una pregunta.']);
    exit;
}

try {
    // 3. Obtener Contexto TOTAL de la Base de Datos
    // Agregamos los campos de PERSONAL (personas y subtotales)
    $sql = "SELECT codigo_unico,
                   COALESCE(NULLIF(proveedor_comercial,''), proveedor) AS proveedor_mostrar,
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

    // Construir el texto de contexto detallado
    $contexto_bd = "DATOS OPERATIVOS DEL SISTEMA GOLDFRUITS (Últimos 10 registros):\n";
    
    if(count($datos) > 0){
        foreach ($datos as $d) {
            $fecha = date('d/m H:i', strtotime($d['fecha_registro']));
            
            // Bloque 1: Cabecera y Transporte
            $contexto_bd .= "🔴 [{$fecha}] PROVEEDOR: {$d['proveedor_mostrar']} | Estado: {$d['estado']}\n";
            $contexto_bd .= "   Transporte: Chofer {$d['conductor']} (Placa: {$d['placa']})\n";
            
            // Bloque 2: La Fruta
            $contexto_bd .= "   Carga: Neto {$d['total_kilos_neto']}kg (Cat1: {$d['total_cat1']}, Cat2: {$d['total_cat2']}, Rastrojo: {$d['total_rastrojo']})\n";
            
            // Bloque 3: El Personal (NUEVO)
            $contexto_bd .= "   Personal de Campo:\n";
            $contexto_bd .= "     - Cosecha: {$d['cosecha_personas']} pers. (Costo: S/ {$d['subtotal_cosecha']})\n";
            $contexto_bd .= "     - Cargadores: {$d['cargadores_personas']} pers. (Costo: S/ {$d['subtotal_cargadores']})\n";
            $contexto_bd .= "     - Inspectores: {$d['inspectores_personas']} pers. (Costo: S/ {$d['subtotal_inspectores']})\n";
            
            // Bloque 4: Total Monetario
            $contexto_bd .= "   💰 PAGO TOTAL FRUTA: S/ {$d['importe_total_fruta']}\n";
            $contexto_bd .= "   --------------------------------------------------\n";
        }
    } else {
        $contexto_bd .= "No hay registros recientes.\n";
    }

    // 4. Configuración de Gemini (Modelo 2.5 Flash)
    $apiKey = "AIzaSyBPmnLsLzu6IVhHOUllCUoma7_n8cFf2d8"; 
    $modelo = "gemini-2.5-flash"; 
    
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/$modelo:generateContent?key=" . $apiKey;

    // Prompt (Instrucciones)
    $prompt_final = "Eres el asistente operativo experto de 'GoldFruits'. \n" .
                    "Tienes acceso total a los siguientes registros:\n" .
                    "=====================\n" .
                    $contexto_bd . 
                    "=====================\n" .
                    "Pregunta del usuario: " . $pregunta_usuario . "\n" .
                    "Instrucciones: \n" .
                    "1. Responde basándote estrictamente en los datos.\n" .
                    "2. Si preguntan por gastos de personal (cosecha, carga, etc.), dales el detalle de personas y costo.\n" .
                    "3. Usa negritas (**) para resaltar nombres, kilos y montos de dinero.";

    $payload = [
        "contents" => [
            ["parts" => [["text" => $prompt_final]]]
        ]
    ];

    // 5. Enviar petición a Google
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

    // 6. Procesar respuesta
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