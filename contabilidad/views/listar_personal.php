<?php
$root = $_SERVER['DOCUMENT_ROOT'];
include_once $root . '/contabilidad/config/db.php'; 

// Consulta optimizada
$sql = "SELECT t.*, a.nombre_area, p.nombre_puesto, c.nombre_categoria, b.nombre_banco
        FROM trabajadores t
        LEFT JOIN areas a ON t.id_area = a.id_area
        LEFT JOIN puestos p ON t.id_puesto = p.id_puesto
        LEFT JOIN categorias_pago c ON t.id_categoria = c.id_categoria
        LEFT JOIN bancos b ON t.banco_nombre = b.nombre_banco 
        ORDER BY t.estado ASC, t.apellidos_nombres ASC"; // Ordena: Activos primero

$res = mysqli_query($conexion, $sql);
?>

<div class="animate__animated animate__fadeIn">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-success mb-0"><i class="bi bi-people me-2"></i>Nómina de Personal</h4>
            <p class="text-muted small mb-0">Gestión de colaboradores activos y cesados</p>
        </div>
        <div class="input-group w-50 shadow-sm rounded-pill overflow-hidden border">
            <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="smartSearch" class="form-control border-0" placeholder="Buscar...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle" id="workerTable">
            <thead class="bg-light">
                <tr class="text-muted small text-uppercase">
                    <th class="ps-3">Estado</th>
                    <th>Colaborador</th>
                    <th>Documento</th>
                    <th>Cargo</th>
                    <th class="text-end pe-3">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($res)): ?>
                <?php 
                    // Estilos según estado
                    $is_active = ($row['estado'] == 'ACTIVO' || $row['estado'] == '' || $row['estado'] == null);
                    $row_class = $is_active ? '' : 'bg-light text-muted opacity-75';
                    $badge_class = $is_active ? 'bg-success' : 'bg-secondary';
                    $estado_text = $is_active ? 'ACTIVO' : 'CESADO';
                ?>
                <tr class="<?= $row_class ?>">
                    <td class="ps-3">
                        <span class="badge <?= $badge_class ?> rounded-pill" style="font-size: 0.7rem; width: 70px;">
                            <?= $estado_text ?>
                        </span>
                    </td>
                    <td>
                        <div class="fw-bold <?= $is_active ? 'text-dark' : 'text-muted' ?>"><?= $row['apellidos_nombres'] ?></div>
                        <?php if(!$is_active): ?>
                            <small class="text-danger" style="font-size: 0.7rem;">Salida: <?= $row['fecha_salida'] ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= $row['numero_documento'] ?></td>
                    <td>
                        <div class="small fw-bold"><?= $row['nombre_area'] ?></div>
                        <div class="small"><?= $row['nombre_puesto'] ?></div>
                    </td>
                    <td class="text-end pe-3">
                        <div class="btn-group shadow-sm">
                            
                            <a href="index.php?view=editar&id=<?= $row['id_trabajador'] ?>" 
                               class="btn btn-sm btn-outline-primary bg-white" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </a>

                            <?php if($is_active): ?>
                                <a href="controllers/baja_trabajador.php?id=<?= $row['id_trabajador'] ?>" 
                                   class="btn btn-sm btn-outline-warning bg-white text-warning" 
                                   title="Dar de Baja (Cese)"
                                   onclick="return confirm('¿Confirmar cese de <?= $row['apellidos_nombres'] ?>? Pasará a inactivo.')">
                                    <i class="bi bi-person-slash"></i>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-sm btn-light border" disabled><i class="bi bi-slash-circle"></i></button>
                            <?php endif; ?>
                            
                            <a href="controllers/eliminar_trabajador.php?id=<?= $row['id_trabajador'] ?>" 
                               class="btn btn-sm btn-outline-danger bg-white text-danger" 
                               title="Eliminar Definitivamente"
                               onclick="return confirm('¡PELIGRO! ¿Eliminar permanentemente a <?= $row['apellidos_nombres'] ?>? Esto no se puede deshacer.')">
                                <i class="bi bi-trash"></i>
                            </a>

                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Buscador
document.getElementById('smartSearch').addEventListener('keyup', function() {
    let filter = this.value.toUpperCase();
    let rows = document.querySelectorAll("#workerTable tbody tr");
    rows.forEach(row => {
        let text = row.innerText.toUpperCase();
        row.style.display = text.includes(filter) ? "" : "none";
    });
});
</script>