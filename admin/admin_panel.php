<?php
require_once '../includes/auth_admin.php'; // Acceso solo para administradores
require_once '../includes/db_connect.php';

// 1. CONSULTA ROBUSTA: Trae TODOS los proveedores concatenados
// Usamos LEFT JOIN para asegurar que si no hay detalle, al menos muestre la cabecera.
$sql = "
    SELECT 
        a.id, 
        a.codigo_unico,
        a.fecha_registro, 
        a.estado, 
        a.total_kilos_neto, 
        a.importe_total_fruta,
        u.nombre_completo as autor,
        COALESCE(
            NULLIF(GROUP_CONCAT(DISTINCT p.nombre ORDER BY p.nombre SEPARATOR ', '), ''),
            a.proveedor
        ) AS proveedor_mostrar
    FROM acopios_cabecera a
    LEFT JOIN usuarios u ON a.usuario_id = u.id
    LEFT JOIN acopios_origenes ao ON ao.acopio_id = a.id
    LEFT JOIN proveedores p ON p.id = ao.proveedor_id
    GROUP BY a.id, a.codigo_unico, a.fecha_registro, a.estado, a.total_kilos_neto, a.importe_total_fruta, u.nombre_completo
    ORDER BY a.fecha_registro DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute();
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. CÁLCULO DE KPIS (Métricas Generales)
$kpi_kilos = 0;
$kpi_dinero = 0;
$kpi_ops = count($solicitudes);

