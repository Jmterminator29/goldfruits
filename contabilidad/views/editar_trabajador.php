<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/contabilidad/config/db.php';

// 1. OBTENER DATOS DEL TRABAJADOR
if (isset($_GET['id'])) {
    $id_trabajador = intval($_GET['id']);
    $sql = "SELECT * FROM trabajadores WHERE id_trabajador = $id_trabajador";
    $query = mysqli_query($conexion, $sql);
    $trabajador = mysqli_fetch_assoc($query);

    if (!$trabajador) {
        echo "<div class='alert alert-danger'>Trabajador no encontrado.</div>";
        return;
    }

    // Si no tiene categoría, forzamos la 1 por defecto
    $cat_actual = $trabajador['id_categoria'] ? $trabajador['id_categoria'] : 1;
    
    // Obtener valor de planilla (Si está vacío, asumimos SI)
    $en_planilla_actual = isset($trabajador['en_planilla']) ? $trabajador['en_planilla'] : 'SI';
    if (empty($en_planilla_actual)) $en_planilla_actual = 'SI';
    
    // Obtener hijos actuales
    $sql_hijos = "SELECT * FROM hijos_detalles WHERE id_trabajador = $id_trabajador";
    $query_hijos = mysqli_query($conexion, $sql_hijos);
    $hijos_actuales = [];
    while($h = mysqli_fetch_assoc($query_hijos)) { $hijos_actuales[] = $h; }
}

// 2. CARGAR LISTAS (Catálogos)
$res_a   = mysqli_query($conexion, "SELECT * FROM areas ORDER BY nombre_area ASC");
$res_p   = mysqli_query($conexion, "SELECT * FROM puestos ORDER BY nombre_puesto ASC");
$res_b   = mysqli_query($conexion, "SELECT * FROM bancos ORDER BY nombre_banco ASC");
$res_afp = mysqli_query($conexion, "SELECT * FROM aseguradoras ORDER BY nombre_aseguradora ASC");
$res_cat = mysqli_query($conexion, "SELECT * FROM categorias_pago ORDER BY nombre_categoria ASC");
$res_td  = mysqli_query($conexion, "SELECT * FROM tipos_documento ORDER BY nombre_tipo ASC");

// Array de tipos doc para JS
$tipos_docs_array = [];
while($row = mysqli_fetch_assoc($res_td)) { $tipos_docs_array[] = $row['nombre_tipo']; }
mysqli_data_seek($res_td, 0); // Resetear puntero para usarlo en PHP también
?>

