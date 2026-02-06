<?php
// views/reporteria_produccion.php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/config/db.php';

// --- GESTIÓN DE FECHAS ---
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
if (isset($_GET['mes']) && isset($_GET['anio']) && !isset($_GET['fecha'])) {
    $fecha_filtro = $_GET['anio'] . "-" . str_pad($_GET['mes'], 2, '0', STR_PAD_LEFT) . "-01";
    if($_GET['mes'] == date('n') && $_GET['anio'] == date('Y')) $fecha_filtro = date('Y-m-d');
}
$anio = date('Y', strtotime($fecha_filtro));
$mes = date('n', strtotime($fecha_filtro));

// --- HELPER: SEGUNDOS A HH:MM ---
function seg_a_hora($segundos) {
    $h = floor($segundos / 3600);
    $m = floor(($segundos % 3600) / 60);
    return sprintf("%02d:%02d", $h, $m);
}

// =================================================================================
// 1. CONTROLADORES
// =================================================================================
$msg_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_lote') {
    $fut = mysqli_real_escape_string($conexion, $_POST['numero_fut']);
    $hi = $_POST['hora_inicio'];
    $hf = $_POST['hora_fin'];
    $jabas = (int)$_POST['cant_jabas'];
    $neto = (float)$_POST['kilos_netos'];
    $descarte = (float)$_POST['kilos_descarte'];
    $export = (float)$_POST['kilos_exportables'];
    $fecha_lote = $_POST['fecha']; 
    
    if($neto > 0) {
        $sql = "INSERT INTO detalle_lotes (fecha_produccion, numero_fut, hora_inicio, hora_fin, cant_jabas, kilos_netos, kilos_descarte, kilos_exportables) 
                VALUES ('$fecha_lote', '$fut', '$hi', '$hf', '$jabas', '$neto', '$descarte', '$export')";
        if(mysqli_query($conexion, $sql)) {
            actualizarTotalesDia($conexion, $fecha_lote);
            echo "<script>window.location.href='index.php?view=reporteria&fecha=$fecha_lote&status=ok';</script>";
            exit;
        }
    }
}

if (isset($_GET['del_lote'])) {
    $id_del = (int)$_GET['del_lote'];
    $qf = mysqli_query($conexion, "SELECT fecha_produccion FROM detalle_lotes WHERE id_lote=$id_del");
    $f_del = mysqli_fetch_assoc($qf)['fecha_produccion'];
    mysqli_query($conexion, "DELETE FROM detalle_lotes WHERE id_lote = $id_del");
    actualizarTotalesDia($conexion, $f_del);
    echo "<script>window.location.href='index.php?view=reporteria&fecha=$f_del';</script>";
    exit;
}

function actualizarTotalesDia($conn, $fecha) {
    $q = mysqli_query($conn, "SELECT SUM(kilos_netos) as k, SUM(cant_jabas) as j FROM detalle_lotes WHERE fecha_produccion='$fecha'");
    $r = mysqli_fetch_assoc($q);
    $kilos = $r['k'] ?? 0; $jabas = $r['j'] ?? 0;
    $sql_upd = "INSERT INTO produccion_diaria (fecha_produccion, cantidad_kilos, cantidad_jabas) VALUES ('$fecha', '$kilos', '$jabas') ON DUPLICATE KEY UPDATE cantidad_kilos='$kilos', cantidad_jabas='$jabas'";
    mysqli_query($conn, $sql_upd);
}

// =================================================================================
// 2. LÓGICA DE TIEMPOS EXACTOS
// =================================================================================

// A. Lotes y Ventana de Producción
$lotes_dia = [];
$inicio_produccion_ts = null;
$fin_produccion_ts = null;
$segundos_maquina = 0;

