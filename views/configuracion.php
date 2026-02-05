<?php
$root = $_SERVER['DOCUMENT_ROOT'];
include_once $root . '/config/db.php'; 

// Consultas de catálogos
$areas = mysqli_query($conexion, "SELECT * FROM areas ORDER BY nombre_area ASC");
$puestos = mysqli_query($conexion, "SELECT * FROM puestos ORDER BY nombre_puesto ASC");
$bancos = mysqli_query($conexion, "SELECT * FROM bancos ORDER BY nombre_banco ASC");
$aseguradoras = mysqli_query($conexion, "SELECT * FROM aseguradoras ORDER BY nombre_aseguradora ASC");
$tipos_doc = mysqli_query($conexion, "SELECT * FROM tipos_documento ORDER BY nombre_tipo ASC");
?>

<style>
    /* Estilos de Tarjetas Estilizadas */
    .config-card {
        background: #ffffff;
        border-radius: 15px;
        border: none;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        overflow: hidden;
    }

    .config-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    }

    .card-header-custom {
        padding: 1rem;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        background: rgba(255,255,255,0.5);
    }

    /* Personalización de Scrollbar */
    .custom-scroll {
        max-height: 280px;
        overflow-y: auto;
        padding-right: 5px;
    }

    .custom-scroll::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scroll::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    .custom-scroll::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 10px;
    }

    /* Estilos de Lista */
    .list-group-item {
        border-left: none;
        border-right: none;
        border-color: rgba(0,0,0,0.03);
        transition: background 0.2s ease;
    }

    .list-group-item:hover {
        background-color: #f8f9fa !important;
    }

    .btn-delete-opt {
        opacity: 0.3;
        transition: opacity 0.2s, color 0.2s;
    }

    .list-group-item:hover .btn-delete-opt {
        opacity: 1;
    }

    .input-custom {
        border-radius: 8px;
        border: 1.5px solid #eee;
    }

    .input-custom:focus {
        box-shadow: none;
        border-color: inherit;
    }
</style>

<div class="animate__animated animate__fadeIn">
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="config-card h-100">
                <div class="card-header-custom">
                    <h6 class="fw-bold text-success m-0"><i class="bi bi-geo-fill me-2"></i>Áreas Operativas</h6>
                </div>
                <div class="p-3">
                    <?php renderForm('areas', 'Nueva área...', 'success', $areas, 'id_area', 'nombre_area'); ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="config-card h-100">
                <div class="card-header-custom">
                    <h6 class="fw-bold text-warning m-0"><i class="bi bi-briefcase-fill me-2"></i>Puestos / Cargos</h6>
                </div>
                <div class="p-3">
                    <?php renderForm('puestos', 'Nuevo puesto...', 'warning', $puestos, 'id_puesto', 'nombre_puesto'); ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="config-card h-100">
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
function renderForm($tabla, $placeholder, $color, $datos, $id_col, $nom_col) {
    $btnClass = ($color == 'warning' || $color == 'info') ? 'text-white' : '';
    
    echo '<form action="controllers/config_controller.php" method="POST" class="mb-3">
        <input type="hidden" name="accion" value="crear">
        <input type="hidden" name="tabla" value="'.$tabla.'">
        <div class="input-group shadow-sm">';
    
    echo '<input type="text" name="nombre" class="form-control form-control-sm input-custom" placeholder="'.$placeholder.'" required>';
    
    if($tabla == 'aseguradoras') {
        echo '<input type="number" step="0.001" name="porcentaje" class="form-control form-control-sm text-center fw-bold" style="max-width:80px" placeholder="%" required>';
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
                $extra = '<span class="badge rounded-pill bg-light text-dark border ms-2 fw-normal">'.number_format($row['porcentaje_descuento'], 2).'%</span>';
            }

            echo '
            <li class="list-group-item d-flex justify-content-between align-items-center bg-transparent py-2 border-0 border-bottom">
                <div class="text-truncate me-2">
                    <span class="text-dark">'.$nombre.'</span>
                    '.$extra.'
                </div>
                <a href="controllers/config_controller.php?eliminar='.$row[$id_col].'&tabla='.$tabla.'" 
                   class="btn-delete-opt text-danger" onclick="return confirm(\'¿Está seguro de eliminar este registro?\')">
                    <i class="bi bi-trash3"></i>
                </a>
            </li>';
        }
    } else {
        echo '<li class="list-group-item text-center text-muted small py-3 italic">No hay registros</li>';
    }
    echo '</ul></div>';
}
?>