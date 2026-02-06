<?php
// controllers/guardar_nomina.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Conexión a la base de datos
$root = $_SERVER['DOCUMENT_ROOT'];
if(file_exists($root.'/contabilidad/config/db.php')) {
    include_once $root.'/contabilidad/config/db.php';
} else {
    include_once '../contabilidad/config/db.php'; 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Error: Método no permitido.");
}

// Recibir datos globales
$estado_pago = $_POST['estado_pago'] ?? 'BORRADOR';
$mes = $_POST['mes_pago'];
$periodo = $_POST['periodo_pago'];
$anio = date('Y');
$fecha_registro = date('Y-m-d H:i:s');

if (isset($_POST['trab']) && is_array($_POST['trab'])) {
    
    $guardados = 0;
    $errores = 0;
    $ultimo_error = "";

    // NOTA: Aquí usamos 'bono_extra_6' que es como se llama en tu Base de Datos
    $sql = "INSERT INTO nomina_procesada 
            (id_trabajador, mes_pago, anio_pago, periodo_pago, fecha_registro, 
             dias_trabajados, horas_normales_total, horas_25_total, horas_35_total, horas_nocturnas_total,
             monto_base_afp, monto_afp, monto_neto_final, 
             bono_beta, bono_extra_6, bono_nocturno,
             estado, detalle_horarios)
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            dias_trabajados = VALUES(dias_trabajados),
            horas_normales_total = VALUES(horas_normales_total),
            horas_25_total = VALUES(horas_25_total),
            horas_35_total = VALUES(horas_35_total),
            horas_nocturnas_total = VALUES(horas_nocturnas_total),
            monto_base_afp = VALUES(monto_base_afp),
            monto_afp = VALUES(monto_afp),
            monto_neto_final = VALUES(monto_neto_final),
            bono_beta = VALUES(bono_beta),
            bono_extra_6 = VALUES(bono_extra_6),
            bono_nocturno = VALUES(bono_nocturno),
            estado = VALUES(estado),
            detalle_horarios = VALUES(detalle_horarios),
            fecha_registro = VALUES(fecha_registro)";

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        die("Error SQL: " . $conexion->error);
    }

    foreach ($_POST['trab'] as $t) {
        // Datos básicos
        $id_trabajador = $t['id'];
        $dias = (int)($t['dias'] ?? 0);
        
        // Horas
        $hn = $t['horas_n'] ?? '00:00'; 
        $h25 = $t['horas_25'] ?? '00:00';
        $h35 = $t['horas_35'] ?? '00:00';
        $hNoct = $t['horas_noct'] ?? '00:00';
        
        // Montos
        $base_afp = floatval($t['base_afp'] ?? 0);
        $afp = floatval($t['afp_monto'] ?? 0);
        $neto = floatval($t['neto'] ?? 0);
        $bono_beta = floatval($t['bono_beta'] ?? 0);
        
        // Aquí está la corrección clave: Recibimos 'bono_6' del HTML, guardamos en 'bono_extra_6'
        $bono_6 = floatval($t['bono_6'] ?? 0); 
        $bono_nocturno = floatval($t['bono_nocturno'] ?? 0);
        
        // JSON
        $json_horarios = $t['json_horarios'] ?? '{}';

        // Bind Parameters
        $stmt->bind_param("iiississssddddddss", 
            $id_trabajador, $mes, $anio, $periodo, $fecha_registro,
            $dias, $hn, $h25, $h35, $hNoct,
            $base_afp, $afp, $neto,
            $bono_beta, $bono_6, $bono_nocturno,
            $estado_pago, $json_horarios
        );

        if ($stmt->execute()) {
            $guardados++;
        } else {
            $errores++;
            $ultimo_error = $stmt->error;
        }
    }
    
    $stmt->close();
    
    if ($errores > 0) {
        echo "Advertencia: Guardados $guardados. Fallidos $errores. Error: $ultimo_error";
    } else {
        echo "ÉXITO: Se guardaron $guardados registros.";
    }

} else {
    echo "Error: No llegaron datos del formulario.";
}
?>