$q_lotes = mysqli_query($conexion, "SELECT * FROM detalle_lotes WHERE fecha_produccion = '$fecha_filtro' ORDER BY hora_inicio ASC");
while($l = mysqli_fetch_assoc($q_lotes)) {
    $lotes_dia[] = $l;
    if($l['hora_inicio'] && $l['hora_fin']) {
        $ts_ini = strtotime($fecha_filtro . ' ' . $l['hora_inicio']);
        $ts_fin = strtotime($fecha_filtro . ' ' . $l['hora_fin']);
        if($ts_fin < $ts_ini) $ts_fin += 86400;

        // Acumular tiempo maquina
        $segundos_maquina += ($ts_fin - $ts_ini);

        // Ventana Global
        if ($inicio_produccion_ts === null || $ts_ini < $inicio_produccion_ts) $inicio_produccion_ts = $ts_ini;
        if ($fin_produccion_ts === null || $ts_fin > $fin_produccion_ts) $fin_produccion_ts = $ts_fin;
    }
}

// B. Procesar Personal
$personal_presente = [];
$costo_total_mo = 0;
$costo_no_utilizable = 0;
$total_seg_pagados = 0;
$total_seg_muertos = 0;

$primer_ingreso = null;
$ultima_salida = null;

$res_c = mysqli_query($conexion, "SELECT valor FROM configuracion_global WHERE clave='RMV'");
$rmv = mysqli_fetch_assoc($res_c)['valor'] ?? 1130.00;
$af_hora_std = ($rmv / 30) / 8 * 0.10;

$sql_mo = "SELECT n.detalle_horarios, t.apellidos_nombres, p.nombre_puesto, c.monto_categoria, t.tiene_hijos
           FROM nomina_procesada n
           JOIN trabajadores t ON n.id_trabajador = t.id_trabajador
           LEFT JOIN categorias_pago c ON t.id_categoria = c.id_categoria
           LEFT JOIN puestos p ON t.id_puesto = p.id_puesto
           WHERE n.detalle_horarios LIKE '%\"$fecha_filtro\"%' AND n.estado != 'ANULADO'";
$res_mo = mysqli_query($conexion, $sql_mo);

while($row = mysqli_fetch_assoc($res_mo)) {
    $json = json_decode($row['detalle_horarios'], true);
    
    if (isset($json[$fecha_filtro])) {
        $raw = explode(',', $json[$fecha_filtro]['raw'] ?? '');
        $seg_pagados = 0;
        $seg_muertos = 0;
        $tramos_visual = [];

        for($i=0; $i<count($raw); $i+=2) {
            if(isset($raw[$i+1])) {
                $t_entrada = strtotime($fecha_filtro.' '.$raw[$i]); 
                $t_salida = strtotime($fecha_filtro.' '.$raw[$i+1]);
                if($t_salida < $t_entrada) $t_salida += 86400; 

                // Límites Globales Planta
                if ($primer_ingreso === null || $t_entrada < $primer_ingreso) $primer_ingreso = $t_entrada;
                if ($ultima_salida === null || $t_salida > $ultima_salida) $ultima_salida = $t_salida;

                if($t_salida > $t_entrada) {
                    $seg_pagados += ($t_salida - $t_entrada);
                    $tramos_visual[] = substr($raw[$i],0,5).'-'.substr($raw[$i+1],0,5);
                    
                    // Tiempo Muerto (Fuera de la ventana de lotes)
                    if ($inicio_produccion_ts && $fin_produccion_ts) {
                        if ($t_entrada < $inicio_produccion_ts) {
                            $limite = min($t_salida, $inicio_produccion_ts);
                            $seg_muertos += max(0, $limite - $t_entrada);
                        }
                        if ($t_salida > $fin_produccion_ts) {
                            $inicio_extra = max($t_entrada, $fin_produccion_ts);
                            $seg_muertos += max(0, $t_salida - $inicio_extra);
                        }
                    } else {
                        $seg_muertos += ($t_salida - $t_entrada); // Si no hay lotes, todo es muerto
                    }
                }
            }
        }
        
        $hrs_decimal = $seg_pagados / 3600;
        $hrs_muertas_decimal = $seg_muertos / 3600;
        
        if ($seg_pagados > 0) {
            // Costos (usando decimales para precisión monetaria)
            $costo_base_h = ((float)$row['monto_categoria']/30)/8;
            $costo_h_real = $costo_base_h * 1.09;
            if ($row['tiene_hijos']==1) $costo_h_real += ($af_hora_std * 1.09);

            $dinero_pagado = $hrs_decimal * $costo_h_real;
            $dinero_muerto = $hrs_muertas_decimal * $costo_h_real;

            $costo_total_mo += $dinero_pagado;
            $costo_no_utilizable += $dinero_muerto;
            
            // Acumular segundos para totales exactos
            $total_seg_pagados += $seg_pagados;
            $total_seg_muertos += $seg_muertos;

            $personal_presente[] = [
                'nombre' => $row['apellidos_nombres'],
                'puesto' => $row['nombre_puesto'],
                'horario' => implode(' | ', $tramos_visual),
                'h_visual' => seg_a_hora($seg_pagados), // FORMATO HH:MM
                'hm_visual' => seg_a_hora($seg_muertos), // FORMATO HH:MM
                'c_total' => $dinero_pagado,
                'c_muerto' => $dinero_muerto
            ];
        }
    }
}

