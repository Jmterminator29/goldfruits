<?php
$root = $_SERVER['DOCUMENT_ROOT'];
include_once $root . '/config/db.php'; 
$categorias = mysqli_query($conexion, "SELECT * FROM categorias_pago ORDER BY nombre_categoria ASC");
?>

<style>
    /* Estilos de refinamiento visual */
    .ls-1 { letter-spacing: 0.5px; }
    
    .category-card {
        border: 1px solid rgba(0,0,0,0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        background: #ffffff;
    }

    .category-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 20px rgba(25, 135, 84, 0.1) !important;
        border-color: rgba(25, 135, 84, 0.2);
    }

    .btn-delete-circle {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        background: #fff5f5;
        color: #dc3545;
        border: none;
    }

    .btn-delete-circle:hover {
        background: #dc3545;
        color: #ffffff;
    }

    .input-form-custom {
        border: 2px solid #f8f9fa;
        transition: all 0.3s;
    }

    .input-form-custom:focus {
        border-color: #198754;
        background-color: #ffffff !important;
        box-shadow: none;
    }

    .icon-container {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #198754, #146c43);
        color: white;
        border-radius: 12px;
    }
</style>

<div class="animate__animated animate__fadeInRight">
    <div class="glass-card border-0 p-0">
        
        <div class="d-flex align-items-center mb-4 p-2">
            <div class="icon-container shadow-sm me-3">
                <i class="bi bi-wallet2 fs-4"></i>
            </div>
            <div>
                <h4 class="fw-bold text-dark mb-0">Estructura Salarial</h4>
                <p class="text-muted small mb-0">Configura los jornales base para los cálculos de nómina.</p>
            </div>
        </div>
        
        <form action="controllers/config_controller.php" method="POST" class="row g-3 align-items-end mb-5 bg-white p-4 rounded-4 shadow-sm border">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="tabla" value="categorias_pago">
            
            <div class="col-md-5">
                <label class="small fw-bolder text-uppercase text-success ls-1 mb-2">Nombre de la Categoría</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0 text-muted"><i class="bi bi-person-badge"></i></span>
                    <input type="text" name="nombre" class="form-control bg-light input-form-custom" placeholder="Ej: Operario de Packing" required>
                </div>
            </div>
            
            <div class="col-md-4">
                <label class="small fw-bolder text-uppercase text-success ls-1 mb-2">Monto del Jornal</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-0 fw-bold text-success">S/</span>
                    <input type="number" step="0.01" name="monto" class="form-control bg-light input-form-custom fw-bold" placeholder="0.00" required>
                </div>
            </div>
            
            <div class="col-md-3">
                <button type="submit" class="btn btn-success w-100 rounded-3 py-2 fw-bold shadow-sm">
                    <i class="bi bi-plus-circle me-2"></i>AÑADIR TARIFA
                </button>
            </div>
        </form>

        <div class="d-flex align-items-center mb-3">
            <hr class="flex-grow-1 opacity-10">
            <span class="mx-3 text-uppercase small fw-bold text-muted ls-1">Tarifas Vigentes</span>
            <hr class="flex-grow-1 opacity-10">
        </div>

        <div class="row g-3">
            <?php if(mysqli_num_rows($categorias) > 0): ?>
                <?php while($cat = mysqli_fetch_assoc($categorias)): ?>
                <div class="col-md-4 col-sm-6">
                    <div class="card category-card rounded-4 shadow-sm h-100">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center">
                            <div class="overflow-hidden">
                                <h6 class="fw-bold text-dark mb-1 text-truncate" title="<?= $cat['nombre_categoria'] ?>">
                                    <?= $cat['nombre_categoria'] ?>
                                </h6>
                                <div class="d-flex align-items-center">
                                    <span class="text-success fw-bold fs-5">S/ <?= number_format($cat['monto_categoria'], 2) ?></span>
                                    <small class="text-muted ms-1" style="font-size: 0.7rem;">/ Día</small>
                                </div>
                            </div>
                            <a href="controllers/config_controller.php?eliminar=<?= $cat['id_categoria'] ?>&tabla=categorias_pago" 
                               class="btn-delete-circle rounded-circle" 
                               onclick="return confirm('¿Está seguro de eliminar esta tarifa? Los trabajadores asociados podrían verse afectados.')">
                                <i class="bi bi-trash3-fill"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-4">
                    <img src="https://illustrations.popsy.co/green/abstract-art-6.svg" style="width: 150px; opacity: 0.5;" class="mb-3">
                    <p class="text-muted italic">No hay tarifas registradas aún.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>