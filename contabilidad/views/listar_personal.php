<?php
// Evitar error si ya se inició sesión arriba
if (session_status() === PHP_SESSION_NONE) session_start();

$root = $_SERVER['DOCUMENT_ROOT'];
require_once $root . '/contabilidad/config/db.php';

// --- 1. CONSULTA DE DATOS ---
// Ajusta el nombre de tu campo si es diferente
$campoPlanilla = 'en_planilla'; 

$sql = "SELECT t.*, a.nombre_area, p.nombre_puesto, c.nombre_categoria, b.nombre_banco
        FROM trabajadores t
        LEFT JOIN areas a ON t.id_area = a.id_area
        LEFT JOIN puestos p ON t.id_puesto = p.id_puesto
        LEFT JOIN categorias_pago c ON t.id_categoria = c.id_categoria
        LEFT JOIN bancos b ON t.banco_nombre = b.nombre_banco
        ORDER BY t.apellidos_nombres ASC"; // Ordenamos solo por nombre, el estado lo filtramos en PHP

$res = mysqli_query($conexion, $sql);

// --- 2. CLASIFICACIÓN ---
$listaPlanilla = [];
$listaNoPlanilla = [];
$listaCesados = [];

while($row = mysqli_fetch_assoc($res)) {
    // 1. Determinar Estado (Activo/Cesado)
    // Asumimos que si está vacío o null, es activo por defecto (o ajusta según tu lógica)
    $estadoStr = strtoupper(trim($row['estado'] ?? ''));
    $esActivo = ($estadoStr === 'ACTIVO' || $estadoStr === '');

    // 2. Determinar Modalidad (Planilla/No Planilla)
    $v = $row[$campoPlanilla] ?? null;
    $vStr = strtoupper(trim((string)$v));
    $esPlanilla = false;
    
    if (is_numeric($v) && (int)$v === 1) $esPlanilla = true;
    if (in_array($vStr, ['1','SI','S','TRUE','PLANILLA','EN PLANILLA','EN_PLANILLA'], true)) $esPlanilla = true;

    // 3. Clasificar en los 3 grupos
    if (!$esActivo) {
        // Si no es activo, va directo a la lista de CESADOS
        $listaCesados[] = $row;
    } else {
        // Si es activo, verificamos si es planilla o no
        if ($esPlanilla) {
            $listaPlanilla[] = $row;
        } else {
            $listaNoPlanilla[] = $row;
        }
    }
}

// Conteos para las Cards
$cnt_planilla = count($listaPlanilla);
$cnt_noplanilla = count($listaNoPlanilla);
$cnt_cesados = count($listaCesados);
$cnt_total_activos = $cnt_planilla + $cnt_noplanilla;

