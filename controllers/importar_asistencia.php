<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['archivo_asistencia'])) {
    $archivo = $_FILES['archivo_asistencia']['tmp_name'];
    $datos_importados = [];

    if (($handle = fopen($archivo, "r")) !== FALSE) {
        $delimitador = ',';
        $cabecera = fgetcsv($handle, 1000, $delimitador);
        
        // Si la primera columna no es "Id del Empleado", probamos con punto y coma
        if (stripos($cabecera[0], 'Id') === false) {
            rewind($handle);
            $delimitador = ';';
            $cabecera = fgetcsv($handle, 1000, $delimitador);
        }

        while (($data = fgetcsv($handle, 1000, $delimitador)) !== FALSE) {
            if (empty($data[0]) || !is_numeric(preg_replace('/[^0-9]/', '', $data[0]))) continue;

            // CORRECCIÓN: Limpieza de DNI igual que en la vista
            $dni_csv = (string)(int)preg_replace('/[^0-9]/', '', $data[0]);
            $fecha = $data[4];
            $marcas_str = str_replace(';', ',', ($data[6] ?? ''));

            if (empty($marcas_str)) continue;

            $marcas = array_map('trim', explode(',', $marcas_str));
            $segundos_dia = 0;
            for ($i = 0; $i < count($marcas) - 1; $i += 2) {
                $e = strtotime($marcas[$i]);
                $s = strtotime($marcas[$i+1]);
                if ($s > $e) $segundos_dia += ($s - $e);
            }
            
            $horas_dia = $segundos_dia / 3600;
            if ($horas_dia > 0) {
                if (!isset($datos_importados[$dni_csv])) $datos_importados[$dni_csv] = ['dias' => [], 'total_horas' => 0];
                $datos_importados[$dni_csv]['dias'][$fecha] = true;
                $datos_importados[$dni_csv]['total_horas'] += $horas_dia;
            }
        }
        fclose($handle);

        foreach ($datos_importados as $id => $info) {
            $datos_importados[$id]['total_dias'] = count($info['dias']);
            $datos_importados[$id]['total_horas'] = round($info['total_horas'], 2);
        }

        $_SESSION['importacion_asistencia'] = $datos_importados;
        header("Location: ../index.php?view=nomina&status=success");
    }
}
?>