<?php
// views/procesar_nomina.php
if (session_status() === PHP_SESSION_NONE) session_start();
$root = $_SERVER['DOCUMENT_ROOT'];
include_once $root . '/contabilidad/config/db.php';

// 1. Filtros
$mes = $_GET['mes'] ?? date('n');
$periodo = $_GET['periodo'] ?? '1RA QUINCENA';
$anio = date('Y');
$fi = $_GET['fi'] ?? '';
$ff = $_GET['ff'] ?? '';

// Helpers PHP
function lastDayOfMonth($y, $m) { return date('Y-m-d', strtotime("$y-$m-01 +1 month -1 day")); }
function pad2($n) { return str_pad($n, 2, '0', STR_PAD_LEFT); }
// Helper para convertir hora HH:mm:ss a decimal en PHP (Para calcular AF al cargar)
function timeToDec($t) { 
    $p = explode(':', $t); 
    return ($p[0]??0) + (($p[1]??0)/60) + (($p[2]??0)/3600); 
}

if ($fi === '' || $ff === '') {
    $m = (int)$mes; $y = (int)$anio; $prevM = $m - 1; $prevY = $y;
    if ($prevM <= 0) { $prevM = 12; $prevY = $y - 1; }
    $lastPrev = lastDayOfMonth($prevY, $prevM); $lastCurr = lastDayOfMonth($y, $m);

    if ($periodo === '1RA QUINCENA') { 
        $fi = $fi ?: $lastPrev; 
        $ff = $ff ?: ($y . '-' . pad2($m) . '-15'); 
    } else { 
        $fi = $fi ?: ($y . '-' . pad2($m) . '-16'); 
        $ff = $ff ?: $lastCurr; 
    }
}

// 2. Configuración Global (RMV)
$res_c = mysqli_query($conexion, "SELECT valor FROM configuracion_global WHERE clave='RMV'");
$rmv = mysqli_fetch_assoc($res_c)['valor'] ?? 1130.00;

// 3. Obtener Trabajadores y Nóminas
$trabajadores_db = [];
$sql = "SELECT t.id_trabajador, t.apellidos_nombres, t.numero_documento, c.monto_categoria,
               t.tiene_hijos, t.en_planilla, COALESCE(a.porcentaje_descuento, 0) as p_seguro,
               COALESCE(a.nombre_aseguradora, 'S.S') as nom_seg,
               n.id_nomina,
               n.dias_trabajados as dias_guardados,
               n.detalle_horarios,
               /* DATOS PERSISTENTES DE BD */
               n.horas_normales_total,
               n.horas_25_total,
               n.horas_35_total,
               n.horas_nocturnas_total,
               n.monto_base_afp,
               n.monto_afp,
               n.monto_neto_final,
               n.bono_beta,
               n.bono_extra_6,
               n.bono_nocturno
        FROM trabajadores t
        LEFT JOIN categorias_pago c ON t.id_categoria = c.id_categoria
        LEFT JOIN aseguradoras a ON t.id_aseguradora = a.id_aseguradora
        LEFT JOIN nomina_procesada n ON t.id_trabajador = n.id_trabajador
             AND n.mes_pago = '$mes' AND n.periodo_pago = '$periodo' AND n.anio_pago = '$anio'
        WHERE t.estado='ACTIVO' ORDER BY t.apellidos_nombres ASC";

$res_t = mysqli_query($conexion, $sql);
while($t = mysqli_fetch_assoc($res_t)) {
    $dni = ltrim(trim($t['numero_documento']), '0');
    $trabajadores_db[$dni] = $t;
}
?>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

