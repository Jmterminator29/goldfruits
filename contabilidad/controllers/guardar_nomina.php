<?php
// controllers/guardar_nomina.php
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();

$root = $_SERVER['DOCUMENT_ROOT'];
if(file_exists($root.'/contabilidad/config/db.php')) {
    include_once $root.'/contabilidad/config/db.php';
} else {
    // Fallback de ruta
    include_once '../config/db.php'; 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Error: Método no permitido.");
}

// 1. RECIBIR DATOS GLOBALES
$estado_pago = $_POST['estado_pago'] ?? 'BORRADOR';
$mes         = $_POST['mes_pago'] ?? '';
$periodo     = $_POST['periodo_pago'] ?? '';
$anio        = date('Y');
$fecha_hoy   = date('Y-m-d H:i:s');

// VALIDACIÓN: Si no llegan mes o periodo, detenemos todo para no ensuciar la BD
if (empty($mes) || empty($periodo)) {
    die("Error Crítico: Los datos de Mes o Periodo están vacíos. No se guardó nada.");
}

if (isset($_POST['trab']) && is_array($_POST['trab'])) {
    
    $procesados = 0;
    $errores    = 0;

    foreach ($_POST['trab'] as $t) {
        // --- A. Sanitización ---
        $id_trabajador = intval($t['id']);
        $id_nomina     = intval($t['id_nomina'] ?? 0); // ID para saber si es UPDATE

        // Valores
        $dias          = intval($t['dias'] ?? 0);
        $hn            = mysqli_real_escape_string($conexion, $t['horas_n'] ?? '00:00');
        $h25           = mysqli_real_escape_string($conexion, $t['horas_25'] ?? '00:00');
        $h35           = mysqli_real_escape_string($conexion, $t['horas_35'] ?? '00:00');
        $hNoct         = mysqli_real_escape_string($conexion, $t['horas_noct'] ?? '00:00');
        
        $base_afp      = floatval($t['base_afp'] ?? 0);
        $afp           = floatval($t['afp_monto'] ?? 0);
        $neto          = floatval($t['neto'] ?? 0);
        
        $bono_beta     = floatval($t['bono_beta'] ?? 0);
        $bono_6        = floatval($t['bono_6'] ?? 0);
        $bono_nocturno = floatval($t['bono_nocturno'] ?? 0);
        
        $json_horarios = mysqli_real_escape_string($conexion, $t['json_horarios'] ?? '{}');

        // --- B. VERIFICACIÓN DE EXISTENCIA (Anti-Duplicados) ---
        // Si el frontend dice que es nuevo (ID 0), verificamos en BD por si acaso ya existe
        if ($id_nomina == 0) {
            $check_sql = "SELECT id_nomina FROM nomina_procesada 
                          WHERE id_trabajador = '$id_trabajador' 
                          AND mes_pago = '$mes' 
                          AND periodo_pago = '$periodo' 
                          AND anio_pago = '$anio' LIMIT 1";
            $q_check = mysqli_query($conexion, $check_sql);
            if ($row = mysqli_fetch_assoc($q_check)) {
                $id_nomina = intval($row['id_nomina']); // ¡Ya existía! Usamos este ID para actualizar
            }
        }

        // --- C. EJECUCIÓN ---
        if ($id_nomina > 0) {
            // === UPDATE ===
            $sql = "UPDATE nomina_procesada SET 
                    dias_trabajados = '$dias',
                    horas_normales_total = '$hn',
                    horas_25_total = '$h25',
                    horas_35_total = '$h35',
                    horas_nocturnas_total = '$hNoct',
                    monto_base_afp = '$base_afp',
                    monto_afp = '$afp',
                    monto_neto_final = '$neto',
                    bono_beta = '$bono_beta',
                    bono_extra_6 = '$bono_6',
                    bono_nocturno = '$bono_nocturno',
                    detalle_horarios = '$json_horarios',
                    estado = '$estado_pago',
                    fecha_actualizacion = '$fecha_hoy'
                    WHERE id_nomina = '$id_nomina'";
        } else {
            // === INSERT ===
            $sql = "INSERT INTO nomina_procesada 
                    (id_trabajador, mes_pago, periodo_pago, anio_pago, 
                     dias_trabajados, horas_normales_total, horas_25_total, horas_35_total, horas_nocturnas_total,
                     monto_base_afp, monto_afp, monto_neto_final, 
                     bono_beta, bono_extra_6, bono_nocturno, 
                     detalle_horarios, estado, fecha_registro)
                    VALUES 
                    ('$id_trabajador', '$mes', '$periodo', '$anio',
                     '$dias', '$hn', '$h25', '$h35', '$hNoct',
                     '$base_afp', '$afp', '$neto',
                     '$bono_beta', '$bono_6', '$bono_nocturno',
                     '$json_horarios', '$estado_pago', '$fecha_hoy')";
        }

        if (mysqli_query($conexion, $sql)) {
            $procesados++;
        } else {
            $errores++;
        }
    }
    
    if ($errores > 0) {
        echo "Proceso terminado con alertas. Guardados: $procesados. Errores: $errores.";
    } else {
        echo "ÉXITO: Se procesaron $procesados trabajadores correctamente.";
    }

} else {
    echo "Error: No llegaron datos.";
}
?>