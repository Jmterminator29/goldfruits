<?php
// views/gestion_nominas.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

// Filtros
$estado_filtro = $_GET['estado'] ?? 'BORRADOR';
$mes_filtro = $_GET['mes'] ?? date('n');
$periodo_filtro = $_GET['periodo'] ?? '1RA QUINCENA';
$anio_filtro = date('Y');

// Función auxiliar PHP
function sumarHoras($cadena_horas) {
    if (!$cadena_horas) return "00:00";
    $lista = explode(',', $cadena_horas);
    $total_minutos = 0;
    foreach($lista as $h) {
        $partes = explode(':', trim($h));
        if(count($partes) >= 2) { 
            $total_minutos += ($partes[0] * 60) + $partes[1]; 
        }
    }
    return sprintf("%02d:%02d", floor($total_minutos / 60), $total_minutos % 60);
}

// CONSULTA SQL
if ($periodo_filtro == 'MENSUAL') {
    $sql = "SELECT n.id_trabajador, n.dni_trabajador,
                   t.apellidos_nombres, t.numero_documento, 
                   SUM(n.monto_neto_final) as monto_neto_final,
                   GROUP_CONCAT(n.horas_normales_total SEPARATOR ',') as raw_hn,
                   GROUP_CONCAT(n.horas_25_total SEPARATOR ',') as raw_h25,
                   GROUP_CONCAT(n.horas_35_total SEPARATOR ',') as raw_h35,
                   t.fecha_ingreso, t.banco_nombre, t.numero_cuenta, t.tiene_hijos,
                   c.nombre_categoria, c.monto_categoria, p.nombre_puesto, asf.nombre_aseguradora,
                   SUM(n.dias_trabajados) as dias_trabajados, 
                   SUM(n.monto_afp) as monto_afp,
                   SUM(n.bono_beta) as bono_beta,
                   SUM(n.bono_extra_6) as bono_extra_6,
                   SUM(n.bono_nocturno) as bono_nocturno,
                   n.detalle_horarios, n.id_nomina, n.estado
            FROM nomina_procesada n
            JOIN trabajadores t ON n.id_trabajador = t.id_trabajador
            LEFT JOIN categorias_pago c ON t.id_categoria = c.id_categoria
            LEFT JOIN puestos p ON t.id_puesto = p.id_puesto
            LEFT JOIN aseguradoras asf ON t.id_aseguradora = asf.id_aseguradora
            WHERE n.estado = '$estado_filtro' 
              AND n.mes_pago = '$mes_filtro' 
              AND (n.periodo_pago = '1RA QUINCENA' OR n.periodo_pago = '2DA QUINCENA')
            GROUP BY n.id_trabajador
            ORDER BY t.apellidos_nombres ASC";
} else {
    $sql = "SELECT n.*, t.apellidos_nombres, t.numero_documento,
                   t.fecha_ingreso, t.banco_nombre, t.numero_cuenta, t.tiene_hijos,
                   c.nombre_categoria, c.monto_categoria, p.nombre_puesto,
                   asf.nombre_aseguradora, asf.porcentaje_descuento as p_seguro
            FROM nomina_procesada n
            JOIN trabajadores t ON n.id_trabajador = t.id_trabajador
            LEFT JOIN categorias_pago c ON t.id_categoria = c.id_categoria
            LEFT JOIN puestos p ON t.id_puesto = p.id_puesto
            LEFT JOIN aseguradoras asf ON t.id_aseguradora = asf.id_aseguradora
            WHERE n.estado = '$estado_filtro' 
              AND n.mes_pago = '$mes_filtro' 
              AND n.periodo_pago = '$periodo_filtro'
            ORDER BY t.apellidos_nombres ASC";
}
$res = mysqli_query($conexion, $sql);
?>

