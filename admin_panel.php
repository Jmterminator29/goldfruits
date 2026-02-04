<?php
require_once 'auth_admin.php'; // Candado de Admin
require_once 'db_connect.php';

// CONSULTA CORREGIDA: Sin proveedor_comercial
$sql = "SELECT a.id, a.codigo_unico,
               a.proveedor AS proveedor_mostrar, 
               a.fecha_registro, a.estado, a.total_kilos_neto, u.nombre_completo as autor 
        FROM acopios_cabecera a
        JOIN usuarios u ON a.usuario_id = u.id
        ORDER BY a.fecha_registro DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Admin | GoldFruits</title>
    <style>
        :root { --primary: #0d47a1; --gold: #ffca28; --bg: #f0f2f5; --text: #212121; }
        body { font-family: sans-serif; background: var(--bg); margin: 0; padding-bottom: 50px; }
        .app-bar { background: var(--primary); color: white; padding: 15px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 5px rgba(0,0,0,0.2); display: flex; justify-content: space-between; align-items: center; }
        .container { padding: 15px; max-width: 800px; margin: 0 auto; }
        
        .card { background: white; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-left: 5px solid #ccc; position: relative; }
        .card.abierto { border-left-color: #4caf50; } 
        .card.terminado { border-left-color: #555; background: #fafafa; } 
        
        .header { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .autor { font-size: 0.75rem; color: #666; text-transform: uppercase; letter-spacing: 1px; }
        .prov { font-weight: bold; font-size: 1.1rem; color: #333; }
        
        .info-row { display: flex; justify-content: space-between; font-size: 0.9rem; margin-top: 5px; color: #444; }
        
        .actions { margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px; display: flex; justify-content: flex-end; gap: 10px; }
        
        .btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem; font-weight: bold; border: none; cursor: pointer; }
        .btn-view { background: #e3f2fd; color: #1565c0; }
        .btn-lock { background: #ffebee; color: #c62828; }
        .btn-unlock { background: #e8f5e9; color: #2e7d32; }
        
        .logout { color: white; text-decoration: none; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.3); padding: 5px 10px; border-radius: 4px; }
    </style>
</head>
<body>

    <div class="app-bar">
        <h1 style="margin:0; font-size:1.2rem;">Panel Administrador</h1>
        <a href="logout.php" class="logout">Salir</a>
    </div>

    <div class="container">
        <?php foreach($solicitudes as $row): ?>
            <div class="card <?php echo $row['estado']; ?>">
                <div class="autor">👤 <?php echo $row['autor']; ?></div>
                <div class="header">
                    <span class="prov"><?php echo $row['proveedor_mostrar']; ?></span>
                    <span style="font-size:0.8rem; font-weight:bold; color:<?php echo ($row['estado']=='abierto'?'#2e7d32':'#666'); ?>">
                        <?php echo strtoupper($row['estado']); ?>
                    </span>
                </div>
                
                <div class="info-row">
                    <span><?php echo $row['codigo_unico']; ?></span>
                    <span><?php echo date('d/m H:i', strtotime($row['fecha_registro'])); ?></span>
                </div>
                <div class="info-row">
                    <span>Peso Neto:</span>
                    <strong><?php echo number_format($row['total_kilos_neto'], 2); ?> kg</strong>
                </div>

                <div class="actions">
                    <a href="admin_ver.php?id=<?php echo $row['id']; ?>" class="btn btn-view">👁️ Ver Datos</a>
                    
                    <?php if($row['estado'] == 'abierto'): ?>
                        <a href="admin_estado.php?id=<?php echo $row['id']; ?>&accion=cerrar" class="btn btn-lock" onclick="return confirm('¿Bloquear esta solicitud?')">🔒 Cerrar</a>
                    <?php else: ?>
                        <a href="admin_estado.php?id=<?php echo $row['id']; ?>&accion=abrir" class="btn btn-unlock" onclick="return confirm('¿Reabrir esta solicitud?')">🔓 Abrir</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>