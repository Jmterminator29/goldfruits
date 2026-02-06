<?php
require_once '../includes/auth_admin.php';
require_once '../includes/db_connect.php';

// --- CONFIGURACIÓN DE PAGINACIÓN ---
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// --- BÚSQUEDA ---
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$condicion_sql = "";
$params_sql = [];

if (!empty($busqueda)) {
    $condicion_sql = " WHERE (
        a.codigo_unico LIKE ? OR 
        a.proveedor LIKE ? OR 
        u.nombre_completo LIKE ?
    )";
    $term = "%" . $busqueda . "%";
    $params_sql = [$term, $term, $term];
}

// 1. CONSULTA DE TOTALES (KPIs Globales y Paginador)
// Sumamos Fruta por un lado y TODOS los Gastos por otro
$sqlCount = "
    SELECT 
        COUNT(*) as total_ops,
        COALESCE(SUM(a.total_kilos_neto), 0) as kpi_kilos,
        COALESCE(SUM(a.importe_total_fruta), 0) as kpi_fruta,
        COALESCE(SUM(
            COALESCE(a.precio_flete,0) + 
            COALESCE(a.subtotal_cosecha,0) + 
            COALESCE(a.subtotal_cargadores,0) + 
            COALESCE(a.subtotal_inspectores,0) + 
            COALESCE(a.viaticos,0) + 
            COALESCE(a.gastos_operativos,0)
        ), 0) as kpi_gastos
    FROM acopios_cabecera a
    LEFT JOIN usuarios u ON a.usuario_id = u.id
    $condicion_sql
";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute($params_sql);
$totales = $stmtCount->fetch(PDO::FETCH_ASSOC);

$total_registros = $totales['total_ops'];
$total_paginas = ceil($total_registros / $registros_por_pagina);

$kpi_kilos = $totales['kpi_kilos'];
$kpi_fruta = $totales['kpi_fruta'];
$kpi_gastos = $totales['kpi_gastos'];
$kpi_inversion_total = $kpi_fruta + $kpi_gastos; // La suma real

// 2. CONSULTA LIMITADA (Para la tabla)
// Calculamos 'total_gastos' fila por fila para mostrarlo en la tabla
$sqlData = "
    SELECT 
        a.id, 
        a.codigo_unico,
        a.fecha_registro, 
        a.estado, 
        a.total_kilos_neto, 
        a.importe_total_fruta,
        (
            COALESCE(a.precio_flete,0) + 
            COALESCE(a.subtotal_cosecha,0) + 
            COALESCE(a.subtotal_cargadores,0) + 
            COALESCE(a.subtotal_inspectores,0) + 
            COALESCE(a.viaticos,0) + 
            COALESCE(a.gastos_operativos,0)
        ) as total_gastos,
        u.nombre_completo as autor,
        a.proveedor as proveedor_directo,
        (
            SELECT GROUP_CONCAT(DISTINCT p.nombre SEPARATOR ', ')
            FROM acopios_origenes ao
            JOIN proveedores p ON p.id = ao.proveedor_id
            WHERE ao.acopio_id = a.id
        ) as proveedores_detalle
    FROM acopios_cabecera a
    LEFT JOIN usuarios u ON a.usuario_id = u.id
    $condicion_sql
    ORDER BY a.fecha_registro DESC
    LIMIT $registros_por_pagina OFFSET $offset
";