// C. Cálculos Planta
$segundos_planta = 0;
if ($primer_ingreso && $ultima_salida) {
    $segundos_planta = ($ultima_salida - $primer_ingreso);
}
// Tiempo No Utilizable GLOBAL = Planta - Máquina
$segundos_no_utilizables_global = max(0, $segundos_planta - $segundos_maquina);

// Totales Generales
$res_prod = mysqli_query($conexion, "SELECT * FROM produccion_diaria WHERE fecha_produccion = '$fecha_filtro'");
$prod_dia = mysqli_fetch_assoc($res_prod) ?? ['cantidad_kilos'=>0, 'cantidad_jabas'=>0];
$kilos_totales = $prod_dia['cantidad_kilos'];
$unitario_dia = ($kilos_totales > 0) ? ($costo_total_mo / $kilos_totales) : 0;

// =================================================================================
// 3. GRÁFICOS
// =================================================================================
$datos_grafico = [];
$dias_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
for($d=1; $d<=$dias_mes; $d++) $datos_grafico[$d] = ['kilos'=>0, 'costo'=>0];

$q_pm = mysqli_query($conexion, "SELECT DAY(fecha_produccion) as d, cantidad_kilos FROM produccion_diaria WHERE MONTH(fecha_produccion)=$mes AND YEAR(fecha_produccion)=$anio");
while($p = mysqli_fetch_assoc($q_pm)) $datos_grafico[$p['d']]['kilos'] = (float)$p['cantidad_kilos'];

