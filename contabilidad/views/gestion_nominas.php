<?php
// views/gestion_nominas.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

/* =========================
   1) FILTROS
   ========================= */
$estado_filtro  = $_GET['estado'] ?? 'BORRADOR';
$mes_filtro     = $_GET['mes'] ?? date('n');
$periodo_filtro = $_GET['periodo'] ?? '1RA QUINCENA';
$anio_filtro    = $_GET['anio'] ?? date('Y');

$anio_filtro = (int)$anio_filtro;
$estado_filtro = ($estado_filtro === 'FINAL') ? 'FINAL' : 'BORRADOR';
$mes_filtro = (int)$mes_filtro;
if ($mes_filtro < 1 || $mes_filtro > 12) $mes_filtro = (int)date('n');
$periodo_filtro = in_array($periodo_filtro, ['1RA QUINCENA','2DA QUINCENA','MENSUAL'], true) ? $periodo_filtro : '1RA QUINCENA';

/* =========================
   RMV (configuracion_global)
   ========================= */
$res_c = mysqli_query($conexion, "SELECT valor FROM configuracion_global WHERE clave='RMV' LIMIT 1");
$row_c = $res_c ? mysqli_fetch_assoc($res_c) : null;
$rmv = (isset($row_c['valor']) && is_numeric($row_c['valor'])) ? (float)$row_c['valor'] : 1130.00;

/* =========================
   Helpers PHP
   ========================= */
function sumarHoras($cadena_horas) {
    if (!$cadena_horas) return "00:00";
    $lista = explode(',', $cadena_horas);
    $total_minutos = 0;
    foreach($lista as $h) {
        $partes = explode(':', trim($h));
        if(count($partes) >= 2) {
            $total_minutos += ((int)$partes[0] * 60) + (int)$partes[1];
        }
    }
    return sprintf("%02d:%02d", floor($total_minutos / 60), $total_minutos % 60);
}

/* =========================
   2) SQL (FIX: traer cuspp)
   ========================= */
$estado_sql  = mysqli_real_escape_string($conexion, $estado_filtro);
$periodo_sql = mysqli_real_escape_string($conexion, $periodo_filtro);
$mes_sql     = (int)$mes_filtro;
$anio_sql    = (int)$anio_filtro;

if ($periodo_filtro === 'MENSUAL') {
    $sql = "SELECT
                n.id_trabajador, n.dni_trabajador,
                t.apellidos_nombres, t.numero_documento,
                t.fecha_ingreso, t.banco_nombre, t.numero_cuenta, t.tiene_hijos,
                t.cuspp, /* FIX CUSPP */
                c.nombre_categoria, c.monto_categoria,
                p.nombre_puesto,
                asf.nombre_aseguradora,
                SUM(n.monto_neto_final) as monto_neto_final,
                GROUP_CONCAT(n.horas_normales_total SEPARATOR ',') as raw_hn,
                GROUP_CONCAT(n.horas_25_total SEPARATOR ',') as raw_h25,
                GROUP_CONCAT(n.horas_35_total SEPARATOR ',') as raw_h35,
                SUM(n.dias_trabajados) as dias_trabajados,
                SUM(n.monto_afp) as monto_afp,
                SUM(n.monto_base_afp) as monto_base_afp,
                SUM(n.bono_beta) as bono_beta,
                SUM(n.bono_extra_6) as bono_extra_6,
                SUM(n.bono_nocturno) as bono_nocturno,
                MAX(n.detalle_horarios) as detalle_horarios,
                MAX(n.id_nomina) as id_nomina,
                MAX(n.estado) as estado
            FROM nomina_procesada n
            JOIN trabajadores t ON n.id_trabajador = t.id_trabajador
            LEFT JOIN categorias_pago c ON t.id_categoria = c.id_categoria
            LEFT JOIN puestos p ON t.id_puesto = p.id_puesto
            LEFT JOIN aseguradoras asf ON t.id_aseguradora = asf.id_aseguradora
            WHERE n.estado = '{$estado_sql}'
              AND n.mes_pago = {$mes_sql}
              AND n.anio_pago = {$anio_sql}
              AND (n.periodo_pago = '1RA QUINCENA' OR n.periodo_pago = '2DA QUINCENA')
            GROUP BY n.id_trabajador
            ORDER BY t.apellidos_nombres ASC";
} else {
    $sql = "SELECT
                n.*,
                t.apellidos_nombres, t.numero_documento,
                t.fecha_ingreso, t.banco_nombre, t.numero_cuenta, t.tiene_hijos,
                t.cuspp, /* FIX CUSPP */
                c.nombre_categoria, c.monto_categoria,
                p.nombre_puesto,
                asf.nombre_aseguradora,
                asf.porcentaje_descuento as p_seguro
            FROM nomina_procesada n
            JOIN trabajadores t ON n.id_trabajador = t.id_trabajador
            LEFT JOIN categorias_pago c ON t.id_categoria = c.id_categoria
            LEFT JOIN puestos p ON t.id_puesto = p.id_puesto
            LEFT JOIN aseguradoras asf ON t.id_aseguradora = asf.id_aseguradora
            WHERE n.estado = '{$estado_sql}'
              AND n.mes_pago = {$mes_sql}
              AND n.anio_pago = {$anio_sql}
              AND n.periodo_pago = '{$periodo_sql}'
            ORDER BY t.apellidos_nombres ASC";
}

