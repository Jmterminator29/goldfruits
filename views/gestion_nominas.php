<?php
// Programa/views/gestion_nominas.php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

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
        if(count($partes) >= 2) { $total_minutos += ($partes[0] * 60) + $partes[1]; }
    }
    return sprintf("%02d:%02d", floor($total_minutos / 60), $total_minutos % 60);
}

// CONSULTA SQL
if ($periodo_filtro == 'MENSUAL') {
    // AQUI AGREGAMOS LA SUMA DE LOS BONOS DESDE LA BD
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
                   SUM(n.bono_beta) as bono_beta,       /* <--- RECUPERADO */
                   SUM(n.bono_extra_6) as bono_extra_6, /* <--- RECUPERADO */
                   SUM(n.bono_nocturno) as bono_nocturno, /* <--- RECUPERADO */
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
    // EN QUINCENAL TRAEMOS TODO CON n.*
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
        
        <div class="col-md-6 d-flex gap-2 mt-2 mt-md-0">
            <select id="gest_mes" class="form-select form-select-sm fw-bold border-success" onchange="actualizarFiltros()">
                <?php 
                $meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                foreach($meses as $i => $m) {
                    $sel = ($mes_filtro == ($i+1)) ? 'selected' : '';
                    echo "<option value='".($i+1)."' $sel>$m</option>";
                }
                ?>
            </select>
            
            <select id="gest_peri" class="form-select form-select-sm fw-bold border-success" onchange="actualizarFiltros()">
                <option value="1RA QUINCENA" <?= $periodo_filtro == '1RA QUINCENA' ? 'selected' : '' ?>>1RA QUINCENA</option>
                <option value="2DA QUINCENA" <?= $periodo_filtro == '2DA QUINCENA' ? 'selected' : '' ?>>2DA QUINCENA</option>
                <option value="MENSUAL" <?= $periodo_filtro == 'MENSUAL' ? 'selected' : '' ?>>MENSUAL (LECTURA)</option>
            </select>
            
            <input type="text" id="busc_gest" class="form-control form-select-sm border-success" placeholder="Buscar..." onkeyup="filtrarTabla()">
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
            <tbody>
                <?php while($n = mysqli_fetch_assoc($res)): 
                    if($periodo_filtro == 'MENSUAL') {
                        $n['horas_normales_total'] = sumarHoras($n['raw_hn']);
                    }
                    $json_safe = htmlspecialchars(json_encode($n), ENT_QUOTES, 'UTF-8');
                ?>
                <tr>
                    <td class="ps-4">
                        <div class="fw-bold text-dark text-uppercase"><?= $n['apellidos_nombres'] ?></div>
                        <small class="text-muted"><?= $n['dni_trabajador'] ?? $n['numero_documento'] ?></small>
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
function actualizarFiltros() {
    const mes = document.getElementById('gest_mes').value;
    const peri = document.getElementById('gest_peri').value;
    window.location.href = `index.php?view=gestion&estado=<?= $estado_filtro ?>&mes=${mes}&periodo=${peri}`;
}

function filtrarTabla() {
    const filter = document.getElementById('busc_gest').value.toUpperCase();
    const rows = document.querySelectorAll("#tablaBol tbody tr");
    rows.forEach(row => { row.style.display = row.innerText.toUpperCase().includes(filter) ? "" : "none"; });
}

function parseTimeToDecimal(t) { if(!t || !t.includes(':')) return 0; const p = t.split(':'); return (parseInt(p[0]) + (parseInt(p[1])/60)); }
let visorMovido = false;

function mostrarVisor(data) {
    const visor = document.getElementById('visor-boleta-full');
    if (!visorMovido) { document.body.appendChild(visor); visorMovido = true; }

    const htmlBoleta = generarHtmlBoleta(data);

    document.getElementById('contenedor-hojas').innerHTML = `
        <div class="boleta-page">
            <div class="copia-label">COPIA EMPLEADOR</div>
            ${htmlBoleta}
        </div>
        <div class="boleta-page">
            <div class="copia-label">COPIA TRABAJADOR</div>
            ${htmlBoleta}
        </div>
    `;
    
    visor.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function generarHtmlBoleta(data) {
    const meses = ["ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"];
    const m_anio = meses[parseInt(<?= $mes_filtro ?>) - 1] + " - " + <?= date('Y') ?>;

    const hN = parseFloat(parseTimeToDecimal(data.horas_normales_total)) || 0;
    const h25 = parseFloat(parseTimeToDecimal(data.horas_25_total)) || 0;
    const h35 = parseFloat(parseTimeToDecimal(data.horas_35_total)) || 0;
    
    // DATOS DE BD
    const beta = parseFloat(data.bono_beta) || 0;
    const bet6 = parseFloat(data.bono_extra_6) || 0;
    const bono_nocturno = parseFloat(data.bono_nocturno) || 0;
    const neto_bd = parseFloat(data.monto_neto_final) || 0;
    const afp_bd = parseFloat(data.monto_afp) || 0;

    const rbh = (parseFloat(data.monto_categoria) || 0) / 30 / 8;
    const jornal = hN * rbh; 
    const m25 = h25 * (rbh * 1.2638 * 1.25); 
    const m35 = h35 * (rbh * 1.2638 * 1.35);
    const grati = hN * (rbh * 0.1666);
    const cts = hN * (rbh * 0.0972);
    
    let af = 0;
    if (data.tiene_hijos == "1") { af = (jornal + ((h25 + h35) * rbh)) * 0.10; }

    const tot_hab = jornal + m25 + m35 + grati + cts + beta + bet6 + af + bono_nocturno;
    // ESSALUD (Bono Nocturno SÍ es remunerativo)
    const essalud = (jornal + m25 + m35 + af + bono_nocturno) * 0.06;

    return `
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
            <div style="line-height: 1.1;">
                <h4 style="margin: 0; font-weight: bold; font-size:18px;">GOLDFRUITS S.A.C</h4>
                <p style="margin: 0; font-size: 11px;">20603919433</p>
                <p style="margin: 0; font-size: 10px; color: #555;">NRO. S/N C.P. DESAGRAVIO LIMA - HUAURA</p>
            </div>
            <img src="https://i.ibb.co/bgdpX9nn/56005109-316a-4192-a603-c6286c5f2661.jpg" style="width: 120px;">
        </div>
        <div style="text-align: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-weight: bold; font-size: 16px; text-decoration: underline;">BOLETA DE PAGO</h3>
            <h4 style="margin: 5px 0 0; font-weight: bold; font-size: 13px;">${m_anio}</h4>
        </div>
        <table class="boleta-table header-info" style="margin-bottom: 15px;">
            <tr><td width="15%">Trabajador:</td><td width="50%"><strong>${data.apellidos_nombres}</strong></td><td width="20%">Días Trab.:</td><td width="15%"><strong>${data.dias_trabajados}</strong></td></tr>
            <tr><td>Cargo:</td><td>${data.nombre_puesto || '---'}</td><td>Horas Norm.:</td><td>${hN.toFixed(2)}</td></tr>
            <tr><td>DNI:</td><td>${data.dni_trabajador || data.numero_documento}</td><td>Horas 25%:</td><td>${h25.toFixed(2)}</td></tr>
            <tr><td>Sueldo Base:</td><td>S/ ${parseFloat(data.monto_categoria).toFixed(2)}</td><td>Horas 35%:</td><td>${h35.toFixed(2)}</td></tr>
        </table>
        <table class="boleta-table text-center">
            <thead class="totales-row"><tr><th class="text-start">Concepto</th><th>Ingresos</th><th>Descuentos</th><th>Aporte Empleador</th></tr></thead>
            <tbody>
                <tr><td class="text-start">Jornal Básico</td><td>${jornal.toFixed(2)}</td><td></td><td></td></tr>
                <tr><td class="text-start">Asignación Familiar</td><td>${af.toFixed(2)}</td><td></td><td></td></tr>
                <tr><td class="text-start">Horas Extras 25%</td><td>${m25.toFixed(2)}</td><td></td><td></td></tr>
                <tr><td class="text-start">Horas Extras 35%</td><td>${m35.toFixed(2)}</td><td></td><td></td></tr>
                <tr><td class="text-start">Bono Nocturno (35%)</td><td>${bono_nocturno.toFixed(2)}</td><td></td><td></td></tr>
                <tr><td class="text-start">Gratificación Prop.</td><td>${grati.toFixed(2)}</td><td></td><td></td></tr>
                <tr><td class="text-start">CTS Prop.</td><td>${cts.toFixed(2)}</td><td></td><td></td></tr>
                <tr><td class="text-start">Bono Beta (30%)</td><td>${beta.toFixed(2)}</td><td></td><td></td></tr>
                <tr><td class="text-start">Bono Extra (6%)</td><td>${bet6.toFixed(2)}</td><td></td><td></td></tr>
                <tr><td class="text-start">AFP / ONP (${data.nombre_aseguradora})</td><td></td><td>${afp_bd.toFixed(2)}</td><td></td></tr>
                <tr><td class="text-start">ESSALUD (6%)</td><td></td><td></td><td>${essalud.toFixed(2)}</td></tr>
            </tbody>
            <tfoot class="totales-row">
                <tr><td class="text-end">TOTALES:</td><td>${tot_hab.toFixed(2)}</td><td>${afp_bd.toFixed(2)}</td><td>${essalud.toFixed(2)}</td></tr>
            </tfoot>
        </table>
        <div style="margin-top: 15px; text-align: right; font-size: 1.2rem; font-weight: bold; border-top: 2px solid #000; padding-top: 10px;">LÍQUIDO A PAGAR: S/ ${neto_bd.toFixed(2)}</div>
        <div style="font-size: 10px; margin-top: 40px; line-height: 1.3;">Certifico haber recibido...<br><strong>Forma de pago:</strong> ${data.banco_nombre || 'EFECTIVO'} - ${data.numero_cuenta || '---'}</div>
        <div class="firma-linea">${data.apellidos_nombres}</div>
    `;
}

function cerrarVisor() { document.getElementById('visor-boleta-full').style.display = 'none'; document.body.style.overflow = 'auto'; }

// --- FUNCIONES EDITOR ---
const ANIO = document.getElementById('current_year').value;
function abrirEditor(data) {
    document.getElementById('edit_id_nomina').value = data.id_nomina;
    document.getElementById('edit_nombre_trabajador').innerText = data.apellidos_nombres;
    document.getElementById('edit_h25').value = data.horas_25_total;
    document.getElementById('edit_h35').value = data.horas_35_total;
    document.getElementById('edit_monto_categoria').value = data.monto_categoria || 0;
    document.getElementById('edit_porcentaje_seguro').value = data.p_seguro || 0;
    document.getElementById('edit_tiene_hijos').value = data.tiene_hijos || 0;

    const mes = document.getElementById('gest_mes').value;
    const peri = document.getElementById('gest_peri').value;
    let startDay = 1, endDay = 15;
    if (peri === '2DA QUINCENA') { startDay = 16; endDay = new Date(ANIO, mes, 0).getDate(); }

    let horariosDB = {};
    try { 
        const strJson = data.detalle_horarios || data.detalle_dias;
        if(strJson) horariosDB = JSON.parse(strJson);
    } catch(e) {}

    const tbody = document.getElementById('tbody_editor');
    let html = '';
    for (let d = startDay; d <= endDay; d++) {
        const fechaKey = `${ANIO}-${String(mes).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const infoDia = horariosDB[fechaKey] || { raw: "" };
        const rawTimes = infoDia.raw ? infoDia.raw.split(',') : [];
        let inputsHtml = `<div class="d-flex flex-wrap justify-content-center gap-2" id="tramos_${d}">`;
        
        // LIMPIEZA DE SEGUNDOS: "08:00:00" -> "08:00"
        if (rawTimes.length > 0) {
            for (let k = 0; k < rawTimes.length; k += 2) { 
                let t1 = rawTimes[k] ? rawTimes[k].substr(0,5) : ''; 
                let t2 = rawTimes[k+1] ? rawTimes[k+1].substr(0,5) : '';
                inputsHtml += createTramoHTML(t1, t2, d); 
            }
        } else { inputsHtml += createTramoHTML('', '', d); }
        
        inputsHtml += `</div><button type="button" class="btn btn-outline-success btn-add-tramo mt-1" onclick="addTramo(${d})"><i class="bi bi-plus"></i></button>`;
        html += `<tr class="align-middle"><td class="fw-bold bg-light">${fechaKey}</td><td>${inputsHtml}</td><td><span class="total-day text-success" id="total_dia_${d}">0.00</span></td></tr>`;
    }
    tbody.innerHTML = html;
    for (let d = startDay; d <= endDay; d++) calcRow(d);
    updateGrandTotal();
    
    var myModalEl = document.getElementById('modalEditar');
    document.body.appendChild(myModalEl);
    new bootstrap.Modal(myModalEl).show();
}

function createTramoHTML(i, o, id) { 
    return `<div class="tramo-box"><input type="time" class="inp-time-mini i_time" value="${i}" onchange="calcRow(${id})"><i class="bi bi-arrow-right-short text-muted"></i><input type="time" class="inp-time-mini o_time" value="${o}" onchange="calcRow(${id})"><button type="button" class="btn-close ms-1" style="width: 8px; height: 8px;" onclick="removeTramo(this, ${id})"></button></div>`; 
}
function addTramo(id) { document.getElementById(`tramos_${id}`).appendChild(document.createElement('div')).innerHTML = createTramoHTML('', '', id); document.getElementById(`tramos_${id}`).lastChild.classList.add('tramo-box'); } 
function removeTramo(btn, id) { btn.parentElement.remove(); calcRow(id); }
function calcRow(id) {
    const container = document.getElementById(`tramos_${id}`); if(!container) return;
    let totalMin = 0;
    container.querySelectorAll('.i_time').forEach((inp, idx) => {
        const t1 = inp.value; const t2 = container.querySelectorAll('.o_time')[idx] ? container.querySelectorAll('.o_time')[idx].value : '';
        if(t1 && t2) { const m1 = parseTimeMin(t1); const m2 = parseTimeMin(t2); if(m2 > m1) totalMin += (m2 - m1); }
    });
    document.getElementById(`total_dia_${id}`).innerText = (totalMin / 60).toFixed(2);
    updateGrandTotal();
}
function parseTimeMin(t) { const p = t.split(':'); return (parseInt(p[0]) * 60) + parseInt(p[1]); }
function updateGrandTotal() {
    let total = 0; document.querySelectorAll('.total-day').forEach(s => total += parseFloat(s.innerText));
    const h = Math.floor(total); const m = Math.round((total - h) * 60);
    const timeStr = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`;
    document.getElementById('display_total_hn').innerText = timeStr;
    document.getElementById('edit_hn').value = timeStr;
}

function prepararEnvio() {
    let jsonFinal = {}; let diasTrabajados = 0;
    document.querySelectorAll('#tbody_editor tr').forEach(tr => {
        const fecha = tr.querySelector('td').innerText;
        const total = parseFloat(tr.querySelector('.total-day').innerText);
        let rawArr = [];
        tr.querySelectorAll('.tramo-box').forEach(box => {
            const i = box.querySelector('.i_time').value; 
            const o = box.querySelector('.o_time').value;
            // IMPORTANTE: NO AGREGAR ":00" PARA EVITAR CONFLICTOS DE FORMATO
            if(i && o) { rawArr.push(i); rawArr.push(o); }
        });
        if(rawArr.length > 0) { jsonFinal[fecha] = { raw: rawArr.join(',') }; if(total > 0) diasTrabajados++; }
    });
    document.getElementById('edit_detalle_dias_json').value = JSON.stringify(jsonFinal);
    document.getElementById('edit_dias').value = diasTrabajados;
}

function llenarTodo8Horas() {
    document.querySelectorAll('#tbody_editor tr').forEach(tr => {
        const container = tr.querySelector('div[id^="tramos_"]');
        const id = container.id.split('_')[1];
        container.innerHTML = createTramoHTML('08:00', '12:00', id) + createTramoHTML('13:00', '17:00', id);
        calcRow(id);
    });
}
function limpiarTodoEditor() {
    document.querySelectorAll('#tbody_editor tr').forEach(tr => {
        const container = tr.querySelector('div[id^="tramos_"]');
        const id = container.id.split('_')[1];
        container.innerHTML = createTramoHTML('', '', id);
        calcRow(id);
    });
}
function cambiarEstado(id, nuevoEstado) {
    Swal.fire({ title: '¿Seguro?', icon:'question', showCancelButton: true, confirmButtonText: 'Sí', confirmButtonColor: '#1b4d2e' }).then((r) => {
        if (r.isConfirmed) {
            const f = document.createElement('form'); f.method = 'POST'; f.action = 'controllers/nomina_actions.php';
            f.innerHTML = `<input type="hidden" name="id_nomina" value="${id}"><input type="hidden" name="accion" value="cambiar_estado"><input type="hidden" name="nuevo_estado" value="${nuevoEstado}">`;
            document.body.appendChild(f); f.submit();
        }
    });
}
</script>