// Función para dibujar las filas (Reutilizable)
function renderTableRows($rows, $tipoLista) {
    if (empty($rows)) {
        echo '<tr><td colspan="6" class="text-center text-muted py-4 small">No se encontraron registros en esta categoría.</td></tr>';
        return;
    }
    foreach($rows as $row){
        $estado = strtoupper(trim($row['estado'] ?? ''));
        $esActivo = ($estado == 'ACTIVO' || $estado == '');
        
        // Colores y badges
        $badgeClass = $esActivo ? 'bg-success' : 'bg-secondary';
        $estadoTxt = $esActivo ? 'ACTIVO' : 'CESADO';
        
        // Opacidad para cesados
        $rowClass = !$esActivo ? 'bg-light text-muted' : '';
        ?>
        <tr class="<?= $rowClass ?>">
            <td class="ps-3 fw-bold text-muted small row-number">-</td>
            <td>
                <span class="badge <?= $badgeClass ?> rounded-pill" style="font-size: 0.65rem; width: 65px;">
                    <?= $estadoTxt ?>
                </span>
            </td>
            <td>
                <div class="fw-bold <?= $esActivo ? 'text-dark' : 'text-muted' ?>" style="font-size: 0.9rem;">
                    <?= htmlspecialchars($row['apellidos_nombres'] ?? '') ?>
                </div>
                <?php if(!$esActivo && !empty($row['fecha_salida'])): ?>
                    <small class="text-danger fw-bold" style="font-size: 0.7rem;">
                        <i class="bi bi-box-arrow-right"></i> Salida: <?= htmlspecialchars($row['fecha_salida']) ?>
                    </small>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['numero_documento'] ?? '') ?></td>
            <td>
                <div class="small fw-bold text-secondary"><?= htmlspecialchars($row['nombre_area'] ?? '') ?></div>
                <div class="small text-muted"><?= htmlspecialchars($row['nombre_puesto'] ?? '') ?></div>
            </td>
            <td class="text-end pe-3">
                <div class="btn-group shadow-sm">
                    <a href="index.php?view=editar&id=<?= (int)$row['id_trabajador'] ?>" 
                       class="btn btn-sm btn-outline-primary bg-white" title="Editar ficha">
                        <i class="bi bi-pencil-square"></i>
                    </a>

                    <?php if($esActivo): ?>
                        <a href="controllers/baja_trabajador.php?id=<?= (int)$row['id_trabajador'] ?>" 
                           class="btn btn-sm btn-outline-warning bg-white text-warning" 
                           title="Dar de Baja (Mover a Cesados)"
                           onclick="return confirm('¿Seguro que deseas dar de BAJA a este trabajador? Pasará a la pestaña Cesados.')">
                            <i class="bi bi-person-slash"></i>
                        </a>
                    <?php else: ?>
                        <button class="btn btn-sm btn-light border text-muted" title="Ya está cesado" disabled>
                            <i class="bi bi-slash-circle"></i>
                        </button>
                    <?php endif; ?>

                    <a href="controllers/eliminar_trabajador.php?id=<?= (int)$row['id_trabajador'] ?>" 
                       class="btn btn-sm btn-outline-danger bg-white text-danger" 
                       title="Eliminar registro permanentemente"
                       onclick="return confirm('¡ADVERTENCIA! ¿Eliminar permanentemente de la base de datos? Esta acción NO se puede deshacer.')">
                        <i class="bi bi-trash"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php
    }
}
?>

