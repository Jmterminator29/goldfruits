<?php
// Programa/controllers/nomina_actions.php
include_once '../config/db.php';

// 1. OBTENER RMV
$q_rmv = mysqli_query($conexion, "SELECT valor FROM configuracion_global WHERE clave='RMV'");
$row_rmv = mysqli_fetch_assoc($q_rmv);
$RMV = $row_rmv ? floatval($row_rmv['valor']) : 1130.00;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'recalcular') {

    $id_nomina = $_POST['id_nomina'];
    
    // Si magic_quotes está activado o el servidor escapa strings, limpiamos el JSON
    $json_raw = $_POST['detalle_dias'];
    if (get_magic_quotes_gpc()) {
        $json_raw = stripslashes($json_raw);
    }
    
    $monto_categoria = floatval($_POST['monto_categoria']);
    $p_seguro = floatval($_POST['porcentaje_seguro']);
    $tiene_hijos = $_POST['tiene_hijos']; 

    // ACUMULADORES
    $total_horas_n = 0;
    $total_horas_25 = 0;
    $total_horas_35 = 0;
    $total_horas_nocturnas = 0; 
    $dias_trabajados_reales = 0;
    $dias_para_beta = 0; 

    // DECODIFICAR JSON
    $dias_array = json_decode($json_raw, true);

    // Si el JSON falla, no hacemos nada para no borrar datos
    if (json_last_error() === JSON_ERROR_NONE && is_array($dias_array)) {
        
        foreach ($dias_array as $fecha => $data) {
            $raw = isset($data['raw']) ? $data['raw'] : '';
            if (empty($raw)) continue;

            $partes = explode(',', $raw);
            $segundos_dia = 0;
            $segundos_nocturnos_dia = 0;

            // Procesar pares de Entrada/Salida
            for ($k = 0; $k < count($partes) - 1; $k += 2) {
                $t1_str = trim($partes[$k]);
                $t2_str = trim($partes[$k+1]);

                if ($t1_str && $t2_str) {
                    $t1 = timeToSeconds($t1_str);
                    $t2 = timeToSeconds($t2_str);

                    if ($t2 > $t1) {
                        $segundos_dia += ($t2 - $t1);
                        $segundos_nocturnos_dia += calculateNightSecondsPHP($t1, $t2);
                    }
                }
            }

            // Redondeo a 5 minutos
            $segundos_dia = round($segundos_dia / 300) * 300;

            if ($segundos_dia > 0) {
                $dias_trabajados_reales++;
                
                // BETA: Días > 4 horas (14400 seg)
                if ($segundos_dia > 14400) {
                    $dias_para_beta++;
                }

                $horas_dia = $segundos_dia / 3600;

                // Distribución H.Extras
                $hn = min($horas_dia, 8);
                $resto = max(0, $horas_dia - 8);
                $h25 = min($resto, 2);
                $h35 = max(0, $resto - 2);

                $total_horas_n += $hn;
                $total_horas_25 += $h25;
                $total_horas_35 += $h35;
                
                $total_horas_nocturnas += ($segundos_nocturnos_dia / 3600);
            }
        }

        // --- CÁLCULOS MONETARIOS ---
        $rbh = ($monto_categoria / 30) / 8; // Valor hora simple
        
        // Jornal "Full" para extras y nocturnas (incluye prorrata)
        $gh_hora = $rbh * 0.1666;
        $ct_hora = $rbh * 0.0972;
        $jornal_hora_total = $rbh + $gh_hora + $ct_hora; 

        // Pagos
        $pago_hn = $total_horas_n * $rbh; 
        $pago_25 = $total_horas_25 * ($jornal_hora_total * 1.25); 
        $pago_35 = $total_horas_35 * ($jornal_hora_total * 1.35);
        $monto_bono_nocturno = $total_horas_nocturnas * ($jornal_hora_total * 0.35);

        // Conceptos adicionales
        $grati_total = $total_horas_n * $gh_hora;
        $cts_total   = $total_horas_n * $ct_hora;
        
        $asig_familiar = ($tiene_hijos == "1") ? ($RMV * 0.10) : 0;

        // BETA (Días * Factor Diario)
        $factor_beta_dia = ($RMV * 0.30) / 30; 
        $monto_beta = $dias_para_beta * $factor_beta_dia;

        // Bono 6%
        $bono_6 = $grati_total * 0.06;

        // AFP (Base sin Bono 6%)
        $base_afp = $pago_hn + $pago_25 + $pago_35 + $asig_familiar + $monto_bono_nocturno;
        $monto_descuento_afp = $base_afp * ($p_seguro / 100);

        // Neto (Sumando Bono 6%)
        $neto_final = ($base_afp - $monto_descuento_afp) + $grati_total + $cts_total + $monto_beta + $bono_6;

        // Textos para BD
        $str_hn = decimalToTimeStr($total_horas_n);
        $str_25 = decimalToTimeStr($total_horas_25);
        $str_35 = decimalToTimeStr($total_horas_35);

        // UPDATE
        $sql_update = "UPDATE nomina_procesada SET 
            dias_trabajados = '$dias_trabajados_reales',
            horas_normales_total = '$str_hn',
            horas_25_total = '$str_25',
            horas_35_total = '$str_35',
            monto_base_afp = '$base_afp',
            monto_afp = '$monto_descuento_afp',
            monto_neto_final = '$neto_final',
            bono_beta = '$monto_beta',
            bono_extra_6 = '$bono_6',
            bono_nocturno = '$monto_bono_nocturno',
            detalle_horarios = '$json_raw'
            WHERE id_nomina = '$id_nomina'";

        if (mysqli_query($conexion, $sql_update)) {
            header("Location: ../index.php?view=gestion&estado=BORRADOR&msg=ok");
        } else {
            echo "Error DB: " . mysqli_error($conexion);
        }
    } else {
        // Si el JSON estaba mal, redirigimos sin romper nada
        header("Location: ../index.php?view=gestion&estado=BORRADOR&err=json");
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
    $id = $_POST['id_nomina'];
    $est = $_POST['nuevo_estado'];
    mysqli_query($conexion, "UPDATE nomina_procesada SET estado='$est' WHERE id_nomina='$id'");
    header("Location: " . $_SERVER['HTTP_REFERER']);
}

// FUNCIONES ROBUSTAS
function timeToSeconds($time) {
    // Soporta "08:00" y "08:00:00"
    $parts = explode(':', $time);
    $h = isset($parts[0]) ? intval($parts[0]) : 0;
    $m = isset($parts[1]) ? intval($parts[1]) : 0;
    $s = isset($parts[2]) ? intval($parts[2]) : 0;
    return ($h * 3600) + ($m * 60) + $s;
}

function decimalToTimeStr($decimal) {
    $hours = floor($decimal);
    $minutes = round(($decimal - $hours) * 60);
    return sprintf("%02d:%02d", $hours, $minutes);
}

function calculateNightSecondsPHP($t1, $t2) {
    $night = 0;
    if ($t2 > $t1) {
        $night += getOverlap($t1, $t2, 0, 21600);
        $night += getOverlap($t1, $t2, 79200, 86400);
    }
    return $night;
}

function getOverlap($s1, $e1, $s2, $e2) {
    $start = max($s1, $s2);
    $end = min($e1, $e2);
    return max(0, $end - $start);
}
?>