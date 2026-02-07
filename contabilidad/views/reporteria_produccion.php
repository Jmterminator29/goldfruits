<?php
// views/reporteria_produccion.php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once $_SERVER['DOCUMENT_ROOT'] . '/contabilidad/config/db.php';

// =================================================================================
// 0. CONFIGURACIÓN DINÁMICA (COOKIE)
// =================================================================================
$puestos_exentos = [];
if(isset($_COOKIE['gf_puestos_exentos'])) {
    $puestos_exentos = json_decode($_COOKIE['gf_puestos_exentos'], true);
    if(!is_array($puestos_exentos)) $puestos_exentos = [];
}
$todos_los_puestos_hoy = [];

// --- GESTIÓN DE FECHAS ---
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
if (isset($_GET['mes']) && isset($_GET['anio']) && !isset($_GET['fecha'])) {
    $fecha_filtro = $_GET['anio'] . "-" . str_pad($_GET['mes'], 2, '0', STR_PAD_LEFT) . "-01";
    if($_GET['mes'] == date('n') && $_GET['anio'] == date('Y')) $fecha_filtro = date('Y-m-d');
}
$anio = date('Y', strtotime($fecha_filtro));
$mes = date('n', strtotime($fecha_filtro));
$dias_en_el_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);

// --- HELPER ---
function seg_a_hora($segundos) {
    $h = floor($segundos / 3600);
    $m = floor(($segundos % 3600) / 60);
    return sprintf("%02d:%02d", $h, $m);
}

// =================================================================================
// 1. CONTROLADORES (POST/GET)
// =================================================================================

// A. GUARDAR LOTE
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
            echo "<script>window.location.href='index.php?view=reporteria&fecha=$fecha_lote';</script>";
            exit;
        }
    }
}

// B. GUARDAR ACTIVIDAD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_actividad') {
    $actividad = mysqli_real_escape_string($conexion, $_POST['nombre_actividad']);
    $desc = mysqli_real_escape_string($conexion, $_POST['descripcion']);
    $hi = $_POST['hora_inicio'];
    $hf = $_POST['hora_fin'];
    $fecha_act = $_POST['fecha'];

    $sql = "INSERT INTO registro_actividades (fecha, hora_inicio, hora_fin, nombre_actividad, descripcion)
            VALUES ('$fecha_act', '$hi', '$hf', '$actividad', '$desc')";
    if(mysqli_query($conexion, $sql)) {
        echo "<script>window.location.href='index.php?view=reporteria&fecha=$fecha_act';</script>";
        exit;
    }
}

// C. ELIMINAR
if (isset($_GET['del_lote'])) {
    $id_del = (int)$_GET['del_lote'];
    $qf = mysqli_query($conexion, "SELECT fecha_produccion FROM detalle_lotes WHERE id_lote=$id_del");
    $f_del = mysqli_fetch_assoc($qf)['fecha_produccion'] ?? $fecha_filtro;
    mysqli_query($conexion, "DELETE FROM detalle_lotes WHERE id_lote = $id_del");
    actualizarTotalesDia($conexion, $f_del);
    echo "<script>window.location.href='index.php?view=reporteria&fecha=$f_del';</script>";
    exit;
}

if (isset($_GET['del_act'])) {
    $id_del = (int)$_GET['del_act'];
    $qf = mysqli_query($conexion, "SELECT fecha FROM registro_actividades WHERE id_actividad=$id_del");
    $f_del = mysqli_fetch_assoc($qf)['fecha'] ?? $fecha_filtro;
    mysqli_query($conexion, "DELETE FROM registro_actividades WHERE id_actividad = $id_del");
    echo "<script>window.location.href='index.php?view=reporteria&fecha=$f_del';</script>";
    exit;
}

function actualizarTotalesDia($conn, $fecha) {
    // Calculo básico de kilos
    $q = mysqli_query($conn, "SELECT SUM(kilos_netos) as k, SUM(cant_jabas) as j FROM detalle_lotes WHERE fecha_produccion='$fecha'");
    $r = mysqli_fetch_assoc($q);
    $kilos = $r['k'] ?? 0; $jabas = $r['j'] ?? 0;
    
    $sql_upd = "INSERT INTO produccion_diaria (fecha_produccion, cantidad_kilos, cantidad_jabas)
                VALUES ('$fecha', '$kilos', '$jabas')
                ON DUPLICATE KEY UPDATE cantidad_kilos='$kilos', cantidad_jabas='$jabas'";
    mysqli_query($conn, $sql_upd);
}

