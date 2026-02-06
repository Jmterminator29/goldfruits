<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/contabilidad/config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id_trabajador']);
    
    // 1. RECIBIR DATOS DEL FORMULARIO
    $nombre = mysqli_real_escape_string($conexion, $_POST['apellidos_nombres']);
    $sexo = mysqli_real_escape_string($conexion, $_POST['sexo']);
    $tipo_doc = mysqli_real_escape_string($conexion, $_POST['tipo_documento']);
    $dni = mysqli_real_escape_string($conexion, $_POST['numero_documento']);
    $correo = mysqli_real_escape_string($conexion, $_POST['correo']);
    $celular = mysqli_real_escape_string($conexion, $_POST['celular']);
    $f_nac = $_POST['fecha_nacimiento'];
    
    // Datos Laborales
    $f_ingreso = $_POST['fecha_ingreso'];
    $codigo = mysqli_real_escape_string($conexion, $_POST['codigo_empleado']);
    
    // --- NUEVOS CAMPOS CLAVE ---
    $en_planilla = mysqli_real_escape_string($conexion, $_POST['en_planilla']); // Para reportes
    $estado = mysqli_real_escape_string($conexion, $_POST['estado']);           // Activo/Inactivo
    // ---------------------------

    // Categoría y FKs (Manejo de nulos)
    $id_cat = !empty($_POST['id_categoria']) ? $_POST['id_categoria'] : 1;
    $id_area = !empty($_POST['id_area']) ? $_POST['id_area'] : 'NULL';
    $id_puesto = !empty($_POST['id_puesto']) ? $_POST['id_puesto'] : 'NULL';
    $id_aseg = !empty($_POST['id_aseguradora']) ? $_POST['id_aseguradora'] : 'NULL';
    
    $cuspp = mysqli_real_escape_string($conexion, $_POST['cuspp']);
    $banco = mysqli_real_escape_string($conexion, $_POST['banco_nombre']);
    $cuenta = mysqli_real_escape_string($conexion, $_POST['numero_cuenta']);

    // 2. SQL UPDATE (Incluyendo en_planilla y estado)
    $sql = "UPDATE trabajadores SET 
            apellidos_nombres='$nombre', 
            sexo='$sexo', 
            tipo_documento='$tipo_doc', 
            numero_documento='$dni',
            correo='$correo', 
            celular='$celular', 
            fecha_nacimiento='$f_nac', 
            codigo_empleado='$codigo', 
            fecha_ingreso='$f_ingreso', 
            
            en_planilla='$en_planilla',  /* <--- ACTUALIZADO */
            estado='$estado',            /* <--- ACTUALIZADO */
            
            id_categoria=$id_cat,
            id_area=$id_area, 
            id_puesto=$id_puesto, 
            id_aseguradora=$id_aseg,
            cuspp='$cuspp', 
            banco_nombre='$banco', 
            numero_cuenta='$cuenta'
            WHERE id_trabajador = $id";

    if (mysqli_query($conexion, $sql)) {
        
        // 3. ACTUALIZAR HIJOS (Borrar anteriores e insertar nuevos)
        mysqli_query($conexion, "DELETE FROM hijos_detalles WHERE id_trabajador = $id");

        if (isset($_POST['dni_hijos']) && is_array($_POST['dni_hijos'])) {
            $tiene_hijos = 0; // Reiniciar contador
            
            foreach ($_POST['dni_hijos'] as $i => $dni_hijo) {
                if (!empty($dni_hijo)) {
                    $tipo_h = $_POST['tipo_doc_hijos'][$i];
                    $nom_h = mysqli_real_escape_string($conexion, $_POST['nombre_hijos'][$i]);
                    $sex_h = $_POST['sexo_hijos'][$i];
                    $dni_h = mysqli_real_escape_string($conexion, $dni_hijo);
                    
                    mysqli_query($conexion, "INSERT INTO hijos_detalles (id_trabajador, tipo_documento_hijo, dni_hijo, nombre_hijo, sexo_hijo) 
                                             VALUES ('$id', '$tipo_h', '$dni_h', '$nom_h', '$sex_h')");
                    $tiene_hijos = 1;
                }
            }
            
            // Actualizar flag tiene_hijos en la tabla principal
            mysqli_query($conexion, "UPDATE trabajadores SET tiene_hijos=$tiene_hijos WHERE id_trabajador=$id");
        }
        
        // Redireccionar a la lista
        header("Location: ../index.php?view=lista_personal&status=updated");
    } else {
        echo "Error al actualizar: " . mysqli_error($conexion);
    }
}
?>