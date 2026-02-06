<?php
// views/configuracion.php

// 1. CONEXIÓN SEGURA (La corrección clave)
// Usamos __DIR__ para salir de 'views' y entrar a 'config' sin importar la ruta del servidor
require_once __DIR__ . '/../config/db.php'; 

// Consultas de catálogos
$areas = mysqli_query($conexion, "SELECT * FROM areas ORDER BY nombre_area ASC");
$puestos = mysqli_query($conexion, "SELECT * FROM puestos ORDER BY nombre_puesto ASC");
$bancos = mysqli_query($conexion, "SELECT * FROM bancos ORDER BY nombre_banco ASC");
$aseguradoras = mysqli_query($conexion, "SELECT * FROM aseguradoras ORDER BY nombre_aseguradora ASC");
$tipos_doc = mysqli_query($conexion, "SELECT * FROM tipos_documento ORDER BY nombre_tipo ASC");
?>

<style>
    /* Estilos Premium Glassmorphism adaptados a Configuración */
    .config-card {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 16px;
        border: 1px solid rgba(255,255,255,0.6);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
        height: 100%;
    }

    .config-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        border-color: var(--gf-gold, #fbc02d);
    }

    .card-header-custom {
        padding: 1.2rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        background: linear-gradient(to right, rgba(255,255,255,0.8), rgba(255,255,255,0.4));
    }

    /* Personalización de Scrollbar */
    .custom-scroll {
        max-height: 250px;
        overflow-y: auto;
        padding-right: 5px;
    }

    .custom-scroll::-webkit-scrollbar { width: 5px; }
    .custom-scroll::-webkit-scrollbar-track { background: transparent; }
    .custom-scroll::-webkit-scrollbar-thumb { background: #cfd8dc; border-radius: 10px; }
    .custom-scroll::-webkit-scrollbar-thumb:hover { background: #b0bec5; }

    /* Estilos de Lista */
    .list-group-item {
        border-left: none;
        border-right: none;
        border-color: rgba(0,0,0,0.03);
        transition: background 0.2s ease;
        padding: 0.8rem 1rem;
    }

    .list-group-item:hover {
        background-color: #f1f8e9 !important; /* Un verde muy sutil al pasar el mouse */
    }

    .btn-delete-opt {
        opacity: 0.4;
        transition: all 0.2s;
        background: #ffebee;
        width: 30px; 
        height: 30px;
        display: flex; 
        align-items: center; 
        justify-content: center;
        border-radius: 8px;
    }

    .list-group-item:hover .btn-delete-opt {
        opacity: 1;
    }

    .input-custom {
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        padding: 8px 12px;
    }

    .input-custom:focus {
        box-shadow: 0 0 0 3px rgba(27, 94, 32, 0.1); /* Sombra verde corporativa */
        border-color: #1b5e20;
    }
</style>

<div class="animate__animated animate__fadeIn p-2">
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="config-card border-top border-success border-4">
                <div class="card-header-custom">
                    <h6 class="fw-bold text-success m-0"><i class="bi bi-geo-fill me-2"></i>Áreas Operativas</h6>
                </div>
                <div class="p-3">
                    <?php renderForm('areas', 'Nueva área...', 'success', $areas, 'id_area', 'nombre_area'); ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="config-card border-top border-warning border-4">
                <div class="card-header-custom">
                    <h6 class="fw-bold text-warning m-0"><i class="bi bi-briefcase-fill me-2"></i>Puestos / Cargos</h6>
                </div>
                <div class="p-3">
                    <?php renderForm('puestos', 'Nuevo puesto...', 'warning', $puestos, 'id_puesto', 'nombre_puesto'); ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="config-card border-top border-secondary border-4">
                <div class="card-header-custom">
                    <h6 class="fw-bold text-secondary m-0"><i class="bi bi-card-heading me-2"></i>Tipos de Documento</h6>
                </div>
                <div class="p-3">
                    <?php renderForm('tipos_documento', 'Ej: CPP, Pasaporte...', 'secondary', $tipos_doc, 'id_tipo_doc', 'nombre_tipo'); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="config-card border-top border-primary border-4">
                <div class="card-header-custom">
                    <h6 class="fw-bold text-primary m-0"><i class="bi bi-bank2 me-2"></i>Entidades Bancarias</h6>
                </div>
                <div class="p-3">
                    <?php renderForm('bancos', 'Nombre del banco...', 'primary', $bancos, 'id_banco', 'nombre_banco'); ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="config-card border-top border-info border-4">
                <div class="card-header-custom">
                    <h6 class="fw-bold text-info m-0"><i class="bi bi-shield-check me-2"></i>Aseguradoras (AFP / ONP)</h6>
                </div>
                <div class="p-3">
                    <?php renderForm('aseguradoras', 'Nombre de la entidad...', 'info', $aseguradoras, 'id_aseguradora', 'nombre_aseguradora'); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Función auxiliar para renderizar los mini-formularios
function renderForm($tabla, $placeholder, $color, $datos, $id_col, $nom_col) {
    $btnClass = ($color == 'warning' || $color == 'info') ? 'text-white' : '';
    
    echo '<form action="controllers/config_controller.php" method="POST" class="mb-3">
        <input type="hidden" name="accion" value="crear">
        <input type="hidden" name="tabla" value="'.$tabla.'">
        <div class="input-group shadow-sm" style="border-radius: 8px; overflow: hidden;">';
    
    echo '<input type="text" name="nombre" class="form-control form-control-sm border-0 bg-light" style="padding: 10px;" placeholder="'.$placeholder.'" required>';
    
    if($tabla == 'aseguradoras') {
        echo '<input type="number" step="0.001" name="porcentaje" class="form-control form-control-sm text-center fw-bold border-start bg-white" style="max-width:80px" placeholder="%" required>';
    }
    
    echo '  <button type="submit" class="btn btn-sm btn-'.$color.' '.$btnClass.' px-3">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>
    </form>
    
    <div class="custom-scroll">
        <ul class="list-group list-group-flush">';
    
    if(mysqli_num_rows($datos) > 0) {
        while($row = mysqli_fetch_assoc($datos)) {
            $nombre = $row[$nom_col];
            $extra = '';
            
            if($tabla == 'aseguradoras') {
                $extra = '<span class="badge rounded-pill bg-info bg-opacity-10 text-info border border-info ms-2 fw-bold">'.number_format($row['porcentaje_descuento'], 2).'%</span>';
            }

            echo '
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2 border-0 border-bottom">
                <div class="text-truncate me-2">
                    <i class="bi bi-dot text-'.$color.' me-1"></i>
                    <span class="text-dark fw-500">'.$nombre.'</span>
                    '.$extra.'
                </div>
                <a href="controllers/config_controller.php?eliminar='.$row[$id_col].'&tabla='.$tabla.'" 
                   class="btn-delete-opt text-danger" onclick="return confirm(\'¿Está seguro de eliminar este registro?\')">
                    <i class="bi bi-trash3"></i>
                </a>
            </li>';
        }
    } else {
        echo '<li class="list-group-item text-center text-muted small py-3 fst-italic">
                <i class="bi bi-inbox me-1"></i> No hay registros
              </li>';
    }
    echo '</ul></div>';
}
?>