// =================================================================================
// 2. CÁLCULO DE VENTANA OPERATIVA Y DATOS PARA CRUCE
// =================================================================================

$lotes_dia = [];
$actividades_dia = [];
$inicio_prod = null; 
$fin_prod = null; 

function actualizarVentana($h_ini, $h_fin, $fecha, &$inicio, &$fin) {
    if($h_ini && $h_fin) {
        $ts_ini = strtotime($fecha . ' ' . $h_ini);
        $ts_fin = strtotime($fecha . ' ' . $h_fin);
        if($ts_fin < $ts_ini) $ts_fin += 86400; // Turno noche

        if ($inicio === null || $ts_ini < $inicio) $inicio = $ts_ini;
        if ($fin === null || $ts_fin > $fin) $fin = $ts_fin;
        return ($ts_fin - $ts_ini);
    }
    return 0;
}

// 1. Cargar Lotes
$q_lotes = mysqli_query($conexion, "SELECT * FROM detalle_lotes WHERE fecha_produccion = '$fecha_filtro' ORDER BY hora_inicio ASC");
while($l = mysqli_fetch_assoc($q_lotes)) {
    $lotes_dia[] = $l;
    actualizarVentana($l['hora_inicio'], $l['hora_fin'], $fecha_filtro, $inicio_prod, $fin_prod);
}

// 2. Cargar Actividades
$q_act = @mysqli_query($conexion, "SELECT * FROM registro_actividades WHERE fecha = '$fecha_filtro' ORDER BY hora_inicio ASC");
if($q_act) {
    while($a = mysqli_fetch_assoc($q_act)) {
        $actividades_dia[] = $a;
        actualizarVentana($a['hora_inicio'], $a['hora_fin'], $fecha_filtro, $inicio_prod, $fin_prod);
    }
}

// =================================================================================
// 3. PROCESAMIENTO DE PERSONAL Y TARIFAS (TIME-DRIVEN)
// =================================================================================
$personal_presente = [];
$costo_total_mo = 0;
$costo_no_utilizable = 0;
$total_seg_pagados = 0;
$total_seg_muertos = 0;
$calculadora_trabajadores = []; 

$res_c = mysqli_query($conexion, "SELECT valor FROM configuracion_global WHERE clave='RMV'");
$rmv = mysqli_fetch_assoc($res_c)['valor'] ?? 1130.00;

$sql_mo = "SELECT n.detalle_horarios, t.apellidos_nombres, p.nombre_puesto, c.monto_categoria, t.tiene_hijos, t.en_planilla,
                  COALESCE(a.porcentaje_descuento, 13.00) as tasa_afp
           FROM nomina_procesada n
           JOIN trabajadores t ON n.id_trabajador = t.id_trabajador
           LEFT JOIN categorias_pago c ON t.id_categoria = c.id_categoria
           LEFT JOIN puestos p ON t.id_puesto = p.id_puesto
           LEFT JOIN aseguradoras a ON t.id_aseguradora = a.id_aseguradora
           WHERE n.detalle_horarios LIKE '%\"$fecha_filtro\"%' AND n.estado != 'ANULADO'";

$res_mo = mysqli_query($conexion, $sql_mo);