<style>
  /* CARDS INDICADORES */
  .stat-card {
      background: white; border-radius: 12px; padding: 15px; position: relative; overflow: hidden;
      box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid rgba(0,0,0,0.04);
      display: flex; align-items: center; justify-content: space-between; transition: transform 0.2s;
  }
  .stat-card:hover { transform: translateY(-3px); }
  .stat-icon {
      width: 48px; height: 48px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center; font-size: 1.6rem;
  }
  
  /* TABS PERSONALIZADOS */
  .custom-tabs {
      background-color: #fff; padding: 6px; border-radius: 50px;
      display: inline-flex; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #eee;
      width: 100%; max-width: 600px; margin-bottom: 20px;
  }
  .custom-tab-btn {
      flex: 1; border: none; background: transparent; padding: 10px 20px;
      border-radius: 40px; font-weight: 600; color: #6c757d; font-size: 0.85rem;
      transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px;
  }
  .custom-tab-btn:hover { color: #1b4d2e; background: rgba(27, 77, 46, 0.05); }
  .custom-tab-btn.active {
      background-color: #1b4d2e; color: white; box-shadow: 0 4px 10px rgba(27, 77, 46, 0.3);
  }
  .badge-counter {
      background: rgba(255,255,255,0.25); color: inherit; padding: 2px 8px;
      border-radius: 10px; font-size: 0.75rem;
  }
  .custom-tab-btn.active .badge-counter { background: rgba(255,255,255,0.9); color: #1b4d2e; font-weight: 800; }

  /* HERRAMIENTAS DE TABLA */
  .table-tools { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 15px; }
  .search-box { flex-grow: 1; min-width: 250px; }
</style>

<div class="animate__animated animate__fadeIn">
    
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill me-2 text-success"></i>Personal</h4>
            <p class="text-muted small mb-0">Gestión de RR.HH.</p>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card border-start border-4 border-success">
                <div>
                    <div class="text-uppercase small fw-bold text-muted">Planilla (Activos)</div>
                    <div class="fs-4 fw-bold text-dark"><?= $cnt_planilla ?></div>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-person-badge"></i></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card border-start border-4 border-warning">
                <div>
                    <div class="text-uppercase small fw-bold text-muted">No Planilla (Activos)</div>
                    <div class="fs-4 fw-bold text-dark"><?= $cnt_noplanilla ?></div>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-file-earmark-person"></i></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card border-start border-4 border-secondary">
                <div>
                    <div class="text-uppercase small fw-bold text-muted">Cesados (Histórico)</div>
                    <div class="fs-4 fw-bold text-dark"><?= $cnt_cesados ?></div>
                </div>
                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-person-x"></i></div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-center">
        <div class="custom-tabs">
            <button class="custom-tab-btn active" onclick="switchTab('pane-planilla', this)">
                EN PLANILLA <span class="badge-counter"><?= $cnt_planilla ?></span>
            </button>
            <button class="custom-tab-btn" onclick="switchTab('pane-noplanilla', this)">
                NO PLANILLA <span class="badge-counter"><?= $cnt_noplanilla ?></span>
            </button>
            <button class="custom-tab-btn" onclick="switchTab('pane-cesados', this)">
                CESADOS <span class="badge-counter"><?= $cnt_cesados ?></span>
            </button>
        </div>
    </div>

    <div class="tab-content">
        
        <div class="tab-pane fade show active" id="pane-planilla">
            <div class="table-tools">
                <div class="search-box input-group shadow-sm rounded-pill overflow-hidden border">
                    <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="search1" class="form-control border-0" placeholder="Buscar personal en planilla...">
                </div>
                <select id="size1" class="form-select shadow-sm" style="width: auto; border-radius: 20px;">
                    <option value="10">10 filas</option><option value="25" selected>25 filas</option><option value="50">50 filas</option>
                </select>
            </div>
            <div class="table-responsive bg-white rounded-3 shadow-sm border">
                <table class="table table-hover align-middle mb-0" id="table1">
                    <thead class="bg-light text-uppercase small text-muted">
                        <tr><th class="ps-3">#</th><th>Estado</th><th>Colaborador</th><th>DNI</th><th>Cargo</th><th class="text-end pe-4">Acciones</th></tr>
                    </thead>
                    <tbody><?php renderTableRows($listaPlanilla, 'planilla'); ?></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3 px-2">
                <div class="text-muted small fw-bold" id="info1"></div>
                <nav><ul class="pagination pagination-sm mb-0" id="pag1"></ul></nav>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-noplanilla">
            <div class="table-tools">
                <div class="search-box input-group shadow-sm rounded-pill overflow-hidden border">
                    <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="search2" class="form-control border-0" placeholder="Buscar personal no planilla...">
                </div>
                <select id="size2" class="form-select shadow-sm" style="width: auto; border-radius: 20px;">
                    <option value="10">10 filas</option><option value="25" selected>25 filas</option><option value="50">50 filas</option>
                </select>
            </div>
            <div class="table-responsive bg-white rounded-3 shadow-sm border">
                <table class="table table-hover align-middle mb-0" id="table2">
                    <thead class="bg-light text-uppercase small text-muted">
                        <tr><th class="ps-3">#</th><th>Estado</th><th>Colaborador</th><th>DNI</th><th>Cargo</th><th class="text-end pe-4">Acciones</th></tr>
                    </thead>
                    <tbody><?php renderTableRows($listaNoPlanilla, 'noplanilla'); ?></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3 px-2">
                <div class="text-muted small fw-bold" id="info2"></div>
                <nav><ul class="pagination pagination-sm mb-0" id="pag2"></ul></nav>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-cesados">
            <div class="table-tools">
                <div class="search-box input-group shadow-sm rounded-pill overflow-hidden border">
                    <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="search3" class="form-control border-0" placeholder="Buscar personal cesado...">
                </div>
                <select id="size3" class="form-select shadow-sm" style="width: auto; border-radius: 20px;">
                    <option value="10">10 filas</option><option value="25" selected>25 filas</option><option value="50">50 filas</option>
                </select>
            </div>
            <div class="table-responsive bg-white rounded-3 shadow-sm border">
                <table class="table table-hover align-middle mb-0" id="table3">
                    <thead class="bg-light text-uppercase small text-muted">
                        <tr><th class="ps-3">#</th><th>Estado</th><th>Colaborador</th><th>DNI</th><th>Cargo</th><th class="text-end pe-4">Acciones</th></tr>
                    </thead>
                    <tbody><?php renderTableRows($listaCesados, 'cesados'); ?></tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3 px-2">
                <div class="text-muted small fw-bold" id="info3"></div>
                <nav><ul class="pagination pagination-sm mb-0" id="pag3"></ul></nav>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// --- LÓGICA DE TABS MANUAL (Simple y Robusta) ---
function switchTab(targetId, btnElement) {
    // 1. Ocultar todos los paneles
    document.querySelectorAll('.tab-pane').forEach(el => {
        el.classList.remove('show', 'active');
    });
    // 2. Mostrar el seleccionado
    document.getElementById(targetId).classList.add('show', 'active');

    // 3. Actualizar estado de los botones
    document.querySelectorAll('.custom-tab-btn').forEach(btn => btn.classList.remove('active'));
    btnElement.classList.add('active');
}

// --- LÓGICA DE PAGINACIÓN Y BÚSQUEDA ---
function initTable(config) {
    const table = document.getElementById(config.tableId);
    if (!table) return; 
    const tbody = table.querySelector('tbody');
    const searchInp = document.getElementById(config.searchId);
    const sizeSel = document.getElementById(config.sizeId);
    const pagCont = document.getElementById(config.pagId);
    const infoDiv = document.getElementById(config.infoId);

    let allRows = Array.from(tbody.querySelectorAll('tr'));
    let filtered = [...allRows];
    let page = 1;
    let limit = parseInt(sizeSel.value);

    function filter() {
        const term = searchInp.value.toUpperCase();
        filtered = allRows.filter(r => r.innerText.toUpperCase().includes(term));
        page = 1; render();
    }

    function render() {
        const total = filtered.length;
        const maxPage = Math.ceil(total / limit) || 1;
        if (page > maxPage) page = maxPage;

        const start = (page - 1) * limit;
        const end = start + limit;

        // Ocultar todo y mostrar slice
        allRows.forEach(r => r.style.display = 'none');
        filtered.slice(start, end).forEach((r, i) => {
            r.style.display = '';
            // Renumerar visualmente
            const n = r.querySelector('.row-number');
            if(n) n.innerText = (start + i + 1);
        });

        // Info text
        infoDiv.innerText = total > 0 ? `Viendo ${start + 1} - ${Math.min(end, total)} de ${total}` : 'Sin resultados';

        // Paginador
        let html = '';
        if(maxPage > 1){
            html += `<li class="page-item ${page==1?'disabled':''}"><button class="page-link" onclick="changePage('${config.tableId}', ${page-1})"><i class="bi bi-chevron-left"></i></button></li>`;
            
            // Lógica simple de botones (1 ... current ... last)
            if(page > 2) html += `<li class="page-item"><button class="page-link" onclick="changePage('${config.tableId}', 1)">1</button></li>`;
            if(page > 3) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            
            html += `<li class="page-item active"><span class="page-link">${page}</span></li>`;
            
            if(page < maxPage - 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            if(page < maxPage - 1) html += `<li class="page-item"><button class="page-link" onclick="changePage('${config.tableId}', ${maxPage})">${maxPage}</button></li>`;

            html += `<li class="page-item ${page==maxPage?'disabled':''}"><button class="page-link" onclick="changePage('${config.tableId}', ${page+1})"><i class="bi bi-chevron-right"></i></button></li>`;
        }
        pagCont.innerHTML = html;
        
        // Guardar estado en el objeto global para acceso externo (changePage)
        if(!window.tableStates) window.tableStates = {};
        window.tableStates[config.tableId] = { setPage: (p) => { page=p; render(); } };
    }

    searchInp.addEventListener('keyup', filter);
    sizeSel.addEventListener('change', () => { limit = parseInt(sizeSel.value); page = 1; render(); });
    render();
}

// Función global para los botones del paginador
function changePage(tblId, p) {
    if(window.tableStates && window.tableStates[tblId]) {
        window.tableStates[tblId].setPage(p);
    }
}

// Inicializar las 3 tablas
document.addEventListener('DOMContentLoaded', () => {
    initTable({ tableId:'table1', searchId:'search1', sizeId:'size1', pagId:'pag1', infoId:'info1' });
    initTable({ tableId:'table2', searchId:'search2', sizeId:'size2', pagId:'pag2', infoId:'info2' });
    initTable({ tableId:'table3', searchId:'search3', sizeId:'size3', pagId:'pag3', infoId:'info3' });
});
</script>