<div class="animate__animated animate__fadeIn">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="text-primary fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Ficha de Personal</h4>
        <a href="index.php?view=lista_personal" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>
    
    <form action="controllers/actualizar_trabajador.php" method="POST">
        <input type="hidden" name="id_trabajador" value="<?= $trabajador['id_trabajador'] ?>">
        
        <div class="row g-3">
            <div class="col-12"><h6 class="text-muted border-bottom pb-2 mt-2">Datos Personales</h6></div>
            
            <div class="col-md-5">
                <label class="form-label fw-bold small">Apellidos y Nombres</label>
                <input type="text" name="apellidos_nombres" class="form-control bg-light border-0" value="<?= $trabajador['apellidos_nombres'] ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small">Sexo</label>
                <select name="sexo" class="form-select bg-light border-0">
                    <option value="M" <?= ($trabajador['sexo'] == 'M') ? 'selected' : '' ?>>Masculino</option>
                    <option value="F" <?= ($trabajador['sexo'] == 'F') ? 'selected' : '' ?>>Femenino</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small">Tipo Doc.</label>
                <select name="tipo_documento" class="form-select bg-light border-0">
                    <?php foreach($tipos_docs_array as $td): ?>
                        <option value="<?= $td ?>" <?= ($trabajador['tipo_documento'] == $td) ? 'selected' : '' ?>><?= $td ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Número Doc.</label>
                <input type="text" name="numero_documento" class="form-control bg-light border-0" value="<?= $trabajador['numero_documento'] ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">F. Nacimiento</label>
                <input type="date" name="fecha_nacimiento" class="form-control bg-light border-0" value="<?= $trabajador['fecha_nacimiento'] ?>">
            </div>

            <div class="col-md-5">
                <label class="form-label fw-bold small text-success">Correo</label>
                <input type="email" name="correo" class="form-control bg-light border-0" value="<?= $trabajador['correo'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-success">Celular</label>
                <input type="tel" name="celular" class="form-control bg-light border-0" value="<?= $trabajador['celular'] ?>">
            </div>

            <div class="col-12"><h6 class="text-muted border-bottom pb-2 mt-4">Información Laboral</h6></div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold small text-danger">¿Está en Planilla?</label>
                <select name="en_planilla" class="form-select border-success fw-bold text-success bg-white shadow-sm">
                    <option value="SI" <?= ($en_planilla_actual == 'SI') ? 'selected' : '' ?>>✅ SÍ</option>
                    <option value="NO" <?= ($en_planilla_actual == 'NO') ? 'selected' : '' ?>>⚠️ NO</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold small text-success">Categoría (Pago)</label>
                <select name="id_categoria" class="form-select bg-light border-0">
                    <?php while($c = mysqli_fetch_assoc($res_cat)): ?>
                        <option value="<?= $c['id_categoria'] ?>" <?= ($cat_actual == $c['id_categoria']) ? 'selected' : '' ?>>
                            <?= $c['nombre_categoria'] ?> (S/ <?= $c['monto_categoria'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold small">Área</label>
                <select name="id_area" class="form-select bg-light border-0">
                    <option value="">Seleccionar...</option>
                    <?php while($a = mysqli_fetch_assoc($res_a)): ?>
                        <option value="<?= $a['id_area'] ?>" <?= ($trabajador['id_area'] == $a['id_area']) ? 'selected' : '' ?>><?= $a['nombre_area'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Puesto</label>
                <select name="id_puesto" class="form-select bg-light border-0">
                    <option value="">Seleccionar...</option>
                    <?php while($p = mysqli_fetch_assoc($res_p)): ?>
                        <option value="<?= $p['id_puesto'] ?>" <?= ($trabajador['id_puesto'] == $p['id_puesto']) ? 'selected' : '' ?>><?= $p['nombre_puesto'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Fecha Ingreso</label>
                <input type="date" name="fecha_ingreso" class="form-control bg-light border-0" value="<?= $trabajador['fecha_ingreso'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Código</label>
                <input type="text" name="codigo_empleado" class="form-control bg-light border-0" value="<?= $trabajador['codigo_empleado'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Estado Actual</label>
                <select name="estado" class="form-select bg-light border-0">
                    <option value="ACTIVO" <?= ($trabajador['estado'] == 'ACTIVO') ? 'selected' : '' ?>>ACTIVO</option>
                    <option value="INACTIVO" <?= ($trabajador['estado'] == 'INACTIVO') ? 'selected' : '' ?>>CESADO / INACTIVO</option>
                </select>
            </div>

            <div class="col-12"><h6 class="text-muted border-bottom pb-2 mt-4">Pago y Seguros</h6></div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold small text-info">Aseguradora</label>
                <select name="id_aseguradora" class="form-select bg-light border-0">
                    <option value="">Seleccionar...</option>
                    <?php while($afp = mysqli_fetch_assoc($res_afp)): ?>
                        <option value="<?= $afp['id_aseguradora'] ?>" <?= ($trabajador['id_aseguradora'] == $afp['id_aseguradora']) ? 'selected' : '' ?>><?= $afp['nombre_aseguradora'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small text-info">CUSPP</label>
                <input type="text" name="cuspp" class="form-control bg-light border-0" value="<?= $trabajador['cuspp'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small text-primary">Banco</label>
                <select name="banco_nombre" class="form-select bg-light border-0">
                    <option value="">Seleccionar...</option>
                    <?php while($b = mysqli_fetch_assoc($res_b)): ?>
                        <option value="<?= $b['nombre_banco'] ?>" <?= ($trabajador['banco_nombre'] == $b['nombre_banco']) ? 'selected' : '' ?>><?= $b['nombre_banco'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small text-primary">N° Cuenta</label>
                <input type="text" name="numero_cuenta" class="form-control bg-light border-0" value="<?= $trabajador['numero_cuenta'] ?>">
            </div>

            <div class="col-12 mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <label class="form-label fw-bold text-secondary mb-0">Cargas Familiares</label>
                    <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick="addHijo()">
                        <i class="bi bi-plus-lg"></i> Agregar Hijo
                    </button>
                </div>
                
                <div id="hijos-area">
                    <?php foreach($hijos_actuales as $hijo): ?>
                    <div class="row g-2 mb-2 align-items-center animate__animated animate__fadeIn">
                        <div class="col-md-2">
                             <select name="tipo_doc_hijos[]" class="form-select bg-light border-0" style="font-size:0.85rem;">
                                <?php foreach($tipos_docs_array as $td): ?>
                                    <option value="<?= $td ?>" <?= ($hijo['tipo_documento_hijo'] == $td) ? 'selected' : '' ?>><?= $td ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" name="dni_hijos[]" class="form-control bg-light border-0" value="<?= $hijo['dni_hijo'] ?>">
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="nombre_hijos[]" class="form-control bg-light border-0" value="<?= $hijo['nombre_hijo'] ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="sexo_hijos[]" class="form-select bg-light border-0" style="font-size:0.85rem;">
                                <option value="M" <?= ($hijo['sexo_hijo'] == 'M') ? 'selected' : '' ?>>M</option>
                                <option value="F" <?= ($hijo['sexo_hijo'] == 'F') ? 'selected' : '' ?>>F</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100 rounded-pill" onclick="this.parentElement.parentElement.remove()">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-5">
            <button type="submit" class="btn btn-primary btn-lg px-5 rounded-pill shadow">GUARDAR CAMBIOS</button>
        </div>
    </form>
</div>

<script>
const tiposDocumento = <?php echo json_encode($tipos_docs_array); ?>;

function addHijo() {
    let opcionesHTML = '';
    tiposDocumento.forEach(tipo => {
        opcionesHTML += `<option value="${tipo}">${tipo}</option>`;
    });

    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 animate__animated animate__fadeIn align-items-center';
    div.innerHTML = `
        <div class="col-md-2">
            <select name="tipo_doc_hijos[]" class="form-select bg-light border-0" style="font-size:0.85rem;">${opcionesHTML}</select>
        </div>
        <div class="col-md-3">
            <input type="text" name="dni_hijos[]" class="form-control bg-light border-0" placeholder="Número Doc.">
        </div>
        <div class="col-md-4">
            <input type="text" name="nombre_hijos[]" class="form-control bg-light border-0" placeholder="Nombres">
        </div>
        <div class="col-md-2">
            <select name="sexo_hijos[]" class="form-select bg-light border-0" style="font-size:0.85rem;">
                <option value="M">M</option>
                <option value="F">F</option>
            </select>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger btn-sm w-100 rounded-pill" onclick="this.parentElement.parentElement.remove()">X</button>
        </div>
    `;
    document.getElementById('hijos-area').appendChild(div);
}
</script>