while($row = mysqli_fetch_assoc($res_mo)) {
    $json = json_decode($row['detalle_horarios'], true);
    if(!is_array($json)) continue;

    $es_fijo = isset($json['es_fijo']) && $json['es_fijo'] === true;
    $puesto_actual = strtoupper(trim($row['nombre_puesto'] ?? 'SIN PUESTO'));
    if(!in_array($puesto_actual, $todos_los_puestos_hoy, true)) $todos_los_puestos_hoy[] = $puesto_actual;
    $es_puesto_exento = in_array($puesto_actual, $puestos_exentos, true);

    if (isset($json[$fecha_filtro])) {
        $raw = explode(',', $json[$fecha_filtro]['raw'] ?? '');
        $seg_pagados = 0;
        $seg_muertos = 0;
        $tramos_visual = [];
        $tramos_timestamps = []; 

        // A. Procesar Tiempos
        for($i=0; $i<count($raw); $i+=2) {
            if(isset($raw[$i+1])) {
                $t_ent = strtotime($fecha_filtro.' '.$raw[$i]);
                $t_sal = strtotime($fecha_filtro.' '.$raw[$i+1]);
                if($t_sal < $t_ent) $t_sal += 86400;

                if($t_sal > $t_ent) {
                    $duracion = $t_sal - $t_ent;
                    $seg_pagados += $duracion;
                    $tramos_visual[] = substr($raw[$i],0,5).'-'.substr($raw[$i+1],0,5);
                    $tramos_timestamps[] = ['ini' => $t_ent, 'fin' => $t_sal];

                    if ($es_puesto_exento) {
                        $seg_muertos += 0;
                    } else {
                        if ($inicio_prod && $fin_prod) {
                            if ($t_ent < $inicio_prod) $seg_muertos += max(0, min($t_sal, $inicio_prod) - $t_ent);
                            if ($t_sal > $fin_prod) $seg_muertos += max(0, $t_sal - max($t_ent, $fin_prod));
                        } else {
                            $seg_muertos += $duracion;
                        }
                    }
                }
            }
        }

        // B. Calcular Costo
        $dinero_pagado = 0;
        $dinero_muerto = 0;
        $monto_base = (float)($row['monto_categoria'] ?? 0);

        if ($es_fijo) {
            $costo_mensual_bruto = 0;
            if (($row['en_planilla'] ?? '') == 'SI') {
                $neto_objetivo = $monto_base;
                $tasa = ((float)($row['tasa_afp'] ?? 0) > 0) ? ((float)$row['tasa_afp'] / 100) : 0.1137;
                $bruto_calculado = ($tasa < 1) ? ($neto_objetivo / (1 - $tasa)) : $neto_objetivo;
                if ((int)($row['tiene_hijos'] ?? 0) == 1) $bruto_calculado += ($rmv * 0.10);
                $costo_mensual_bruto = $bruto_calculado; 
            } else {
                $costo_mensual_bruto = $monto_base;
            }
            $dinero_pagado = $costo_mensual_bruto / $dias_en_el_mes;
            if ($seg_pagados > 0) $dinero_muerto = $dinero_pagado * ($seg_muertos / $seg_pagados);
        } else {
            $costo_hora_base = ($monto_base / 30) / 8;
            if ((int)($row['tiene_hijos'] ?? 0) == 1 && ($row['en_planilla'] ?? '') == 'SI') {
                $costo_hora_base += (($rmv * 0.10) / 30) / 8;
            }
            $dinero_pagado = ($seg_pagados / 3600) * $costo_hora_base;
            $dinero_muerto = ($seg_muertos / 3600) * $costo_hora_base;
        }

        if ($seg_pagados > 0) {
            $costo_por_segundo = $dinero_pagado / $seg_pagados;
            
            $calculadora_trabajadores[] = [
                'nombre' => $row['apellidos_nombres'],
                'tramos' => $tramos_timestamps,
                'costo_segundo' => $costo_por_segundo
            ];

            $costo_total_mo += $dinero_pagado;
            $costo_no_utilizable += $dinero_muerto;
            $total_seg_pagados += $seg_pagados;
            $total_seg_muertos += $seg_muertos;

            $badge_tipo = $es_fijo ? '<span class="badge bg-primary">FIJO</span>' : '<span class="badge bg-secondary">VAR</span>';
            if ($es_puesto_exento) $badge_tipo .= ' <span class="badge bg-success"><i class="bi bi-shield-check"></i></span>';

            $personal_presente[] = [
                'nombre' => $row['apellidos_nombres'] ?? '',
                'puesto' => $row['nombre_puesto'] ?? '',
                'horario' => implode(' | ', $tramos_visual),
                'h_visual' => seg_a_hora($seg_pagados),
                'hm_visual' => seg_a_hora($seg_muertos),
                'c_total' => $dinero_pagado,
                'c_muerto' => $dinero_muerto,
                'badge' => $badge_tipo
            ];
        }
    }
}

