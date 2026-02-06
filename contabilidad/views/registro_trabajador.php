<?php
// 1. CONEXIÓN Y CARGA DE LISTAS
if (session_status() === PHP_SESSION_NONE) session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/contabilidad/config/db.php';

// Consultas para llenar los selectores (Sin alias complicados para evitar errores)
$res_a   = mysqli_query($conexion, "SELECT * FROM areas ORDER BY nombre_area ASC");
$res_p   = mysqli_query($conexion, "SELECT * FROM puestos ORDER BY nombre_puesto ASC");
$res_b   = mysqli_query($conexion, "SELECT * FROM bancos ORDER BY nombre_banco ASC");
$res_afp = mysqli_query($conexion, "SELECT * FROM aseguradoras ORDER BY nombre_aseguradora ASC");
$res_cat = mysqli_query($conexion, "SELECT * FROM categorias_pago ORDER BY nombre_categoria ASC"); 
$res_td  = mysqli_query($conexion, "SELECT * FROM tipos_documento ORDER BY nombre_tipo ASC");

// Array para el Javascript de hijos
$tipos_docs_array = [];
while($row = mysqli_fetch_assoc($res_td)) {
    $tipos_docs_array[] = $row['nombre_tipo'];
}
// Reseteamos el puntero para usarlo en el HTML también
mysqli_data_seek($res_td, 0);
?>