$stmtData = $conn->prepare($sqlData);
$stmtData->execute($params_sql);
$solicitudes = $stmtData->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Panel Gerencial | GoldFruits</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --primary: #0f172a;
            --accent: #f59e0b;
            --text: #334155;
            --text-light: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --border: #e2e8f0;
        }

        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding-bottom: 60px; font-size: 14px; }

        /* NAVBAR */
        .navbar { background: var(--primary); color: white; padding: 15px 25px; position: sticky; top: 0; z-index: 100; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 20px -5px rgba(0,0,0,0.2); }
        .brand { font-weight: 800; font-size: 1.2rem; display: flex; align-items: center; gap: 8px; }
        .brand span { color: var(--accent); }
        .btn-logout { background: rgba(255,255,255,0.1); color: white; text-decoration: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; transition: 0.2s; border: 1px solid rgba(255,255,255,0.1); }
        .btn-logout:hover { background: rgba(255,255,255,0.2); }

        .container { max-width: 1300px; margin: 0 auto; padding: 25px; }

        /* KPIs */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .kpi-card { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px -2px rgba(0,0,0,0.03); }
        .kpi-label { font-size: 0.7rem; text-transform: uppercase; color: var(--text-light); font-weight: 700; letter-spacing: 0.5px; display: block; margin-bottom: 8px; }
        .kpi-value { font-size: 1.4rem; font-weight: 800; color: var(--primary); }
        .kpi-value.gold { color: var(--accent); }
        .kpi-value.red { color: var(--danger); }
        .kpi-value.green { color: var(--success); }

        /* TOOLBAR */
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap; }
        .search-form { display: flex; gap: 10px; flex: 1; max-width: 500px; }
        .search-input { width: 100%; padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border); outline: none; font-size: 0.95rem; }
        .search-input:focus { border-color: var(--accent); }
        .btn-search { background: var(--primary); color: white; border: none; padding: 0 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-reset { background: white; color: var(--text-light); border: 1px solid var(--border); padding: 0 15px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; font-weight: 600; }

        /* TABLA */
        .table-responsive { background: var(--surface); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        thead { background: #f1f5f9; border-bottom: 2px solid var(--border); }
        th { padding: 16px; font-weight: 700; color: var(--text-light); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text); }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #f8fafc; }

        /* CELDAS */
        .cell-code { font-family: 'Consolas', monospace; font-weight: 700; color: var(--text-light); font-size: 0.85rem; }
        .cell-date { font-size: 0.8rem; color: #94a3b8; display: block; margin-top: 2px; }
        .cell-prov { font-weight: 700; color: var(--primary); font-size: 0.95rem; }
        .cell-user { display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 500; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; }
        .cell-kilo { font-weight: 700; font-size: 0.95rem; }
        .cell-money { font-weight: 700; color: var(--primary); font-size: 0.95rem; }
        .cell-gastos { font-weight: 600; color: var(--danger); font-size: 0.9rem; }

        /* BADGES */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; }
        .badge.abierto { background: #dcfce7; color: #15803d; }
        .badge.terminado { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        /* ACCIONES */
        .btn-group { display: flex; gap: 8px; justify-content: flex-end; }
        .action-btn { text-decoration: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-ver { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .btn-cerrar { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .btn-abrir { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

        /* PAGINACIÓN */
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 25px; }
        .page-link { padding: 8px 14px; background: white; border: 1px solid var(--border); color: var(--text); text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.9rem; transition: 0.2s; }
        .page-link:hover { background: #f1f5f9; border-color: #cbd5e1; }
        .page-link.active { background: var(--primary); color: white; border-color: var(--primary); }
        .page-link.disabled { opacity: 0.5; pointer-events: none; }

        /* MOVIL */
        @media (max-width: 768px) {
            .navbar { padding: 15px; }
            .container { padding: 15px; }
            .kpi-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            thead { display: none; }
            .table-responsive { background: transparent; border: none; box-shadow: none; }
            tr { background: white; display: block; margin-bottom: 15px; border-radius: 12px; box-shadow: 0 4px 10px -2px rgba(0,0,0,0.05); border: 1px solid var(--border); padding: 15px; position: relative; }
            td { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed #f1f5f9; text-align: right; }
            td:last-child { border-bottom: none; padding-top: 15px; margin-top: 5px; border-top: 1px solid var(--border); display: block; }
            td::before { content: attr(data-label); font-weight: 700; color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; float: left; }
            .cell-prov { font-size: 1.1rem; color: var(--primary); }
            .btn-group { justify-content: stretch; }
            .action-btn { flex: 1; justify-content: center; padding: 10px; }
        }
    </style>
</head>
<body>

    <div class="navbar">
        <div class="brand">🥑 GoldFruits <span>Admin</span></div>
        <a href="../logout.php" class="btn-logout">Salir</a>
    </div>

    <div class="container">
        
        <div class="kpi-grid">
            <div class="kpi-card">
                <span class="kpi-label">Kilos Netos</span>
                <span class="kpi-value"><?php echo number_format($kpi_kilos, 2); ?></span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Costo Fruta (S/)</span>
                <span class="kpi-value"><?php echo number_format($kpi_fruta, 2); ?></span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Gastos Op. (S/)</span>
                <span class="kpi-value red"><?php echo number_format($kpi_gastos, 2); ?></span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Inversión Total (S/)</span>
                <span class="kpi-value gold"><?php echo number_format($kpi_inversion_total, 2); ?></span>
            </div>
        </div>

        <div class="toolbar">
            <form class="search-form" method="GET">
                <input type="text" name="q" class="search-input" placeholder="Buscar proveedor, código..." value="<?php echo htmlspecialchars($busqueda); ?>">
                <button type="submit" class="btn-search">🔍</button>
            </form>
            <?php if($busqueda): ?>
                <a href="admin_panel.php" class="btn-reset">× Limpiar</a>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Operación</th>
                        <th>Proveedor</th>
                        <th>Neto</th>
                        <th>Fruta (S/)</th>
                        <th>Gastos (S/)</th>
                        <th>Estado</th>
                        <th style="text-align:right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($solicitudes as $row): 
                        $statusClass = ($row['estado'] == 'abierto') ? 'abierto' : 'terminado';
                        $provShow = !empty($row['proveedores_detalle']) ? $row['proveedores_detalle'] : $row['proveedor_directo'];
                    ?>
                    <tr>
                        <td data-label="Operación">
                            <div class="cell-code"><?php echo $row['codigo_unico']; ?></div>
                            <span class="cell-date"><?php echo date('d/m/y H:i', strtotime($row['fecha_registro'])); ?></span>
                        </td>

                        <td data-label="Proveedor">
                            <div class="cell-prov"><?php echo $provShow; ?></div>
                            <div style="font-size:0.75rem; color:#94a3b8;">👤 <?php echo explode(' ', $row['autor'])[0]; ?></div>
                        </td>

                        <td data-label="Peso Neto">
                            <div class="cell-kilo"><?php echo number_format($row['total_kilos_neto'], 2); ?> kg</div>
                        </td>

                        <td data-label="Fruta (S/)">
                            <div class="cell-money">S/ <?php echo number_format($row['importe_total_fruta'], 2); ?></div>
                        </td>

                        <td data-label="Gastos (S/)">
                            <div class="cell-gastos">S/ <?php echo number_format($row['total_gastos'], 2); ?></div>
                        </td>

                        <td data-label="Estado">
                            <span class="badge <?php echo $statusClass; ?>"><?php echo strtoupper($row['estado']); ?></span>
                        </td>

                        <td data-label="Acciones">
                            <div class="btn-group">
                                <a href="admin_ver.php?id=<?php echo $row['id']; ?>" class="action-btn btn-ver">
                                    👁️ Ver
                                </a>
                                
                                <?php if($row['estado'] == 'abierto'): ?>
                                    <a href="admin_estado.php?id=<?php echo $row['id']; ?>&accion=cerrar" 
                                       class="action-btn btn-cerrar" 
                                       onclick="return confirm('¿Bloquear esta operación?')">
                                       🔒 Cerrar
                                    </a>
                                <?php else: ?>
                                    <a href="admin_estado.php?id=<?php echo $row['id']; ?>&accion=abrir" 
                                       class="action-btn btn-abrir"
                                       onclick="return confirm('¿Reabrir esta operación?')">
                                       🔓 Abrir
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if(empty($solicitudes)): ?>
            <div style="text-align:center; padding:50px; color:#94a3b8;">
                <p style="font-size:1.2rem;">📭 No se encontraron resultados.</p>
            </div>
        <?php endif; ?>

        <?php if ($total_paginas > 1): ?>
        <div class="pagination">
            <?php 
            if ($pagina_actual > 1) {
                echo '<a href="?pagina='.($pagina_actual-1).'&q='.urlencode($busqueda).'" class="page-link">←</a>';
            } else {
                echo '<span class="page-link disabled">←</span>';
            }

            $rango = 2; 
            for ($i = 1; $i <= $total_paginas; $i++) {
                if ($i == 1 || $i == $total_paginas || ($i >= $pagina_actual - $rango && $i <= $pagina_actual + $rango)) {
                    $active = ($i == $pagina_actual) ? 'active' : '';
                    echo '<a href="?pagina='.$i.'&q='.urlencode($busqueda).'" class="page-link '.$active.'">'.$i.'</a>';
                } elseif ($i == $pagina_actual - $rango - 1 || $i == $pagina_actual + $rango + 1) {
                    echo '<span class="page-link disabled">...</span>';
                }
            }

            if ($pagina_actual < $total_paginas) {
                echo '<a href="?pagina='.($pagina_actual+1).'&q='.urlencode($busqueda).'" class="page-link">→</a>';
            } else {
                echo '<span class="page-link disabled">→</span>';
            }
            ?>
        </div>
        <div style="text-align:center; color:#94a3b8; font-size:0.8rem; margin-top:10px;">
            Pág <?php echo $pagina_actual; ?> de <?php echo $total_paginas; ?>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>