// Actualizar BD Histórica
$db_horas_pagadas = $total_seg_pagados > 0 ? ($total_seg_pagados / 3600) : 0;
$db_horas_muertas = $total_seg_muertos > 0 ? ($total_seg_muertos / 3600) : 0;

$sql_snapshot = "INSERT INTO produccion_diaria 
    (fecha_produccion, costo_total_mo, costo_no_utilizable, total_horas_pagadas, total_horas_muertas)
    VALUES 
    ('$fecha_filtro', '$costo_total_mo', '$costo_no_utilizable', '$db_horas_pagadas', '$db_horas_muertas')
    ON DUPLICATE KEY UPDATE 
    costo_total_mo = VALUES(costo_total_mo),
    costo_no_utilizable = VALUES(costo_no_utilizable),
    total_horas_pagadas = VALUES(total_horas_pagadas),
    total_horas_muertas = VALUES(total_horas_muertas)";
@mysqli_query($conexion, $sql_snapshot); 

$res_prod = mysqli_query($conexion, "SELECT * FROM produccion_diaria WHERE fecha_produccion = '$fecha_filtro'");
$prod_dia = mysqli_fetch_assoc($res_prod) ?? ['cantidad_kilos'=>0, 'cantidad_jabas'=>0];
$kilos_totales = (float)($prod_dia['cantidad_kilos'] ?? 0);
$unitario_dia = ($kilos_totales > 0) ? ($costo_total_mo / $kilos_totales) : 0;

// =================================================================================
// 4. FUNCIÓN MÁGICA: CÁLCULO PRECISO COSTO LOTE
// =================================================================================
function calcularCostoRealLote($lote_ini_str, $lote_fin_str, $fecha, $workers_data) {
    if(!$lote_ini_str || !$lote_fin_str) return 0;

    $l_ini = strtotime($fecha . ' ' . $lote_ini_str);
    $l_fin = strtotime($fecha . ' ' . $lote_fin_str);
    if($l_fin < $l_ini) $l_fin += 86400;

    $costo_acumulado = 0;

    foreach($workers_data as $w) {
        foreach($w['tramos'] as $tramo) {
            $w_ini = $tramo['ini'];
            $w_fin = $tramo['fin'];

            // Intersección
            $overlap_start = max($l_ini, $w_ini);
            $overlap_end = min($l_fin, $w_fin);

            if($overlap_end > $overlap_start) {
                $segundos_trabajados_en_lote = $overlap_end - $overlap_start;
                $costo_acumulado += ($segundos_trabajados_en_lote * $w['costo_segundo']);
            }
        }
    }
    return $costo_acumulado;
}

// =================================================================================
// GRÁFICOS
// =================================================================================
$datos_grafico = [];
for($d=1; $d<=$dias_en_el_mes; $d++) $datos_grafico[$d] = ['kilos'=>0, 'costo'=>0];

$sql_grafico = "SELECT DAY(fecha_produccion) as dia, cantidad_kilos, costo_total_mo 
                FROM produccion_diaria 
                WHERE MONTH(fecha_produccion)='$mes' AND YEAR(fecha_produccion)='$anio'";
$q_graf = mysqli_query($conexion, $sql_grafico);

while($fila = mysqli_fetch_assoc($q_graf)) {
    $d = (int)$fila['dia'];
    $datos_grafico[$d]['kilos'] = (float)$fila['cantidad_kilos'];
    $datos_grafico[$d]['costo'] = (float)$fila['costo_total_mo'];
}