$res = mysqli_query($conexion, $sql);
if(!$res){
    die("<div style='font-family:sans-serif;padding:18px;border:1px solid #f5c2c7;background:#f8d7da;color:#842029;border-radius:10px;max-width:980px;margin:20px auto;'>
        <h4 style='margin:0 0 10px;'>Error SQL</h4>
        <pre style='margin:0;white-space:pre-wrap;'>".htmlspecialchars(mysqli_error($conexion))."</pre>
    </div>");
}
?>

<style>
    .table-gestion { background: white; border-radius: 12px; overflow: hidden; font-size: 0.85rem; }
    .table-gestion th { background-color: #1a4221; color: white; padding: 12px; font-weight: 500; text-transform: uppercase; }
    .table-gestion td { vertical-align: middle; padding: 8px 12px; border-bottom: 1px solid #eee; }

    .tramo-input { background: #f8f9fa; padding: 4px; border-radius: 6px; border: 1px solid #dee2e6; display: flex; align-items: center; gap: 3px; margin-bottom: 3px; }
    .time-field { width: 75px !important; border: 1px solid #ced4da; border-radius: 4px; text-align: center; font-weight: 600; color: #2c3e50; font-size: 0.8rem; }
    .f-txt { font-weight: 700; color: #0d6efd; font-size: 0.85rem; }

    .fila-oculta { display: none !important; }
    .pagination-container { display: flex; justify-content: center; gap: 10px; margin-top: 15px; padding: 10px; background: white; }
    .btn-page { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: 1px solid #ddd; background: white; cursor: pointer; }

    .toolbar-container {
        background: #fff; border-radius: 12px; padding: 15px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        border-left: 5px solid #1b4d2e;
        display: flex; flex-wrap: wrap; gap: 15px; align-items: center;
        margin-bottom: 20px;
    }
    .toolbar-title { flex-grow: 1; }
    .toolbar-group { display: flex; align-items: center; gap: 10px; }
    .separator { width: 1px; height: 30px; background: #e0e0e0; margin: 0 10px; }

    /* Modales y visor */
    .modal-backdrop { z-index: 2000 !important; }
    .modal { z-index: 2001 !important; }
    #visor-boleta-full { z-index: 9999 !important; }

    /* ====== PRINT FIX: que no empiece abajo ====== */
    @page { margin: 6mm; }

    @media print {
        html, body { height: auto !important; }
        body { margin: 0 !important; }
        body > * { display: none !important; }

        #visor-boleta-full {
            display: block !important;
            position: absolute !important;
            top: 0 !important; left: 0 !important;
            width: 100% !important; height: auto !important;
            background: white !important;
            overflow: visible !important;
            margin: 0 !important;
            padding: 0 !important;
            z-index: 999999 !important;
        }

        #visor-boleta-full .visor-content,
        #visor-boleta-full .visor-wrap,
        #contenedor-hojas {
            margin: 0 !important;
            padding: 0 !important;
        }

        .boleta-page {
            margin: 0 !important;
            box-shadow: none !important;
            width: auto !important;
            min-height: auto !important;
            page-break-after: always !important;
        }

        .no-print { display: none !important; }
    }

    .boleta-page {
        width: 21cm;
        min-height: 29.7cm;
        padding: 8mm;
        margin: 0 auto 16px auto;
        background: white;
        box-shadow: 0 0 15px rgba(0,0,0,0.2);
        position: relative;
        page-break-after: always;
        font-family: Arial, sans-serif;
    }
    .boleta-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-family: Arial, sans-serif; }
    .boleta-table td, .boleta-table th { border: 1px solid #000; padding: 4px 6px; font-size: 11px; }
    .header-info td { border: none !important; padding: 2px 0; font-size: 12px; }
    .totales-row { background-color: #f0f0f0; font-weight: bold; }
    .copia-label {
        position: absolute;
        top: 6mm; right: 6mm;
        font-size: 10px;
        font-weight: bold;
        border: 1px solid #000;
        padding: 4px 8px;
        background: #f8f9fa;
    }
</style>

<div class="p-4 no-print animate__animated animate__fadeIn">
    <input type="hidden" id="current_year" value="<?= (int)$anio_filtro ?>">

    <div class="toolbar-container">
        <div class="toolbar-title">
            <h5 class="fw-bold m-0 text-dark"><i class="bi bi-receipt-cutoff text-success me-2"></i>Gestión de Boletas</h5>
            <small class="text-muted">Control de horas y pagos</small>
        </div>

        <div class="toolbar-group">
            <select id="gest_mes" class="form-select form-select-sm fw-bold border-secondary bg-light" style="width: auto;" onchange="actualizarFiltros()">
                <?php
                $meses = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
                foreach($meses as $i => $m) {
                    $sel = ($mes_filtro == ($i+1)) ? 'selected' : '';
                    echo "<option value='".($i+1)."' $sel>$m</option>";
                }
                ?>
            </select>

            <select id="gest_peri" class="form-select form-select-sm fw-bold border-secondary bg-light" style="width: auto;" onchange="actualizarFiltros()">
                <option value="1RA QUINCENA" <?= $periodo_filtro == '1RA QUINCENA' ? 'selected' : '' ?>>1RA QUINCENA</option>
                <option value="2DA QUINCENA" <?= $periodo_filtro == '2DA QUINCENA' ? 'selected' : '' ?>>2DA QUINCENA</option>
                <option value="MENSUAL" <?= $periodo_filtro == 'MENSUAL' ? 'selected' : '' ?>>MENSUAL (LECTURA)</option>
            </select>
        </div>

        <div class="separator d-none d-md-block"></div>

        <div class="toolbar-group">
            <div class="input-group input-group-sm" style="width: 220px;">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="busc_gest" class="form-control" placeholder="Buscar empleado..." onkeyup="renderizarTabla()">
            </div>

            <div class="form-check form-switch m-0" title="Mostrar filas con monto cero">
                <input class="form-check-input" type="checkbox" id="chkVerCeros" onchange="renderizarTabla()">
                <label class="form-check-label small fw-bold text-muted" for="chkVerCeros">Ver S/0</label>
            </div>
        </div>

        <div class="separator d-none d-md-block"></div>

        <div class="btn-group shadow-sm">
            <a href="index.php?view=gestion&estado=BORRADOR&mes=<?= (int)$mes_filtro ?>&periodo=<?= urlencode($periodo_filtro) ?>"
               class="btn btn-sm <?= $estado_filtro == 'BORRADOR' ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?>">BORRADORES</a>
            <a href="index.php?view=gestion&estado=FINAL&mes=<?= (int)$mes_filtro ?>&periodo=<?= urlencode($periodo_filtro) ?>"
               class="btn btn-sm <?= $estado_filtro == 'FINAL' ? 'btn-success fw-bold' : 'btn-outline-secondary' ?>">PAGADOS</a>
        </div>
    </div>

    <div class="table-responsive table-gestion border shadow-sm">
        <table class="table table-hover mb-0" id="tablaBol">
            <thead>
                <tr>
                    <th class="ps-4">Colaborador</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Días</th>
                    <th class="text-center">Normal</th>
                    <th class="text-center text-warning">25%</th>
                    <th class="text-center text-danger">35%</th>
                    <th class="text-center">Neto</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody id="cuerpoTabla">
            <?php while($n = mysqli_fetch_assoc($res)):
                if($periodo_filtro === 'MENSUAL') {
                    $n['horas_normales_total'] = sumarHoras($n['raw_hn'] ?? '');
                    $n['horas_25_total']       = sumarHoras($n['raw_h25'] ?? '');
                    $n['horas_35_total']       = sumarHoras($n['raw_h35'] ?? '');
                }

                $n['detalle_horarios'] = $n['detalle_horarios'] ?: '{}';

                $jsonData = json_encode($n, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
                $b64 = base64_encode($jsonData);

                $neto = (float)($n['monto_neto_final'] ?? 0);
                $nombre = strtolower($n['apellidos_nombres'] ?? '');
                $dni = $n['dni_trabajador'] ?? $n['numero_documento'] ?? '';
            ?>
                <tr class="fila-dato" data-neto="<?= htmlspecialchars((string)$neto) ?>" data-nombre="<?= htmlspecialchars($nombre) ?>" data-dni="<?= htmlspecialchars((string)$dni) ?>">
                    <td class="ps-4">
                        <div class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($n['apellidos_nombres'] ?? '') ?></div>
                        <small class="text-muted"><?= htmlspecialchars((string)$dni) ?></small>
                    </td>
                    <td class="text-center"><span class="badge <?= $estado_filtro=='BORRADOR'?'bg-warning text-dark':'bg-success' ?>"><?= htmlspecialchars($estado_filtro) ?></span></td>
                    <td class="text-center fw-bold"><?= htmlspecialchars((string)($n['dias_trabajados'] ?? 0)) ?></td>
                    <td class="text-center font-monospace small bg-light"><?= htmlspecialchars((string)($n['horas_normales_total'] ?? '00:00')) ?></td>
                    <td class="text-center font-monospace small text-warning fw-bold"><?= htmlspecialchars((string)($n['horas_25_total'] ?? '00:00')) ?></td>
                    <td class="text-center font-monospace small text-danger fw-bold"><?= htmlspecialchars((string)($n['horas_35_total'] ?? '00:00')) ?></td>
                    <td class="text-center fw-bold text-success">S/ <?= number_format((float)($n['monto_neto_final'] ?? 0), 2) ?></td>
                    <td class="text-end pe-4">
                        <button class="btn-action bg-primary text-white me-1 border-0 rounded px-2 py-1"
                                onclick="mostrarVisor('<?= htmlspecialchars($b64, ENT_QUOTES, 'UTF-8') ?>')"
                                title="Ver Boleta"><i class="bi bi-eye-fill"></i></button>

                        <?php if($periodo_filtro !== 'MENSUAL'): ?>
                            <button class="btn-action bg-warning text-dark me-1 border-0 rounded px-2 py-1"
                                    onclick="abrirEditor('<?= htmlspecialchars($b64, ENT_QUOTES, 'UTF-8') ?>')"
                                    title="Editar Horarios"><i class="bi bi-pencil-fill"></i></button>

                            <?php if($estado_filtro === 'BORRADOR'): ?>
                                <button class="btn-action bg-success text-white border-0 rounded px-2 py-1"
                                        onclick="cambiarEstado(<?= (int)($n['id_nomina'] ?? 0) ?>, 'FINAL')"
                                        title="Aprobar Pago"><i class="bi bi-check-lg"></i></button>
                            <?php endif; ?>

                            <?php if($estado_filtro === 'FINAL'): ?>
                                <button class="btn-action bg-secondary text-white border-0 rounded px-2 py-1"
                                        onclick="cambiarEstado(<?= (int)($n['id_nomina'] ?? 0) ?>, 'BORRADOR')"
                                        title="Reabrir"><i class="bi bi-arrow-counterclockwise"></i></button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <div id="paginador" class="pagination-container"></div>
    </div>
</div>

<div class="modal fade no-print" id="modalEditar" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-xl-custom">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <div class="d-flex align-items-center">
                    <i class="bi bi-clock-history fs-4 me-2 text-success"></i>
                    <div>
                        <h6 class="modal-title mb-0 fw-bold">Editor de Asistencia</h6>
                        <small class="text-white-50" id="edit_nombre_trabajador">...</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form id="formEdicionNomina" onsubmit="return false;">
                <input type="hidden" name="accion" value="recalcular">
                <input type="hidden" name="id_nomina" id="edit_id_nomina">
                <input type="hidden" name="detalle_dias" id="edit_detalle_dias_json">

                <input type="hidden" name="monto_categoria" id="edit_monto_categoria">
                <input type="hidden" name="porcentaje_seguro" id="edit_porcentaje_seguro">
                <input type="hidden" name="tiene_hijos" id="edit_tiene_hijos">

                <input type="hidden" name="dias" id="edit_dias">
                <input type="hidden" name="hn" id="edit_hn">
                <input type="hidden" name="h25" id="edit_h25">
                <input type="hidden" name="h35" id="edit_h35">

                <div class="modal-body p-4 bg-light">
                    <div class="table-responsive rounded-3 bg-white shadow-sm border">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-success text-dark small text-uppercase">
                                <tr class="text-center">
                                    <th width="120">Fecha</th>
                                    <th>Horarios</th>
                                    <th>Norm.</th><th>25%</th><th>35%</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="bodyEditor"></tbody>
                            <tfoot id="footerEditor" class="text-center bg-light fw-bold"></tfoot>
                        </table>
                    </div>
                </div>

                <div class="modal-footer bg-white justify-content-between">
                    <button type="button" class="btn btn-outline-primary fw-bold rounded-pill" onclick="agregarDiaModal()">+ Agregar Fecha</button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light fw-bold rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success px-5 fw-bold rounded-pill shadow" onclick="guardarEdicionAjax()">
                            <i class="bi bi-check-circle-fill me-2"></i> GUARDAR
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="visor-boleta-full" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.95); overflow-y:auto;">
    <!-- FIX: sin py-5 para que no empuje hacia abajo -->
    <div class="visor-content visor-wrap d-flex justify-content-center">
        <button class="btn btn-danger position-fixed top-0 end-0 m-4 rounded-circle shadow no-print" onclick="cerrarVisor()" style="width:50px;height:50px;z-index:2001;"><i class="bi bi-x-lg"></i></button>
        <div style="width:100%; display:flex; flex-direction:column; align-items:center;">
             <button class="btn btn-light rounded-pill px-5 fw-bold shadow mb-3 no-print" onclick="window.print()"><i class="bi bi-printer me-2"></i> IMPRIMIR</button>
             <div id="contenedor-hojas"></div>
        </div>
    </div>
</div>

<script>
let paginaActual = 1;
const filasPorPagina = 10;
let todasLasFilas = [];
const ANIO = document.getElementById('current_year').value;
const RMV = <?= json_encode($rmv) ?>;
let modalInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    todasLasFilas = Array.from(document.querySelectorAll('.fila-dato'));
    document.querySelectorAll('.modal').forEach(m => document.body.appendChild(m));
    renderizarTabla();
});

function actualizarFiltros() {
    const mes = document.getElementById('gest_mes').value;
    const peri = document.getElementById('gest_peri').value;
    window.location.href = `index.php?view=gestion&estado=<?= htmlspecialchars($estado_filtro, ENT_QUOTES, 'UTF-8') ?>&mes=${mes}&periodo=${encodeURIComponent(peri)}`;
}

function renderizarTabla() {
    const texto = (document.getElementById('busc_gest').value || '').toLowerCase();
    const verCeros = document.getElementById('chkVerCeros').checked;

    const filtradas = todasLasFilas.filter(row => {
        const neto = parseFloat(row.dataset.neto) || 0;
        const match = (row.dataset.nombre || '').includes(texto) || (row.dataset.dni || '').includes(texto);
        return match && ((neto > 0) || verCeros);
    });

    const totalPags = Math.ceil(filtradas.length / filasPorPagina) || 1;
    if (paginaActual > totalPags) paginaActual = 1;

    const inicio = (paginaActual - 1) * filasPorPagina;
    const fin = inicio + filasPorPagina;

    todasLasFilas.forEach(r => r.classList.add('fila-oculta'));
    filtradas.slice(inicio, fin).forEach(r => r.classList.remove('fila-oculta'));

    const pag = document.getElementById('paginador');
    if (filtradas.length === 0) {
        pag.innerHTML = `<span class="small text-muted fw-bold">Sin resultados.</span>`;
    } else {
        pag.innerHTML =
            `<button class="btn-page" onclick="cambiarPagina(${paginaActual-1})" ${paginaActual===1?'disabled':''}><i class="bi bi-chevron-left"></i></button>
             <span class="mx-2 small fw-bold mt-1">Pág ${paginaActual} / ${totalPags}</span>
             <button class="btn-page" onclick="cambiarPagina(${paginaActual+1})" ${paginaActual===totalPags?'disabled':''}><i class="bi bi-chevron-right"></i></button>`;
    }
}
function cambiarPagina(p) { if (p > 0) { paginaActual = p; renderizarTabla(); } }

function decodeData(encoded) {
    try { return JSON.parse(atob(encoded)); }
    catch (e) { console.error("Error decodificando", e); return null; }
}

/* ===== helpers HH:MM para totales ===== */
function hhmmToMin(hhmm){
  if(!hhmm) return 0;
  const s = String(hhmm).trim();
  const parts = s.split(':');
  if(parts.length < 2) return 0;
  const h = parseInt(parts[0] || '0', 10);
  const m = parseInt(parts[1] || '0', 10);
  return (h*60) + m;
}
function minToHHMM(min){
  const total = Math.max(0, Math.round(min));
  const h = Math.floor(total / 60);
  const m = total % 60;
  return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
}

/* ==========================
   EDITOR (tu lógica original)
   ========================== */
function abrirEditor(encodedData){
    const data = decodeData(encodedData);
    if (!data) return alert("Error al cargar datos del trabajador");

    document.getElementById('edit_id_nomina').value = data.id_nomina;
    document.getElementById('edit_nombre_trabajador').innerText = data.apellidos_nombres;
    document.getElementById('edit_monto_categoria').value = data.monto_categoria || 0;
    document.getElementById('edit_porcentaje_seguro').value = data.p_seguro || 0;
    document.getElementById('edit_tiene_hijos').value = data.tiene_hijos || 0;

    const tbody = document.getElementById('bodyEditor'); tbody.innerHTML = "";
    let dias = {};
    try {
        if(data.detalle_horarios && data.detalle_horarios !== "null" && data.detalle_horarios !== "")
            dias = JSON.parse(data.detalle_horarios);
    } catch(e) { console.error("Error parseo JSON detalle_horarios", e); }

    const keys = Object.keys(dias).sort();
    if(keys.length > 0) {
        keys.forEach(f => {
            if(f === 'es_fijo' || f === 'backup_data') return;

            let raw = (dias[f].raw || "").split(',').filter(x => x);
            let html = '<div class="tramos-container d-flex flex-column gap-2">';

            if(raw.length === 0) html += genInp("08:00", "17:00", true);
            else {
                for(let i=0; i<raw.length; i+=2) {
                    let t1 = raw[i] ? raw[i].substr(0,5) : "00:00";
                    let t2 = raw[i+1] ? raw[i+1].substr(0,5) : "00:00";
                    html += genInp(t1, t2, i===0);
                }
            }
            html += '</div><button type="button" class="btn btn-link btn-sm p-0 text-success fw-bold text-decoration-none mt-1" onclick="addTramo(this)">+ Intervalo</button>';

            const tr = document.createElement('tr'); tr.className = "text-center align-middle";
            tr.innerHTML = `
                <td><span class="f-txt fw-bold text-primary">${f}</span></td>
                <td class="text-start">${html}</td>
                <td class="c-n fw-bold text-secondary"></td>
                <td class="c-25 fw-bold text-warning"></td>
                <td class="c-35 fw-bold text-danger"></td>
                <td><button type="button" class="btn btn-sm text-danger" onclick="borrarFilaModal(this)"><i class="bi bi-trash"></i></button></td>
            `;
            tbody.appendChild(tr);
            recalcM(tr.querySelector('input'));
        });
    } else {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">Sin registros previos.</td></tr>`;
    }

    sumM();
    const modalEl = document.getElementById('modalEditar');
    if(!modalInstance) modalInstance = new bootstrap.Modal(modalEl);
    modalInstance.show();
}

function genInp(v1, v2, esPrimero){
    const btn = esPrimero ? '' : `<button type="button" class="btn btn-sm text-danger ms-1 border-0" onclick="removerTramo(this)"><i class="bi bi-x-circle-fill"></i></button>`;
    return `<div class="tramo-input d-flex align-items-center gap-1">
              <input type="time" class="time-field form-control form-control-sm" value="${v1}" onchange="recalcM(this)">
              <i class="bi bi-arrow-right small text-muted"></i>
              <input type="time" class="time-field form-control form-control-sm" value="${v2}" onchange="recalcM(this)">
              ${btn}
            </div>`;
}
function addTramo(btn){
    const d=document.createElement('div');
    d.innerHTML=genInp("00:00","00:00",false);
    btn.previousElementSibling.appendChild(d.firstElementChild);
}
function removerTramo(btn){
    const f=btn.closest('tr');
    btn.closest('.tramo-input').remove();
    recalcM(f.querySelector('input'));
}
function borrarFilaModal(btn){
    btn.closest('tr').remove();
    sumM();
}

function agregarDiaModal(){
    const m = document.getElementById('gest_mes').value;
    const p = document.getElementById('gest_peri').value;
    let dI=1; if(p==='2DA QUINCENA'){ dI=16; }
    const fStr = `${ANIO}-${String(m).padStart(2,'0')}-${String(dI).padStart(2,'0')}`;
    const f = prompt(`Ingrese Fecha (YYYY-MM-DD):`, fStr);

    if(!f) return;
    if(!/^\d{4}-\d{2}-\d{2}$/.test(f)) return alert("Formato incorrecto. Use AAAA-MM-DD");

    let ex = false;
    document.querySelectorAll('.f-txt').forEach(s => { if(s.innerText === f) ex = true; });
    if(ex) return alert("Fecha ya existe en la lista.");

    const tbody = document.getElementById('bodyEditor');
    if(tbody.querySelector('td[colspan]')) tbody.innerHTML = "";

    const tr = document.createElement('tr'); tr.className="text-center align-middle";
    tr.innerHTML=`
        <td><span class="f-txt fw-bold text-primary">${f}</span></td>
        <td class="text-start">
            <div class="tramos-container d-flex flex-column gap-2">${genInp("08:00","17:00",true)}</div>
            <button type="button" class="btn btn-link btn-sm p-0 text-success fw-bold text-decoration-none mt-1" onclick="addTramo(this)">+ Intervalo</button>
        </td>
        <td class="c-n fw-bold text-secondary">08:00</td>
        <td class="c-25 fw-bold text-warning">00:00</td>
        <td class="c-35 fw-bold text-danger">00:00</td>
        <td><button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="borrarFilaModal(this)"><i class="bi bi-trash"></i></button></td>`;
    tbody.appendChild(tr);
    recalcM(tr.querySelector('input'));
}

function recalcM(el){
    if(!el) return;
    const tr = el.closest('tr');
    let s = 0;
    tr.querySelectorAll('.tramo-input').forEach(d => {
        const i = d.querySelectorAll('input');
        if(i.length === 2){
            const t1 = parseTime(i[0].value), t2 = parseTime(i[1].value);
            if(t2 > t1) s += (t2 - t1);
        }
    });
    let h = Math.round(s/300)*300 / 3600;
    let hN=Math.min(h,8), hResto=h-hN, h25=Math.min(hResto,2), h35=Math.max(0,hResto-2);
    tr.querySelector('.c-n').innerText  = decimalToTime(hN);
    tr.querySelector('.c-25').innerText = decimalToTime(h25);
    tr.querySelector('.c-35').innerText = decimalToTime(h35);
    sumM();
}

function sumM(){
    let n=0,e2=0,e3=0,dias=0;
    document.querySelectorAll('#bodyEditor tr').forEach(tr=>{
        if(tr.querySelector('.c-n')){
            n  += parseTime(tr.querySelector('.c-n').innerText)  || 0;
            e2 += parseTime(tr.querySelector('.c-25').innerText) || 0;
            e3 += parseTime(tr.querySelector('.c-35').innerText) || 0;
            if((parseTime(tr.querySelector('.c-n').innerText)||0) > 0) dias++;
        }
    });

    document.getElementById('footerEditor').innerHTML =
      `<tr>
         <td colspan="2" class="text-end pe-3 text-uppercase text-muted">Totales (${dias} días):</td>
         <td class="bg-light fw-bold text-dark">${decimalToTime(n/3600)}</td>
         <td class="bg-warning bg-opacity-25 fw-bold">${decimalToTime(e2/3600)}</td>
         <td class="bg-danger bg-opacity-25 fw-bold">${decimalToTime(e3/3600)}</td>
         <td></td>
       </tr>`;

    document.getElementById('edit_dias').value = dias;
    document.getElementById('edit_hn').value  = decimalToTime(n/3600);
    document.getElementById('edit_h25').value = decimalToTime(e2/3600);
    document.getElementById('edit_h35').value = decimalToTime(e3/3600);
}

function guardarEdicionAjax(){
    const btn = document.querySelector("button[onclick*='guardarEdicionAjax']");
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Guardando...`;

    sumM();

    const j = {};
    document.querySelectorAll('#bodyEditor tr').forEach(tr=>{
        const f = tr.querySelector('.f-txt')?.innerText;
        if(f){
            const t=[];
            tr.querySelectorAll('input.time-field').forEach(i=>{
                if(i.value){
                    let v=i.value; if(v.length===5) v+=":00";
                    t.push(v);
                }
            });
            if(t.length>0 && t.length%2===0) j[f]={raw:t.join(',')};
        }
    });
    document.getElementById('edit_detalle_dias_json').value = JSON.stringify(j);

    const fd = new FormData(document.getElementById('formEdicionNomina'));
    fetch('controllers/nomina_actions.php', { method: 'POST', body: fd })
    .then(r => r.text())
    .then(res => {
        if(res.trim() === 'OK' || res.includes('Éxito') || res.toLowerCase().includes('success')) {
            alert("✅ Guardado correctamente.");
            location.reload();
        } else {
            alert("❌ Error al guardar en base de datos:\n" + res);
        }
    })
    .catch(e => alert("❌ Error de conexión: " + e))
    .finally(() => { btn.disabled = false; btn.innerHTML = oldHtml; });
}

function decimalToTime(d){
    let s=Math.round(d*3600);
    return `${Math.floor(s/3600).toString().padStart(2,'0')}:${Math.floor((s%3600)/60).toString().padStart(2,'0')}`;
}
function parseTime(t){
    if(!t) return 0;
    if(t.length===5) t+=":00";
    let p=t.split(':');
    return (parseInt(p[0])*3600)+(parseInt(p[1])*60)+(parseInt(p[2]||0));
}

function cambiarEstado(id,st){
    Swal.fire({title:'¿Seguro?',icon:'question',showCancelButton:true,confirmButtonText:'Sí',confirmButtonColor:'#1b4d2e'}).then((r)=>{
        if(r.isConfirmed){
            const f=document.createElement('form');
            f.method='POST';
            f.action='controllers/nomina_actions.php';
            f.innerHTML=`<input type="hidden" name="id_nomina" value="${id}">
                         <input type="hidden" name="accion" value="cambiar_estado">
                         <input type="hidden" name="nuevo_estado" value="${st}">`;
            document.body.appendChild(f);
            f.submit();
        }
    });
}

/* ==========================
   VISOR: 2 copias (2 páginas)
   ========================== */
function mostrarVisor(encodedData){
    const d = decodeData(encodedData);
    if(!d) return;

    const v=document.getElementById('visor-boleta-full');
    if(!window.vm){document.body.appendChild(v);window.vm=true;}

    document.getElementById('contenedor-hojas').innerHTML = `
      <div class="boleta-page">
        <div class="copia-label">COPIA EMPLEADOR</div>
        ${genBolHTML(d)}
      </div>
      <div class="boleta-page">
        <div class="copia-label">COPIA TRABAJADOR</div>
        ${genBolHTML(d)}
      </div>
    `;

    v.style.display='block';
    document.body.style.overflow='hidden';
}

function cerrarVisor(){
    document.getElementById('visor-boleta-full').style.display='none';
    document.body.style.overflow='auto';
}

/* ==========================
   BOLETA (FIX CUSPP + AFP + Horas Totales)
   ========================== */
function genBolHTML(d){
    const meses=["ENERO","FEBRERO","MARZO","ABRIL","MAYO","JUNIO","JULIO","AGOSTO","SEPTIEMBRE","OCTUBRE","NOVIEMBRE","DICIEMBRE"];
    const ma = meses[parseInt(<?= (int)$mes_filtro ?>,10)-1] + " - " + <?= (int)$anio_filtro ?>;

    // Horas (HH:MM)
    const hnStr   = d.horas_normales_total || '00:00';
    const h25Str  = d.horas_25_total       || '00:00';
    const h35Str  = d.horas_35_total       || '00:00';
    const hNocStr = d.horas_noct_35_total  || '00:00'; // si no existe, queda 00:00

    // CALC Horas Totales (sin campo en BD)
    const horasTotales = minToHHMM(
        hhmmToMin(hnStr) + hhmmToMin(h25Str) + hhmmToMin(h35Str) + hhmmToMin(hNocStr)
    );

    // Para cálculos monetarios en horas decimales:
    const hN  = ptd(hnStr);
    const h25 = ptd(h25Str);
    const h35 = ptd(h35Str);

    const rb = parseFloat(d.monto_categoria||0);
    const rbh = rb / 30 / 8;
    const dias = parseFloat(d.dias_trabajados||0);

    const af = (String(d.tiene_hijos) === "1") ? ((RMV * 0.10 / 30) * dias) : 0;

    let jor = hN * rbh;
    let m25 = h25 * rbh * 1.25;
    let m35 = h35 * rbh * 1.35;
    let gr  = hN * rbh * 0.1666;
    let ct  = hN * rbh * 0.0972;

    const bn  = parseFloat(d.bono_nocturno||0);
    const bt  = parseFloat(d.bono_beta||0);
    const b6  = parseFloat(d.bono_extra_6||0);
    const dsc = parseFloat(d.monto_afp||0);
    const net = parseFloat(d.monto_neto_final||0);

    const base_db = parseFloat(d.monto_base_afp||0);
    const base_calc = jor + m25 + m35 + af + bn;

    if (Math.abs(base_db - base_calc) > 0.05) {
        const diff = base_db - base_calc;
        jor = jor + diff;
    }

    const tot = jor + m25 + m35 + af + bn + bt + b6 + gr + ct;

    const puesto = d.nombre_puesto || '---';
    const dni = d.dni_trabajador || d.numero_documento || '---';

    // FIX AFP duplicado:
    // Si nombre_aseguradora ya viene como "AFP INTEGRA", NO anteponer otro "AFP"
    let aseguradora = (d.nombre_aseguradora || 'SIN SEGURO').trim();
    const pensionTexto = aseguradora.toUpperCase().startsWith('AFP') ? aseguradora : ('AFP ' + aseguradora);

    // FIX CUSPP (la BD es cuspp)
    const cuspp = d.cuspp || '';

    const banco = d.banco_nombre || 'BANCO';
    const cuenta = d.numero_cuenta || '';

    return `
    <div style="display:flex;justify-content:space-between;margin-bottom:18px;">
      <div>
        <h4 style="margin:0;font-weight:bold;">GOLDFRUITS S.A.C</h4>
        <p style="margin:0;font-size:11px;">20603919433</p>
        <p style="margin:0;font-size:10px;">NRO. S/N C.P. DESAGRAVIO (ANTIGUA BASE MILITAR) LIMA - HUAURA - SANTA MARIA</p>
        <p style="margin:0;font-size:10px;">+51 977366677</p>
      </div>
      <img src="https://i.ibb.co/bgdpX9nn/56005109-316a-4192-a603-c6286c5f2661.jpg" style="width:110px;">
    </div>

    <div style="text-align:center;margin-bottom:12px;">
      <h3 style="margin:0;font-size:16px;text-decoration:underline;">BOLETA DE PAGO DE REMUNERACIONES</h3>
      <h4 style="margin:5px 0 0;font-size:12px;">${ma}</h4>
    </div>

    <div style="border:2px solid #bdbdbd;padding:8px 10px;margin-bottom:10px;">
      <div style="display:flex;justify-content:space-between;gap:10px;font-size:11px;">
        <div style="flex:1;">
          <div><b>Sr./Sra.:</b> ${d.apellidos_nombres || ''} <b>DNI -</b> ${dni}</div>
          <div style="margin-top:4px;"><b>Cargo:</b> ${puesto}</div>
          <div><b>C. costo:</b> ${d.centro_costo || 'Sin definir'}</div>
          <div><b>Situación Especial:</b> ${d.situacion_especial || 'Ninguna'}</div>
          <div><b>Área:</b> ${d.area || 'Producción'}</div>

          <div style="display:flex;gap:18px;margin-top:2px;">
            <div><b>Ingreso:</b> ${(d.fecha_ingreso || '').toString().slice(2)}</div>
            <div><b>Nro. Contrato:</b> ${d.nro_contrato || 1}</div>
          </div>

          <div style="display:flex;gap:18px;margin-top:2px;">
            <div><b>Pensión:</b> ${pensionTexto}</div>
            <div><b>Sueldo:</b> ${rb.toFixed(0)}</div>
          </div>

          <div style="margin-top:2px;"><b>CUSPP:</b> ${cuspp}</div>
        </div>

        <div style="width:250px;text-align:right;">
          <div><b>Dias Trabajados:</b> ${d.dias_trabajados || 0}</div>
          <div><b>Horas Trabajadas:</b> ${hnStr}</div>
          <div><b>Horas Ext 25%:</b> ${h25Str}</div>
          <div><b>Horas Ext 35%:</b> ${h35Str}</div>
          <div><b>Horas Noct. 35%:</b> ${hNocStr}</div>
          <div><b>Horas Totales:</b> ${horasTotales}</div>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;margin-top:6px;font-size:11px;font-weight:bold;color:#666;">
        <span>Inicio Vacación:</span>
        <span>Fin Vacación:</span>
        <span>Días Vacación: <b style="color:#111;">${d.dias_vacacion || 0}</b></span>
      </div>
    </div>

    <table class="boleta-table text-center">
      <thead class="totales-row">
        <tr>
          <th class="text-start">Concepto</th>
          <th>Haberes</th>
          <th>Descuentos</th>
          <th>Apo. Patronal</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="text-start" style="font-weight:bold;color:#666;">Horas trabajadas: Valor = <span style="color:#111;">${(hN).toFixed(2)}</span></td>
          <td></td><td></td><td></td>
        </tr>

        <tr><td class="text-start">Remuneración o Jornal</td><td style="text-align:right;">${jor.toFixed(2)}</td><td></td><td></td></tr>
        <tr><td class="text-start">Asignación Familiar</td><td style="text-align:right;">${af.toFixed(2)}</td><td></td><td></td></tr>
        <tr><td class="text-start">Remuneración horas extra (+ 25%)</td><td style="text-align:right;">${m25.toFixed(2)}</td><td></td><td></td></tr>
        <tr><td class="text-start">Remuneración horas extra (+ 35%)</td><td style="text-align:right;">${m35.toFixed(2)}</td><td></td><td></td></tr>
        <tr><td class="text-start">Remuneración horas nocturnas (35%)</td><td style="text-align:right;">${(0).toFixed(2)}</td><td></td><td></td></tr>

        <tr><td class="text-start">Gratificación Agrario 16.66%</td><td style="text-align:right;">${gr.toFixed(2)}</td><td></td><td></td></tr>
        <tr><td class="text-start">CTS Agrario 9.72%</td><td style="text-align:right;">${ct.toFixed(2)}</td><td></td><td></td></tr>
        <tr><td class="text-start">Bonif. Especial por Trab. Agrario Beta 30%</td><td style="text-align:right;">${bt.toFixed(2)}</td><td></td><td></td></tr>
        <tr><td class="text-start">Bonificación Extraordinaria 6%</td><td style="text-align:right;">${b6.toFixed(2)}</td><td></td><td></td></tr>
        <tr><td class="text-start">Otros haberes</td><td style="text-align:right;">${(0).toFixed(2)}</td><td></td><td></td></tr>

        <tr><td class="text-start">AFP/ONP (${aseguradora})</td><td></td><td style="text-align:right;">${dsc.toFixed(2)}</td><td></td></tr>
        <tr><td class="text-start">ESSALUD (6%)</td><td></td><td></td><td style="text-align:right;">${(base_db*0.06).toFixed(2)}</td></tr>
      </tbody>
      <tfoot class="totales-row">
        <tr>
          <td class="text-end">Totales:</td>
          <td style="text-align:right;">${tot.toFixed(2)}</td>
          <td style="text-align:right;">${dsc.toFixed(2)}</td>
          <td style="text-align:right;">${(base_db*0.06).toFixed(2)}</td>
        </tr>
      </tfoot>
    </table>

    <div style="margin-top:14px;text-align:center;font-size:16px;font-weight:bold;color:#666;">
      Liquido a Pagar: <span style="color:#111;">${net.toFixed(2)}</span>
    </div>

    <div style="margin-top:16px;font-size:10px;color:#777;line-height:1.4;">
      Certifico haber recibido los haberes indicados en la presente liquidación de Sueldo. Al mismo tiempo declaro no
      tener reclamo alguno a este respecto, en contra de la empresa: GOLDFRUITS S.A.C
    </div>

    <div style="margin-top:6px;font-size:10px;color:#777;">
      <b>Forma de pago:</b> Cuenta Ahorros en ${banco}, cuenta: ${cuenta}
    </div>

    <div style="margin-top:16px;text-align:center;font-size:10px;font-weight:bold;">
      <div style="width:260px;border-top:2px solid #111;margin:0 auto 6px auto;"></div>
      ${(d.apellidos_nombres || '').toString().toUpperCase()}
    </div>
    `;
}

function ptd(t){
    if(!t) return 0;
    const s = String(t).trim();
    if(s.includes(':')){
        const p = s.split(':');
        return (parseInt(p[0]||0,10)) + (parseInt(p[1]||0,10)/60);
    }
    const n = parseFloat(s);
    return isNaN(n) ? 0 : n;
}
</script>




