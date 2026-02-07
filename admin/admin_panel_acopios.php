<?php
// admin/admin_panel_acopios.php
require_once '../includes/auth_admin.php';
require_once '../includes/db_connect.php';

// --- CONFIGURACIÓN ---
$registros_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// --- BÚSQUEDA ---
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';
$condicion_sql = "";
$params_sql = [];

if (!empty($busqueda)) {
    $condicion_sql = " WHERE (a.codigo_unico LIKE ? OR a.proveedor LIKE ? OR u.nombre_completo LIKE ?)";
    $term = "%" . $busqueda . "%";
    $params_sql = [$term, $term, $term];
}

// 1. KPI TOTALES
$sqlCount = "SELECT COUNT(*) as total_ops,
        COALESCE(SUM(a.total_kilos_neto), 0) as kpi_kilos,
        COALESCE(SUM(a.importe_total_fruta), 0) as kpi_fruta,
        COALESCE(SUM(COALESCE(a.precio_flete,0) + COALESCE(a.subtotal_cosecha,0) + COALESCE(a.subtotal_cargadores,0) + COALESCE(a.subtotal_inspectores,0) + COALESCE(a.viaticos,0) + COALESCE(a.gastos_operativos,0)), 0) as kpi_gastos
    FROM acopios_cabecera a LEFT JOIN usuarios u ON a.usuario_id = u.id $condicion_sql";
$stmtCount = $conn->prepare($sqlCount);
$stmtCount->execute($params_sql);
$totales = $stmtCount->fetch(PDO::FETCH_ASSOC);

$total_paginas = ceil($totales['total_ops'] / $registros_por_pagina);
$kpi_inversion_total = $totales['kpi_fruta'] + $totales['kpi_gastos'];

// 2. LISTADO DE DATOS
$sqlData = "SELECT a.id, a.codigo_unico, a.fecha_registro, a.estado, a.total_kilos_neto, a.importe_total_fruta,
        (COALESCE(a.precio_flete,0) + COALESCE(a.subtotal_cosecha,0) + COALESCE(a.subtotal_cargadores,0) + COALESCE(a.subtotal_inspectores,0) + COALESCE(a.viaticos,0) + COALESCE(a.gastos_operativos,0)) as total_gastos,
        u.nombre_completo as autor, a.proveedor as proveedor_directo,
        (SELECT GROUP_CONCAT(DISTINCT p.nombre SEPARATOR ', ') FROM acopios_origenes ao JOIN proveedores p ON p.id = ao.proveedor_id WHERE ao.acopio_id = a.id) as proveedores_detalle
    FROM acopios_cabecera a LEFT JOIN usuarios u ON a.usuario_id = u.id $condicion_sql
    ORDER BY a.fecha_registro DESC LIMIT $registros_por_pagina OFFSET $offset";
