<?php
// controllers/nomina_actions.php

// 1. ACTIVAR REPORTES DE ERROR (Solo log, no imprimir para no romper JSON)
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);

// 2. CONEXIÓN A BASE DE DATOS
// Ajustamos la ruta para asegurar que encuentre el archivo db.php
if (file_exists('../config/db.php')) {
    include_once '../config/db.php';
} elseif (file_exists('../../contabilidad/config/db.php')) {
    include_once '../../contabilidad/config/db.php';
} else {
    // Si no encuentra la conexión, devolvemos error texto plano
    die("Error Crítico: No se encuentra el archivo de conexión (db.php)");
}

// 3. OBTENER RMV
$q_rmv = mysqli_query($conexion, "SELECT valor FROM configuracion_global WHERE clave='RMV'");
if (!$q_rmv) { die("Error DB Conexión: " . mysqli_error($conexion)); }
$row_rmv = mysqli_fetch_assoc($q_rmv);
$RMV = $row_rmv ? floatval($row_rmv['valor']) : 1130.00;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    // =================================================================
    // ACCIÓN: RECALCULAR (Guardar cambios del Modal Editor)
    // =================================================================
    if ($_POST['accion'] === 'recalcular') {
        
        if (empty($_POST['id_nomina'])) {
            echo "Error: Falta ID de nómina.";
            exit;
        }
        $id_nomina = intval($_POST['id_nomina']); 
        
        // Recibir JSON y protegerlo para SQL
        $json_raw = $_POST['detalle_dias'] ?? '{}';
        $json_safe = mysqli_real_escape_string($conexion, $json_raw);
        
        // Recibir otros datos
        $monto_categoria = floatval($_POST['monto_categoria'] ?? 0);
        $p_seguro = floatval($_POST['porcentaje_seguro'] ?? 0);
        $tiene_hijos = $_POST['tiene_hijos'] ?? '0'; 

        // Variables de Acumulación
        $total_horas_n = 0; $total_horas_25 = 0; $total_horas_35 = 0; $total_horas_nocturnas = 0; 
        $dias_trabajados_reales = 0; $dias_para_beta = 0; 

        // Decodificar JSON para cálculos matemáticos
        $dias_array = json_decode($json_raw, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($dias_array)) {
            
            foreach ($dias_array as $fecha => $data) {
                $raw = isset($data['raw']) ? $data['raw'] : '';
                if (empty($raw)) continue;

                $partes = explode(',', $raw);
                $segundos_dia = 0;
                $segundos_nocturnos_dia = 0;

                for ($k = 0; $k < count($partes) - 1; $k += 2) {
                    $t1_str = trim($partes[$k]); $t2_str = trim($partes[$k+1]);
                    if ($t1_str && $t2_str) {
                        $t1 = timeToSeconds($t1_str); $t2 = timeToSeconds($t2_str);
                        if ($t2 > $t1) {
                            $segundos_dia += ($t2 - $t1);
                            $segundos_nocturnos_dia += calculateNightSecondsPHP($t1, $t2);
                        }
                    }
                }

                $segundos_dia = round($segundos_dia / 300) * 300; // Redondeo 5 min

                if ($segundos_dia > 0) {
                    $dias_trabajados_reales++;
                    if ($segundos_dia > 14400) $dias_para_beta++; // > 4 horas

                    $horas_dia = $segundos_dia / 3600;
                    // Lógica 8 - 2 - Resto
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
            $rbh = ($monto_categoria / 30) / 8; 
            $gh_hora = $rbh * 0.1666; $ct_hora = $rbh * 0.0972;
            $jornal_hora_total = $rbh + $gh_hora + $ct_hora; 

            $pago_hn = $total_horas_n * $rbh; 
            $pago_25 = $total_horas_25 * ($jornal_hora_total * 1.25); 
            $pago_35 = $total_horas_35 * ($jornal_hora_total * 1.35);
            $monto_bono_nocturno = $total_horas_nocturnas * ($jornal_hora_total * 0.35);

            $grati_total = $total_horas_n * $gh_hora;
            $cts_total   = $total_horas_n * $ct_hora;
            
            // Asignación Familiar
            $asig_familiar = ($tiene_hijos == "1") ? (($RMV / 30 / 8) * 0.10 * $total_horas_n) : 0; 

            // Bonos
            $factor_beta_dia = ($RMV * 0.30) / 30; 
            $monto_beta = $dias_para_beta * $factor_beta_dia;
            $bono_6 = $grati_total * 0.06;

            // AFP
            $base_afp = $pago_hn + $pago_25 + $pago_35 + $asig_familiar + $monto_bono_nocturno;
            $monto_descuento_afp = $base_afp * ($p_seguro / 100);

            // Neto Final
            $neto_final = ($base_afp - $monto_descuento_afp) + $grati_total + $cts_total + $monto_beta + $bono_6;

            // Textos HH:MM para BD
            $str_hn = decimalToTimeStr($total_horas_n);
            $str_25 = decimalToTimeStr($total_horas_25);
            $str_35 = decimalToTimeStr($total_horas_35);
            $str_noct = decimalToTimeStr($total_horas_nocturnas);

            // UPDATE SQL
            $sql_update = "UPDATE nomina_procesada SET 
                dias_trabajados = '$dias_trabajados_reales',
                horas_normales_total = '$str_hn',
                horas_25_total = '$str_25',
                horas_35_total = '$str_35',
                horas_nocturnas_total = '$str_noct',
                monto_base_afp = '$base_afp',
                monto_afp = '$monto_descuento_afp',
                monto_neto_final = '$neto_final',
                bono_beta = '$monto_beta',
                bono_extra_6 = '$bono_6',
                bono_nocturno = '$monto_bono_nocturno',
                detalle_horarios = '$json_safe'
                WHERE id_nomina = $id_nomina";

            if (mysqli_query($conexion, $sql_update)) {
                echo "OK"; 
            } else {
                echo "Error DB SQL: " . mysqli_error($conexion);
            }
        } else {
            echo "Error: JSON enviado no es válido.";
        }
        exit; 

    // =================================================================
    // ACCIÓN: CAMBIAR ESTADO
    // =================================================================
    } elseif ($_POST['accion'] === 'cambiar_estado') {
        $id = intval($_POST['id_nomina']);
        $est = mysqli_real_escape_string($conexion, $_POST['nuevo_estado']);
        $sql = "UPDATE nomina_procesada SET estado='$est' WHERE id_nomina=$id";
        
        if(mysqli_query($conexion, $sql)){
            header("Location: " . $_SERVER['HTTP_REFERER']);
        } else {
            echo "Error DB Estado: " . mysqli_error($conexion);
        }
        exit;
    }
}

// FUNCIONES AUXILIARES
function timeToSeconds($time) {
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