foreach($solicitudes as $s) {
    $kpi_kilos += $s['total_kilos_neto'];
    $kpi_dinero += $s['importe_total_fruta'];
}
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
            --accent: #f59e0b; /* Dorado */
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
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: var(--surface); padding: 20px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px -2px rgba(0,0,0,0.03); }
        .kpi-label { font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); font-weight: 700; letter-spacing: 0.5px; display: block; margin-bottom: 8px; }
        .kpi-value { font-size: 1.6rem; font-weight: 800; color: var(--primary); }
        .kpi-value.gold { color: var(--accent); }
        .kpi-value.green { color: var(--success); }

        /* FILTROS */
        .toolbar { margin-bottom: 20px; }
        .search-box { position: relative; max-width: 400px; }
        .search-box input { width: 100%; padding: 12px 15px 12px 45px; border-radius: 10px; border: 1px solid var(--border); outline: none; font-size: 0.95rem; transition: 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .search-box input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1); }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); opacity: 0.5; font-size: 1.1rem; }

        /* TABLA MODERNA */
        .table-responsive { background: var(--surface); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        
        thead { background: #f1f5f9; border-bottom: 2px solid var(--border); }
        th { padding: 16px; font-weight: 700; color: var(--text-light); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 16px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text); }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #f8fafc; }

        /* DATA CELLS */
        .cell-code { font-family: 'Consolas', monospace; font-weight: 700; color: var(--text-light); font-size: 0.85rem; }
        .cell-date { font-size: 0.8rem; color: #94a3b8; display: block; margin-top: 2px; }
        .cell-prov { font-weight: 700; color: var(--primary); font-size: 0.95rem; }
        .cell-user { display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 500; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; }
        .cell-kilo { font-weight: 700; font-size: 0.95rem; }
        .cell-money { font-weight: 700; color: var(--accent); font-size: 0.95rem; }

        /* ESTADOS */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block; }
        .badge.abierto { background: #dcfce7; color: #15803d; }
        .badge.terminado { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        /* BOTONES ACCION */
        .btn-group { display: flex; gap: 8px; justify-content: flex-end; }
        .action-btn { text-decoration: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-ver { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
        .btn-ver:hover { background: #dbeafe; }
        .btn-cerrar { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        .btn-cerrar:hover { background: #fee2e2; }
        .btn-abrir { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }

        /* --- RESPONSIVE MOBILE (CARD VIEW) --- */
        @media (max-width: 768px) {
            .navbar { padding: 15px; }
            .container { padding: 15px; }
            .kpi-grid { gap: 10px; }
            
            /* Ocultar cabecera tabla */
            thead { display: none; }
            
            /* Convertir filas en tarjetas */
            .table-responsive { background: transparent; border: none; box-shadow: none; }
            tr { 
                background: white; 
                display: block; 
                margin-bottom: 15px; 
                border-radius: 12px; 
                box-shadow: 0 4px 10px -2px rgba(0,0,0,0.05); 
                border: 1px solid var(--border);
                padding: 15px;
                position: relative;
            }
            td { 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 8px 0; 
                border-bottom: 1px dashed #f1f5f9;
                text-align: right;
            }
            td:last-child { border-bottom: none; padding-top: 15px; margin-top: 5px; border-top: 1px solid var(--border); display: block; }
            
            /* Etiquetas para celdas en móvil */
            td::before { 
                content: attr(data-label); 
                font-weight: 700; 
                color: #94a3b8; 
                font-size: 0.75rem; 
                text-transform: uppercase;
                float: left;
            }

            /* Ajustes visuales específicos móvil */
            .cell-code { font-size: 0.9rem; }
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
                <span class="kpi-label">Kilos Acopiados</span>
                <span class="kpi-value"><?php echo number_format($kpi_kilos, 2); ?></span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Inversión Total</span>
                <span class="kpi-value gold">S/ <?php echo number_format($kpi_dinero, 2); ?></span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Operaciones</span>
                <span class="kpi-value green"><?php echo $kpi_ops; ?></span>
            </div>
        </div>

        <div class="toolbar">
            <div class="search-box">
                <span class="search-icon">🔍</span>
                <input type="text" id="searchInput" placeholder="Buscar proveedor, código, responsable..." onkeyup="filtrarTabla()">
            </div>
        </div>

        <div class="table-responsive">
            <table id="mainTable">
                <thead>
                    <tr>
                        <th>Operación</th>
                        <th>Proveedor</th>
                        <th>Responsable</th>
                        <th>Peso Neto</th>
                        <th>Total (S/)</th>
                        <th>Estado</th>
                        <th style="text-align:right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($solicitudes as $row): 
                        $statusClass = ($row['estado'] == 'abierto') ? 'abierto' : 'terminado';
                    ?>
                    <tr>
                        <td data-label="Operación">
                            <div class="cell-code"><?php echo $row['codigo_unico']; ?></div>
                            <span class="cell-date"><?php echo date('d/m/Y H:i', strtotime($row['fecha_registro'])); ?></span>
                        </td>

                        <td data-label="Proveedor">
                            <div class="cell-prov"><?php echo $row['proveedor_mostrar']; ?></div>
                        </td>

                        <td data-label="Responsable">
                            <div class="cell-user">
                                👤 <?php echo explode(' ', $row['autor'])[0]; // Solo primer nombre ?>
                            </div>
                        </td>

                        <td data-label="Peso Neto">
                            <div class="cell-kilo"><?php echo number_format($row['total_kilos_neto'], 2); ?> kg</div>
                        </td>

                        <td data-label="Total (S/)">
                            <div class="cell-money">S/ <?php echo number_format($row['importe_total_fruta'], 2); ?></div>
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
                                       onclick="return confirm('¿Bloquear esta operación para que no se edite más?')">
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
                <p style="font-size:1.2rem;">📭 No hay operaciones registradas.</p>
            </div>
        <?php endif; ?>

    </div>

    <script>
        function filtrarTabla() {
            const input = document.getElementById("searchInput");
            const filter = input.value.toUpperCase();
            const table = document.getElementById("mainTable");
            const tr = table.getElementsByTagName("tr");

            for (let i = 0; i < tr.length; i++) {
                // Saltamos el header (thead está separado, pero por si acaso)
                if(tr[i].parentNode.nodeName === 'THEAD') continue;
                
                // Buscamos en todo el texto de la fila
                const txtValue = tr[i].textContent || tr[i].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    </script>
</body>
</html>