<style>
    /* Estilos Premium */
    .table-gestion { background: white; border-radius: 12px; overflow: hidden; font-size: 0.85rem; }
    .table-gestion th { background-color: #1a4221; color: white; padding: 12px; font-weight: 500; text-transform: uppercase; }
    .table-gestion td { vertical-align: middle; padding: 8px 12px; border-bottom: 1px solid #eee; }
    
    .tramo-input { background: #f8f9fa; padding: 4px; border-radius: 6px; border: 1px solid #dee2e6; display: flex; align-items: center; gap: 3px; margin-bottom: 3px; }
    .time-field { width: 75px !important; border: 1px solid #ced4da; border-radius: 4px; text-align: center; font-weight: 600; color: #2c3e50; font-size: 0.8rem; }
    .f-txt { font-weight: 700; color: #0d6efd; font-size: 0.85rem; }
    
    /* Paginación */
    .fila-oculta { display: none !important; }
    .pagination-container { display: flex; justify-content: center; gap: 10px; margin-top: 15px; padding: 10px; background: white; }
    .btn-page { width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: 1px solid #ddd; background: white; cursor: pointer; }
    .btn-page:hover { background: #eee; }
    
    /* Boleta e Impresión */
    @media print {
        body > * { display: none !important; }
        #visor-boleta-full { display: block !important; position: absolute !important; top: 0 !important; left: 0 !important; width: 100% !important; height: auto !important; background: white !important; z-index: 999999 !important; }
        .boleta-page { margin: 0 !important; box-shadow: none !important; width: 100% !important; height: 100vh !important; page-break-after: always !important; }
    }
    .boleta-page { width: 21cm; min-height: 29.7cm; padding: 1cm; margin: 0 auto 20px auto; background: white; box-shadow: 0 0 15px rgba(0,0,0,0.2); position: relative; page-break-after: always; }
    .boleta-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-family: Arial, sans-serif; }
    .boleta-table td, .boleta-table th { border: 1px solid #000; padding: 4px 6px; font-size: 11px; } 
    .header-info td { border: none !important; padding: 2px 0; font-size: 12px; }
    .totales-row { background-color: #f0f0f0; font-weight: bold; }
    .copia-label { position: absolute; top: 15px; right: 15px; font-size: 10px; font-weight: bold; border: 1px solid #000; padding: 4px 8px; background: #f8f9fa; }
    
    /* FIX Z-INDEX PARA QUE NO SE ROMPA */
    .modal-backdrop { z-index: 1050 !important; }
    .modal { z-index: 1055 !important; }
</style>

<div class="p-4 no-print animate__animated animate__fadeIn">
    <input type="hidden" id="current_year" value="<?= $anio_filtro ?>">
    
    <div class="row mb-4 align-items-center bg-white p-3 rounded shadow-sm border-start border-4 border-success">
        <div class="col-md-3">
            <h5 class="fw-bold m-0 text-success"><i class="bi bi-receipt-cutoff me-2"></i>Gestión de Boletas</h5>
        </div>
        <div class="col-md-6 d-flex gap-2 align-items-center flex-wrap">
            <select id="gest_mes" class="form-select form-select-sm fw-bold border-success" style="width: auto;" onchange="actualizarFiltros()">
                <?php 
                $meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                foreach($meses as $i => $m) {
                    $sel = ($mes_filtro == ($i+1)) ? 'selected' : '';
                    echo "<option value='".($i+1)."' $sel>$m</option>";
                }
                ?>
            </select>
            <select id="gest_peri" class="form-select form-select-sm fw-bold border-success" style="width: auto;" onchange="actualizarFiltros()">
                <option value="1RA QUINCENA" <?= $periodo_filtro == '1RA QUINCENA' ? 'selected' : '' ?>>1RA QUINCENA</option>
                <option value="2DA QUINCENA" <?= $periodo_filtro == '2DA QUINCENA' ? 'selected' : '' ?>>2DA QUINCENA</option>
                <option value="MENSUAL" <?= $periodo_filtro == 'MENSUAL' ? 'selected' : '' ?>>MENSUAL (LECTURA)</option>
            </select>
            <div class="input-group input-group-sm" style="width: 200px;">
                <span class="input-group-text border-success bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="busc_gest" class="form-control border-success" placeholder="Buscar..." onkeyup="renderizarTabla()">
            </div>
            <div class="form-check form-switch ms-2 bg-light px-3 py-1 rounded border">
                <input class="form-check-input" type="checkbox" id="chkVerCeros" onchange="renderizarTabla()">
                <label class="form-check-label small fw-bold text-muted" for="chkVerCeros" style="cursor:pointer;">Ver S/ 0.00</label>
            </div>
        </div>
        <div class="col-md-3 text-end">
            <div class="btn-group shadow-sm">
                <a href="index.php?view=gestion&estado=BORRADOR&mes=<?= $mes_filtro ?>&periodo=<?= $periodo_filtro ?>" class="btn btn-sm <?= $estado_filtro == 'BORRADOR' ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?>">BORRADORES</a>
                <a href="index.php?view=gestion&estado=FINAL&mes=<?= $mes_filtro ?>&periodo=<?= $periodo_filtro ?>" class="btn btn-sm <?= $estado_filtro == 'FINAL' ? 'btn-success fw-bold' : 'btn-outline-secondary' ?>">PAGADOS</a>
            </div>
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
                    if($periodo_filtro == 'MENSUAL') {
                        $n['horas_normales_total'] = sumarHoras($n['raw_hn']);
                    }
                    $n['detalle_horarios'] = $n['detalle_horarios'] ?: '{}';
                    // USAR DATA ATTRIBUTE PARA EVITAR ERRORES DE PARSEO
                    $json_safe = htmlspecialchars(json_encode($n), ENT_QUOTES, 'UTF-8');
                    $neto = (float)$n['monto_neto_final'];
                    $nombre = strtolower($n['apellidos_nombres']);
                    $dni = $n['dni_trabajador'] ?? $n['numero_documento'];
                ?>
                <tr class="fila-dato" data-neto="<?= $neto ?>" data-nombre="<?= $nombre ?>" data-dni="<?= $dni ?>">
                    <td class="ps-4">
                        <div class="fw-bold text-dark text-uppercase"><?= $n['apellidos_nombres'] ?></div>
                        <small class="text-muted"><?= $dni ?></small>
                    </td>
                    <td class="text-center"><span class="badge <?= $estado_filtro=='BORRADOR'?'bg-warning text-dark':'bg-success' ?>"><?= $estado_filtro ?></span></td>
                    <td class="text-center fw-bold"><?= $n['dias_trabajados'] ?></td>
                    <td class="text-center font-monospace small bg-light"><?= $n['horas_normales_total'] ?></td>
                    <td class="text-center font-monospace small text-warning fw-bold"><?= $n['horas_25_total'] ?></td>
                    <td class="text-center font-monospace small text-danger fw-bold"><?= $n['horas_35_total'] ?></td>
                    <td class="text-center fw-bold text-success">S/ <?= number_format($n['monto_neto_final'], 2) ?></td>
                    <td class="text-end pe-4">
                        <button class="btn-action bg-primary text-white me-1" 
                                onclick='mostrarVisor(this)' 
                                data-json='<?= $json_safe ?>' 
                                title="Ver Boleta"><i class="bi bi-eye-fill"></i></button>
                        
                        <?php if($periodo_filtro != 'MENSUAL'): ?>
                            <button class="btn-action bg-warning text-dark me-1" 
                                    onclick='abrirEditor(this)' 
                                    data-json='<?= $json_safe ?>'
                                    title="Editar Horarios"><i class="bi bi-pencil-fill"></i></button>
                            
                            <?php if($estado_filtro == 'BORRADOR'): ?>
                            <button class="btn-action bg-success text-white" onclick="cambiarEstado(<?= $n['id_nomina'] ?>, 'FINAL')" title="Aprobar Pago"><i class="bi bi-check-lg"></i></button>
                            <?php endif; ?>
                            <?php if($estado_filtro == 'FINAL'): ?>
                            <button class="btn-action bg-secondary text-white" onclick="cambiarEstado(<?= $n['id_nomina'] ?>, 'BORRADOR')" title="Reabrir"><i class="bi bi-arrow-counterclockwise"></i></button>
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
                    <div><h6 class="modal-title mb-0 fw-bold">Editor de Asistencia Detallada</h6><small class="text-white-50" id="edit_nombre_trabajador">...</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <form action="controllers/nomina_actions.php" method="POST">
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
                                    <th>Intervalos de Horas</th>
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
                        <button type="submit" class="btn btn-success px-5 fw-bold rounded-pill shadow" onclick="prepararEnvio()">
                            <i class="bi bi-check-circle-fill me-2"></i> GUARDAR CAMBIOS
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="visor-boleta-full" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.95); overflow-y:auto;">
    <div class="visor-content d-flex justify-content-center py-5">
        <button class="btn btn-danger position-fixed top-0 end-0 m-4 rounded-circle shadow no-print" onclick="cerrarVisor()" style="width:50px;height:50px;z-index:2001;"><i class="bi bi-x-lg"></i></button>
        <div style="width:100%; display:flex; flex-direction:column; align-items:center;">
             <button class="btn btn-light rounded-pill px-5 fw-bold shadow mb-4 no-print" onclick="window.print()"><i class="bi bi-printer me-2"></i> IMPRIMIR BOLETAS (2 HOJAS)</button>
             <div id="contenedor-hojas"></div>
        </div>
    </div>
</div>

<script>
// --- VARIABLES PAGINACION ---
let paginaActual = 1; const filasPorPagina = 10; let todasLasFilas = [];
const ANIO = document.getElementById('current_year').value;
let modalInstance = null;

document.addEventListener('DOMContentLoaded', function() {
    todasLasFilas = Array.from(document.querySelectorAll('.fila-dato'));
    // Mover modales al final del body para evitar conflictos de z-index
    document.querySelectorAll('.modal').forEach(m => document.body.appendChild(m));
    renderizarTabla();
});

function actualizarFiltros() {
    const mes = document.getElementById('gest_mes').value, peri = document.getElementById('gest_peri').value;
    window.location.href = `index.php?view=gestion&estado=<?= $estado_filtro ?>&mes=${mes}&periodo=${peri}`;
}

function renderizarTabla() {
    const texto = document.getElementById('busc_gest').value.toLowerCase();
    const verCeros = document.getElementById('chkVerCeros').checked;
    
    const filtradas = todasLasFilas.filter(row => {
        const neto = parseFloat(row.dataset.neto) || 0;
        const match = row.dataset.nombre.includes(texto) || row.dataset.dni.includes(texto);
        return match && ((neto > 0) || verCeros);
    });

    const totalPags = Math.ceil(filtradas.length / filasPorPagina) || 1;
    if (paginaActual > totalPags) paginaActual = 1;
    
    const inicio = (paginaActual - 1) * filasPorPagina;
    const fin = inicio + filasPorPagina;
    
    todasLasFilas.forEach(r => r.classList.add('fila-oculta'));
    filtradas.slice(inicio, fin).forEach(r => r.classList.remove('fila-oculta'));

    const pag = document.getElementById('paginador');
    if (filtradas.length === 0) pag.innerHTML = `<span class="small text-muted fw-bold">Sin resultados.</span>`;
    else pag.innerHTML = `
        <button class="btn-page" onclick="cambiarPagina(${paginaActual-1})" ${paginaActual===1?'disabled':''}><i class="bi bi-chevron-left"></i></button>
        <span class="mx-2 small fw-bold mt-1">Pág ${paginaActual} de ${totalPags} (${filtradas.length})</span>
        <button class="btn-page" onclick="cambiarPagina(${paginaActual+1})" ${paginaActual===totalPags?'disabled':''}><i class="bi bi-chevron-right"></i></button>`;
}
function cambiarPagina(p) { if(p>0) { paginaActual=p; renderizarTabla(); } }

// --- EDITOR LOGICA BLINDADA Y AUTOMÁTICA ---
function abrirEditor(btn){
    // Recuperar datos de forma segura
    const data = JSON.parse(btn.getAttribute('data-json'));

    document.getElementById('edit_id_nomina').value = data.id_nomina; 
    document.getElementById('edit_nombre_trabajador').innerText = data.apellidos_nombres;
    document.getElementById('edit_monto_categoria').value = data.monto_categoria || 0; 
    document.getElementById('edit_porcentaje_seguro').value = data.p_seguro || 0; 
    document.getElementById('edit_tiene_hijos').value = data.tiene_hijos || 0;

    const tbody = document.getElementById('bodyEditor'); tbody.innerHTML = "";
    
    let dias = {}; 
    try { 
        if(data.detalle_horarios && data.detalle_horarios!=="null") dias = JSON.parse(data.detalle_horarios); 
    } catch(e) { console.warn("JSON error", e); }
    
    const keys = Object.keys(dias).sort();
    
    if(keys.length === 0) {
        // Nada que mostrar
    } else {
        keys.forEach(f => {
            let raw = (dias[f].raw || "").split(',').filter(x => x);
            let html = '<div class="tramos-container d-flex flex-column gap-2">';
            if(raw.length === 0) html += genInp("08:00", "17:00", true);
            else {
                // FIX CRÍTICO: Prevenir error si falta la hora de salida
                for(let i=0; i<raw.length; i+=2) {
                    let t1 = raw[i] ? raw[i].substr(0,5) : "00:00";
                    let t2 = (raw[i+1]) ? raw[i+1].substr(0,5) : "00:00"; // <-- Aquí estaba el fallo
                    html += genInp(t1, t2, i===0);
                }
            }
            html += '</div><button type="button" class="btn btn-link btn-sm p-0 text-success fw-bold text-decoration-none mt-1" onclick="addTramo(this)">+ Intervalo</button>';
            const tr = document.createElement('tr'); tr.className = "text-center align-middle";
            tr.innerHTML = `<td><span class="f-txt fw-bold text-primary">${f}</span></td><td class="text-start">${html}</td><td class="c-n fw-bold text-secondary"></td><td class="c-25 fw-bold text-warning"></td><td class="c-35 fw-bold text-danger"></td><td><button type="button" class="btn btn-sm text-danger" onclick="borrarFilaModal(this)"><i class="bi bi-trash"></i></button></td>`;
            tbody.appendChild(tr);
            recalcM(tr.querySelector('input'));
        });
    }
    sumM(); 
    
    const modalEl = document.getElementById('modalEditar');
    if(!modalInstance) modalInstance = new bootstrap.Modal(modalEl);
    modalInstance.show();
}

function genInp(v1, v2, esPrimero){
    const btn = esPrimero ? '' : `<button type="button" class="btn btn-sm text-danger ms-1 border-0" onclick="removerTramo(this)"><i class="bi bi-x-circle-fill"></i></button>`;
    return `<div class="tramo-input d-flex align-items-center gap-1"><input type="time" class="time-field form-control form-control-sm" value="${v1}" onchange="recalcM(this)"><i class="bi bi-arrow-right small text-muted"></i><input type="time" class="time-field form-control form-control-sm" value="${v2}" onchange="recalcM(this)">${btn}</div>`;
}

function addTramo(btn){ const d=document.createElement('div'); d.innerHTML=genInp("00:00","00:00",false); btn.previousElementSibling.appendChild(d.firstElementChild); }
function removerTramo(btn){ const f=btn.closest('tr'); btn.closest('.tramo-input').remove(); recalcM(f.querySelector('input')); }
function borrarFilaModal(btn){ btn.closest('tr').remove(); sumM(); }

function agregarDiaModal(){
    const m = document.getElementById('gest_mes').value, p = document.getElementById('gest_peri').value;
    let dI=1; if(p==='2DA QUINCENA'){ dI=16; }
    const fStr = `${ANIO}-${String(m).padStart(2,'0')}-${String(dI).padStart(2,'0')}`;
    const f = prompt(`Ingrese Fecha (YYYY-MM-DD):`, fStr);
    if(!f) return;
    
    let ex = false; document.querySelectorAll('.f-txt').forEach(s=>{if(s.innerText==f)ex=true});
    if(ex) return alert("Esa fecha ya está en la lista.");

    const tbody = document.getElementById('bodyEditor');
    const tr = document.createElement('tr'); tr.className="text-center align-middle";
    tr.innerHTML=`<td><span class="f-txt fw-bold text-primary">${f}</span></td><td class="text-start"><div class="tramos-container d-flex flex-column gap-2">${genInp("08:00","17:00",true)}</div><button type="button" class="btn btn-link btn-sm p-0 text-success fw-bold text-decoration-none mt-1" onclick="addTramo(this)">+ Intervalo</button></td><td class="c-n fw-bold text-secondary">08:00</td><td class="c-25 fw-bold text-warning">00:00</td><td class="c-35 fw-bold text-danger">00:00</td><td><button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="borrarFilaModal(this)"><i class="bi bi-trash"></i></button></td>`;
    tbody.appendChild(tr); 
    recalcM(tr.querySelector('input'));
}

// CÁLCULO AUTOMÁTICO (Igual a procesar nómina)
function recalcM(el){
    if(!el)return; const tr=el.closest('tr'); let s=0; 
    tr.querySelectorAll('.tramo-input').forEach(d=>{ 
        const i=d.querySelectorAll('input'); 
        if(i.length==2){ 
            const t1=parseTime(i[0].value), t2=parseTime(i[1].value); 
            if(t2>t1) s+=(t2-t1); 
        } 
    }); 
    let h = Math.round(s/300)*300 / 3600; // Redondeo a 5min
    
    // Regla de Negocio: 8h Normal, 2h al 25%, Resto al 35%
    let hN = Math.min(h, 8);
    let hResto = h - hN;
    let h25 = Math.min(hResto, 2);
    let h35 = Math.max(0, hResto - 2);

    tr.querySelector('.c-n').innerText = decimalToTime(hN); 
    tr.querySelector('.c-25').innerText = decimalToTime(h25); 
    tr.querySelector('.c-35').innerText = decimalToTime(h35); 
    sumM(); 
}

function sumM(){ 
    let n=0,e2=0,e3=0,dias=0; 
    document.querySelectorAll('#bodyEditor tr').forEach(tr=>{ 
        if(tr.querySelector('.c-n')){ 
            n+=parseTime(tr.querySelector('.c-n').innerText)||0; 
            e2+=parseTime(tr.querySelector('.c-25').innerText)||0; 
            e3+=parseTime(tr.querySelector('.c-35').innerText)||0; 
            if((parseTime(tr.querySelector('.c-n').innerText)||0) > 0) dias++;
        } 
    }); 
    document.getElementById('footerEditor').innerHTML=`<tr><td colspan="2" class="text-end pe-3 text-uppercase text-muted">Totales (${dias} días):</td><td class="bg-light fw-bold text-dark">${decimalToTime(n/3600)}</td><td class="bg-warning bg-opacity-25 fw-bold">${decimalToTime(e2/3600)}</td><td class="bg-danger bg-opacity-25 fw-bold">${decimalToTime(e3/3600)}</td><td></td></tr>`;
    
    document.getElementById('edit_dias').value = dias;
    document.getElementById('edit_hn').value = decimalToTime(n/3600);
    document.getElementById('edit_h25').value = decimalToTime(e2/3600);
    document.getElementById('edit_h35').value = decimalToTime(e3/3600);
}

function prepararEnvio(){
    const j = {};
    document.querySelectorAll('#bodyEditor tr').forEach(tr=>{
        const f = tr.querySelector('.f-txt')?.innerText;
        if(f){
            const t=[];
            tr.querySelectorAll('input.time-field').forEach(i=>{ if(i.value){ let v=i.value; if(v.length===5)v+=":00"; t.push(v); } });
            if(t.length>0 && t.length%2===0) j[f]={raw:t.join(',')};
        }
    });
    document.getElementById('edit_detalle_dias_json').value = JSON.stringify(j);
}

// Helpers Tiempo
function decimalToTime(d){ let s=Math.round(d*3600); return `${Math.floor(s/3600).toString().padStart(2,'0')}:${Math.floor((s%3600)/60).toString().padStart(2,'0')}`; }
function parseTime(t){ if(!t)return 0; if(t.length===5)t+=":00"; let p=t.split(':'); return (parseInt(p[0])*3600)+(parseInt(p[1])*60)+(parseInt(p[2]||0)); }
function cambiarEstado(id,st){Swal.fire({title:'¿Seguro?',icon:'question',showCancelButton:true,confirmButtonText:'Sí',confirmButtonColor:'#1b4d2e'}).then((r)=>{if(r.isConfirmed){const f=document.createElement('form');f.method='POST';f.action='controllers/nomina_actions.php';f.innerHTML=`<input type="hidden" name="id_nomina" value="${id}"><input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="nuevo_estado" value="${st}">`;document.body.appendChild(f);f.submit();}});}

// VISOR SEGURO
function mostrarVisor(btn){
    const d = JSON.parse(btn.getAttribute('data-json'));
    const v=document.getElementById('visor-boleta-full');
    if(!window.vm){document.body.appendChild(v);window.vm=true;}
    document.getElementById('contenedor-hojas').innerHTML=`<div class="boleta-page"><div class="copia-label">COPIA EMPLEADOR</div>${genBolHTML(d)}</div><div class="boleta-page"><div class="copia-label">COPIA TRABAJADOR</div>${genBolHTML(d)}</div>`;
    v.style.display='block';document.body.style.overflow='hidden';
}
function genBolHTML(d){
    const m=["ENERO","FEBRERO","MARZO","ABRIL","MAYO","JUNIO","JULIO","AGOSTO","SEPTIEMBRE","OCTUBRE","NOVIEMBRE","DICIEMBRE"];
    const ma=m[parseInt(<?= $mes_filtro ?>)-1]+" - "+<?= date('Y') ?>;
    const hN=ptd(d.horas_normales_total), h25=ptd(d.horas_25_total), h35=ptd(d.horas_35_total);
    const rb=parseFloat(d.monto_categoria||0), rbh=rb/30/8;
    const af=d.tiene_hijos=="1"?(1130/30/8*0.1*hN):0, jor=hN*rbh, m25=h25*rbh*1.25, m35=h35*rbh*1.35;
    const gr=hN*rbh*0.1666, ct=hN*rbh*0.0972, bn=parseFloat(d.bono_nocturno||0), bt=parseFloat(d.bono_beta||0), b6=parseFloat(d.bono_extra_6||0);
    const tot=jor+m25+m35+gr+ct+af+bn+bt+b6, dsc=parseFloat(d.monto_afp||0), net=parseFloat(d.monto_neto_final||0);
    return `<div style="display:flex;justify-content:space-between;margin-bottom:20px;"><div><h4 style="margin:0;font-weight:bold;">GOLDFRUITS S.A.C</h4><p style="margin:0;font-size:11px;">20603919433</p></div><img src="https://i.ibb.co/bgdpX9nn/56005109-316a-4192-a603-c6286c5f2661.jpg" style="width:100px;"></div><div style="text-align:center;margin-bottom:15px;"><h3 style="margin:0;font-size:16px;text-decoration:underline;">BOLETA DE PAGO</h3><h4 style="margin:5px 0 0;font-size:13px;">${ma}</h4></div><table class="boleta-table header-info" style="margin-bottom:15px;"><tr><td width="15%">Trabajador:</td><td width="50%"><strong>${d.apellidos_nombres}</strong></td><td width="20%">Días:</td><td width="15%"><strong>${d.dias_trabajados}</strong></td></tr><tr><td>Cargo:</td><td>${d.nombre_puesto||'---'}</td><td>Horas:</td><td>${d.horas_normales_total}</td></tr><tr><td>DNI:</td><td>${d.dni_trabajador||d.numero_documento}</td><td>25%:</td><td>${d.horas_25_total}</td></tr><tr><td>Base:</td><td>S/ ${rb.toFixed(2)}</td><td>35%:</td><td>${d.horas_35_total}</td></tr></table><table class="boleta-table text-center"><thead class="totales-row"><tr><th class="text-start">Concepto</th><th>Ingresos</th><th>Dctos</th><th>Aporte</th></tr></thead><tbody><tr><td class="text-start">Jornal Básico</td><td>${jor.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Asignación Familiar</td><td>${af.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Horas Extras 25%</td><td>${m25.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Horas Extras 35%</td><td>${m35.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Bono Nocturno</td><td>${bn.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Gratificación</td><td>${gr.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">CTS</td><td>${ct.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Bono Beta</td><td>${bt.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Bono Extra</td><td>${b6.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">AFP/ONP (${d.nombre_aseguradora})</td><td></td><td>${dsc.toFixed(2)}</td><td></td></tr><tr><td class="text-start">ESSALUD (9%)</td><td></td><td></td><td>${(tot*0.09).toFixed(2)}</td></tr></tbody><tfoot class="totales-row"><tr><td class="text-end">TOTALES:</td><td>${tot.toFixed(2)}</td><td>${dsc.toFixed(2)}</td><td>${(tot*0.09).toFixed(2)}</td></tr></tfoot></table><div style="margin-top:15px;text-align:right;font-size:1.2rem;font-weight:bold;border-top:2px solid #000;padding-top:10px;">NETO: S/ ${net.toFixed(2)}</div><div class="firma-linea" style="margin-top:50px;">${d.apellidos_nombres}</div>`;
}
function cerrarVisor(){document.getElementById('visor-boleta-full').style.display='none';document.body.style.overflow='auto';}
function ptd(t){if(!t)return 0;const p=t.split(':');return parseInt(p[0])+(parseInt(p[1])/60);}
</script>