<style>
  .contenedor-nomina-total { width: 100%; background-color: #f4f7f6; min-height: 100vh; padding: 15px; }
  .table-nomina { background: white; border-radius: 10px; overflow: hidden; font-size: 0.65rem; }
  .table-nomina th { padding: 5px; vertical-align: middle; background-color: #2c3e50; color: white; border: 1px solid #444; }
  .table-nomina td { padding: 3px; vertical-align: middle; border: 1px solid #dee2e6; }
  .bg-jornal { background-color: #e8f4fd; font-weight: bold; }
  .inp-invisible { border: none; background: transparent; pointer-events: none; width: 100%; text-align: center; font-weight: bold; }

  .fila-no-planilla { background-color: #fff3cd !important; }
  .fila-no-planilla td { border-bottom: 1px solid #ffecb5; }
  .fila-oculta { display: none; }
  
  .seccion-carga { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 20px; }
  .range-box { background:#fff; border-radius:12px; padding:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05); }

  .modal-backdrop { z-index: 2000 !important; }
  .modal { z-index: 2050 !important; }
  .modal-dialog { z-index: 2100 !important; }

  .tramo-input { background: #f8f9fa; padding: 6px; border-radius: 8px; border: 1px solid #dee2e6; display: flex; align-items: center; gap: 5px; margin-bottom: 5px; }
  .time-field { width: 85px !important; border: 1px solid #ced4da; border-radius: 5px; text-align: center; font-weight: 600; color: #2c3e50; }
  .f-txt { font-weight: 700; color: #0d6efd; font-size: 0.85rem; }
  .total-row td { background-color: #212529; color: #fff; font-weight: bold; }
  
  .busqueda-flotante { position: relative; }
  .resultado-busqueda { position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; max-height: 200px; overflow-y: auto; display: none; border: 1px solid #ddd; background: white; }
  .nav-tabs .nav-link.active { font-weight: bold; color: #198754; border-bottom: 3px solid #198754; }
</style>

<div class="animate__animated animate__fadeIn contenedor-nomina-total">
    
    <div class="row mb-3 align-items-center">
        <div class="col-md-4"><h4 class="fw-bold m-0 text-dark">Panel de Nómina</h4></div>
        <div class="col-md-8 d-flex justify-content-end gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <span class="small fw-bold text-muted text-uppercase">Mes:</span>
                <select id="mes_pago_sel" class="form-select form-select-sm fw-bold border-success" style="width: 140px;" onchange="recargarFiltros()">
                    <?php
                    $meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
                    foreach($meses as $i => $m) {
                        $sel = ($mes == ($i+1)) ? 'selected' : '';
                        echo "<option value='".($i+1)."' $sel>$m</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small fw-bold text-muted text-uppercase">Periodo:</span>
                <select id="periodo_pago_sel" class="form-select form-select-sm fw-bold border-success" style="width: 160px;" onchange="recargarFiltros()">
                    <option value="1RA QUINCENA" <?= $periodo == '1RA QUINCENA' ? 'selected' : '' ?>>1RA QUINCENA</option>
                    <option value="2DA QUINCENA" <?= $periodo == '2DA QUINCENA' ? 'selected' : '' ?>>2DA QUINCENA</option>
                </select>
            </div>
        </div>
    </div>

    <div class="range-box mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-1 small text-muted fw-bold">FECHA INICIO</label>
                <input type="date" id="periodo_inicio" class="form-control border-success-subtle fw-bold" value="<?= htmlspecialchars($fi) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1 small text-muted fw-bold">FECHA FIN</label>
                <input type="date" id="periodo_fin" class="form-control border-success-subtle fw-bold" value="<?= htmlspecialchars($ff) ?>">
            </div>
            <div class="col-md-6 d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-dark fw-bold" onclick="aplicarRangoYRecalcular()">
                    <i class="bi bi-funnel"></i> APLICAR RANGO
                </button>
                <div class="small text-muted d-flex align-items-center">
                    <span class="badge bg-dark me-2">Activo</span>
                    <span id="txtRangoActivo"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="seccion-carga">
        <div class="row align-items-start">
            <div class="col-md-6 border-end pe-4">
                <label class="form-label fw-bold text-secondary small"><i class="bi bi-file-earmark-spreadsheet"></i> Cargar Asistencia (Excel)</label>
                <div class="input-group">
                    <input type="file" id="inputExcel" class="form-control border-success-subtle">
                    <button type="button" class="btn btn-primary fw-bold" onclick="procesarArchivoExcel()">ANALIZAR</button>
                </div>
                <small class="text-muted d-block mt-1 fst-italic">Se aplicará redondeo automático (Entrada: +5min / Salida: -5min).</small>
            </div>
            <div class="col-md-6 ps-4">
                <label class="form-label fw-bold text-secondary small"><i class="bi bi-search"></i> Buscar / Agregar Manualmente</label>
                <div class="busqueda-flotante">
                    <input type="text" id="inputBuscador" class="form-control border-primary-subtle" placeholder="Escribe nombre o DNI..." autocomplete="off" onkeyup="filtrarBusqueda(this)">
                    <div id="listaResultados" class="list-group resultado-busqueda shadow"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small class="text-muted">Busca para "revelar" un trabajador oculto.</small>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="chkVerTodos" onchange="toggleVerTodos()">
                        <label class="form-check-label small fw-bold" for="chkVerTodos">Ver Todos</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="resumenCarga" class="card mb-4 border-0 shadow-sm d-none">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                <li class="nav-item"><button class="nav-link active" id="found-tab" data-bs-toggle="tab" data-bs-target="#found-content" type="button"><i class="bi bi-check-circle-fill text-success"></i> Coincidentes (<span id="countFound">0</span>)</button></li>
                <li class="nav-item"><button class="nav-link" id="missing-tab" data-bs-toggle="tab" data-bs-target="#missing-content" type="button"><i class="bi bi-question-circle-fill text-danger"></i> No Registrados (<span id="countMissing">0</span>)</button></li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                 <div class="form-check form-switch me-3" title="Si activa: Reemplaza días existentes con Excel. Si desactiva: Solo agrega días nuevos.">
                    <input class="form-check-input" type="checkbox" id="chkSobrescribir">
                    <label class="form-check-label small fw-bold text-danger" for="chkSobrescribir">Sobrescribir</label>
                </div>
                <button type="button" class="btn btn-sm btn-outline-dark" onclick="cancelarCarga()">CANCELAR</button>
                <button type="button" class="btn btn-sm btn-success fw-bold px-3" onclick="confirmarInyeccion()">CONFIRMAR CARGA</button>
            </div>
        </div>
        <div class="card-body p-0 tab-content" style="max-height: 250px; overflow-y: auto;">
            <div class="tab-pane fade show active" id="found-content" role="tabpanel">
                <table class="table table-sm table-hover text-center mb-0 small">
                    <thead class="table-light"><tr><th>DNI</th><th>Nombre</th><th>Estado</th><th>Acción</th></tr></thead>
                    <tbody id="bodyResumen"></tbody>
                </table>
            </div>
            <div class="tab-pane fade" id="missing-content" role="tabpanel">
                 <div class="p-3 text-center bg-danger bg-opacity-10 mb-2 border-bottom border-danger">
                    <span class="text-danger small fw-bold">DNI en Excel NO encontrados en BD.</span>
                    <button class="btn btn-sm btn-danger ms-3 shadow-sm" onclick="abrirModalCrearTrabajador()"><i class="bi bi-person-plus-fill"></i> REGISTRAR RÁPIDO</button>
                 </div>
                <table class="table table-sm table-hover text-center mb-0 small">
                    <thead class="table-light"><tr><th>DNI (Excel)</th><th>Nombre (Excel)</th><th>Estado</th></tr></thead>
                    <tbody id="bodyDesconocidos"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="table-responsive table-nomina border shadow-sm">
        
        <div class="bg-warning bg-opacity-10 border-bottom p-2 d-flex align-items-center gap-2 small">
            <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
            <span class="text-dark">Las filas resaltadas en <b>naranja</b> corresponden a personal <b>FUERA DE PLANILLA</b> (No se aplica descuento AFP).</span>
        </div>

        <form id="formNomina">
            <input type="hidden" name="periodo_inicio" id="hidden_periodo_inicio" value="<?= htmlspecialchars($fi) ?>">
            <input type="hidden" name="periodo_fin" id="hidden_periodo_fin" value="<?= htmlspecialchars($ff) ?>">

            <table class="table table-sm mb-0 text-center align-middle">
                <thead>
                    <tr class="text-uppercase" style="font-size: 0.58rem;">
                        <th rowspan="2" class="text-start ps-3">Personal</th>
                        <th rowspan="2" width="40">Días</th>
                        <th colspan="4" style="background: #157347">Jornal Diario</th>
                        <th colspan="2" style="background: #d35400">Extras (HH:mm)</th>
                        <th colspan="4" style="background: #0d6efd">Bonos / A.F</th>
                        <th class="bg-danger">AFP</th>
                        <th rowspan="2" class="bg-primary" width="100">Neto</th>
                        <th rowspan="2" width="40">Edit</th>
                    </tr>
                    <tr style="font-size: 0.55rem; background: #f8f9fa;">
                        <th>RB/H</th><th>GRA/H</th><th>CTS/H</th><th class="bg-jornal text-primary">JOR/H</th>
                        <th class="text-warning">25%</th><th class="text-danger">35%</th>
                        <th>A.FAM</th><th style="background:#2C3E50; color:#F1C40F;">B.NOCT</th>
                        <th>BET(6%)</th><th>BETA</th><th>Dcto</th>
                    </tr>
                </thead>
                <tbody id="tblNomina">
                <?php $i=0; foreach($trabajadores_db as $dni => $t): $i++;
                    $diasBD = $t['dias_guardados'] ?? 0;
                    $jsonDB = htmlspecialchars($t['detalle_horarios'] ?? '', ENT_QUOTES, 'UTF-8');
                    
                    // --- CARGAR DATOS DESDE BD (Evita 0 al recargar) ---
                    $hN_bd = $t['horas_normales_total'] ?? '00:00';
                    $h25_bd = $t['horas_25_total'] ?? '00:00';
                    $h35_bd = $t['horas_35_total'] ?? '00:00';
                    $hNoct_bd = $t['horas_nocturnas_total'] ?? '00:00';
                    $bonoNoct_bd = $t['bono_nocturno'] ?? 0;
                    $bonoBeta_bd = $t['bono_beta'] ?? 0;
                    $bono6_bd = $t['bono_extra_6'] ?? 0;
                    $afp_bd = $t['monto_afp'] ?? 0;
                    $neto_bd = $t['monto_neto_final'] ?? 0;
                    $base_afp_bd = $t['monto_base_afp'] ?? 0;

                    // CALCULO PHP (Para visualización inicial sin esperar a JS)
                    $rb = (float)$t['monto_categoria'];
                    $rbh = $rb / 30 / 8;
                    $gh_h = $rbh * 0.1666;
                    $ct_h = $rbh * 0.0972;
                    $jornal_h = $rbh + $gh_h + $ct_h;
                    
                    // Asig Fam Calculada en PHP
                    $af_hora = ($rmv / 30 * 0.10) / 8;
                    $hN_dec = timeToDec($hN_bd);
                    $af_total = $t['tiene_hijos'] ? ($af_hora * $hN_dec) : 0;

                    // Estilos
                    $claseOculta = ($diasBD > 0) ? '' : 'fila-oculta';
                    $claseNoPlanilla = ($t['en_planilla'] === 'NO') ? 'fila-no-planilla' : '';
                    $iconoNoPlanilla = ($t['en_planilla'] === 'NO') ? '<i class="bi bi-cone-striped text-warning me-1"></i>' : '';
                ?>
                    <tr class="fila-nomina <?= $claseOculta ?> <?= $claseNoPlanilla ?>" id="fila-<?= $dni ?>" 
                        data-dni="<?= $dni ?>" 
                        data-nombre="<?= strtolower($t['apellidos_nombres']) ?>" 
                        data-rb="<?= $t['monto_categoria'] ?>" 
                        data-hijos="<?= $t['tiene_hijos'] ?>" 
                        data-seg="<?= $t['p_seguro'] ?>"
                        data-planilla="<?= $t['en_planilla'] ?>">
                        
                        <td class="text-start ps-3">
                            <div class="d-flex align-items-center">
                                <?= $iconoNoPlanilla ?>
                                <div>
                                    <div class="fw-bold lh-1 small"><?= $t['apellidos_nombres'] ?></div>
                                    <small class="text-muted" style="font-size: 0.55rem;"><?= $dni ?> | <?= $t['en_planilla']=='SI'?'PLANILLA':'RECIBO' ?></small>
                                </div>
                            </div>
                            <input type="hidden" name="trab[<?= $i ?>][id]" value="<?= $t['id_trabajador'] ?>">
                            <input type="hidden" name="trab[<?= $i ?>][dni]" value="<?= $dni ?>">
                            <input type="hidden" name="trab[<?= $i ?>][json_horarios]" class="val-json" value="<?= $jsonDB ?>">
                        </td>
                        <td><input type="number" name="trab[<?= $i ?>][dias]" class="inp-invisible inp-dias" value="<?= $diasBD ?>" readonly></td>
                        
                        <td class="lbl-rb text-muted small"><?= number_format($rbh, 4) ?></td>
                        <td class="lbl-grati text-muted small"><?= number_format($gh_h, 4) ?></td>
                        <td class="lbl-cts text-muted small"><?= number_format($ct_h, 4) ?></td>
                        <td class="lbl-jornal-h bg-jornal text-primary"><?= number_format($jornal_h, 4) ?></td>
                        <td class="text-warning fw-bold txt-he25"><?= $h25_bd ?></td>
                        <td class="text-danger fw-bold txt-he35"><?= $h35_bd ?></td>
                        <td class="lbl-asig-fam"><?= number_format($af_total, 2) ?></td>
                        <td class="lbl-bnoct text-dark fw-bold" style="background:#FCF3CF;"><?= $hNoct_bd ?></td>
                        <td class="lbl-bet-6"><?= number_format($bono6_bd, 2) ?></td>
                        <td class="lbl-beta-bono"><?= number_format($bonoBeta_bd, 2) ?></td>
                        <td class="lbl-descto text-danger"><?= number_format($afp_bd, 2) ?></td>
                        <td class="bg-primary bg-opacity-10 fw-bold"><span class="lbl-neto"><?= number_format($neto_bd, 2) ?></span></td>
                        
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 border-0" onclick="abrirModalAsistencia('<?= $dni ?>')">
                                <i class="bi bi-pencil-square fs-6"></i>
                            </button>
                        </td>

                        <input type="hidden" name="trab[<?= $i ?>][horas_n]" class="val-hN" value="<?= $hN_bd ?>">
                        <input type="hidden" name="trab[<?= $i ?>][horas_25]" class="val-h25" value="<?= $h25_bd ?>">
                        <input type="hidden" name="trab[<?= $i ?>][horas_35]" class="val-h35" value="<?= $h35_bd ?>">
                        <input type="hidden" name="trab[<?= $i ?>][horas_noct]" class="val-hNoct" value="<?= $hNoct_bd ?>">
                        
                        <input type="hidden" name="trab[<?= $i ?>][base_afp]" class="val-base" value="<?= $base_afp_bd ?>">
                        <input type="hidden" name="trab[<?= $i ?>][afp_monto]" class="val-afp" value="<?= $afp_bd ?>">
                        <input type="hidden" name="trab[<?= $i ?>][neto]" class="val-neto" value="<?= $neto_bd ?>">
                        <input type="hidden" name="trab[<?= $i ?>][bono_beta]" class="val-beta" value="<?= $bonoBeta_bd ?>">
                        <input type="hidden" name="trab[<?= $i ?>][bono_6]" class="val-bono6" value="<?= $bono6_bd ?>">
                        <input type="hidden" name="trab[<?= $i ?>][bono_nocturno]" class="val-bnoct" value="<?= $bonoNoct_bd ?>">
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="p-4 bg-dark text-white d-flex justify-content-between align-items-center mt-3 rounded-bottom shadow">
                <div>
                    <span class="text-warning text-uppercase small fw-bold d-block">Total a Pagar (Visibles)</span>
                    <span class="fw-bold fs-4">S/ <span id="totalGeneral">0.00</span></span>
                </div>
                <div>
                    <button type="button" onclick="enviarNomina('BORRADOR')" class="btn btn-success fw-bold px-5 py-3 rounded-pill shadow-lg fs-6">
                        <i class="bi bi-save me-2"></i> GUARDAR CAMBIOS
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalAsistencia" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white">
                <div class="d-flex align-items-center">
                    <i class="bi bi-clock-history fs-4 me-2 text-success"></i>
                    <div><h6 class="modal-title mb-0 fw-bold">Editor de Asistencia</h6><small class="text-white-50" id="modalInfoTrabajador">...</small></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="table-responsive rounded-3 bg-white shadow-sm border">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-success text-dark small text-uppercase">
                            <tr class="text-center"><th width="120">Fecha</th><th>Intervalos</th><th>Norm.</th><th>25%</th><th>35%</th><th></th></tr>
                        </thead>
                        <tbody id="bodyModal"></tbody>
                        <tfoot id="footerModal" class="total-row text-center"></tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-white justify-content-between">
                <button type="button" class="btn btn-outline-primary fw-bold rounded-pill" onclick="agregarDiaModal()">+ Fecha</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light fw-bold rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success px-4 fw-bold rounded-pill" onclick="guardarCambiosModal()">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearTrabajador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold">Registrar Trabajador Faltante</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formCrearFast">
                    <div class="mb-3"><label class="form-label small fw-bold">DNI</label><input type="text" class="form-control" id="new_dni" required></div>
                    <div class="mb-3"><label class="form-label small fw-bold">Nombres</label><input type="text" class="form-control text-uppercase" id="new_nombre" required></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm fw-bold" onclick="guardarTrabajadorFast()">Guardar y Recargar</button>
            </div>
        </div>
    </div>
</div>

<script>
// --- Variables Globales ---
const RMV = <?= $rmv ?>; 
const trabajadoresDB = <?= json_encode($trabajadores_db) ?>;
let asistenciaGlobal = {}, datosTemporalesExcel = {}, datosDesconocidos = {}, dniActivo = null;

// --- INICIALIZACION ---
document.addEventListener('DOMContentLoaded', function() {
    ['modalAsistencia', 'modalCrearTrabajador'].forEach(id => { var el=document.getElementById(id); if(el) document.body.appendChild(el); });
    Object.keys(trabajadoresDB).forEach(dni => { asistenciaGlobal[dni] = { nombre: trabajadoresDB[dni].apellidos_nombres, dias: {} }; });
    pintarRangoActivo();
    // Suma inicial del total visual que trajo PHP
    recalcTotalGeneral(); 
});

function filtrarBusqueda(input) {
    const txt = input.value.toLowerCase().trim(); const lista = document.getElementById('listaResultados'); lista.innerHTML = '';
    if(txt.length < 2) { lista.style.display = 'none'; return; }
    let c = 0;
    for(let dni in trabajadoresDB) {
        if(dni.includes(txt) || trabajadoresDB[dni].apellidos_nombres.toLowerCase().includes(txt)) {
            const btn = document.createElement('button'); btn.className = 'list-group-item list-group-item-action text-start small';
            btn.innerHTML = `<b>${trabajadoresDB[dni].apellidos_nombres}</b> (${dni})`;
            btn.onclick = () => { mostrarTrabajador(dni); lista.style.display = 'none'; input.value = ''; };
            lista.appendChild(btn); c++; if(c>8) break;
        }
    }
    lista.style.display = c ? 'block' : 'none';
}

function mostrarTrabajador(dni) {
    const f = document.getElementById('fila-' + dni);
    if(f) { 
        f.classList.remove('fila-oculta'); 
        f.scrollIntoView({behavior:'smooth',block:'center'}); 
        f.classList.add('table-warning'); 
        setTimeout(()=>f.classList.remove('table-warning'),2000); 
        inyectarFilaEspecifica(dni); 
    }
}

function toggleVerTodos() {
    const ver = document.getElementById('chkVerTodos').checked;
    document.querySelectorAll('.fila-nomina').forEach(tr => {
        if(ver) tr.classList.remove('fila-oculta');
        else if((parseInt(tr.querySelector('.inp-dias').value)||0) === 0) tr.classList.add('fila-oculta');
    });
    recalcTotalGeneral();
}

function aplicarRangoYRecalcular(){ 
    document.querySelectorAll('.fila-nomina').forEach(tr => {
        inyectarFilaEspecifica(tr.dataset.dni);
    });
    pintarRangoActivo(); 
}

function procesarArchivoExcel() {
    const rango = getRangoActivo(); if(!rango) return alert("Rango inválido.");
    const file = document.getElementById('inputExcel').files[0]; if(!file) return alert("Seleccione archivo.");
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const wb = XLSX.read(e.target.result, {type:'array'});
            const json = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], {header:1});
            datosTemporalesExcel = {}; datosDesconocidos = {};
            let start = 0;
            for(let i=0;i<json.length;i++){ if(json[i][0] && String(json[i][0]).length>=8){ start=i; break; } }
            for(let i=start; i<json.length; i++){
                const row = json[i]; if(!row[0]) continue;
                let dni = String(row[0]).trim().replace(/^0+/,'');
                if(!asistenciaGlobal[dni]) { datosDesconocidos[dni] = row[1]||'S/N'; continue; }
                const fISO = parseFechaFlexible(row[4]);
                if(!fISO || !dentroDeRango(fISO, rango)) continue;
                let raw = String(row[6]||"").replace(/;/g,',').split(',');
                let arr = [];
                for(let k=0;k<raw.length;k++){
                    let s = parseTime(raw[k].trim());
                    if(s>0){
                        let tM = s/60; let rM = 0;
                        if(k % 2 === 0) rM = Math.ceil(tM/5)*5; 
                        else rM = Math.floor(tM/5)*5; 
                        let h = Math.floor(rM/60); let m = rM%60;
                        arr.push(`${h.toString().padStart(2,'0')}:${m.toString().padStart(2,'0')}:00`);
                    }
                }
                if(arr.length>0) { if(!datosTemporalesExcel[dni]) datosTemporalesExcel[dni]={}; datosTemporalesExcel[dni][fISO] = {raw: arr.join(',')}; }
            }
            renderReporte();
        } catch(e){ console.error(e); alert("Error leyendo Excel: " + e.message); }
    };
    reader.readAsArrayBuffer(file);
}

function parseFechaFlexible(v) {
    if (!v) return null;
    if (typeof v === 'number') {
        const dt = new Date(Math.round((v - 25569) * 86400 * 1000));
        return dt.toISOString().split('T')[0];
    }
    const s = String(v).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
    if (/^\d{2}\/\d{2}\/\d{4}$/.test(s)) {
        const p = s.split('/');
        return `${p[2]}-${p[1]}-${p[0]}`;
    }
    return null;
}

function renderReporte() {
    const b1=document.getElementById('bodyResumen'), b2=document.getElementById('bodyDesconocidos');
    b1.innerHTML=""; b2.innerHTML="";
    let c1=0, c2=0;
    for(let d in datosTemporalesExcel){ c1++; b1.innerHTML+=`<tr><td>${d}</td><td>${asistenciaGlobal[d].nombre}</td><td><span class="badge bg-success">Ok</span></td><td><input type="checkbox" class="chk-conf" value="${d}" checked></td></tr>`; }
    for(let d in datosDesconocidos){ c2++; b2.innerHTML+=`<tr><td class="text-danger">${d}</td><td>${datosDesconocidos[d]}</td><td><span class="badge bg-danger">Falta</span></td></tr>`; }
    document.getElementById('countFound').innerText=c1; document.getElementById('countMissing').innerText=c2;
    document.getElementById('resumenCarga').classList.remove('d-none');
    if(c2>0 && c1===0) new bootstrap.Tab(document.getElementById('missing-tab')).show(); else new bootstrap.Tab(document.getElementById('found-tab')).show();
}

// CORRECCIÓN FUSIÓN: No borrar datos anteriores
function confirmarInyeccion() {
    const r=getRangoActivo(); const ov=document.getElementById('chkSobrescribir').checked;
    document.querySelectorAll('.chk-conf:checked').forEach(c=>{
        const d=c.value, f=document.getElementById('fila-'+d);
        f.classList.remove('fila-oculta');
        
        let act={}; 
        try{ act=JSON.parse(f.querySelector('.val-json').value||'{}') }catch(e){}
        
        // Mezclar datos nuevos con los existentes
        for(let k in datosTemporalesExcel[d]){ 
            if(dentroDeRango(k,r)){ 
                // Si no existe el día O si el usuario quiere sobrescribir
                if(!act[k] || ov) {
                    act[k] = datosTemporalesExcel[d][k]; 
                }
            } 
        }
        
        f.querySelector('.val-json').value=JSON.stringify(act);
        inyectarFilaEspecifica(d);
    });
    document.getElementById('resumenCarga').classList.add('d-none');
    alert("Datos cargados correctamente.");
}
function cancelarCarga(){ document.getElementById('resumenCarga').classList.add('d-none'); }

function inyectarFilaEspecifica(dni){
    const tr=document.getElementById('fila-'+dni); if(!tr) return;
    let obj={}; try{obj=JSON.parse(tr.querySelector('.val-json').value||'{}')}catch(e){}
    
    // Calcular totales recorriendo TODO el objeto JSON (no solo el rango)
    // para respetar la persistencia completa
    const k=Object.keys(obj);
    let tN=0,t25=0,t35=0,tNoct=0;
    
    // Si no hay días, y no estamos en "Ver Todos", ocultar
    if(k.length===0 && !document.getElementById('chkVerTodos').checked){ 
        tr.classList.add('fila-oculta'); 
        recalcTotalGeneral(); 
        return; 
    } else if(k.length>0) {
        tr.classList.remove('fila-oculta');
    }

    // Sumar horas de todos los días registrados
    for(let f in obj){
        let s=0, raw=(obj[f].raw||"").split(',');
        for(let i=0;i<raw.length;i+=2){
            const t1=parseTime(raw[i]), t2=parseTime(raw[i+1]);
            if(t2>t1){ s+=(t2-t1); tNoct+=calculateNightSeconds(t1,t2); }
        }
        s=redondearATiempoExacto(s); let h=s/3600;
        tN+=Math.min(h,8); let ex=Math.max(0,h-8); t25+=Math.min(ex,2); t35+=Math.max(0,ex-2);
    }
    
    tr.querySelector('.inp-dias').value=k.length;
    tr.querySelector('.val-hN').value=decimalToTime(tN); 
    tr.querySelector('.val-h25').value=decimalToTime(t25);
    tr.querySelector('.val-h35').value=decimalToTime(t35); 
    tr.querySelector('.val-hNoct').value=decimalToTime(tNoct/3600);
    
    calcularFila(tr);
}

function calcularFila(tr) {
    const rb = parseFloat(tr.dataset.rb) || 0;
    const ps = parseFloat(tr.dataset.seg) || 0;
    const hijos = parseInt(tr.dataset.hijos) || 0;
    const enPlanilla = tr.dataset.planilla; 

    const hN = parseTime(tr.querySelector(".val-hN").value)/3600;
    const h25 = parseTime(tr.querySelector(".val-h25").value)/3600;
    const h35 = parseTime(tr.querySelector(".val-h35").value)/3600;
    const hNoct = parseTime(tr.querySelector(".val-hNoct").value)/3600;

    const rbh = rb / 30 / 8; 
    const gh_hora = rbh * 0.1666; 
    const ct_hora = rbh * 0.0972; 
    const jornal_hora_total = rbh + gh_hora + ct_hora; 

    const af_valor_dia = (RMV / 30) * 0.10;
    const af_valor_hora = af_valor_dia / 8;
    const af = hijos ? (af_valor_hora * hN) : 0;

    const rb_p = hN * rbh;
    const e25_p = h25 * (jornal_hora_total * 1.25); 
    const e35_p = h35 * (jornal_hora_total * 1.35);
    const bono_nocturno = hNoct * (jornal_hora_total * 0.35);

    const grati_total = hN * gh_hora;
    const cts_total = hN * ct_hora;

    let cB = 0; 
    let diasObj = {}; try { diasObj = JSON.parse(tr.querySelector('.val-json').value || '{}'); } catch(e){}
    for(let f in diasObj){
        let s=0; const raw = (diasObj[f].raw || "").split(',');
        for(let i=0;i<raw.length-1;i+=2){
            let t1=parseTime(raw[i]), t2=parseTime(raw[i+1]);
            if(t2>t1) s+=(t2-t1);
        }
        if(redondearATiempoExacto(s) > 14400) cB++;
    }
    const beta = cB * ((RMV * 0.30)/30); 
    const bet6 = grati_total * 0.06;

    const base = rb_p + e25_p + e35_p + af + bono_nocturno;
    let afp = (enPlanilla === 'SI') ? base * (ps/100) : 0;
    const neto = base - afp + grati_total + cts_total + beta + bet6;

    tr.querySelector(".lbl-rb").innerText = rbh.toFixed(4);
    tr.querySelector(".lbl-grati").innerText = gh_hora.toFixed(4);
    tr.querySelector(".lbl-cts").innerText = ct_hora.toFixed(4);
    tr.querySelector(".lbl-jornal-h").innerText = jornal_hora_total.toFixed(4);
    tr.querySelector(".txt-he25").innerText = decimalToTime(h25);
    tr.querySelector(".txt-he35").innerText = decimalToTime(h35);
    tr.querySelector(".lbl-asig-fam").innerText = af.toFixed(2);
    tr.querySelector(".lbl-bnoct").innerText = decimalToTime(hNoct);
    tr.querySelector(".lbl-bet-6").innerText = bet6.toFixed(2);
    tr.querySelector(".lbl-beta-bono").innerText = beta.toFixed(2);
    tr.querySelector(".lbl-descto").innerText = afp.toFixed(2);
    tr.querySelector(".lbl-neto").innerText = neto.toFixed(2);

    tr.querySelector(".val-base").value = base.toFixed(4);
    tr.querySelector(".val-afp").value = afp.toFixed(2);
    tr.querySelector(".val-neto").value = neto.toFixed(2);
    tr.querySelector(".val-beta").value = beta.toFixed(2);
    tr.querySelector(".val-bono6").value = bet6.toFixed(2);
    tr.querySelector(".val-bnoct").value = bono_nocturno.toFixed(2);
    
    recalcTotalGeneral();
}

function recalcTotalGeneral(){
    let t=0;
    document.querySelectorAll('.fila-nomina:not(.fila-oculta) .lbl-neto').forEach(e => {
        // CORRECCIÓN SUMA CON COMAS
        let valText = e.innerText.replace(/,/g, ''); 
        t += parseFloat(valText) || 0;
    });
    document.getElementById('totalGeneral').innerText = t.toFixed(2);
}

// Helpers
function getRangoActivo(){ const fi=document.getElementById('periodo_inicio').value, ff=document.getElementById('periodo_fin').value; return (fi&&ff&&fi<=ff)?{fi,ff}:null; }
function toISODate(d){ const y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), da=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${da}`; }
function dentroDeRango(d,r){ return d>=r.fi && d<=r.ff; }
function decimalToTime(d){ let s=Math.round(d*3600); return `${Math.floor(s/3600).toString().padStart(2,'0')}:${Math.floor((s%3600)/60).toString().padStart(2,'0')}`; }
function parseTime(t){ if(!t)return 0; if(t.length===5)t+=":00"; let p=t.split(':'); return (parseInt(p[0])*3600)+(parseInt(p[1])*60)+(parseInt(p[2]||0)); }
function redondearATiempoExacto(s){ return Math.round(s/300)*300; }
function calculateNightSeconds(t1,t2){ if(t2<=t1)return 0; return Math.max(0,Math.min(t2,21600)-Math.max(t1,0)) + Math.max(0,Math.min(t2,86400)-Math.max(t1,79200)); }
function pintarRangoActivo(){ const r=getRangoActivo(); document.getElementById('txtRangoActivo').innerHTML=r?`${r.fi} &#10132; ${r.ff}`:`<span class="text-danger">Inválido</span>`; }
function recargarFiltros(){ const m=document.getElementById('mes_pago_sel').value, p=document.getElementById('periodo_pago_sel').value, fi=document.getElementById('periodo_inicio').value, ff=document.getElementById('periodo_fin').value; location.href=`?mes=${m}&periodo=${p}&fi=${fi}&ff=${ff}`; }

function enviarNomina(st){
    const fd=new FormData(document.getElementById('formNomina'));
    fd.append('estado_pago',st); 
    fd.append('mes_pago',document.getElementById('mes_pago_sel').value); 
    fd.append('periodo_pago',document.getElementById('periodo_pago_sel').value);
    
    const btn = document.querySelector("button[onclick*='enviarNomina']");
    const old = btn.innerHTML;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Guardando...`; btn.disabled = true;

    fetch('controllers/guardar_nomina.php',{method:'POST',body:fd})
    .then(r=>r.text())
    .then(res=>{ alert(res); location.reload(); })
    .catch(e=>alert("Error"))
    .finally(() => { btn.innerHTML = old; btn.disabled = false; });
}

// Modales
function abrirModalAsistencia(dni){ 
    dniActivo = dni; 
    document.getElementById('modalInfoTrabajador').innerText = `${asistenciaGlobal[dni].nombre} (${dni})`;
    const rango = getRangoActivo(); 
    if(!rango) return alert("Rango inválido.");
    const body = document.getElementById('bodyModal'); body.innerHTML = "";
    const fila = document.getElementById('fila-'+dni);
    let dias = {}; try{ dias = JSON.parse(fila.querySelector('.val-json').value || '{}'); } catch(e){}
    const keys = Object.keys(dias).sort();
    
    if(keys.length === 0) {
        body.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">Sin registros.</td></tr>`;
    } else {
        keys.forEach(f => {
            let raw = (dias[f].raw || "").split(',').filter(x => x);
            let html = '<div class="tramos-container d-flex flex-column gap-2">';
            if(raw.length === 0) {
                html += genInp("08:00", "17:00", true);
            } else {
                for(let i=0; i<raw.length; i+=2) {
                    let t1 = raw[i] ? raw[i].substr(0,5) : "00:00";
                    let t2 = raw[i+1] ? raw[i+1].substr(0,5) : "00:00";
                    html += genInp(t1, t2, i===0);
                }
            }
            html += '</div><button class="btn btn-link btn-sm p-0 text-success fw-bold text-decoration-none mt-1" onclick="addTramo(this)">+ Intervalo</button>';
            const tr = document.createElement('tr'); tr.className = "text-center align-middle";
            tr.innerHTML = `<td><span class="f-txt fw-bold">${f}</span></td><td class="text-start">${html}</td><td class="c-n fw-bold text-secondary"></td><td class="c-25 fw-bold text-warning"></td><td class="c-35 fw-bold text-danger"></td><td><button class="btn btn-sm text-danger" onclick="borrarFilaModal(this)"><i class="bi bi-trash"></i></button></td>`;
            body.appendChild(tr);
            recalcM(tr.querySelector('input')); 
        });
    }
    sumM();
    new bootstrap.Modal(document.getElementById('modalAsistencia')).show();
}

function genInp(v1, v2, esPrimero){
    const btnBorrar = esPrimero ? '' : `<button class="btn btn-sm text-danger ms-1 border-0" onclick="removerTramo(this)"><i class="bi bi-x-circle-fill"></i></button>`;
    return `<div class="tramo-input d-flex align-items-center gap-1"><input type="time" class="time-field form-control form-control-sm" value="${v1}" onchange="recalcM(this)"><i class="bi bi-arrow-right small text-muted"></i><input type="time" class="time-field form-control form-control-sm" value="${v2}" onchange="recalcM(this)">${btnBorrar}</div>`;
}
function addTramo(btn){ const d=document.createElement('div'); d.innerHTML=genInp("00:00","00:00",false); btn.previousElementSibling.appendChild(d.firstElementChild); }
function removerTramo(btn){ const f=btn.closest('tr'); btn.closest('.tramo-input').remove(); recalcM(f.querySelector('input')); }
function borrarFilaModal(btn) { btn.closest('tr').remove(); sumM(); }
function agregarDiaModal(){ const r=getRangoActivo(); const f=prompt(`Fecha (${r.fi}-${r.ff}):`); if(!f||!dentroDeRango(f,r))return alert("Fecha inválida"); let ex=false; document.querySelectorAll('.f-txt').forEach(s=>{if(s.innerText==f)ex=true}); if(ex)return alert("Ya existe"); const b=document.getElementById('bodyModal'); if(b.querySelector('td[colspan]')) b.innerHTML=""; const tr=document.createElement('tr'); tr.className="text-center align-middle"; tr.innerHTML=`<td><span class="f-txt fw-bold">${f}</span></td><td class="text-start"><div class="tramos-container d-flex flex-column gap-2">${genInp("08:00","17:00",true)}</div><button class="btn btn-link btn-sm p-0 text-success fw-bold text-decoration-none mt-1" onclick="addTramo(this)">+ Intervalo</button></td><td class="c-n fw-bold text-secondary">08:00</td><td class="c-25 fw-bold text-warning">00:00</td><td class="c-35 fw-bold text-danger">00:00</td><td><button class="btn btn-sm btn-outline-danger border-0" onclick="borrarFilaModal(this)"><i class="bi bi-trash"></i></button></td>`; body.appendChild(tr); recalcM(tr.querySelector('input')); }
function guardarCambiosModal(){ const n={}; const r=getRangoActivo(); document.querySelectorAll('#bodyModal tr').forEach(tr=>{ const f=tr.querySelector('.f-txt')?.innerText; if(f&&dentroDeRango(f,r)){ const t=[]; tr.querySelectorAll('input').forEach(i=>{ if(i.value){ let v=i.value; if(v.length===5)v+=":00"; t.push(v); } }); if(t.length>0 && t.length%2===0) n[f]={raw:t.join(',')}; } }); document.getElementById('fila-'+dniActivo).querySelector('.val-json').value=JSON.stringify(n); inyectarFilaEspecifica(dniActivo); bootstrap.Modal.getInstance(document.getElementById('modalAsistencia')).hide(); }
function abrirModalCrearTrabajador(){ const k=Object.keys(datosDesconocidos)[0]; if(k){document.getElementById('new_dni').value=k; document.getElementById('new_nombre').value=datosDesconocidos[k];} new bootstrap.Modal(document.getElementById('modalCrearTrabajador')).show(); }
function guardarTrabajadorFast(){ const d=document.getElementById('new_dni').value, n=document.getElementById('new_nombre').value; if(!d||!n)return alert("Faltan datos"); const fd=new FormData(); fd.append('accion','crear_rapido'); fd.append('dni',d); fd.append('nombre',n); fetch('controllers/trabajador_controller.php',{method:'POST',body:fd}).then(r=>r.json()).then(res=>{ if(res.success){ alert("Creado"); location.reload(); }else alert(res.message); }).catch(e=>alert("Error")); }
function recalcM(el){ if(!el)return; const tr=el.closest('tr'); let s=0; tr.querySelectorAll('.tramo-input').forEach(d=>{ const i=d.querySelectorAll('input'); if(i.length==2){ const t1=parseTime(i[0].value), t2=parseTime(i[1].value); if(t2>t1) s+=(t2-t1); } }); let h=redondearATiempoExacto(s)/3600; tr.querySelector('.c-n').innerText=decimalToTime(Math.min(h,8)); tr.querySelector('.c-25').innerText=decimalToTime(Math.min(Math.max(0,h-8),2)); tr.querySelector('.c-35').innerText=decimalToTime(Math.max(0,h-10)); sumM(); }
function sumM(){ let n=0,e2=0,e3=0; document.querySelectorAll('#bodyModal tr').forEach(tr=>{ if(tr.querySelector('.c-n')){ n+=parseTime(tr.querySelector('.c-n').innerText)||0; e2+=parseTime(tr.querySelector('.c-25').innerText)||0; e3+=parseTime(tr.querySelector('.c-35').innerText)||0; } }); document.getElementById('footerModal').innerHTML=`<tr><td colspan="2" class="text-end pe-3 text-uppercase text-muted">Totales:</td><td class="bg-light fw-bold">${decimalToTime(n/3600)}</td><td class="bg-warning bg-opacity-25 fw-bold">${decimalToTime(e2/3600)}</td><td class="bg-danger bg-opacity-25 fw-bold">${decimalToTime(e3/3600)}</td><td></td></tr>`; }
</script>