<div class="animate__animated animate__fadeInUp">
    <h4 class="text-success fw-bold mb-4"><i class="bi bi-person-plus-fill me-2"></i>Registro de Personal</h4>
    
    <form action="controllers/guardar_trabajador.php" method="POST">
        <div class="row g-3">
            
            <div class="col-12"><h6 class="text-muted border-bottom pb-2 mt-2">Datos Personales</h6></div>
            
            <div class="col-md-5">
                <label class="form-label fw-bold small">Apellidos y Nombres</label>
                <input type="text" name="apellidos_nombres" class="form-control bg-light border-0" required placeholder="Ej: PEREZ LOPEZ, JUAN">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small">Sexo</label>
                <select name="sexo" class="form-select bg-light border-0">
                    <option value="M">Masculino</option>
                    <option value="F">Femenino</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small">Tipo Doc.</label>
                <select name="tipo_documento" class="form-select bg-light border-0">
                    <?php 
                    // Reutilizamos el array que llenamos arriba
                    foreach($tipos_docs_array as $td_nombre) {
                        echo "<option value='$td_nombre'>$td_nombre</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Número Doc.</label>
                <input type="text" name="numero_documento" class="form-control bg-light border-0" required>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold small">F. Nacimiento</label>
                <input type="date" name="fecha_nacimiento" class="form-control bg-light border-0">
            </div>

            <div class="col-md-5">
                <label class="form-label fw-bold small text-success">Correo</label>
                <input type="email" name="correo" class="form-control bg-light border-0">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-success">Celular</label>
                <input type="tel" name="celular" class="form-control bg-light border-0">
            </div>

            <div class="col-12"><h6 class="text-muted border-bottom pb-2 mt-4">Información Laboral</h6></div>

            <div class="col-md-3">
                <label class="form-label fw-bold small text-danger">¿Está en Planilla?</label>
                <select name="en_planilla" class="form-select border-success fw-bold text-success bg-white shadow-sm">
                    <option value="SI" selected>✅ SÍ</option>
                    <option value="NO">⚠️ NO</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold small text-success">Categoría (Pago)</label>
                <select name="id_categoria" class="form-select bg-light border-0">
                    <?php while($cat = mysqli_fetch_assoc($res_cat)): ?>
                        <option value="<?= $cat['id_categoria'] ?>">
                            <?= $cat['nombre_categoria'] ?> (S/ <?= number_format($cat['monto_categoria'], 2) ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold small">Área</label>
                <select name="id_area" class="form-select bg-light border-0">
                    <option value="">Seleccionar...</option>
                    <?php while($a = mysqli_fetch_assoc($res_a)) echo "<option value='{$a['id_area']}'>{$a['nombre_area']}</option>"; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Puesto</label>
                <select name="id_puesto" class="form-select bg-light border-0">
                    <option value="">Seleccionar...</option>
                    <?php while($p = mysqli_fetch_assoc($res_p)) echo "<option value='{$p['id_puesto']}'>{$p['nombre_puesto']}</option>"; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Fecha Ingreso</label>
                <input type="date" name="fecha_ingreso" class="form-control bg-light border-0" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small">Código Empleado</label>
                <input type="text" name="codigo_empleado" class="form-control bg-light border-0">
            </div>

            <div class="col-12"><h6 class="text-muted border-bottom pb-2 mt-4">Datos Financieros</h6></div>

            <div class="col-md-3">
                <label class="form-label fw-bold small text-info">Aseguradora</label>
                <select name="id_aseguradora" class="form-select bg-light border-0">
                    <option value="">Seleccionar...</option>
                    <?php while($afp = mysqli_fetch_assoc($res_afp)) echo "<option value='{$afp['id_aseguradora']}'>{$afp['nombre_aseguradora']}</option>"; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small text-info">CUSPP</label>
                <input type="text" name="cuspp" class="form-control bg-light border-0">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small text-primary">Banco</label>
                <select name="banco_nombre" class="form-select bg-light border-0">
                    <option value="">Seleccionar...</option>
                    <?php while($b = mysqli_fetch_assoc($res_b)) echo "<option value='{$b['nombre_banco']}'>{$b['nombre_banco']}</option>"; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold small text-primary">N° Cuenta</label>
                <input type="text" name="numero_cuenta" class="form-control bg-light border-0">
            </div>

            <div class="col-12 mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <label class="form-label fw-bold text-secondary mb-0"><i class="bi bi-people me-1"></i> Cargas Familiares</label>
                    <button type="button" class="btn btn-outline-success btn-sm rounded-pill" onclick="addHijo()">
                        <i class="bi bi-plus-lg"></i> Agregar Hijo
                    </button>
                </div>
                <div id="hijos-area"></div>
            </div>
        </div>
        
        <div class="text-center mt-5 mb-4">
            <button type="submit" class="btn btn-success btn-lg px-5 rounded-pill shadow fw-bold">
                <i class="bi bi-save me-2"></i> GUARDAR TRABAJADOR
            </button>
        </div>
    </form>
</div>

<script>
// Pasamos el array de PHP a JS correctamente
const tiposDocumento = <?php echo json_encode($tipos_docs_array); ?>;

function addHijo() {
    let opcionesHTML = '';
    tiposDocumento.forEach(tipo => {
        opcionesHTML += `<option value="${tipo}">${tipo}</option>`;
    });

    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 animate__animated animate__fadeIn align-items-center bg-white p-2 rounded border';
    div.innerHTML = `
        <div class="col-md-2">
            <small class="text-muted d-block d-md-none">Tipo Doc</small>
            <select name="tipo_doc_hijos[]" class="form-select border-0 bg-light" style="font-size:0.9rem;">${opcionesHTML}</select>
        </div>
        <div class="col-md-3">
            <input type="text" name="dni_hijos[]" class="form-control border-0 bg-light" placeholder="N° Documento">
        </div>
        <div class="col-md-4">
            <input type="text" name="nombre_hijos[]" class="form-control border-0 bg-light" placeholder="Apellidos y Nombres">
        </div>
        <div class="col-md-2">
            <select name="sexo_hijos[]" class="form-select border-0 bg-light" style="font-size:0.9rem;">
                <option value="M">M</option>
                <option value="F">F</option>
            </select>
        </div>
        <div class="col-md-1 text-end">
            <button type="button" class="btn btn-outline-danger btn-sm rounded-circle" onclick="this.parentElement.parentElement.remove()" title="Quitar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    `;
    document.getElementById('hijos-area').appendChild(div);
}
</script>