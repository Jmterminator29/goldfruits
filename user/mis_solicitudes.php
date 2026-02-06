<?php
require_once '../includes/auth.php';
require_once '../includes/db_connect.php';

$uid = $_SESSION['user_id'];

/*
  CONSULTA ORIGINAL MANTENIDA
*/
$sql = "
SELECT
  c.id,
  c.codigo_unico,
  COALESCE(
    NULLIF(GROUP_CONCAT(DISTINCT p.nombre ORDER BY p.nombre SEPARATOR ', '), ''),
    c.proveedor
  ) AS proveedor_mostrar,
  c.fecha_registro,
  c.estado,
  c.total_kilos_neto
FROM acopios_cabecera c
LEFT JOIN acopios_origenes ao ON ao.acopio_id = c.id
LEFT JOIN proveedores p ON p.id = ao.proveedor_id
WHERE c.usuario_id = ?
GROUP BY c.id, c.codigo_unico, c.proveedor, c.fecha_registro, c.estado, c.total_kilos_neto
ORDER BY c.fecha_registro DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute([$uid]);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e($str) {
  return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1b5e20">
    <title>Mis Solicitudes | GoldFruits</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <script src="offline_queue.js"></script>
    <script>
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js');
      }
    </script>

    <style>
        /* --- ESTILOS VISUALES PREMIUM (Idénticos a nuevo_acopio.php) --- */
        :root {
            --gf-primary: #1b5e20;
            --gf-dark: #0f3d14;
            --gf-gold: #fbc02d;
            --gf-glass: rgba(255, 255, 255, 0.95);
            --gf-glass-border: rgba(255, 255, 255, 0.5);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--gf-dark);
            background-image: 
                radial-gradient(at 0% 0%, rgba(251, 192, 45, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(27, 94, 32, 0.2) 0px, transparent 50%),
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            color: #333;
            padding-bottom: 100px;
            min-height: 100vh;
        }

        /* NAVBAR GLASS */
        .app-bar {
            background: rgba(27, 94, 32, 0.9);
            backdrop-filter: blur(10px);
            color: white;
            padding: 15px 20px;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .menu-btn { font-size: 1.6rem; cursor: pointer; color: var(--gf-gold); }
        .page-title { font-weight: 700; font-size: 1.1rem; letter-spacing: 0.5px; margin: 0; }

        /* SIDEBAR PREMIUM */
        .sidebar {
            height: 100%; width: 0; position: fixed; z-index: 2000; top: 0; left: 0;
            background: linear-gradient(180deg, #0f3d14 0%, #1b5e20 100%);
            overflow-x: hidden; transition: 0.3s; padding-top: 60px;
            box-shadow: 5px 0 25px rgba(0,0,0,0.5);
        }
        .sidebar a {
            padding: 15px 25px; text-decoration: none; font-size: 1rem; color: rgba(255,255,255,0.8);
            display: block; transition: 0.2s; border-bottom: 1px solid rgba(255,255,255,0.05);
            font-weight: 500;
        }
        .sidebar a:hover { background: rgba(255,255,255,0.1); color: white; padding-left: 30px; }
        .sidebar .closebtn { position: absolute; top: 10px; right: 20px; font-size: 36px; color: var(--gf-gold); }
        #overlay { position: fixed; display: none; width: 100%; height: 100%; top: 0; left: 0; background: rgba(0,0,0,0.7); z-index: 1500; backdrop-filter: blur(3px); }

        /* TARJETAS DE SOLICITUD (GLASS) */
        .req-card {
            background: var(--gf-glass);
            border: 1px solid var(--gf-glass-border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .req-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .req-card.abierto { border-left: 5px solid var(--gf-gold); }
        .req-card.terminado { border-left: 5px solid var(--gf-primary); }

        .req-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .req-prov { font-weight: 700; font-size: 1.1rem; color: var(--gf-dark); line-height: 1.2; }
        
        /* Badges de Estado */
        .badge-status { font-size: 0.65rem; text-transform: uppercase; padding: 5px 10px; border-radius: 20px; font-weight: 800; letter-spacing: 0.5px; }
        .badge-abierto { background: #fff9c4; color: #fbc02d; border: 1px solid #fbc02d; }
        .badge-terminado { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }

        .req-row { display: flex; align-items: center; font-size: 0.9rem; color: #666; margin-bottom: 6px; }
        .req-row i { width: 24px; color: var(--gf-primary); opacity: 0.7; }

        /* Botones de Acción */
        .btn-edit-pill {
            display: inline-flex; align-items: center;
            background: var(--gf-primary); color: white;
            text-decoration: none; padding: 8px 16px;
            border-radius: 30px; font-size: 0.85rem; font-weight: 600;
            margin-top: 10px; float: right;
            box-shadow: 0 4px 10px rgba(27, 94, 32, 0.2);
            transition: 0.2s;
        }
        .btn-edit-pill:hover { background: #144a18; color:white; transform: scale(1.05); }

        .status-closed { float: right; margin-top: 12px; color: #999; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; }

        /* FAB (Botón Flotante) */
        .btn-fab {
            position: fixed; bottom: 25px; right: 25px;
            background: linear-gradient(135deg, var(--gf-gold) 0%, #f9a825 100%);
            color: #0f3d14; width: 65px; height: 65px;
            border-radius: 50%; display: flex; justify-content: center; align-items: center;
            font-size: 32px; text-decoration: none;
            box-shadow: 0 10px 25px rgba(249, 168, 37, 0.4);
            z-index: 900; transition: transform 0.3s;
        }
        .btn-fab:hover { transform: scale(1.1) rotate(90deg); color: #000; }

        /* Banner Offline */
        #netBanner {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: #212121; color: white; padding: 12px;
            font-size: 0.9rem; z-index: 9999; text-align: center;
            border-top: 2px solid var(--gf-gold);
            display: none;
        }
    </style>
</head>
<body>

    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">×</a>
        
        <div style="text-align:center; padding: 30px 0 20px;">
             <img src="https://i.ibb.co/KzVLFpSV/Gemini-Generated-Image-45ambn45ambn45am-removebg-preview-2.png" alt="GoldFruits" style="width: 120px; height: auto; display: block; margin: 0 auto; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));">
             
             <div style="color:var(--gf-gold); font-weight:bold; margin-top:15px; letter-spacing:1px;">
                <?php echo strtoupper($_SESSION['user_nombre']); ?>
             </div>
        </div>

        <a href="nuevo_acopio.php"><i class="bi bi-plus-circle me-2"></i>Nueva Operación</a>
        <a href="mis_solicitudes.php" style="background: rgba(255,255,255,0.1); color: white; border-left: 4px solid var(--gf-gold);">
            <i class="bi bi-folder2-open me-2"></i>Mis Solicitudes
        </a>
        <a href="ia_panel.php" style="color:var(--gf-gold);"><i class="bi bi-robot me-2"></i>Consultor IA</a>
        <a href="../logout.php" style="color:#ff8a80; margin-top:20px;"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a>
    </div>
    <div id="overlay" onclick="closeNav()"></div>

    <div class="app-bar">
        <span class="menu-btn" onclick="openNav()"><i class="bi bi-list"></i></span>
        <h1 class="page-title">Mis Solicitudes</h1>
        <div style="width: 24px;"></div> </div>

    <div class="container mt-4">
        
        <?php if(empty($solicitudes)): ?>
            <div class="text-center text-white mt-5 opacity-50">
                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                <p class="mt-2">No tienes solicitudes registradas.</p>
            </div>
        <?php endif; ?>

        <?php foreach($solicitudes as $row): ?>
            <?php 
                $isAbierto = ($row['estado'] == 'abierto');
                $statusClass = $isAbierto ? 'abierto' : 'terminado';
                $badgeClass = $isAbierto ? 'badge-abierto' : 'badge-terminado';
                $badgeText = $isAbierto ? 'ABIERTO' : 'CERRADO';
                $fechaFmt = date('d/m/Y • H:i', strtotime($row['fecha_registro']));
            ?>
            
            <div class="req-card <?php echo $statusClass; ?>">
                <div class="req-header">
                    <div class="req-prov">
                        <?php echo e($row['proveedor_mostrar']); ?>
                    </div>
                    <span class="badge-status <?php echo $badgeClass; ?>">
                        <?php echo $badgeText; ?>
                    </span>
                </div>

                <div class="req-row">
                    <i class="bi bi-upc-scan"></i>
                    <span class="fw-bold text-dark me-2">CÓDIGO:</span> 
                    <span style="font-family:monospace; font-size:1rem;"><?php echo e($row['codigo_unico']); ?></span>
                </div>
                
                <div class="req-row">
                    <i class="bi bi-calendar-event"></i>
                    <span class="me-2">Fecha:</span> 
                    <span><?php echo $fechaFmt; ?></span>
                </div>

                <div class="req-row">
                    <i class="bi bi-speedometer2"></i>
                    <span class="me-2">Neto Acumulado:</span> 
                    <b style="color:var(--gf-primary); font-size:1.1rem;"><?php echo e($row['total_kilos_neto']); ?> kg</b>
                </div>

                <?php if($isAbierto): ?>
                    <a href="editar_solicitud.php?id=<?php echo (int)$row['id']; ?>" class="btn-edit-pill">
                        <i class="bi bi-pencil-square me-1"></i> Editar / Continuar
                    </a>
                <?php else: ?>
                    <div class="status-closed">
                        <i class="bi bi-lock-fill me-1"></i> Operación Finalizada
                    </div>
                <?php endif; ?>
                
                <div style="clear:both;"></div>
            </div>
        <?php endforeach; ?>
    </div>

    <a href="nuevo_acopio.php" class="btn-fab shadow-lg">
        <i class="bi bi-plus-lg"></i>
    </a>

    <script>
        function openNav(){ document.getElementById("mySidebar").style.width="280px"; document.getElementById("overlay").style.display="block"; }
        function closeNav(){ document.getElementById("mySidebar").style.width="0"; document.getElementById("overlay").style.display="none"; }

        function ensureNetBanner(){
            let banner = document.getElementById('netBanner');
            if (!banner) {
                banner = document.createElement('div');
                banner.id='netBanner';
                document.body.appendChild(banner);
            }
            return banner;
        }

        async function refreshNetUI(){
            const banner = ensureNetBanner();
            banner.innerHTML = '<i class="bi bi-wifi-off me-2"></i> Sin conexión. <span id="pendingCount" style="background:white; color:black; padding:2px 6px; border-radius:10px; font-size:0.8rem; margin-left:10px;"></span>';
            
            try {
                const items = await window.GF_OFFLINE.listQueue();
                const pCount = document.getElementById('pendingCount');
                if (pCount) pCount.innerText = items.length ? (items.length + ' pendientes') : '';
            } catch (e) { }
            banner.style.display = navigator.onLine ? 'none' : 'block';
        }

        window.addEventListener('online', async () => {
            await refreshNetUI();
            try { await window.GF_OFFLINE.syncQueue(); } catch (e) {}
        });
        window.addEventListener('offline', refreshNetUI);

        (async () => {
            await refreshNetUI();
            if (navigator.onLine) {
                try { await window.GF_OFFLINE.syncQueue(); } catch (e) {}
            }
        })();
    </script>
</body>
</html>
