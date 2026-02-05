<?php
// views/resumen_pagos.php
if (session_status() === PHP_SESSION_NONE) session_start();

// --- 1. CONEXIÓN ---
if (!isset($conexion)) {
    $rutas = [$_SERVER['DOCUMENT_ROOT'] . '/config/db.php', __DIR__ . '/../config/db.php', 'config/db.php'];
    foreach ($rutas as $ruta) { if (file_exists($ruta)) { include_once $ruta; break; } }
}
if (!isset($conexion)) die('<div class="alert alert-danger">Error: Sin conexión a BD.</div>');

mysqli_set_charset($conexion, "utf8");

// Filtros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : date('n'); 
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : date('Y');

// RMV
$q_rmv = mysqli_query($conexion, "SELECT valor FROM configuracion_global WHERE clave = 'RMV' LIMIT 1");
$rmv = ($row = mysqli_fetch_assoc($q_rmv)) ? (float)$row['valor'] : 1130.00;

// --- FUNCIONES AUXILIARES ---
function sumar_tiempos_db($tiempos) {
    $minutos = 0;
    foreach ($tiempos as $t) {
        if (strpos($t, ':') !== false) {
            list($h, $m) = explode(':', $t);
            $minutos += ($h * 60) + $m;
        }
    }
    return $minutos > 0 ? round($minutos / 60, 4) : 0;
}

function formato_hora_visual($decimal) {
    $horas = floor($decimal);
    $minutos = round(($decimal - $horas) * 60);
    return sprintf("%02d:%02d", $horas, $minutos);
}

// --- CONSULTA MAESTRA ---
$sql = "SELECT 
            t.id_trabajador, t.apellidos_nombres, t.numero_documento, 
            t.fecha_nacimiento, t.celular, t.correo, t.cuspp,
            t.banco_nombre, t.numero_cuenta, t.tiene_hijos, t.en_planilla,
            a.nombre_aseguradora, c.monto_categoria as sueldo_base
        FROM trabajadores t
        LEFT JOIN aseguradoras a ON t.id_aseguradora = a.id_aseguradora
        LEFT JOIN categorias_pago c ON t.id_categoria = c.id_categoria
        WHERE t.estado = 'ACTIVO' ORDER BY t.apellidos_nombres ASC";

$res = mysqli_query($conexion, $sql);

$lista_planilla = []; 
$lista_sin_planilla = [];