$stmtData = $conn->prepare($sqlData);
$stmtData->execute($params_sql);
$solicitudes = $stmtData->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Acopios | GoldFruits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        /* --- ESTILOS PREMIUM GLASSMORPHISM --- */
        :root { 
            --gf-primary: #1b5e20; 
            --gf-dark: #0f3d14; 
            --gf-gold: #fbc02d; 
        }
        
        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: var(--gf-dark); 
            background-image: 
                radial-gradient(at 0% 0%, rgba(251, 192, 45, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(27, 94, 32, 0.2) 0px, transparent 50%),
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            color: #333; 
            min-height: 100vh;
            padding-bottom: 80px; 
        }
        
        /* NAVBAR GLASS */
        .app-bar {
            background: rgba(15, 61, 20, 0.9);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            padding: 12px 30px;
            position: sticky; top: 0; z-index: 1000;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 30px rgba(0,0,0,0.3);
            height: 85px;
        }
        
        .main-logo { height: 75px; width: auto; filter: drop-shadow(0 4px 8px rgba(0,0,0,0.4)); transition: transform 0.3s; }
        .main-logo:hover { transform: scale(1.08); }

        .btn-back {
            background: rgba(255, 255, 255, 0.15);
            color: white; border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 20px; border-radius: 30px;
            text-decoration: none; font-weight: 600;
            transition: all 0.3s; display: flex; align-items: center; gap: 8px;
        }
        .btn-back:hover { background: rgba(255,255,255,0.25); color: var(--gf-gold); border-color: var(--gf-gold); }

        /* KPIS GLASS CARDS */
        .kpi-card { 
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 16px; padding: 20px; 
            border: 1px solid rgba(255,255,255,0.5); 
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2); 
            height: 100%; transition: transform 0.3s; 
        }
        .kpi-card:hover { transform: translateY(-5px); border-color: var(--gf-gold); }
        
        .kpi-label { display: block; font-size: 0.75rem; text-transform: uppercase; color: #555; font-weight: 700; margin-bottom: 5px; letter-spacing: 0.5px; }
        .kpi-value { font-size: 1.5rem; font-weight: 800; color: var(--gf-primary); line-height: 1.2; }
        .kpi-value.gold { color: #f57f17; }
        .kpi-value.red { color: #d32f2f; }

        /* BUSCADOR GLASS */
        .search-container { 
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 16px; padding: 15px; margin-bottom: 20px; 
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255,255,255,0.5);
        }
        .form-control { border-radius: 10px; border: 1px solid #ddd; padding: 10px 15px; }
        .form-control:focus { border-color: var(--gf-gold); box-shadow: 0 0 0 3px rgba(251, 192, 45, 0.2); }

        /* TABLA GLASS */
        .table-responsive { 
            border-radius: 16px; overflow: hidden; 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.5); 
            box-shadow: 0 10px 40px rgba(0,0,0,0.3); 
        }
        .table thead { background: rgba(27, 94, 32, 0.1); }
        .table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gf-primary); font-weight: 800; padding: 18px; border: none; }
        .table td { padding: 18px; vertical-align: middle; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 0.95rem; font-weight: 500; }
        
        /* BADGES */
        .status-badge { padding: 6px 12px; border-radius: 30px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-abierto { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-terminado { background: #f5f5f5; color: #616161; border: 1px solid #e0e0e0; }

        /* MÓVIL EXTREMO */
        @media (max-width: 768px) {
            .app-bar { padding: 10px 15px; height: 70px; }
            .main-logo { height: 50px; }
            .btn-back span { display: none; } /* Solo icono en móvil */
            
            .table thead { display: none; }
            .table, .table tbody, .table tr, .table td { display: block; width: 100%; }
            .table tr { margin-bottom: 15px; border: 1px solid rgba(0,0,0,0.1); border-radius: 16px; overflow: hidden; background: white; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
            .table td { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #eee; padding: 12px 15px; text-align: right; }
            .table td::before { content: attr(data-label); float: left; font-weight: 700; color: #888; font-size: 0.75rem; text-transform: uppercase; }
            .table td:last-child { border-bottom: none; display: block; padding-top: 15px; margin-top: 5px; border-top: 1px solid #eee; text-align: center; background: #fafafa; }
            
            .btn-group-mobile { display: flex; gap: 8px; width: 100%; }
            .btn-group-mobile .btn { flex: 1; border-radius: 10px; }
        }
    </style>
</head>
<body>

    <nav class="app-bar animate__animated animate__fadeInDown">
        <img src="https://i.ibb.co/KzVLFpSV/Gemini-Generated-Image-45ambn45ambn45am-removebg-preview-2.png" alt="Gold Fruits" class="main-logo">
        <a href="admin_panel.php" class="btn-back">
            <i class="bi bi-grid-fill"></i> <span>Panel</span>
        </a>
    </nav>

    <div class="container mt-4 pb-5">
        
        <div class="row g-3 mb-4 animate__animated animate__fadeInUp">
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <span class="kpi-label">Kilos Netos</span>
                    <span class="kpi-value"><?= number_format($totales['kpi_kilos'], 2) ?></span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <span class="kpi-label">Costo Fruta</span>
                    <span class="kpi-value">S/ <?= number_format($totales['kpi_fruta'], 2) ?></span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <span class="kpi-label">Gastos Op.</span>
                    <span class="kpi-value red">S/ <?= number_format($totales['kpi_gastos'], 2) ?></span>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="kpi-card">
                    <span class="kpi-label">Inversión Total</span>
                    <span class="kpi-value gold">S/ <?= number_format($kpi_inversion_total, 2) ?></span>
                </div>
            </div>
        </div>

        <div class="search-container animate__animated animate__fadeIn">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="q" class="form-control" placeholder="Buscar proveedor, código..." value="<?= htmlspecialchars($busqueda) ?>">
                <button type="submit" class="btn btn-success px-4 fw-bold" style="background:var(--gf-gold); border:none; color:#000;"><i class="bi bi-search"></i></button>
                <?php if($busqueda): ?>
                    <a href="admin_panel_acopios.php" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-responsive animate__animated animate__fadeInUp">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Operación</th>
                        <th>Proveedor</th>
                        <th class="text-end">Peso Neto</th>
                        <th class="text-end">Fruta (S/)</th>
                        <th class="text-end">Gastos (S/)</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($solicitudes)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted fw-bold">No se encontraron resultados</td></tr>
                    <?php else: foreach($solicitudes as $row): 
                        $statusClass = ($row['estado'] == 'abierto') ? 'status-abierto' : 'status-terminado';
                        $provShow = !empty($row['proveedores_detalle']) ? $row['proveedores_detalle'] : $row['proveedor_directo'];
                    ?>
                    <tr>
                        <td data-label="Operación">
                            <div class="fw-bold text-dark"><?= $row['codigo_unico'] ?></div>
                            <small class="text-muted" style="font-size:0.8rem;"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/y H:i', strtotime($row['fecha_registro'])) ?></small>
                        </td>
                        <td data-label="Proveedor">
                            <div class="fw-bold" style="color:var(--gf-primary);"><?= $provShow ?></div>
                            <small class="text-muted"><i class="bi bi-person-fill"></i> <?= explode(' ', $row['autor'])[0] ?></small>
                        </td>
                        <td data-label="Peso Neto" class="text-end fw-bold">
                            <?= number_format($row['total_kilos_neto'], 2) ?> kg
                        </td>
                        <td data-label="Fruta (S/)" class="text-end fw-bold text-dark">
                            S/ <?= number_format($row['importe_total_fruta'], 2) ?>
                        </td>
                        <td data-label="Gastos (S/)" class="text-end fw-bold text-danger">
                            S/ <?= number_format($row['total_gastos'], 2) ?>
                        </td>
                        <td data-label="Estado" class="text-center">
                            <span class="status-badge <?= $statusClass ?>"><?= strtoupper($row['estado']) ?></span>
                        </td>
                        <td data-label="Acciones">
                            <div class="btn-group-mobile">
                                <a href="admin_ver.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary fw-bold"><i class="bi bi-eye-fill"></i> Ver</a>
                                <?php if($row['estado'] == 'abierto'): ?>
                                    <a href="admin_estado.php?id=<?= $row['id'] ?>&accion=cerrar" class="btn btn-sm btn-outline-danger fw-bold" onclick="return confirm('¿Cerrar operación?')"><i class="bi bi-lock-fill"></i> Cerrar</a>
                                <?php else: ?>
                                    <a href="admin_estado.php?id=<?= $row['id'] ?>&accion=abrir" class="btn btn-sm btn-outline-success fw-bold" onclick="return confirm('¿Reabrir operación?')"><i class="bi bi-unlock-fill"></i> Abrir</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link shadow-sm border-0" href="?pagina=<?= $pagina_actual-1 ?>&q=<?= urlencode($busqueda) ?>">Anterior</a>
                </li>
                <li class="page-item disabled"><span class="page-link border-0 bg-transparent fw-bold text-white">Pág <?= $pagina_actual ?> de <?= $total_paginas ?></span></li>
                <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                    <a class="page-link shadow-sm border-0" href="?pagina=<?= $pagina_actual+1 ?>&q=<?= urlencode($busqueda) ?>">Siguiente</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>