<?php
// 1. Conexión Segura
$root = $_SERVER['DOCUMENT_ROOT'];
if (file_exists($root . '/contabilidad/config/db.php')) {
    include_once $root . '/contabilidad/config/db.php';
} else {
    // Fallback relativo
    include_once '../contabilidad/config/db.php';
}

// 2. Recibir Datos del Formulario
$apellidos = mysqli_real_escape_string($conexion, $_POST['apellidos_nombres']);
$sexo = $_POST['sexo'];
$tipo_doc = $_POST['tipo_documento'];
$num_doc = $_POST['numero_documento'];
$f_nac = $_POST['fecha_nacimiento'];
$correo = $_POST['correo'];
$celular = $_POST['celular'];

// Datos Laborales
$en_planilla = $_POST['en_planilla'] ?? 'SI'; // <--- CAMPO NUEVO
$id_cat = $_POST['id_categoria'];
$id_area = $_POST['id_area'];
$id_puesto = $_POST['id_puesto'];
$f_ingreso = $_POST['fecha_ingreso'];
$cod_emp = $_POST['codigo_empleado'];

// Datos Financieros
$id_seg = $_POST['id_aseguradora'];
$cuspp = $_POST['cuspp'];
$banco = $_POST['banco_nombre'];
$cuenta = $_POST['numero_cuenta'];

// 3. Insertar Trabajador
$sql = "INSERT INTO trabajadores 
(apellidos_nombres, sexo, tipo_documento, numero_documento, fecha_nacimiento, correo, celular, 
 en_planilla, id_categoria, id_area, id_puesto, fecha_ingreso, codigo_empleado, 
 id_aseguradora, cuspp, banco_nombre, numero_cuenta, estado, tiene_hijos) 
VALUES 
('$apellidos', '$sexo', '$tipo_doc', '$num_doc', '$f_nac', '$correo', '$celular', 
 '$en_planilla', '$id_cat', '$id_area', '$id_puesto', '$f_ingreso', '$cod_emp', 
 '$id_seg', '$cuspp', '$banco', '$cuenta', 'ACTIVO', 0)";

if (mysqli_query($conexion, $sql)) {
    $id_trabajador = mysqli_insert_id($conexion);

    // 4. Procesar Hijos (Si existen)
    if (isset($_POST['nombre_hijos']) && count($_POST['nombre_hijos']) > 0) {
        $tiene_hijos = 0;
        $nombres_hijos = $_POST['nombre_hijos'];
        $dnis_hijos = $_POST['dni_hijos'];
        $tipos_hijos = $_POST['tipo_doc_hijos'];
        $sexos_hijos = $_POST['sexo_hijos'];

        for ($i = 0; $i < count($nombres_hijos); $i++) {
            if (!empty($nombres_hijos[$i])) {
                $n = mysqli_real_escape_string($conexion, $nombres_hijos[$i]);
                $d = $dnis_hijos[$i];
                $t = $tipos_hijos[$i];
                $s = $sexos_hijos[$i];

                $sql_hijo = "INSERT INTO hijos_detalles (id_trabajador, tipo_documento_hijo, dni_hijo, nombre_hijo, sexo_hijo) 
                             VALUES ('$id_trabajador', '$t', '$d', '$n', '$s')";
                mysqli_query($conexion, $sql_hijo);
                $tiene_hijos = 1;
            }
        }
        
        // Actualizar flag de hijos
        if ($tiene_hijos) {
            mysqli_query($conexion, "UPDATE trabajadores SET tiene_hijos=1 WHERE id_trabajador='$id_trabajador'");
        }
    }

    // Redireccionar con éxito
    header("Location: ../index.php?view=lista_personal&status=success");
} else {
    echo "Error al guardar: " . mysqli_error($conexion);
}
?>