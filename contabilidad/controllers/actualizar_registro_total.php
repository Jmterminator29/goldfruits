<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/contabilidad/config/db.php';

$id  = $_POST['id_nomina'];
$dias = $_POST['dias'];
$hN  = $_POST['hN'];
$h25 = $_POST['h25'];
$h35 = $_POST['h35'];

// 1. Obtener datos
$sql_info = "SELECT n.monto_base_afp, t.id_categoria, t.tiene_hijos, a.porcentaje_descuento, c.monto_categoria
             FROM nomina_procesada n
             JOIN trabajadores t ON n.id_trabajador = t.id_trabajador
             LEFT JOIN categorias_pago c ON t.id_categoria = c.id_categoria
             LEFT JOIN aseguradoras a ON t.id_aseguradora = a.id_aseguradora
             WHERE n.id_nomina = $id";
$info = mysqli_fetch_assoc(mysqli_query($conexion, $sql_info));

function timeToDec($t) { $p = explode(':', $t); return ($p[0] + ($p[1]/60)); }
$decN = timeToDec($hN);
$dec25 = timeToDec($h25);
$dec35 = timeToDec($h35);

// >>> LÓGICA CORREGIDA: HORA AGRARIA COMPLETA PARA EXTRAS <<<
$rbh = $info['monto_categoria'] / 30 / 8;
$gh_hora = $rbh * 0.1666;
$ct_hora = $rbh * 0.0972;
$jornal_hora_total = $rbh + $gh_hora + $ct_hora;

$jornal = $decN * $rbh; // Base horas normales
$m25 = $dec25 * ($jornal_hora_total * 1.25); // Extra sobre (Básico+Grati+CTS)
$m35 = $dec35 * ($jornal_hora_total * 1.35);

// Asignación familiar sobre Básico
$af = ($info['tiene_hijos'] == 1) ? (($jornal + ($dec25 * $rbh) + ($dec35 * $rbh)) * 0.10) : 0;

$base_imponible = $jornal + $m25 + $m35 + $af;
$afp_monto = $base_imponible * ($info['porcentaje_descuento'] / 100);

$grati = $decN * $gh_hora;
$cts = $decN * $ct_hora;
$beta = $dias * 11.30;
$bet6 = $grati * 0.06;

$neto_final = $base_imponible - $afp_monto + $grati + $cts + $bet6 + $beta;

$update = "UPDATE nomina_procesada SET 
           dias_trabajados = '$dias', horas_normales_total = '$hN', 
           horas_25_total = '$h25', horas_35_total = '$h35',
           monto_afp = '$afp_monto', monto_neto_final = '$neto_final'
           WHERE id_nomina = $id";

if(mysqli_query($conexion, $update)) echo "OK";
?>