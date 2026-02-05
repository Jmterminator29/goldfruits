<?php
// Programa/controllers/guardar_nomina.php
include_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acceso denegado");
}

$estado_pago = $_POST['estado_pago'];
$mes = $_POST['mes_pago'];
$periodo = $_POST['periodo_pago'];
$anio = date('Y');
$fecha_registro = date('Y-m-d H:i:s');

if (isset($_POST['trab']) && is_array($_POST['trab'])) {
    
    foreach ($_POST['trab'] as $t) {
        
        $id_trabajador = $t['id'];
        $dias = (int)$t['dias'];
        
        if ($dias >= 0) {
            
            // Recibir datos DIRECTOS del formulario
            $hn = $t['horas_n']; 
            $h25 = $t['horas_25'];
            $h35 = $t['horas_35'];
            $base_afp = $t['base_afp'];
            $afp = $t['afp_monto'];
            $neto = $t['neto'];
            
            // Bonos
            $bono_beta_final = $t['bono_beta'];
            $bono_6_final = $t['bono_6'];
            $bono_nocturno_final = $t['bono_nocturno'] ?? 0.00; // RECIBIMOS EL NUEVO BONO
            
            $json_horarios = mysqli_real_escape_string($conexion, $t['json_horarios']);

            // Verificar si existe para hacer UPDATE o INSERT
            $check = mysqli_query($conexion, "SELECT id_nomina FROM nomina_procesada WHERE id_trabajador='$id_trabajador' AND mes_pago='$mes' AND periodo_pago='$periodo' AND anio_pago='$anio'");
            
            if (mysqli_num_rows($check) > 0) {
                // UPDATE: Asegúrate de tener la columna bono_nocturno en tu BD
                // Si no tienes la columna, ejecuta: ALTER TABLE nomina_procesada ADD COLUMN bono_nocturno DECIMAL(10,2) DEFAULT 0.00 AFTER bono_extra_6;
                $sql = "UPDATE nomina_procesada SET 
                        dias_trabajados = '$dias',
                        horas_normales_total = '$hn',
                        horas_25_total = '$h25',
                        horas_35_total = '$h35',
                        monto_base_afp = '$base_afp',
                        monto_afp = '$afp',
                        monto_neto_final = '$neto',
                        bono_beta = '$bono_beta_final',
                        bono_extra_6 = '$bono_6_final',
                        bono_nocturno = '$bono_nocturno_final', 
                        estado = '$estado_pago',
                        detalle_horarios = '$json_horarios',
                        fecha_registro = '$fecha_registro'
                        WHERE id_trabajador='$id_trabajador' AND mes_pago='$mes' AND periodo_pago='$periodo' AND anio_pago='$anio'";
            } else {
                // INSERT
                $sql = "INSERT INTO nomina_procesada 
                        (id_trabajador, mes_pago, anio_pago, periodo_pago, fecha_registro, 
                         dias_trabajados, horas_normales_total, horas_25_total, horas_35_total, 
                         monto_base_afp, monto_afp, monto_neto_final, 
                         bono_beta, bono_extra_6, bono_nocturno,
                         estado, detalle_horarios)
                        VALUES 
                        ('$id_trabajador', '$mes', '$anio', '$periodo', '$fecha_registro', 
                         '$dias', '$hn', '$h25', '$h35', 
                         '$base_afp', '$afp', '$neto', 
                         '$bono_beta_final', '$bono_6_final', '$bono_nocturno_final',
                         '$estado_pago', '$json_horarios')";
            }
            mysqli_query($conexion, $sql);
        }
    }
    echo "OK: Nómina guardada correctamente.";
} else {
    echo "Error: No hay datos de trabajadores.";
}
?>