$labels_js = []; $data_kilos_js = []; $data_costo_js = []; $data_unit_js = [];
foreach($datos_grafico as $d => $data) {
    $labels_js[] = (string)$d;
    $data_kilos_js[] = (float)$data['kilos'];
    $data_costo_js[] = round((float)$data['costo'], 2);
    $unit = ($data['kilos'] > 0) ? ((float)$data['costo'] / (float)$data['kilos']) : 0;
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
        .chart-wrapper { position: relative; height: 300px; width: 100%; }
        .grid-detail { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: #eee; border-radius: 6px; overflow: hidden; margin-top: 10px; }
        .grid-cell { background: #fff; padding: 5px; text-align: center; }
        .grid-label { font-size: 0.65rem; font-weight: 700; color: #777; background: #f9f9f9; }
        .grid-val { font-weight: 700; font-size: 0.85rem; }
        .nav-pills .nav-link { color: #555; font-weight: 600; border-radius: 50px; padding: 8px 20px; background: #fff; border: 1px solid #eee; margin-right: 5px; }
        .nav-pills .nav-link.active { background: var(--gf-primary); color: #fff; }
        :root{ --bs-backdrop-zindex: 9998; --bs-modal-zindex: 9999; }
        .modal-backdrop { z-index: var(--bs-backdrop-zindex) !important; }
        .modal { z-index: var(--bs-modal-zindex) !important; }
        #modalConfigPuestos { z-index: var(--bs-modal-zindex) !important; }
    </style>
</head>
<body>

<div class="container-fluid p-4 animate__animated animate__fadeIn">

    <div class="panel-header-custom d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold m-0" style="color: var(--gf-primary);"><i class="bi bi-speedometer2 me-2"></i>Control de Producción</h4>
            <span class="text-muted small">Panel de Rendimiento, Costos y Tiempos</span>
        </div>

        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-outline-secondary btn-sm fw-bold rounded-pill" onclick="abrirConfiguracion()">
                <i class="bi bi-gear-fill me-1"></i> Configurar Puestos
            </button>

            <form method="GET" class="d-flex flex-wrap align-items-center gap-2 bg-light p-2 rounded-3 border ms-2">
                <input type="hidden" name="view" value="reporteria">
                <select name="anio" class="form-select form-select-sm fw-bold border-0 bg-transparent" style="width: auto;" onchange="this.form.submit()">
                    <?php for($y=date('Y'); $y>=2024; $y--): ?>
                        <option value="<?= $y ?>" <?= $anio==$y?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <div class="vr"></div>
                <select name="mes" class="form-select form-select-sm fw-bold border-0 bg-transparent" style="width: auto;" onchange="this.form.submit()">
                    <?php foreach(["Ene","Feb","Mar","Abr","May","Jun","Jul","Ago","Sep","Oct","Nov","Dic"] as $i=>$m): ?>
                        <option value="<?= $i+1 ?>" <?= $mes==($i+1)?'selected':'' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="vr"></div>
                <input type="date" name="fecha" value="<?= $fecha_filtro ?>" class="form-control form-control-sm border-0 fw-bold bg-white text-center shadow-sm" style="width: 130px; color: var(--gf-primary);" onchange="this.form.submit()">
            </form>
        </div>
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
                        <div class="text-muted small"><?= (int)($prod_dia['cantidad_jabas'] ?? 0) ?> Jabas</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card border-start border-5 border-danger">
                        <div class="kpi-title">Costo Mano Obra (Total Día)</div>
                        <div class="kpi-value text-danger">S/ <?= number_format($costo_total_mo, 2) ?></div>
                        <div class="text-muted small"><?= count($personal_presente) ?> Personas</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card border-start border-5 border-secondary">
                        <div class="kpi-title">Dinero No Utilizable</div>
                        <div class="kpi-value text-muted">S/ <?= number_format($costo_no_utilizable, 2) ?></div>
                        <div class="text-danger small fw-bold"><?= seg_a_hora($total_seg_muertos) ?> Horas Muertas</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card border-start border-5 border-success">
                        <div class="kpi-title">Costo Unitario (Promedio)</div>
                        <div class="kpi-value text-success">S/ <?= number_format($unitario_dia, 4) ?></div>
                        <div class="text-muted small">Soles por Kilo</div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-dark text-white fw-bold py-3"><i class="bi bi-box-seam me-2"></i>Gestión de Lotes</div>
                        <div class="card-body bg-light">
                            <div class="row">
                                <div class="col-lg-12 mb-3 border-bottom pb-3">
                                    <h6 class="fw-bold mb-2 text-primary">Ingresar Nuevo Lote</h6>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="guardar_lote">
                                        <input type="hidden" name="fecha" value="<?= $fecha_filtro ?>">
                                        <div class="row g-2">
                                            <div class="col-4">
                                                <input type="text" name="numero_fut" class="form-control form-control-sm fw-bold" placeholder="FUT" required>
                                            </div>
                                            <div class="col-4">
                                                <input type="time" name="hora_inicio" class="form-control form-control-sm text-center" required>
                                            </div>
                                            <div class="col-4">
                                                <input type="time" name="hora_fin" class="form-control form-control-sm text-center" required>
                                            </div>
                                        </div>
                                        <div class="row g-2 mt-1">
                                            <div class="col-3"><input type="number" step="0.01" name="kilos_netos" class="form-control form-control-sm border-success text-center" placeholder="Neto" required></div>
                                            <div class="col-3"><input type="number" step="0.01" name="kilos_exportables" class="form-control form-control-sm border-primary text-center" placeholder="Exp"></div>
                                            <div class="col-3"><input type="number" step="0.01" name="kilos_descarte" class="form-control form-control-sm border-danger text-center" placeholder="Desc"></div>
                                            <div class="col-3"><input type="number" name="cant_jabas" class="form-control form-control-sm text-center" placeholder="Jabas"></div>
                                        </div>
                                        <button type="submit" class="btn btn-warning w-100 fw-bold btn-sm mt-2">GUARDAR LOTE</button>
                                    </form>
                                </div>
                                <div class="col-lg-12">
                                    <h6 class="fw-bold mb-2 text-secondary">Lotes (<?= count($lotes_dia) ?>)</h6>
                                    <?php if(empty($lotes_dia)): ?>
                                        <div class="text-muted small">Sin registros.</div>
                                    <?php else: ?>
                                        <div style="max-height: 300px; overflow-y: auto;">
                                            <?php foreach($lotes_dia as $l): ?>
                                                <?php 
                                                    // TIME-DRIVEN ABC: Costo preciso cruzando horarios
                                                    $costo_real_lote = calcularCostoRealLote(
                                                        $l['hora_inicio'], 
                                                        $l['hora_fin'], 
                                                        $fecha_filtro, 
                                                        $calculadora_trabajadores
                                                    );
                                                    
                                                    // Unitario Específico del Lote
                                                    $unitario_lote = ($l['kilos_netos'] > 0) ? ($costo_real_lote / $l['kilos_netos']) : 0;
                                                    
                                                    // Costos de Cajas
                                                    $caja4 = $unitario_lote * 4;
                                                    $caja10 = $unitario_lote * 10;
                                                ?>
                                                <div class="lote-card position-relative py-2 px-3 mb-2">
                                                    <a href="index.php?view=reporteria&fecha=<?= $fecha_filtro ?>&del_lote=<?= (int)$l['id_lote'] ?>" class="position-absolute top-0 end-0 m-1 text-danger" onclick="return confirm('¿Eliminar?')"><i class="bi bi-x"></i></a>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="fw-bold text-dark small"><i class="bi bi-tag-fill me-1 text-secondary"></i><?= htmlspecialchars($l['numero_fut'] ?? '') ?></span>
                                                        <span class="badge bg-light text-dark border"><?= substr($l['hora_inicio'] ?? '',0,5) ?> - <?= substr($l['hora_fin'] ?? '',0,5) ?></span>
                                                    </div>
                                                    <div class="grid-detail mt-1">
                                                        <div class="grid-cell"><div class="grid-label">NETO</div><div class="grid-val text-success"><?= number_format((float)($l['kilos_netos'] ?? 0), 2) ?></div></div>
                                                        <div class="grid-cell"><div class="grid-label">EXP</div><div class="grid-val text-primary"><?= number_format((float)($l['kilos_exportables'] ?? 0), 2) ?></div></div>
                                                        <div class="grid-cell"><div class="grid-label">DESC</div><div class="grid-val text-danger"><?= number_format((float)($l['kilos_descarte'] ?? 0), 2) ?></div></div>
                                                    </div>
                                                    
                                                    <div class="mt-2 pt-2 border-top">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <div class="text-muted fst-italic" style="font-size: 0.75rem;">
                                                                Costo Real MO: <span class="fw-bold text-dark">S/ <?= number_format($costo_real_lote, 2) ?></span>
                                                            </div>
                                                            <div class="fw-bold text-success" style="font-size: 0.8rem;">Unit: S/ <?= number_format($unitario_lote, 3) ?>/kg</div>
                                                        </div>
                                                        <div class="d-flex gap-2 justify-content-end">
                                                            <span class="badge bg-light text-secondary border fw-normal">📦 4kg: <b>S/ <?= number_format($caja4, 2) ?></b></span>
                                                            <span class="badge bg-light text-secondary border fw-normal">📦 10kg: <b>S/ <?= number_format($caja10, 2) ?></b></span>
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
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-secondary text-white fw-bold py-3">
                            <i class="bi bi-tools me-2"></i>Actividades Auxiliares
                        </div>
                        <div class="card-body bg-light">
                            <div class="row">
                                <div class="col-lg-12 mb-3 border-bottom pb-3">
                                    <h6 class="fw-bold mb-2 text-secondary">Registrar Actividad</h6>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="guardar_actividad">
                                        <input type="hidden" name="fecha" value="<?= $fecha_filtro ?>">
                                        <div class="mb-2">
                                            <select name="nombre_actividad" class="form-select form-select-sm fw-bold" required>
                                                <option value="" disabled selected>Tipo de Actividad...</option>
                                                <option value="Limpieza">🧹 Limpieza</option>
                                                <option value="Mantenimiento">🔧 Mantenimiento</option>
                                                <option value="Almuerzo/Cena">🍽️ Almuerzo/Cena</option>
                                                <option value="Reunion">📅 Reunión/Capacitación</option>
                                                <option value="Otros">📝 Otros</option>
                                            </select>
                                        </div>
                                        <div class="input-group mb-2">
                                            <span class="input-group-text small py-0">Inicio</span>
                                            <input type="time" name="hora_inicio" class="form-control form-control-sm text-center" required>
                                            <span class="input-group-text small py-0">Fin</span>
                                            <input type="time" name="hora_fin" class="form-control form-control-sm text-center" required>
                                        </div>
                                        <div class="mb-2">
                                            <input type="text" name="descripcion" class="form-control form-control-sm" placeholder="Detalle adicional (Opcional)">
                                        </div>
                                        <button type="submit" class="btn btn-secondary w-100 fw-bold btn-sm">AGREGAR ACTIVIDAD</button>
                                    </form>
                                </div>
                                <div class="col-lg-12">
                                    <h6 class="fw-bold mb-2 text-secondary">Registros (<?= count($actividades_dia) ?>)</h6>
                                    <?php if(empty($actividades_dia)): ?>
                                        <div class="text-muted small">Sin actividades auxiliares.</div>
                                    <?php else: ?>
                                        <div style="max-height: 300px; overflow-y: auto;">
                                            <div class="d-flex flex-column gap-2">
                                                <?php foreach($actividades_dia as $a): ?>
                                                <div class="bg-white border rounded p-2 position-relative shadow-sm">
                                                    <a href="index.php?view=reporteria&fecha=<?= $fecha_filtro ?>&del_act=<?= (int)$a['id_actividad'] ?>" 
                                                    class="position-absolute top-0 end-0 m-1 text-danger small" 
                                                    onclick="return confirm('¿Eliminar?')"><i class="bi bi-trash"></i></a>
                                                    <div class="d-flex align-items-center mb-1">
                                                        <span class="badge bg-secondary me-2"><?= substr($a['hora_inicio'],0,5) ?> - <?= substr($a['hora_fin'],0,5) ?></span>
                                                        <span class="fw-bold text-dark small"><?= htmlspecialchars($a['nombre_actividad']) ?></span>
                                                    </div>
                                                    <div class="text-muted small fst-italic ps-1">
                                                        <?= htmlspecialchars($a['descripcion'] ?? '-') ?>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-5">
                <div class="card-header bg-white fw-bold py-3"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Desglose de Horas y Costos</div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle text-center" style="font-size: 0.85rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-4">Trabajador</th>
                                <th>Tipo</th>
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
                                <td class="text-start ps-4 fw-bold"><?= htmlspecialchars($p['nombre'] ?? '') ?><br><span class="text-muted small fw-normal"><?= htmlspecialchars($p['puesto'] ?? '') ?></span></td>
                                <td><?= $p['badge'] ?></td>
                                <td class="font-monospace small"><?= htmlspecialchars($p['horario'] ?? '') ?></td>
                                <td class="border-start fw-bold"><?= htmlspecialchars($p['h_visual'] ?? '00:00') ?></td>
                                <td class="fw-bold text-primary">S/ <?= number_format((float)($p['c_total'] ?? 0), 2) ?></td>
                                <td class="bg-danger bg-opacity-10 border-start text-danger fw-bold"><?= htmlspecialchars($p['hm_visual'] ?? '00:00') ?></td>
                                <td class="bg-danger bg-opacity-10 text-danger fw-bold">S/ <?= number_format((float)($p['c_muerto'] ?? 0), 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="fw-bold bg-light">
                            <tr>
                                <td colspan="3" class="text-end pe-3">TOTALES:</td>
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
                <div class="card-body">
                    <h6 class="fw-bold mb-4">Producción vs Costo Mensual</h6>
                    <div class="chart-wrapper"><canvas id="chartProduccion"></canvas></div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-4">Evolución Costo Unitario</h6>
                    <div class="chart-wrapper"><canvas id="chartUnitario"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConfigPuestos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-shield-check me-2"></i>Exentos de Tiempo Muerto</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Marque los puestos que <strong>NO</strong> deben generar horas muertas (ej. Calidad, Limpieza). Su tiempo será 100% productivo.</p>
                <div id="listaPuestosContainer" class="d-flex flex-column gap-2"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary rounded-pill" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-sm btn-success fw-bold px-4 rounded-pill" onclick="guardarConfigPuestos()">Guardar Cambios</button>
            </div>
        </div>
    </div>
</div>

<script>
const puestosEncontrados = <?= json_encode($todos_los_puestos_hoy) ?>;
const puestosExentosActuales = <?= json_encode($puestos_exentos) ?>;

function asegurarModalEnBody() {
    const modalEl = document.getElementById('modalConfigPuestos');
    if (modalEl && modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
    return modalEl;
}

function abrirConfiguracion() {
    const modalEl = asegurarModalEnBody();
    const container = document.getElementById('listaPuestosContainer');
    container.innerHTML = '';
    if(puestosEncontrados.length === 0) {
        container.innerHTML = '<div class="alert alert-warning small">No se encontraron puestos en el día seleccionado.</div>';
    } else {
        puestosEncontrados.forEach(p => {
            const isChecked = puestosExentosActuales.includes(p) ? 'checked' : '';
            const safeId = 'sw_' + String(p).replace(/[^a-zA-Z0-9_-]/g, '_');
            const html = `
                <div class="form-check form-switch p-2 border rounded bg-light">
                    <input class="form-check-input ms-0 me-2" type="checkbox" role="switch" id="${safeId}" value="${p}" ${isChecked}>
                    <label class="form-check-label fw-bold small text-dark" for="${safeId}" style="cursor:pointer; width:100%;">${p}</label>
                </div>`;
            container.innerHTML += html;
        });
    }
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, focus: true });
    modal.show();
}

function guardarConfigPuestos() {
    const seleccionados = [];
    document.querySelectorAll('#listaPuestosContainer input[type="checkbox"]:checked').forEach(chk => {
        seleccionados.push(chk.value);
    });
    const d = new Date(); d.setTime(d.getTime() + (30*24*60*60*1000));
    document.cookie = "gf_puestos_exentos=" + encodeURIComponent(JSON.stringify(seleccionados)) + ";expires="+ d.toUTCString() + ";path=/";
    location.reload();
}

const labels = <?= json_encode($labels_js) ?>;
const dataKilos = <?= json_encode($data_kilos_js) ?>;
const dataCosto = <?= json_encode($data_costo_js) ?>;
const dataUnit = <?= json_encode($data_unit_js) ?>;

new Chart(document.getElementById('chartProduccion'), {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [
            { label: 'Kilos', data: dataKilos, backgroundColor: '#f39c12', yAxisID: 'y' },
            { label: 'Costo (S/)', data: dataCosto, type: 'line', borderColor: '#c0392b', yAxisID: 'y1' }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        scales: { y: { position: 'left' }, y1: { position: 'right', grid: {drawOnChartArea: false} } }
    }
});

new Chart(document.getElementById('chartUnitario'), {
    type: 'line',
    data: { labels: labels, datasets: [{ label: 'Costo Unitario', data: dataUnit, borderColor: '#27ae60', backgroundColor: 'rgba(39, 174, 96, 0.1)', fill: true }] },
    options: { responsive: true, maintainAspectRatio: false }
});
</script>

</body>
</html>