if(mysqli_num_rows($res) > 0) {
    while($row = mysqli_fetch_assoc($res)) {
        $sql_nom = "SELECT * FROM nomina_procesada WHERE id_trabajador = {$row['id_trabajador']} AND mes_pago = $mes AND anio_pago = $anio";
        $q_nom = mysqli_query($conexion, $sql_nom);
        if(mysqli_num_rows($q_nom) == 0) continue; 

        $arr_hn = []; $arr_h25 = []; $arr_h35 = [];
        $dias_total = 0; $db_beta = 0; $db_bono6 = 0; $db_noct = 0; $db_afp = 0;
        $neto_1ra = 0; $neto_2da = 0;

        while($n = mysqli_fetch_assoc($q_nom)) {
            $arr_hn[] = $n['horas_normales_total'];
            $arr_h25[] = $n['horas_25_total'];
            $arr_h35[] = $n['horas_35_total'];
            $dias_total += $n['dias_trabajados'];
            $db_beta += (float)$n['bono_beta'];
            $db_bono6 += (float)$n['bono_extra_6'];
            $db_noct += (float)$n['bono_nocturno'];
            $db_afp += (float)$n['monto_afp'];
            if($n['periodo_pago'] == '1RA QUINCENA') $neto_1ra += (float)$n['monto_neto_final'];
            if($n['periodo_pago'] == '2DA QUINCENA') $neto_2da += (float)$n['monto_neto_final'];
        }

        $dec_hn = sumar_tiempos_db($arr_hn);
        $dec_h25 = sumar_tiempos_db($arr_h25);
        $dec_h35 = sumar_tiempos_db($arr_h35);
        
        $rbh = ((float)$row['sueldo_base'] / 30) / 8;
        $gh = $rbh * 0.1666; $ch = $rbh * 0.0972; $rd_h = $rbh + $gh + $ch;

        $dec_noct = ($rd_h > 0) ? ($db_noct / ($rd_h * 0.35)) : 0;

        $fila_data = [
            "num" => 0, 
            "trabajador" => $row['apellidos_nombres'],
            "dni" => "DNI - " . $row['numero_documento'],
            "nacimiento" => $row['fecha_nacimiento'],
            "aseguradora" => $row['nombre_aseguradora'],
            "cuspp" => $row['cuspp'],
            "celular" => $row['celular'],
            "correo" => $row['correo'],
            "banco" => $row['banco_nombre'],
            "cuenta" => $row['numero_cuenta'],
            "hijos" => ($row['tiene_hijos'] == 1 ? 'SI' : 'NO'),
            "h_trab" => formato_hora_visual($dec_hn),
            "h_25" => formato_hora_visual($dec_h25),
            "h_35" => formato_hora_visual($dec_h35),
            "h_noct" => formato_hora_visual($dec_noct),
            "h_total" => formato_hora_visual($dec_hn + $dec_h25 + $dec_h35),
            "dec_total" => (float)number_format($dec_hn + $dec_h25 + $dec_h35 + $dec_noct, 2),
            "pago_base" => ($dec_hn * $rd_h),
            "rem_25" => ($dec_h25 * $rd_h * 1.25),
            "rem_35" => ($dec_h35 * $rd_h * 1.35),
            "rem_noct" => $db_noct,
            "asig_fam" => ($row['tiene_hijos'] == 1 ? ($rmv * 0.10) : 0),
            "total_rem" => ($dec_hn * $rd_h) + ($dec_h25 * $rd_h * 1.25) + ($dec_h35 * $rd_h * 1.35) + $db_noct + $db_bono6 + ($row['tiene_hijos'] == 1 ? ($rmv * 0.10) : 0),
            "afp_desc" => $db_afp,
            "neto" => (($dec_hn * $rd_h) + ($dec_h25 * $rd_h * 1.25) + ($dec_h35 * $rd_h * 1.35) + $db_noct + $db_bono6 + $db_beta + ($row['tiene_hijos'] == 1 ? ($rmv * 0.10) : 0)) - $db_afp,
            "sp1" => "", "sp2" => "", "sp3" => "",
            "ph_base" => (float)number_format($dec_hn * $rbh, 2),
            "ph_grati" => (float)number_format($dec_hn * $gh, 2),
            "ph_cts" => (float)number_format($dec_hn * $ch, 2),
            "ph_beta" => (float)number_format($db_beta, 2),
            "ph_6" => (float)number_format($db_bono6, 2),
            "pago_1ra" => $neto_1ra, "pago_2da" => $neto_2da, "total_mes" => ($neto_1ra + $neto_2da),
            "dias_val" => $dias_total, "beta_val" => $db_beta, "bono6_val" => $db_bono6
        ];

        if($row['en_planilla'] == 'SI') { $lista_planilla[] = $fila_data; } 
        else { $lista_sin_planilla[] = $fila_data; }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <style>
        :root { --gf-main: #145A32; --gf-dark: #0E3E23; --gf-gold: #D4AC0D; }
        body { background-color: #F8FAFB; font-family: 'Inter', sans-serif; }
        .panel-header { background: white; border-radius: 16px; padding: 30px; margin-bottom: 30px; border-left: 8px solid var(--gf-main); box-shadow: 0 10px 30px rgba(0,0,0,0.04); }
        .btn-gf-gold { background: linear-gradient(135deg, #F1C40F 0%, #D4AC0D 100%); color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 700; box-shadow: 0 4px 15px rgba(212, 172, 13, 0.3); cursor: pointer; transition: 0.3s; }
        .btn-gf-outline { background: white; color: var(--gf-main); border: 2px solid var(--gf-main); padding: 10px 20px; border-radius: 12px; font-weight: 700; transition: 0.3s; cursor: pointer;}
        .btn-gf-outline:hover { background: var(--gf-main); color: white; }
        .table-container { background: white; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; }
        .table-gf { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.82rem; }
        .table-gf thead th { background: linear-gradient(135deg, var(--gf-main), var(--gf-dark)); color: white; padding: 15px 10px; text-transform: uppercase; text-align: center; border-right: 1px solid rgba(255,255,255,0.1); }
        .table-gf tbody td { padding: 12px 10px; border-bottom: 1px solid #F1F3F5; vertical-align: middle; text-align: center; }
        .text-money { font-family: 'Roboto Mono', monospace; font-weight: 700; text-align: right; }
        .badge-status { padding: 5px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 800; display: inline-block; }
        .bg-si { background: #E8F8F5; color: #117864; border: 1px solid #A2D9CE; }
        .bg-no { background: #FDEDEC; color: #CB4335; border: 1px solid #F5B7B1; }
        .nav-gf-pills .nav-link { background: #EDF2F4; color: #7F8C8D; font-weight: 700; border-radius: 12px; margin-right: 10px; padding: 12px 25px; cursor: pointer; border: none; }
        .nav-gf-pills .nav-link.active { background: var(--gf-main); color: white; }
    </style>
</head>
<body>

<div class="container-fluid p-4">
    <div class="panel-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1" style="color: var(--gf-main);">Resumen de Pagos Goldfruits</h2>
                <p class="text-muted mb-0">Gestión de Planilla Mensual y Reportes Detallados</p>
            </div>
            
            <div class="d-flex align-items-center gap-3 bg-light p-3 rounded-4 border">
                <form method="GET" class="d-flex gap-2">
                    <?php if(isset($_GET['view'])): ?><input type="hidden" name="view" value="<?= $_GET['view'] ?>"><?php endif; ?>
                    <select name="mes" class="form-select form-select-sm fw-bold shadow-sm" style="width:130px">
                        <?php foreach(["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"] as $i=>$m): ?>
                        <option value="<?= $i+1 ?>" <?= ($mes==$i+1)?'selected':'' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="anio" value="<?= $anio ?>" class="form-control form-control-sm fw-bold shadow-sm" style="width:85px">
                    <button type="submit" class="btn btn-sm btn-dark px-3 rounded-3">Filtrar</button>
                </form>
                <div style="width:2px; height:30px; background:#DEE2E6;"></div>
                <div class="d-flex gap-2">
                    <select id="export_periodo" class="form-select form-select-sm fw-bold shadow-sm" style="width:150px; color: var(--gf-main);">
                        <option value="TODO">MES COMPLETO</option>
                        <option value="1RA">1RA QUINCENA</option>
                        <option value="2DA">2DA QUINCENA</option>
                    </select>
                    <button class="btn-gf-outline" onclick="generarExcelResumen()">RESUMEN</button>
                    <button class="btn-gf-gold" onclick="generarExcelDetallado()">DETALLADO</button>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-gf-pills mb-4" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-p">EN PLANILLA (<?= count($lista_planilla) ?>)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-np">SIN PLANILLA (<?= count($lista_sin_planilla) ?>)</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-p"><?php renderTablaGF($lista_planilla); ?></div>
        <div class="tab-pane fade" id="tab-np"><?php renderTablaGF($lista_sin_planilla); ?></div>
    </div>
</div>

<?php function renderTablaGF($lista) { if(empty($lista)) { echo "<div class='panel-header text-center py-5'>No hay datos registrados.</div>"; return; } ?>
<div class="table-container">
    <table class="table-gf">
        <thead>
            <tr>
                <th rowspan="2" style="text-align:left; padding-left:25px;">Colaborador</th>
                <th rowspan="2">DNI</th><th rowspan="2">Hijos</th><th rowspan="2">Días</th>
                <th colspan="2" style="background:#1B5E20">Normal</th>
                <th colspan="2" style="background:#F9A825">Extras</th>
                <th colspan="3" style="background:#455A64">Bonificaciones</th>
                <th rowspan="2" style="background:#C62828">Dscto</th>
                <th rowspan="2" style="background:#145A32">Neto S/</th>
            </tr>
            <tr>
                <th style="background:#2E7D32">Hrs</th><th style="background:#2E7D32">Monto</th>
                <th style="background:#FBC02D">25%</th><th style="background:#FBC02D">35%</th>
                <th style="background:#546E7A">Beta</th><th style="background:#546E7A">Noct</th><th style="background:#546E7A">6%</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($lista as $i): ?>
            <tr>
                <td style="text-align: left; padding-left: 25px;"><strong><?= $i['trabajador'] ?></strong></td>
                <td><?= str_replace("DNI - ","",$i['dni']) ?></td>
                <td><span class="badge-status <?= $i['hijos']=='SI'?'bg-si':'bg-no' ?>"><?= $i['hijos'] ?></span></td>
                <td class="fw-bold"><?= $i['dias_val'] ?></td>
                <td class="bg-light"><?= $i['h_trab'] ?></td>
                <td class="text-money bg-light">S/ <?= number_format($i['pago_base'],2) ?></td>
                <td class="text-money"><?= number_format($i['rem_25'],2) ?></td>
                <td class="text-money"><?= number_format($i['rem_35'],2) ?></td>
                <td class="text-money" style="color:#2980B9"><?= number_format($i['beta_val'],2) ?></td>
                <td class="text-money" style="color:#D35400"><?= number_format($i['rem_noct'],2) ?></td>
                <td class="text-money"><?= number_format($i['bono6_val'],2) ?></td>
                <td class="text-money text-danger">-<?= number_format($i['afp_desc'],2) ?></td>
                <td class="text-money text-success" style="font-size:0.9rem;">S/ <?= number_format($i['neto'],2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php } ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

<script>
    async function generarExcelDetallado() {
        const wb = new ExcelJS.Workbook();
        const crearHoja = (nombreHoja, datos) => {
            const ws = wb.addWorksheet(nombreHoja);
            
            const columnasEstructura = [
                { header: "ITEM", key: 'num', width: 5 },
                { header: "DNI", key: 'dni', width: 15 },
                { header: "APELLIDOS Y NOMBRES", key: 'trabajador', width: 35 },
                { header: "FECHA NAC.", key: 'nacimiento', width: 12 },
                { header: "ASEGURADORA", key: 'aseguradora', width: 15 },
                { header: "CUSPP", key: 'cuspp', width: 15 },
                { header: "CELULAR", key: 'celular', width: 12 },
                { header: "CORREO", key: 'correo', width: 30 },
                { header: "BANCO", key: 'banco', width: 10 },
                { header: "NUMERO DE CUENTA", key: 'cuenta', width: 18 },
                { header: "HIJOS", key: 'hijos', width: 8 },
                { header: "HORAS\nTRABAJADAS", key: 'h_trab', width: 12 },
                { header: "HORAS EXTRA\n25%", key: 'h_25', width: 12 },
                { header: "HORAS EXTRA\n35%", key: 'h_35', width: 12 },
                { header: "HORAS\nNOCTURNAS", key: 'h_noct', width: 12 },
                { header: "TOTAL\nHORAS", key: 'h_total', width: 12 },
                { header: "DECIMAL\nTOTAL", key: 'dec_total', width: 12 },
                { header: "REMUNERACION\nBASE", key: 'pago_base', width: 15 },
                { header: "REMUNERACIÓN\n25%", key: 'rem_25', width: 15 },
                { header: "REMUNERACIÓN\n35%", key: 'rem_35', width: 15 },
                { header: "REMUNERACIÓN\nNOCTURNA", key: 'rem_noct', width: 15 },
                { header: "ASIGNACION\nFAMILIAR", key: 'asig_fam', width: 15 },
                { header: "TOTAL\nREMUNERACION", key: 'total_rem', width: 18 },
                { header: "DEDUCCION\nAFP", key: 'afp_desc', width: 15 },
                { header: "NETO A\nPAGAR", key: 'neto', width: 15 },
                { header: "", key: 'sp1', width: 5 },
                { header: "", key: 'sp2', width: 5 },
                { header: "", key: 'sp3', width: 5 },
                { header: "REMUNERACION\nBASE", key: 'ph_base', width: 15 },
                { header: "GRATIFICACION\n(16.66%)", key: 'ph_grati', width: 15 },
                { header: "CTS\n(9.72%)", key: 'ph_cts', width: 15 },
                { header: "BETA\n(30%)", key: 'ph_beta', width: 15 },
                { header: "BONIFICACION\nEXTRAORDINARIA\n6%", key: 'ph_6', width: 15 }
            ];

            ws.columns = columnasEstructura;
            const headerRow = ws.getRow(1);
            headerRow.height = 65; 

            const colors = ['A9BCF5','A9BCF5','A9BCF5','A9BCF5','A9BCF5','A9BCF5','A9BCF5','A9BCF5','A9BCF5','A9BCF5','FF0000','FFC000','0070C0','00B0F0','E4B792','FFFF00','70AD47','E26B0A','E26B0A','E26B0A','E26B0A','FFFF00','FFFF00','E26B0A','70AD47','FFFFFF','FFFFFF','FFFFFF','E26B0A','E26B0A','E26B0A','E26B0A','E26B0A'];

            headerRow.eachCell((cell, colNum) => {
                const color = colors[colNum-1];
                if (color !== 'FFFFFF') {
                    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF' + color } };
                    cell.font = { bold: true, size: 9 };
                }
                cell.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
                cell.border = { top: {style:'thin'}, left: {style:'thin'}, bottom: {style:'thin'}, right: {style:'thin'} };
            });

            datos.forEach(d => {
                const rowData = {};
                columnasEstructura.forEach(col => { rowData[col.key] = d[col.key]; });
                const r = ws.addRow(rowData);
                [18,19,20,21,22,23,24,25,29,30,31,32,33].forEach(idx => { r.getCell(idx).numFmt = '#,##0.00'; });
                r.getCell(24).font = { color: { argb: 'FFFF0000' }, bold: true };
                r.eachCell(cell => { cell.border = { top: {style:'thin'}, left: {style:'thin'}, bottom: {style:'thin'}, right: {style:'thin'} }; cell.alignment = { vertical: 'middle', horizontal: 'center' }; });
            });
        };
        crearHoja("PLANILLA", <?= json_encode($lista_planilla) ?>); crearHoja("SIN PLANILLA", <?= json_encode($lista_sin_planilla) ?>);
        const buffer = await wb.xlsx.writeBuffer();
        saveAs(new Blob([buffer]), `Reporte_Goldfruits_Final.xlsx`);
    }

    async function generarExcelResumen() {
        const periodo = document.getElementById('export_periodo').value;
        const wb = new ExcelJS.Workbook();
        const ws = wb.addWorksheet("Resumen");
        ws.columns = [
            { header: '#', key: 'num', width: 5 }, { header: 'TRABAJADOR', key: 'trabajador', width: 35 },
            { header: 'DNI', key: 'dni', width: 15 }, { header: 'BANCO', key: 'banco', width: 15 }, { header: 'N° CUENTA', key: 'cuenta', width: 25 },
            { header: '1RA QUINCENA', key: 'p1', width: 15, style: { numFmt: '"S/"#,##0.00' } },
            { header: '2DA QUINCENA', key: 'p2', width: 15, style: { numFmt: '"S/"#,##0.00' } },
            { header: 'TOTAL MES', key: 'tm', width: 18, style: { numFmt: '"S/"#,##0.00', font: { bold: true } } }
        ];
        let raw = [...<?= json_encode($lista_planilla) ?>, ...<?= json_encode($lista_sin_planilla) ?>];
        let datos = (periodo === '1RA') ? raw.filter(r => r.pago_1ra > 0) : (periodo === '2DA') ? raw.filter(r => r.pago_2da > 0) : raw;
        datos.forEach((r, i) => { ws.addRow({ num: i+1, trabajador: r.trabajador, dni: r.dni.replace("DNI - ",""), banco: r.banco, cuenta: r.cuenta, p1: r.pago_1ra, p2: r.pago_2da, tm: r.total_mes }); });
        const buffer = await wb.xlsx.writeBuffer();
        saveAs(new Blob([buffer]), `Resumen_Pagos_${periodo}.xlsx`);
    }
</script>
</body>
</html>