$like_mes = $anio . '-' . str_pad($mes, 2, '0', STR_PAD_LEFT);
$q_nm = mysqli_query($conexion, "SELECT n.detalle_horarios, c.monto_categoria, t.tiene_hijos FROM nomina_procesada n JOIN trabajadores t ON n.id_trabajador=t.id_trabajador LEFT JOIN categorias_pago c ON t.id_categoria=c.id_categoria WHERE n.detalle_horarios LIKE '%$like_mes%' AND n.estado != 'ANULADO'");
while($row = mysqli_fetch_assoc($q_nm)) {
    $json = json_decode($row['detalle_horarios'], true);
    if(is_array($json)) {
        foreach($json as $fecha => $data) {
            $ts = strtotime($fecha);
            if(date('n', $ts) == $mes && date('Y', $ts) == $anio) {
                $d = (int)date('d', $ts);
                $raw = explode(',', $data['raw'] ?? ''); $s=0;
                for($i=0; $i<count($raw); $i+=2) {
                    if(isset($raw[$i+1])) {
                        $t1 = strtotime($fecha.' '.$raw[$i]); $t2 = strtotime($fecha.' '.$raw[$i+1]);
                        if($t2<$t1) $t2+=86400; if($t2>$t1) $s+=($t2-$t1);
                    }
                }
                $hrs = round($s/3600, 2);
                if($hrs > 0) {
                    $ch = ((float)$row['monto_categoria']/30)/8;
                    $c = $hrs * $ch;
                    if($row['tiene_hijos']==1) $c += ($af_hora_std * $hrs);
                    $datos_grafico[$d]['costo'] += ($c * 1.09);
                }
            }
        }
    }
}
$labels_js = []; $data_kilos_js = []; $data_costo_js = []; $data_unit_js = [];
foreach($datos_grafico as $d => $data) {
    $labels_js[] = "$d"; $data_kilos_js[] = $data['kilos']; $data_costo_js[] = round($data['costo'], 2);
    $unit = ($data['kilos'] > 0) ? ($data['costo'] / $data['kilos']) : 0;
    $data_unit_js[] = round($unit, 4);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --gf-primary: #145A32; --gf-secondary: #D4AC0D; }
        .panel-header-custom { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-left: 5px solid var(--gf-primary); }
        .kpi-card { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); height: 100%; position: relative; overflow: hidden; border: 1px solid rgba(0,0,0,0.02); }
        .kpi-value { font-size: 1.8rem; font-weight: 800; color: #2c3e50; margin: 5px 0; }
        .kpi-title { font-size: 0.75rem; text-transform: uppercase; font-weight: 700; color: #7f8c8d; }
        .lote-card { background: #fff; border-radius: 10px; border-left: 4px solid var(--gf-secondary); box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 15px; margin-bottom: 10px; transition: 0.2s; }
        .fecha-selector { border: 2px solid #198754; font-weight: bold; color: #198754; }
        .chart-wrapper { position: relative; height: 300px; width: 100%; }
        
        /* CELDAS DETALLE LOTE */
        .grid-detail { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: #eee; border-radius: 6px; overflow: hidden; margin-top: 10px; }
        .grid-cell { background: #fff; padding: 5px; text-align: center; }
        .grid-label { font-size: 0.65rem; font-weight: 700; color: #777; background: #f9f9f9; }
        .grid-val { font-weight: 700; font-size: 0.85rem; }
        
        /* NAV PILLS */
        .nav-pills .nav-link { color: #555; font-weight: 600; border-radius: 50px; padding: 8px 20px; background: #fff; border: 1px solid #eee; margin-right: 5px; }
        .nav-pills .nav-link.active { background: var(--gf-primary); color: #fff; }
    </style>
</head>
<body>

<div class="container-fluid p-4 animate__animated animate__fadeIn">

    <div class="panel-header-custom d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold m-0" style="color: var(--gf-primary);"><i class="bi bi-speedometer2 me-2"></i>Control de Producción</h4>
            <span class="text-muted small">Panel de Rendimiento, Costos y Tiempos</span>
        </div>
        <form method="GET" class="d-flex flex-wrap align-items-center gap-2 bg-light p-2 rounded-3 border">
            <input type="hidden" name="view" value="reporteria">
            <select name="anio" class="form-select form-select-sm fw-bold border-0 bg-transparent" style="width: auto;" onchange="this.form.submit()">
                <?php for($y=date('Y'); $y>=2024; $y--): ?><option value="<?= $y ?>" <?= $anio==$y?'selected':'' ?>><?= $y ?></option><?php endfor; ?>
            </select>
            <div class="vr"></div>
            <select name="mes" class="form-select form-select-sm fw-bold border-0 bg-transparent" style="width: auto;" onchange="this.form.submit()">
                <?php foreach(["Ene","Feb","Mar","Abr","May","Jun","Jul","Ago","Sep","Oct","Nov","Dic"] as $i=>$m): ?><option value="<?= $i+1 ?>" <?= $mes==($i+1)?'selected':'' ?>><?= $m ?></option><?php endforeach; ?>
            </select>
            <div class="vr"></div>
            <input type="date" name="fecha" value="<?= $fecha_filtro ?>" class="form-control form-control-sm border-0 fw-bold bg-white text-center shadow-sm" style="width: 130px; color: var(--gf-primary);" onchange="this.form.submit()">
        </form>
    </div>

    <ul class="nav nav-pills mb-4 justify-content-center" id="pills-tab" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="tab-diario" data-bs-toggle="pill" data-bs-target="#content-diario"><i class="bi bi-kanban me-2"></i>Operación Diaria</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-mensual" data-bs-toggle="pill" data-bs-target="#content-mensual"><i class="bi bi-bar-chart-line me-2"></i>Análisis Mensual</button></li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        
        <div class="tab-pane fade show active" id="content-diario">
            
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card border-start border-5 border-warning">
                        <div class="kpi-title">Kilos Totales</div>
                        <div class="kpi-value text-dark"><?= number_format($kilos_totales, 2) ?></div>
                        <div class="text-muted small"><?= $prod_dia['cantidad_jabas'] ?> Jabas</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card border-start border-5 border-danger">
                        <div class="kpi-title">Costo Mano Obra</div>
                        <div class="kpi-value text-danger">S/ <?= number_format($costo_total_mo, 2) ?></div>
                        <div class="text-muted small"><?= count($personal_presente) ?> Personas | <?= seg_a_hora($total_seg_pagados) ?> Hrs</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card border-start border-5 border-secondary">
                        <div class="kpi-title">Dinero No Utilizable</div>
                        <div class="kpi-value text-muted">S/ <?= number_format($costo_no_utilizable, 2) ?></div>
                        <div class="text-danger small fw-bold"><?= seg_a_hora($total_seg_muertos) ?> Horas Pagadas sin Producción</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card border-start border-5 border-success">
                        <div class="kpi-title">Costo Unitario</div>
                        <div class="kpi-value text-success">S/ <?= number_format($unitario_dia, 4) ?></div>
                        <div class="text-muted small">Soles por Kilo</div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark text-white fw-bold py-3"><i class="bi bi-box-seam me-2"></i>Gestión de Lotes</div>
                <div class="card-body bg-light">
                    <div class="row">
                        <div class="col-lg-4 border-end">
                            <h6 class="fw-bold mb-3 text-primary">Ingresar Nuevo Lote</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="guardar_lote">
                                <input type="hidden" name="fecha" value="<?= $fecha_filtro ?>">
                                <div class="mb-2"><input type="text" name="numero_fut" class="form-control fw-bold" placeholder="FUT-001" required></div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text small">Inicio</span><input type="time" name="hora_inicio" class="form-control text-center" required>
                                    <span class="input-group-text small">Fin</span><input type="time" name="hora_fin" class="form-control text-center" required>
                                </div>
                                <div class="row g-1 mb-2">
                                    <div class="col-4"><input type="number" step="0.01" name="kilos_netos" class="form-control text-center border-success" placeholder="Neto" required></div>
                                    <div class="col-4"><input type="number" step="0.01" name="kilos_exportables" class="form-control text-center border-primary" placeholder="Export"></div>
                                    <div class="col-4"><input type="number" step="0.01" name="kilos_descarte" class="form-control text-center border-danger" placeholder="Desc"></div>
                                </div>
                                <div class="mb-2"><input type="number" name="cant_jabas" class="form-control" placeholder="Cant. Jabas"></div>
                                <button type="submit" class="btn btn-warning w-100 fw-bold btn-sm">GUARDAR LOTE</button>
                            </form>
                        </div>
                        <div class="col-lg-8">
                            <h6 class="fw-bold mb-3 text-secondary">Lotes Procesados (<?= count($lotes_dia) ?>)</h6>
                            <?php if(empty($lotes_dia)): ?>
                                <div class="text-muted small">No hay lotes registrados hoy.</div>
                            <?php else: ?>
                                <div class="row g-2">
                                    <?php foreach($lotes_dia as $l): ?>
                                    <div class="col-md-6">
                                        <div class="lote-card position-relative py-2 px-3">
                                            <a href="index.php?view=reporteria&fecha=<?= $fecha_filtro ?>&del_lote=<?= $l['id_lote'] ?>" class="position-absolute top-0 end-0 m-1 text-danger" onclick="return confirm('¿Eliminar?')"><i class="bi bi-x"></i></a>
                                            <div class="d-flex justify-content-between">
                                                <span class="fw-bold text-dark"><?= $l['numero_fut'] ?></span>
                                                <span class="badge bg-light text-dark border"><?= substr($l['hora_inicio'],0,5) ?> - <?= substr($l['hora_fin'],0,5) ?></span>
                                            </div>
                                            <div class="grid-detail">
                                                <div class="grid-cell"><div class="grid-label">NETO</div><div class="grid-val text-success"><?= $l['kilos_netos'] ?></div></div>
                                                <div class="grid-cell"><div class="grid-label">EXP</div><div class="grid-val text-primary"><?= $l['kilos_exportables'] ?></div></div>
                                                <div class="grid-cell"><div class="grid-label">DESC</div><div class="grid-val text-danger"><?= $l['kilos_descarte'] ?></div></div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-5">
                <div class="card-header bg-white fw-bold py-3"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Desglose de Horas y Costos por Personal</div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle text-center" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-4">Trabajador</th>
                                <th>Horario Marca</th>
                                <th class="bg-light border-start text-primary">Horas Pagadas</th>
                                <th class="bg-light text-primary">Costo Total</th>
                                <th class="bg-danger bg-opacity-10 border-start text-danger">Horas No Prod.</th>
                                <th class="bg-danger bg-opacity-10 text-danger">Dinero No Prod.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($personal_presente as $p): ?>
                            <tr>
                                <td class="text-start ps-4 fw-bold"><?= $p['nombre'] ?><br><span class="text-muted small fw-normal"><?= $p['puesto'] ?></span></td>
                                <td class="font-monospace small"><?= $p['horario'] ?></td>
                                <td class="border-start fw-bold"><?= $p['h_visual'] ?></td>
                                <td class="fw-bold text-primary">S/ <?= number_format($p['c_total'], 2) ?></td>
                                <td class="bg-danger bg-opacity-10 border-start text-danger fw-bold"><?= $p['hm_visual'] ?></td>
                                <td class="bg-danger bg-opacity-10 text-danger fw-bold">S/ <?= number_format($p['c_muerto'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="fw-bold bg-light">
                            <tr>
                                <td colspan="2" class="text-end pe-3">TOTALES:</td>
                                <td class="border-start"><?= seg_a_hora($total_seg_pagados) ?></td>
                                <td class="text-primary">S/ <?= number_format($costo_total_mo, 2) ?></td>
                                <td class="border-start text-danger"><?= seg_a_hora($total_seg_muertos) ?></td>
                                <td class="text-danger">S/ <?= number_format($costo_no_utilizable, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        </div>

        <div class="tab-pane fade" id="content-mensual">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body"><h6 class="fw-bold mb-4">Producción vs Costo Mensual</h6><div class="chart-wrapper"><canvas id="chartProduccion"></canvas></div></div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body"><h6 class="fw-bold mb-4">Evolución Costo Unitario</h6><div class="chart-wrapper"><canvas id="chartUnitario"></canvas></div></div>
            </div>
        </div>

    </div>
</div>

<script>
    const labels = <?= json_encode($labels_js) ?>;
    const dataKilos = <?= json_encode($data_kilos_js) ?>;
    const dataCosto = <?= json_encode($data_costo_js) ?>;
    const dataUnit = <?= json_encode($data_unit_js) ?>;

    new Chart(document.getElementById('chartProduccion'), { type: 'bar', data: { labels: labels, datasets: [ { label: 'Kilos', data: dataKilos, backgroundColor: '#f39c12', yAxisID: 'y' }, { label: 'Costo (S/)', data: dataCosto, type: 'line', borderColor: '#c0392b', yAxisID: 'y1' } ] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { position: 'left' }, y1: { position: 'right', grid: {drawOnChartArea: false} } } } });
    new Chart(document.getElementById('chartUnitario'), { type: 'line', data: { labels: labels, datasets: [{ label: 'Costo Unitario', data: dataUnit, borderColor: '#27ae60', backgroundColor: 'rgba(39, 174, 96, 0.1)', fill: true }] }, options: { responsive: true, maintainAspectRatio: false } });
</script>

</body>
</html>