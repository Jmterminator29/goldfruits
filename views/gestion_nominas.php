<?php
// views/gestion_nominas.php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

$estado_filtro = $_GET['estado'] ?? 'BORRADOR';
$mes_filtro = $_GET['mes'] ?? date('n');
$periodo_filtro = $_GET['periodo'] ?? '1RA QUINCENA';
$anio_filtro = date('Y');

// Función auxiliar PHP para sumar horas en formato texto
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
                   n.detalle_horarios
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
    /* --- ESTILOS GENERALES PANTALLA --- */
    /* FIX CRÍTICO PARA MODAL FONDO NEGRO */
    .modal-backdrop { z-index: 10000 !important; }
    .modal { z-index: 10001 !important; }
    
    #visor-boleta-full { z-index: 20000 !important; }

    @media (min-width: 1200px) { .modal-xl-custom { max-width: 95% !important; } }

    .table-gestion { background: white; border-radius: 12px; overflow: hidden; font-size: 0.85rem; }
    .table-gestion th { background-color: #1a4221; color: white; padding: 12px; font-weight: 500; text-transform: uppercase; }
    .table-gestion td { vertical-align: middle; padding: 8px 12px; border-bottom: 1px solid #eee; }
    
    .btn-action { width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; transition: 0.2s; border: none; }
    .btn-action:hover { transform: scale(1.1); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    
    .status-badge { font-size: 0.75rem; padding: 4px 8px; border-radius: 6px; font-weight: bold; }
    .status-borrador { background: #fff3cd; color: #856404; }
    .status-final { background: #d1e7dd; color: #0f5132; }

    /* --- CLASES DE PAGINACIÓN Y FILTRO --- */
    .fila-oculta { display: none !important; }
    .pagination-container { display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 15px; padding: 10px; background: white; border-radius: 0 0 12px 12px; border-top: 1px solid #eee; }
    .btn-page { width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: 1px solid #ddd; background: white; color: #333; font-weight: bold; cursor: pointer; transition: 0.2s; }
    .btn-page:hover { background: #f8f9fa; }
    .btn-page.disabled { opacity: 0.5; cursor: default; }

    /* --- ESTILOS DE LA BOLETA --- */
    .boleta-page {
        width: 21cm;
        min-height: 29.7cm;
        padding: 1cm;
        margin: 0 auto 20px auto; 
        background: white;
        box-shadow: 0 0 15px rgba(0,0,0,0.2); 
        box-sizing: border-box;
        position: relative;
        page-break-after: always;
    }
    .boleta-page:last-child { page-break-after: auto; }

    .boleta-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-family: Arial, sans-serif; }
    .boleta-table td, .boleta-table th { border: 1px solid #000; padding: 4px 6px; font-size: 11px; } 
    .header-info td { border: none !important; padding: 2px 0; font-size: 12px; }
    .totales-row { background-color: #f0f0f0; font-weight: bold; }
    .firma-linea { border-top: 1px solid #000; width: 250px; text-align: center; margin: 60px auto 0; font-size: 11px; padding-top: 5px; text-transform: uppercase; }
    
    .copia-label {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 10px;
        font-weight: bold;
        border: 1px solid #000;
        padding: 4px 8px;
        background: #f8f9fa;
        text-transform: uppercase;
    }
    
    /* MODAL TRAMOS */
    .tramo-box { display: flex; align-items: center; gap: 5px; background: #fff; padding: 5px; border-radius: 5px; border: 1px solid #ddd; margin-bottom: 5px; }
    .inp-time-mini { width: 80px; text-align: center; border: 1px solid #ccc; border-radius: 4px; font-weight: bold; }

    /* --- ESTILOS DE IMPRESIÓN --- */
    @media print {
        @page { size: A4; margin: 0; }
        body { margin: 0; padding: 0; background: white; }
        body > * { display: none !important; }
        #visor-boleta-full { 
            display: block !important; position: absolute !important;
            top: 0 !important; left: 0 !important;
            width: 100% !important; height: auto !important;
            background: white !important; z-index: 999999 !important;
            overflow: visible !important;
        }
        .boleta-page {
            margin: 0 !important; box-shadow: none !important;
            width: 100% !important; height: 100vh !important;
            page-break-after: always !important;
        }
        .visor-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .no-print, .btn, button { display: none !important; }
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }
</style>

<div class="p-4 no-print animate__animated animate__fadeIn">
    <input type="hidden" id="current_year" value="<?= $anio_filtro ?>">

    <div class="row mb-4 align-items-center bg-white p-3 rounded shadow-sm border-start border-4 border-success">
        <div class="col-md-3">
            <h5 class="fw-bold m-0 text-success"><i class="bi bi-receipt-cutoff me-2"></i>Gestión de Boletas</h5>
        </div>
        
        <div class="col-md-6 d-flex gap-2 mt-2 mt-md-0 align-items-center flex-wrap">
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
                <input type="text" id="busc_gest" class="form-control border-success" placeholder="Buscar trabajador..." onkeyup="renderizarTabla()">
            </div>

            <div class="form-check form-switch ms-2 bg-light px-3 py-1 rounded border">
                <input class="form-check-input" type="checkbox" id="chkVerCeros" onchange="renderizarTabla()">
                <label class="form-check-label small fw-bold text-muted" for="chkVerCeros" style="cursor:pointer;">Ver S/ 0.00</label>
            </div>
        </div>

        <div class="col-md-3 text-end mt-2 mt-md-0">
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
                    <th class="text-center">Horas</th>
                    <th class="text-center" style="background:#2C3E50; color:#F1C40F;">B.Noct</th>
                    <th class="text-center">Neto</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody id="cuerpoTabla">
                <?php while($n = mysqli_fetch_assoc($res)): 
                    if($periodo_filtro == 'MENSUAL') {
                        $n['horas_normales_total'] = sumarHoras($n['raw_hn']);
                    }
                    $json_safe = htmlspecialchars(json_encode($n), ENT_QUOTES, 'UTF-8');
                    $neto = (float)$n['monto_neto_final'];
                    $nombre = strtolower($n['apellidos_nombres']);
                    $dni = $n['dni_trabajador'] ?? $n['numero_documento'];
                ?>
                <tr class="fila-dato" 
                    data-neto="<?= $neto ?>" 
                    data-nombre="<?= $nombre ?>" 
                    data-dni="<?= $dni ?>">
                    
                    <td class="ps-4">
                        <div class="fw-bold text-dark text-uppercase"><?= $n['apellidos_nombres'] ?></div>
                        <small class="text-muted"><?= $dni ?></small>
                    </td>
                    <td class="text-center">
                        <span class="status-badge <?= $estado_filtro=='BORRADOR'?'status-borrador':'status-final' ?>">
                            <?= $estado_filtro ?>
                        </span>
                    </td>
                    <td class="text-center fw-bold"><?= $n['dias_trabajados'] ?></td>
                    <td class="text-center font-monospace small bg-light rounded"><?= $n['horas_normales_total'] ?></td>
                    
                    <td class="text-center fw-bold" style="color:#D35400;">
                        <?= ($n['bono_nocturno'] > 0) ? 'S/ '.number_format($n['bono_nocturno'], 2) : '-' ?>
                    </td>

                    <td class="text-center fw-bold text-success">S/ <?= number_format($n['monto_neto_final'], 2) ?></td>
                    <td class="text-end pe-4">
                        <button class="btn-action bg-primary text-white me-1" onclick='mostrarVisor(<?= $json_safe ?>)' title="Ver Boleta"><i class="bi bi-eye-fill"></i></button>
                        
                        <?php if($periodo_filtro != 'MENSUAL'): ?>
                            <button class="btn-action bg-warning text-dark me-1" onclick='abrirEditor(<?= $json_safe ?>)' title="Editar Horarios"><i class="bi bi-pencil-fill"></i></button>
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

        <div id="paginador" class="pagination-container">
            </div>
    </div>
</div>

<div class="modal fade no-print" id="modalEditar" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-xl-custom">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-calendar-week me-2"></i>Edición Detallada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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

                <div class="modal-body p-4 bg-light">
                    <div class="d-flex justify-content-between align-items-center mb-3 bg-white p-3 rounded shadow-sm">
                        <h5 class="fw-bold m-0 text-uppercase text-primary" id="edit_nombre_trabajador"></h5>
                        <div>
                            <button type="button" class="btn btn-outline-dark me-2" onclick="llenarTodo8Horas()"><i class="bi bi-magic me-1"></i> Llenar L-S (8h)</button>
                            <button type="button" class="btn btn-outline-danger" onclick="limpiarTodoEditor()"><i class="bi bi-trash me-1"></i> Limpiar</button>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-9">
                            <div class="time-grid-container shadow-sm bg-white" style="max-height: 450px; overflow-y: auto;">
                                <table class="table table-bordered table-sm table-time mb-0 align-middle text-center">
                                    <thead class="table-dark">
                                        <tr><th width="100">Fecha</th><th>Intervalos</th><th width="100">Total</th></tr>
                                    </thead>
                                    <tbody id="tbody_editor"></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-lg-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-dark text-white fw-bold">RESUMEN</div>
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div class="mb-4 text-center">
                                        <label class="small text-muted fw-bold text-uppercase">Total Horas</label>
                                        <div class="display-3 fw-bold text-success" id="display_total_hn">00:00</div>
                                    </div>
                                    <hr>
                                    <div class="mb-3">
                                        <label class="small text-muted mb-1 fw-bold">Extras 25%</label>
                                        <input type="text" name="h25" id="edit_h25" class="form-control text-center fw-bold">
                                    </div>
                                    <div>
                                        <label class="small text-muted mb-1 fw-bold">Extras 35%</label>
                                        <input type="text" name="h35" id="edit_h35" class="form-control text-center fw-bold">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 py-3 bg-light">
                    <button type="button" class="btn btn-lg btn-light text-muted px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-lg btn-warning px-5 fw-bold text-dark shadow" onclick="prepararEnvio()">
                        <i class="bi bi-check-circle-fill me-2"></i> GUARDAR
                    </button>
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
let paginaActual = 1;
const filasPorPagina = 10;
let todasLasFilas = [];

document.addEventListener('DOMContentLoaded', function() {
    // 1. Capturar todas las filas al inicio
    todasLasFilas = Array.from(document.querySelectorAll('.fila-dato'));
    
    // 2. Mover Modales al final del body para evitar problemas de superposición (FIX CLAVE)
    const modals = document.querySelectorAll('.modal');
    modals.forEach(m => document.body.appendChild(m));

    // 3. Renderizar tabla con filtro por defecto (ocultar ceros)
    renderizarTabla();
});

function actualizarFiltros() {
    const mes = document.getElementById('gest_mes').value;
    const peri = document.getElementById('gest_peri').value;
    window.location.href = `index.php?view=gestion&estado=<?= $estado_filtro ?>&mes=${mes}&periodo=${peri}`;
}

// --- LOGICA DE PAGINACION Y FILTRADO ---
function renderizarTabla() {
    const textoBusqueda = document.getElementById('busc_gest').value.toLowerCase();
    const mostrarCeros = document.getElementById('chkVerCeros').checked;
    
    // 1. Filtrar filas (Buscador + Logica de Vacíos)
    const filasFiltradas = todasLasFilas.filter(row => {
        const neto = parseFloat(row.dataset.neto) || 0;
        const nombre = row.dataset.nombre;
        const dni = row.dataset.dni;
        
        // Filtro texto
        const coincideTexto = nombre.includes(textoBusqueda) || dni.includes(textoBusqueda);
        
        // Filtro vacios: Si neto > 0 SIEMPRE mostrar. Si neto == 0 solo mostrar si switch activo.
        const mostrarPorDinero = (neto > 0) || mostrarCeros;
        
        return coincideTexto && mostrarPorDinero;
    });

    // 2. Calcular Paginacion
    const totalItems = filasFiltradas.length;
    const totalPaginas = Math.ceil(totalItems / filasPorPagina) || 1;
    
    // Ajustar si la pagina actual se sale del rango
    if (paginaActual > totalPaginas) paginaActual = 1;
    
    const inicio = (paginaActual - 1) * filasPorPagina;
    const fin = inicio + filasPorPagina;
    
    // 3. Ocultar todas las filas del DOM primero
    todasLasFilas.forEach(r => r.classList.add('fila-oculta'));
    
    // 4. Mostrar solo el slice actual de las filtradas
    filasFiltradas.slice(inicio, fin).forEach(r => r.classList.remove('fila-oculta'));

    // 5. Generar Botones Paginador
    const paginador = document.getElementById('paginador');
    if (totalItems === 0) {
        paginador.innerHTML = `<span class="small text-muted fw-bold">No se encontraron resultados.</span>`;
    } else {
        paginador.innerHTML = `
            <button class="btn-page" onclick="cambiarPagina(${paginaActual - 1})" ${paginaActual===1?'disabled':''}><i class="bi bi-chevron-left"></i></button>
            <span class="mx-2 small fw-bold">Pág ${paginaActual} de ${totalPaginas} (${totalItems} regs)</span>
            <button class="btn-page" onclick="cambiarPagina(${paginaActual + 1})" ${paginaActual===totalPaginas?'disabled':''}><i class="bi bi-chevron-right"></i></button>
        `;
    }
}

function cambiarPagina(nuevaPagina) {
    if (nuevaPagina < 1) return;
    paginaActual = nuevaPagina;
    renderizarTabla(); // Re-renderizar solo la vista, sin resetear filtros
}

function filtrarBusqueda(input) {
    paginaActual = 1; // Reset a pagina 1 al buscar
    renderizarTabla();
}

// --- BOLETA Y EDITOR (Sin cambios lógicos, solo integración) ---
function parseTimeToDecimal(t) { if(!t || !t.includes(':')) return 0; const p = t.split(':'); return (parseInt(p[0]) + (parseInt(p[1])/60)); }
let visorMovido = false;

function mostrarVisor(data) {
    const visor = document.getElementById('visor-boleta-full');
    if (!visorMovido) { document.body.appendChild(visor); visorMovido = true; }
    document.getElementById('contenedor-hojas').innerHTML = `
        <div class="boleta-page"><div class="copia-label">COPIA EMPLEADOR</div>${generarHtmlBoleta(data)}</div>
        <div class="boleta-page"><div class="copia-label">COPIA TRABAJADOR</div>${generarHtmlBoleta(data)}</div>`;
    visor.style.display = 'block'; document.body.style.overflow = 'hidden';
}

function generarHtmlBoleta(data) {
    const meses = ["ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"];
    const m_anio = meses[parseInt(<?= $mes_filtro ?>) - 1] + " - " + <?= date('Y') ?>;
    const hN = parseFloat(parseTimeToDecimal(data.horas_normales_total))||0, h25=parseFloat(parseTimeToDecimal(data.horas_25_total))||0, h35=parseFloat(parseTimeToDecimal(data.horas_35_total))||0;
    const beta=parseFloat(data.bono_beta)||0, bet6=parseFloat(data.bono_extra_6)||0, bono_nocturno=parseFloat(data.bono_nocturno)||0, neto_bd=parseFloat(data.monto_neto_final)||0, afp_bd=parseFloat(data.monto_afp)||0;
    const rbh=(parseFloat(data.monto_categoria)||0)/30/8, jornal=hN*rbh, m25=h25*(rbh*1.25), m35=h35*(rbh*1.35), grati=hN*(rbh*0.1666), cts=hN*(rbh*0.0972);
    let af=0; if(data.tiene_hijos=="1") af=(1130/30/8*0.10)*hN;
    const tot_hab=jornal+m25+m35+grati+cts+beta+bet6+af+bono_nocturno, essalud=tot_hab*0.09;
    
    return `<div style="display:flex;justify-content:space-between;margin-bottom:20px;"><div><h4 style="margin:0;font-weight:bold;">GOLDFRUITS S.A.C</h4><p style="margin:0;font-size:11px;">20603919433</p></div><img src="https://i.ibb.co/bgdpX9nn/56005109-316a-4192-a603-c6286c5f2661.jpg" style="width:100px;"></div>
            <div style="text-align:center;margin-bottom:15px;"><h3 style="margin:0;font-size:16px;text-decoration:underline;">BOLETA DE PAGO</h3><h4 style="margin:5px 0 0;font-size:13px;">${m_anio}</h4></div>
            <table class="boleta-table header-info" style="margin-bottom:15px;"><tr><td width="15%">Trabajador:</td><td width="50%"><strong>${data.apellidos_nombres}</strong></td><td width="20%">Días:</td><td width="15%"><strong>${data.dias_trabajados}</strong></td></tr><tr><td>Cargo:</td><td>${data.nombre_puesto||'---'}</td><td>Horas:</td><td>${data.horas_normales_total}</td></tr><tr><td>DNI:</td><td>${data.dni_trabajador||data.numero_documento}</td><td>25%:</td><td>${data.horas_25_total}</td></tr><tr><td>Base:</td><td>S/ ${parseFloat(data.monto_categoria).toFixed(2)}</td><td>35%:</td><td>${data.horas_35_total}</td></tr></table>
            <table class="boleta-table text-center"><thead class="totales-row"><tr><th class="text-start">Concepto</th><th>Ingresos</th><th>Dctos</th><th>Aporte</th></tr></thead><tbody>
            <tr><td class="text-start">Jornal Básico</td><td>${jornal.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Asignación Familiar</td><td>${af.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Horas Extras 25%</td><td>${m25.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Horas Extras 35%</td><td>${m35.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Bono Nocturno</td><td>${bono_nocturno.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Gratificación</td><td>${grati.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">CTS</td><td>${cts.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Bono Beta</td><td>${beta.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">Bono Extra</td><td>${bet6.toFixed(2)}</td><td></td><td></td></tr><tr><td class="text-start">AFP/ONP (${data.nombre_aseguradora})</td><td></td><td>${afp_bd.toFixed(2)}</td><td></td></tr><tr><td class="text-start">ESSALUD (9%)</td><td></td><td></td><td>${essalud.toFixed(2)}</td></tr>
            </tbody><tfoot class="totales-row"><tr><td class="text-end">TOTALES:</td><td>${tot_hab.toFixed(2)}</td><td>${afp_bd.toFixed(2)}</td><td>${essalud.toFixed(2)}</td></tr></tfoot></table>
            <div style="margin-top:15px;text-align:right;font-size:1.2rem;font-weight:bold;border-top:2px solid #000;padding-top:10px;">NETO: S/ ${neto_bd.toFixed(2)}</div>
            <div class="firma-linea" style="margin-top:50px;">${data.apellidos_nombres}</div>`;
}
function cerrarVisor(){document.getElementById('visor-boleta-full').style.display='none';document.body.style.overflow='auto';}

// EDITOR
const ANIO = document.getElementById('current_year').value;
function abrirEditor(data){
    document.getElementById('edit_id_nomina').value=data.id_nomina; document.getElementById('edit_nombre_trabajador').innerText=data.apellidos_nombres;
    document.getElementById('edit_h25').value=data.horas_25_total; document.getElementById('edit_h35').value=data.horas_35_total;
    document.getElementById('edit_monto_categoria').value=data.monto_categoria||0; document.getElementById('edit_porcentaje_seguro').value=data.p_seguro||0; document.getElementById('edit_tiene_hijos').value=data.tiene_hijos||0;
    const mes=document.getElementById('gest_mes').value, peri=document.getElementById('gest_peri').value;
    let s=1, e=15; if(peri==='2DA QUINCENA'){s=16; e=new Date(ANIO,mes,0).getDate();}
    let hDB={}; try{if(data.detalle_horarios)hDB=JSON.parse(data.detalle_horarios);}catch(e){}
    let html='';
    for(let d=s; d<=e; d++){
        const k=`${ANIO}-${String(mes).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const raw=(hDB[k]&&hDB[k].raw)?hDB[k].raw.split(','):[];
        let inps=`<div class="d-flex flex-wrap justify-content-center gap-2" id="tramos_${d}">`;
        if(raw.length>0) for(let z=0;z<raw.length;z+=2) inps+=createTramoHTML(raw[z].substr(0,5), raw[z+1].substr(0,5), d);
        else inps+=createTramoHTML('','',d);
        inps+=`</div><button type="button" class="btn btn-outline-success btn-add-tramo mt-1" onclick="addTramo(${d})"><i class="bi bi-plus"></i></button>`;
        html+=`<tr class="align-middle"><td class="fw-bold bg-light">${k}</td><td>${inps}</td><td><span class="total-day text-success" id="total_dia_${d}">0.00</span></td></tr>`;
    }
    document.getElementById('tbody_editor').innerHTML=html;
    for(let d=s; d<=e; d++) calcRow(d); updateGrandTotal();
    
    // FORZAR MODAL AL FRENTE
    var myModalEl = document.getElementById('modalEditar');
    new bootstrap.Modal(myModalEl).show();
}
function createTramoHTML(i,o,id){return `<div class="tramo-box"><input type="time" class="inp-time-mini i_time" value="${i}" onchange="calcRow(${id})"><i class="bi bi-arrow-right-short text-muted"></i><input type="time" class="inp-time-mini o_time" value="${o}" onchange="calcRow(${id})"><button type="button" class="btn-close ms-1" style="width:8px;height:8px;" onclick="removeTramo(this,${id})"></button></div>`;}
function addTramo(id){document.getElementById(`tramos_${id}`).appendChild(document.createElement('div')).innerHTML=createTramoHTML('','',id);document.getElementById(`tramos_${id}`).lastChild.classList.add('tramo-box');}
function removeTramo(b,id){b.parentElement.remove();calcRow(id);}
function calcRow(id){let t=0;document.getElementById(`tramos_${id}`).querySelectorAll('.i_time').forEach((i,x)=>{const v1=i.value, v2=document.getElementById(`tramos_${id}`).querySelectorAll('.o_time')[x].value;if(v1&&v2){const m1=parseTimeMin(v1), m2=parseTimeMin(v2);if(m2>m1)t+=(m2-m1);}});document.getElementById(`total_dia_${id}`).innerText=(t/60).toFixed(2);updateGrandTotal();}
function parseTimeMin(t){const p=t.split(':');return(parseInt(p[0])*60)+parseInt(p[1]);}
function updateGrandTotal(){let t=0;document.querySelectorAll('.total-day').forEach(s=>t+=parseFloat(s.innerText));const h=Math.floor(t), m=Math.round((t-h)*60);const s=`${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;document.getElementById('display_total_hn').innerText=s;document.getElementById('edit_hn').value=s;}
function llenarTodo8Horas(){document.querySelectorAll('#tbody_editor tr').forEach(tr=>{const id=tr.querySelector('div[id^="tramos_"]').id.split('_')[1];tr.querySelector('div[id^="tramos_"]').innerHTML=createTramoHTML('08:00','12:00',id)+createTramoHTML('13:00','17:00',id);calcRow(id);});}
function limpiarTodoEditor(){document.querySelectorAll('#tbody_editor tr').forEach(tr=>{const id=tr.querySelector('div[id^="tramos_"]').id.split('_')[1];tr.querySelector('div[id^="tramos_"]').innerHTML=createTramoHTML('','',id);calcRow(id);});}
function prepararEnvio(){let j={},d=0;document.querySelectorAll('#tbody_editor tr').forEach(tr=>{const k=tr.querySelector('td').innerText, t=parseFloat(tr.querySelector('.total-day').innerText);let r=[];tr.querySelectorAll('.tramo-box').forEach(b=>{const i=b.querySelector('.i_time').value, o=b.querySelector('.o_time').value;if(i&&o){r.push(i);r.push(o);}});if(r.length>0){j[k]={raw:r.join(',')};if(t>0)d++;}});document.getElementById('edit_detalle_dias_json').value=JSON.stringify(j);document.getElementById('edit_dias').value=d;}
function cambiarEstado(id,st){Swal.fire({title:'¿Seguro?',icon:'question',showCancelButton:true,confirmButtonText:'Sí',confirmButtonColor:'#1b4d2e'}).then((r)=>{if(r.isConfirmed){const f=document.createElement('form');f.method='POST';f.action='controllers/nomina_actions.php';f.innerHTML=`<input type="hidden" name="id_nomina" value="${id}"><input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="nuevo_estado" value="${st}">`;document.body.appendChild(f);f.submit();}});}
</script>