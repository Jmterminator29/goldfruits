<?php
require_once 'auth.php';
require_once 'db_connect.php';

$uid = $_SESSION['user_id'];

/*
  Mostramos proveedores reales con comas, desde:
  acopios_cabecera -> acopios_origenes -> proveedores

  Fallback: si no hay orígenes, usamos c.proveedor.
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

// helper escape
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
    <title>Mis Solicitudes</title>

    <!-- Offline-first: mostrar operaciones pendientes cuando no hay internet -->
    <script src="offline_queue.js"></script>

    <script>
      // Asegura SW también en páginas internas
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js');
      }
    </script>
    <style>
        :root { --primary: #1b5e20; --gold: #fbc02d; --bg: #f5f5f5; --text: #212121; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); margin: 0; padding-bottom: 90px; }
        .app-bar { background: var(--primary); color: white; padding: 15px 20px; position: sticky; top: 0; z-index: 100; display: flex; align-items: center; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .menu-btn { font-size: 1.5rem; cursor: pointer; margin-right: 15px; }
        .container { padding: 15px; }

        .card { background: white; border-radius: 12px; padding: 15px; margin-bottom: 15px; border-left: 5px solid #ccc; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .card.abierto { border-left-color: var(--gold); }
        .card.terminado { border-left-color: var(--primary); }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .prov { font-weight: 700; font-size: 1.05rem; }
        .status { font-size: 0.7rem; text-transform: uppercase; padding: 4px 8px; border-radius: 10px; font-weight: bold; background: #eee; }
        .card.abierto .status { background: #fff9c4; color: #f9a825; }
        .card.terminado .status { background: #c8e6c9; color: #2e7d32; }

        .row { display: flex; justify-content: space-between; font-size: 0.9rem; color: #555; margin-bottom: 4px; }

        .btn-edit { display: inline-block; background: var(--primary); color: white; text-decoration: none; padding: 6px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; float: right; margin-top: 10px; }
        .sidebar { height: 100%; width: 0; position: fixed; z-index: 200; top: 0; left: 0; background: #111; overflow-x: hidden; transition: 0.3s; padding-top: 60px; }
        .sidebar a { padding: 15px 20px; text-decoration: none; font-size: 1.1rem; color: #818181; display: block; border-bottom: 1px solid #333; }
        .sidebar .closebtn { position: absolute; top: 0; right: 25px; font-size: 36px; border: none; }
        #overlay { position: fixed; display: none; width: 100%; height: 100%; top: 0; left: 0; background: rgba(0,0,0,0.5); z-index: 150; }

        .btn-new { position: fixed; bottom: 25px; right: 25px; background: var(--gold); color: var(--primary); width: 60px; height: 60px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 30px; text-decoration: none; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
    <div id="mySidebar" class="sidebar">
        <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">×</a>
        <div style="text-align:center; color:var(--gold); margin-bottom:20px;">MENÚ</div>

        <a href="nuevo_acopio.php">➕ Nueva Operación</a>
        <a href="mis_solicitudes.php" style="background:#222; color:white;">📂 Mis Solicitudes</a>

        <a href="ia_panel.php" style="color:var(--gold); border-left: 4px solid var(--gold);">🤖 Consultor IA</a>

        <a href="logout.php" style="color:#ff5252;">🚪 Salir</a>
    </div>
    <div id="overlay" onclick="closeNav()"></div>

    <div class="app-bar">
        <span class="menu-btn" onclick="openNav()">☰</span>
        <h1 style="margin:0; font-size:1.2rem;">Mis Solicitudes</h1>
    </div>

    <div class="container">
        <?php foreach($solicitudes as $row): ?>
            <div class="card <?php echo e($row['estado']); ?>">
                <div class="header">
                    <span class="prov"><?php echo e($row['proveedor_mostrar']); ?></span>
                    <span class="status"><?php echo e($row['estado']); ?></span>
                </div>
                <div class="row"><span>CÓDIGO:</span> <b><?php echo e($row['codigo_unico']); ?></b></div>
                <div class="row"><span>FECHA:</span> <span><?php echo e(date('d/m H:i', strtotime($row['fecha_registro']))); ?></span></div>
                <div class="row"><span>NETO:</span> <b style="color:var(--primary);"><?php echo e($row['total_kilos_neto']); ?> kg</b></div>

                <?php if($row['estado']=='abierto'): ?>
                    <a href="editar_solicitud.php?id=<?php echo (int)$row['id']; ?>" class="btn-edit">✏️ Editar</a>
                <?php else: ?>
                    <span style="float:right; font-size:0.8rem; color:#999; margin-top:10px;">🔒 Cerrado</span>
                <?php endif; ?>
                <div style="clear:both;"></div>
            </div>
        <?php endforeach; ?>
    </div>
    <a href="nuevo_acopio.php" class="btn-new">+</a>

    <script>
        function openNav(){ document.getElementById("mySidebar").style.width="250px"; document.getElementById("overlay").style.display="block"; }
        function closeNav(){ document.getElementById("mySidebar").style.width="0"; document.getElementById("overlay").style.display="none"; }

        function ensureNetBanner(){
            let banner = document.getElementById('netBanner');
            if (!banner) {
                banner = document.createElement('div');
                banner.id='netBanner';
                banner.style.position='fixed';
                banner.style.left='0';
                banner.style.right='0';
                banner.style.bottom='0';
                banner.style.padding='10px 12px';
                banner.style.zIndex='9999';
                banner.style.background='#111';
                banner.style.color='#fff';
                banner.style.fontSize='0.9rem';
                banner.style.display='none';
                banner.innerHTML='📴 Sin internet. <span id="pendingCount" style="opacity:0.9; margin-left:10px;"></span>';
                document.body.appendChild(banner);
            }
            return banner;
        }

        async function refreshNetUI(){
            const banner = ensureNetBanner();
            const pending = document.getElementById('pendingCount');
            try {
                const items = await window.GF_OFFLINE.listQueue();
                if (pending) pending.innerText = items.length ? ('Pendientes por enviar: ' + items.length) : '';
            } catch (e) {
                if (pending) pending.innerText = '';
            }
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
