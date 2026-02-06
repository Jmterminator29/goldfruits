<?php
require_once '../includes/auth_admin.php';
require_once '../includes/db_connect.php';

$id = $_GET['id'] ?? null;
if (!$id) die("ID inválido.");

// 1. Datos de Cabecera
$stmt = $conn->prepare("SELECT * FROM acopios_cabecera WHERE id = ?");
$stmt->execute([$id]);
$d = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$d) die("Solicitud no encontrada.");

// 2. Datos Detallados por Proveedor + Liquidación Guardada (+ CLP)
$stmtOri = $conn->prepare("
    SELECT ao.id as origen_id, p.nombre, ao.tara_asignada, 
           ao.k_cat1, ao.p_cat1, 
           ao.k_cat2, ao.p_cat2, 
           ao.k_rastrojo, ao.p_rastrojo, 
           ao.subtotal,
           al.porc_merma as liq_porc,
           al.precio_merma as liq_precio_m,
           al.clp_c1 as liq_clp_c1,
           al.clp_c2 as liq_clp_c2,
           al.clp_rz as liq_clp_rz,
           al.clp_subtotal as liq_clp_subtotal,
           COALESCE((SELECT SUM(peso_bruto) FROM acopios_pesadas WHERE origen_id = ao.id), 0) as total_bruto,
           COALESCE((SELECT SUM(jabas) FROM acopios_pesadas WHERE origen_id = ao.id), 0) as total_jabas
    FROM acopios_origenes ao
    JOIN proveedores p ON ao.proveedor_id = p.id
    LEFT JOIN acopios_liquidaciones al ON al.origen_id = ao.id
    WHERE ao.acopio_id = ?
");
$stmtOri->execute([$id]);
$proveedores = $stmtOri->fetchAll(PDO::FETCH_ASSOC);

// 3. Fotos
$stmtPes = $conn->prepare("SELECT * FROM acopios_pesadas WHERE acopio_id = ? ORDER BY numero_tanda ASC");
$stmtPes->execute([$id]);
$todas_pesadas = $stmtPes->fetchAll(PDO::FETCH_ASSOC);

$pesadas_por_origen = [];
foreach ($todas_pesadas as $p) {
    if (!empty($p['origen_id'])) $pesadas_por_origen[$p['origen_id']][] = $p;
}

// 4. Gastos (Cálculo dinámico de los fijos)
$sub_cosecha = (float)$d['cosecha_personas'] * (float)$d['cosecha_dias'] * (float)$d['cosecha_precio'];
$sub_cargadores = (float)$d['cargadores_personas'] * (float)$d['cargadores_dias'] * (float)$d['cargadores_precio'];
$sub_inspectores = (float)$d['inspectores_personas'] * (float)$d['inspectores_dias'] * (float)$d['inspectores_precio'];

// Base fija para JS (Flete + Cosecha + Carga + Insp + Viáticos) - NO incluye otros gastos
$gastos_base_fijos = (float)$d['precio_flete'] + $sub_cosecha + $sub_cargadores + $sub_inspectores + (float)$d['viaticos'];

// 5. Auto activar CLP si ya existe guardado
$clp_guardado_activo = false;
foreach ($proveedores as $pp) {
    $c1 = (float)($pp['liq_clp_c1'] ?? 0);
    $c2 = (float)($pp['liq_clp_c2'] ?? 0);
    $rz = (float)($pp['liq_clp_rz'] ?? 0);
    $st = (float)($pp['liq_clp_subtotal'] ?? 0);
    if ($c1 > 0 || $c2 > 0 || $rz > 0 || $st > 0) { $clp_guardado_activo = true; break; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Liquidación Gerencial | <?php echo htmlspecialchars($d['codigo_unico']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --bg-body: #f8fafc; 
            --surface: #ffffff; 
            --primary: #059669; 
            --primary-dark: #047857; 
            --accent: #f59e0b; 
            --text-main: #1e293b; 
            --text-muted: #64748b; 
            --border: #e2e8f0; 
            --danger: #ef4444; 
            --success: #10b981; 
        }

        * { box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            margin: 0; 
            padding-bottom: 220px;
            font-size: 14px; 
        }

        /* --- TOP BAR --- */
        .top-bar { 
            background: var(--surface); 
            border-bottom: 1px solid var(--border); 
            padding: 15px 30px; 
            position: sticky; 
            top: 0; 
            z-index: 50; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        }
        .brand { font-weight: 800; font-size: 1.2rem; color: var(--primary-dark); display: flex; align-items: center; gap: 10px; }
        .btn-back { text-decoration: none; color: var(--text-muted); font-weight: 600; padding: 8px 16px; border-radius: 8px; background: #f1f5f9; }

        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .section-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); font-weight: 700; margin-bottom: 15px; display: block; }

        /* --- PROVIDER CARD --- */
        .prov-card { background: var(--surface); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid var(--border); margin-bottom: 30px; overflow: hidden; }
        
        .prov-header { 
            padding: 20px 25px; 
            border-bottom: 1px solid var(--border); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background: linear-gradient(to right, #ffffff, #f8fafc); 
        }
        .prov-name { font-size: 1.1rem; font-weight: 700; color: var(--text-main); }
        .prov-badge { background: #e2e8f0; padding: 4px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); }

        .data-grid { display: grid; grid-template-columns: 2fr 3fr; border-bottom: 1px solid var(--border); }
        .col-phys { padding: 25px; border-right: 1px solid var(--border); }
        .col-fin { padding: 25px; background: #fffbeb; }
        
        .stat-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9rem; }
        .stat-label { color: var(--text-muted); }
        .stat-val { font-weight: 600; color: var(--text-main); }
        .stat-val.neto { color: var(--primary); font-size: 1.1rem; font-weight: 800; }
        .stat-val.tara { color: var(--danger); }

        /* --- CATEGORIES --- */
        .cat-list { margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--border); }
        .cat-item { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.85rem; }
        .badge-cat { padding: 2px 6px; border-radius: 4px; color: white; font-weight: 700; font-size: 0.7rem; margin-right: 5px; }

        /* --- SIMULATOR --- */
        .sim-header { font-weight: 800; color: #b45309; margin-bottom: 15px; display:flex; align-items:center; gap:5px; }
        .sim-controls { display: flex; gap: 10px; margin-bottom: 20px; }
        .input-wrap { flex: 1; }
        .input-wrap label { display: block; font-size: 0.7rem; font-weight: 700; margin-bottom: 5px; color: #92400e; }
        .input-wrap input { width: 100%; padding: 10px; border: 1px solid #fcd34d; border-radius: 8px; font-weight: 700; color: #78350f; text-align: center; font-size: 1rem; outline: none; background: white; }

        .fin-result { background: white; padding: 15px; border-radius: 12px; border: 1px dashed #fcd34d; }
        .res-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 0.9rem; }
        .res-row.merma { color: var(--danger); font-size: 0.85rem; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0; margin-bottom: 10px; }
        .res-row.final { margin-top: 15px; padding-top: 10px; border-top: 2px solid #e2e8f0; font-size: 1.1rem; }
        .money { font-family: 'Consolas', monospace; font-weight: 700; }

        /* --- GALLERY --- */
        .gallery-strip { padding: 15px 25px; background: #f8fafc; display: flex; gap: 10px; overflow-x: auto; scroll-behavior: smooth; }
        .gallery-strip::-webkit-scrollbar { height: 6px; }
        .gallery-strip::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .img-card { width: 100px; flex-shrink: 0; text-align: center; font-size: 0.65rem; color: #666; cursor: pointer; }
        .img-card img { width: 100%; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; transition: transform 0.2s; }
        .img-card:hover img { transform: scale(1.05); }

        /* --- EXPENSES CENTRALIZED --- */
        .expenses-wrapper { 
            max-width: 600px; 
            margin: 40px auto; 
        }
        .expense-box { background: var(--surface); padding: 25px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 15px rgba(0,0,0,0.03); }
        
        .exp-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed var(--border); font-size: 0.9rem; align-items: center; }
        
        .row-otros {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 8px 12px;
            margin-top: 5px;
            margin-bottom: 5px;
        }
        .row-otros input {
            width: 100px;
            padding: 6px;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            text-align: right;
            font-weight: 700;
            color: #0369a1;
            outline: none;
            background: white;
        }

        /* --- FOOTER --- */
        .master-footer { 
            position: fixed; bottom: 0; left: 0; width: 100%; 
            background: #0f172a; color: white; 
            padding: 14px 0; z-index: 100; 
            box-shadow: 0 -5px 20px rgba(0,0,0,0.2); 
        }

        .footer-grid { 
            max-width: 1200px; margin: 0 auto; padding: 0 20px;
            display: grid; grid-template-columns: repeat(5, 1fr); 
            gap: 14px; text-align: center; align-items: center; 
        }
        .kpi-val { font-size: 1.15rem; font-weight: 800; font-family: 'Consolas', monospace; }
        .kpi-val.gold { color: var(--accent); font-size: 1.35rem; }
        .kpi-lbl { font-size: 0.65rem; text-transform: uppercase; color: #94a3b8; display: block; margin-bottom: 4px; letter-spacing: 0.5px; }
        
        .btn-save-float { 
            background: var(--accent); color: #0f172a; border: none; 
            padding: 12px 22px; border-radius: 50px; 
            font-weight: 900; cursor: pointer; transition: 0.2s; 
            font-size: 0.9rem; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4); 
            text-transform: uppercase; letter-spacing: 0.5px;
            width: 100%;
        }
        .btn-save-float:active { transform: scale(0.98); }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 2000; display: none; justify-content: center; align-items: center; }
        .modal-overlay.open { display: flex; }
        .modal-content { max-width: 95%; max-height: 90vh; border-radius: 4px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); object-fit: contain; }

        /* --- RESPONSIVE --- */
        @media(max-width: 900px) { 
            .data-grid { grid-template-columns: 1fr; } 
            .col-phys { border-right: none; border-bottom: 1px solid var(--border); padding: 20px; }
            .col-fin { padding: 20px; }
            .footer-grid { grid-template-columns: 1fr 1fr 1fr; } 
            .footer-grid > div:last-child { grid-column: span 3; }
        }

        @media(max-width: 600px) {
            body{ padding-bottom: 260px; } 
            .top-bar { padding: 12px 15px; gap: 10px; }
            .brand { font-size: 1rem; }
            .btn-back { font-size: 0.8rem; padding: 6px 12px; }
            
            .container { margin: 18px auto; padding: 0 14px; }
            .expenses-wrapper { margin: 20px 0; }
            
            .prov-header { flex-direction: column; align-items: flex-start; gap: 8px; }

            .sim-controls { flex-direction: column; } 
            .input-wrap input { font-size: 0.95rem; padding: 10px; }

            .master-footer { padding: 12px 0; }
            .footer-grid { 
                grid-template-columns: 1fr 1fr; 
                gap: 10px; row-gap: 12px;
            }
            .kpi-val { font-size: 1.05rem; }
            .kpi-val.gold { font-size: 1.2rem; }
            .footer-grid > div:last-child { 
                grid-column: span 2; 
                margin-top: 4px; 
            }
            .btn-save-float { padding: 14px; font-size: 1rem; }
        }

        /* ====== CLP ====== */
        .clp-global-switch{
            background:#fff1f2;
            border:1px solid #fecaca;
            padding:14px 16px;
            border-radius:12px;
            display:flex;
            gap:12px;
            align-items:center;
            font-weight:800;
            color:#be123c;
            margin-bottom:20px;
        }
        .clp-global-switch input{ width:18px; height:18px; cursor:pointer; }

        .clp-box{
            margin-top:18px;
            background:#fff1f2;
            border:1px dashed #fda4af;
            border-radius:12px;
            padding:14px;
            display:none;
        }
        .clp-title{
            font-weight:900;
            color:#be123c;
            font-size:0.8rem;
            text-transform:uppercase;
            letter-spacing:.5px;
            margin-bottom:10px;
        }
        .clp-grid{
            display:grid;
            grid-template-columns: repeat(3, 1fr);
            gap:10px;
        }
        .clp-grid .input-wrap label{ color:#9f1239; }
        .clp-grid .input-wrap input{
            border:1px solid #fda4af;
            color:#9f1239;
        }
        .clp-total-row{
            margin-top:10px;
            text-align:right;
            font-weight:900;
            color:#be123c;
        }
        @media(max-width:600px){
            .clp-grid{ grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<div class="modal-overlay" id="imgModal" onclick="closeModal()">
    <img src="" class="modal-content" id="modalImg" onclick="event.stopPropagation()">
</div>

<div class="top-bar">
    <div class="brand">GoldFruits <span>| Op #<?php echo htmlspecialchars($d['codigo_unico']); ?></span></div>
    <a href="admin_panel.php" class="btn-back">← Volver</a>
</div>

<form action="admin_guardar_cierre.php" method="POST" id="formCierre">
    <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
    <input type="hidden" name="total_kilos_neto" id="head_kg_total">
    <input type="hidden" name="importe_total_fruta" id="head_imp_total">

    <input type="hidden" name="total_clp_operativo" id="head_clp_total" value="0">
    <input type="hidden" name="clp_activo" id="head_clp_activo" value="<?php echo $clp_guardado_activo ? '1' : '0'; ?>">

    <div class="container">
        <span class="section-label">1. Detalle por Proveedor y Calidad</span>

        <div class="clp-global-switch">
            <input type="checkbox" id="check_clp_global" onchange="toggleCLP()" <?php echo $clp_guardado_activo ? 'checked' : ''; ?>>
            <span>ACTIVAR PAGO CLP (Costo Logístico por Kg / Categoría / Proveedor)</span>
        </div>

        <?php 
        foreach($proveedores as $idx => $p): 
            $origen_id = (int)$p['origen_id'];
            $mis_fotos = $pesadas_por_origen[$origen_id] ?? [];
            
            $peso_neto_campo = (float)$p['k_cat1'] + (float)$p['k_cat2'] + (float)$p['k_rastrojo'];
            $subtotal_campo = (float)$p['subtotal'];
            
            $val_pct = $p['liq_porc'] ?? 0;
            $val_prc = $p['liq_precio_m'] ?? 0;

            // CLP precargado
            $clp_c1 = $p['liq_clp_c1'] ?? 0;
            $clp_c2 = $p['liq_clp_c2'] ?? 0;
            $clp_rz = $p['liq_clp_rz'] ?? 0;
        ?>
        <div class="prov-card">
            <input type="hidden" name="liq[<?php echo $origen_id; ?>][origen_id]" value="<?php echo $origen_id; ?>">
            <input type="hidden" name="liq[<?php echo $origen_id; ?>][kg_campo_neto]" value="<?php echo $peso_neto_campo; ?>">
            <input type="hidden" name="liq[<?php echo $origen_id; ?>][importe_campo]" value="<?php echo $subtotal_campo; ?>">
            
            <input type="hidden" name="liq[<?php echo $origen_id; ?>][kg_merma]" id="inp_kg_merma_<?php echo $idx; ?>">
            <input type="hidden" name="liq[<?php echo $origen_id; ?>][kg_util]" id="inp_kg_util_<?php echo $idx; ?>">
            <input type="hidden" name="liq[<?php echo $origen_id; ?>][pago_merma]" id="inp_pago_merma_<?php echo $idx; ?>">
            <input type="hidden" name="liq[<?php echo $origen_id; ?>][pago_util]" id="inp_pago_util_<?php echo $idx; ?>">
            <input type="hidden" name="liq[<?php echo $origen_id; ?>][pago_total]" id="inp_pago_total_<?php echo $idx; ?>">

            <input type="hidden" name="liq[<?php echo $origen_id; ?>][clp_subtotal]" id="inp_clp_subtotal_<?php echo $idx; ?>">

            <div class="prov-header">
                <div class="prov-name"><span style="font-size:1.5rem;">👤</span> <?php echo htmlspecialchars($p['nombre']); ?></div>
                <div class="prov-badge">Tara: <?php echo htmlspecialchars($p['tara_asignada']); ?> kg</div>
            </div>

            <div class="data-grid">
                <div class="col-phys">
                    <div class="stat-row"><span class="stat-label">Total Jabas</span> <span class="stat-val"><?php echo (float)$p['total_jabas']; ?> un.</span></div>
                    <div class="stat-row"><span class="stat-label">Peso Bruto</span> <span class="stat-val bruto"><?php echo number_format((float)$p['total_bruto'], 2); ?> kg</span></div>
                    <div class="stat-row"><span class="stat-label">Descuento Tara</span> <span class="stat-val tara">- <?php echo number_format(((float)$p['total_jabas'] * (float)$p['tara_asignada']), 2); ?> kg</span></div>
                    <div class="stat-row" style="margin-top:10px; border-top:1px dashed var(--border); padding-top:10px;">
                        <span class="stat-label" style="font-weight:700;">PESO NETO</span>
                        <span class="stat-val neto"><?php echo number_format($peso_neto_campo, 2); ?> kg</span>
                    </div>

                    <div class="cat-list">
                        <?php if((float)$p['k_cat1']>0): ?><div class="cat-item"><span><span class="badge-cat" style="background:#15803d">C1</span> Grande</span> <strong><?php echo htmlspecialchars($p['k_cat1']); ?> kg</strong></div><?php endif; ?>
                        <?php if((float)$p['k_cat2']>0): ?><div class="cat-item"><span><span class="badge-cat" style="background:#f59e0b">C2</span> Chico</span> <strong><?php echo htmlspecialchars($p['k_cat2']); ?> kg</strong></div><?php endif; ?>
                        <?php if((float)$p['k_rastrojo']>0): ?><div class="cat-item"><span><span class="badge-cat" style="background:#ef4444">RZ</span> Rastrojo</span> <strong><?php echo htmlspecialchars($p['k_rastrojo']); ?> kg</strong></div><?php endif; ?>
                    </div>
                </div>

                <div class="col-fin">
                    <div class="sim-header">⚡ Ajuste Liquidación</div>
                    <div class="sim-controls">
                        <div class="input-wrap">
                            <label>% MERMA</label>
                            <input type="number" step="0.1" name="liq[<?php echo $origen_id; ?>][porc_merma]" id="pct_<?php echo $idx; ?>" value="<?php echo htmlspecialchars($val_pct); ?>" oninput="recalc()" placeholder="0%">
                        </div>
                        <div class="input-wrap">
                            <label>PRECIO MERMA (S/)</label>
                            <input type="number" step="0.01" name="liq[<?php echo $origen_id; ?>][precio_merma]" id="prm_<?php echo $idx; ?>" value="<?php echo htmlspecialchars($val_prc); ?>" oninput="recalc()" placeholder="0.00">
                        </div>
                    </div>

                    <div class="fin-result">
                        <div class="res-row merma">
                            <span>Pago Merma:</span>
                            <span><span id="lbl_k_merma_<?php echo $idx; ?>">0</span> kg x S/ <span id="lbl_p_merma_<?php echo $idx; ?>">0.00</span> = <strong id="lbl_s_merma_<?php echo $idx; ?>">S/ 0.00</strong></span>
                        </div>
                        <div style="font-size:0.75rem; font-weight:700; color:#475569; margin-bottom:5px; text-transform:uppercase;">Fruta Útil (Neto - Merma):</div>
                        <div class="breakdown-list" id="breakdown_<?php echo $idx; ?>"></div>
                        <div class="res-row final">
                            <span style="color:var(--primary-dark); font-weight:800;">A PAGAR</span>
                            <strong class="money" style="color:var(--primary); font-size:1.4rem;" id="res_pago_<?php echo $idx; ?>">S/ 0.00</strong>
                        </div>
                    </div>

                    <div class="clp-box" id="clp_box_<?php echo $idx; ?>">
                        <div class="clp-title">🚛 CLP por Kg (Categoría)</div>
                        <div class="clp-grid">
                            <div class="input-wrap">
                                <label>CLP C1 (S/ x Kg)</label>
                                <input type="number" step="0.01"
                                       name="liq[<?php echo $origen_id; ?>][clp_c1]"
                                       class="clp-in-<?php echo $idx; ?>"
                                       value="<?php echo htmlspecialchars($clp_c1); ?>"
                                       oninput="recalc()">
                            </div>
                            <div class="input-wrap">
                                <label>CLP C2 (S/ x Kg)</label>
                                <input type="number" step="0.01"
                                       name="liq[<?php echo $origen_id; ?>][clp_c2]"
                                       class="clp-in-<?php echo $idx; ?>"
                                       value="<?php echo htmlspecialchars($clp_c2); ?>"
                                       oninput="recalc()">
                            </div>
                            <div class="input-wrap">
                                <label>CLP RZ (S/ x Kg)</label>
                                <input type="number" step="0.01"
                                       name="liq[<?php echo $origen_id; ?>][clp_rz]"
                                       class="clp-in-<?php echo $idx; ?>"
                                       value="<?php echo htmlspecialchars($clp_rz); ?>"
                                       oninput="recalc()">
                            </div>
                        </div>
                        <div class="clp-total-row">
                            Total CLP: <span id="lbl_clp_prov_<?php echo $idx; ?>">S/ 0.00</span>
                        </div>
                    </div>
                    
                    <input type="hidden" class="h-k1" value="<?php echo (float)$p['k_cat1']; ?>">
                    <input type="hidden" class="h-p1" value="<?php echo (float)$p['p_cat1']; ?>">
                    <input type="hidden" class="h-k2" value="<?php echo (float)$p['k_cat2']; ?>">
                    <input type="hidden" class="h-p2" value="<?php echo (float)$p['p_cat2']; ?>">
                    <input type="hidden" class="h-kr" value="<?php echo (float)$p['k_rastrojo']; ?>">
                    <input type="hidden" class="h-pr" value="<?php echo (float)$p['p_rastrojo']; ?>">
                </div>
            </div>

            <?php if(!empty($mis_fotos)): ?>
            <div class="gallery-strip">
                <?php foreach($mis_fotos as $f): ?>
                <div class="img-card" onclick="openModal('../user/<?php echo htmlspecialchars($f['foto_url']); ?>')">
                    <img src="../user/<?php echo htmlspecialchars($f['foto_url']); ?>" loading="lazy">
                    <div style="font-size:0.65rem; color:#666; margin-top:3px;">#<?php echo htmlspecialchars($f['numero_tanda']); ?> | <?php echo htmlspecialchars($f['peso']); ?>kg</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="expenses-wrapper">
            <div class="expense-box">
                <div style="font-weight:700; color:var(--text-main); margin-bottom:15px; font-size:1.1rem;">Resumen de Gastos</div>
                <div class="exp-row"><label>Flete</label> <span>S/ <?php echo number_format((float)$d['precio_flete'], 2); ?></span></div>
                
                <div class="exp-row">
                    <label>Cosecha</label> 
                    <span id="lbl_row_cosecha">S/ <?php echo number_format($sub_cosecha, 2); ?></span>
                </div>
                <div class="exp-row">
                    <label>Carga</label> 
                    <span id="lbl_row_carga">S/ <?php echo number_format($sub_cargadores, 2); ?></span>
                </div>
                <div class="exp-row">
                    <label>Inspección</label> 
                    <span id="lbl_row_inspeccion">S/ <?php echo number_format($sub_inspectores, 2); ?></span>
                </div>

                <div class="exp-row"><label>Viáticos</label> <span>S/ <?php echo number_format((float)$d['viaticos'], 2); ?></span></div>
                
                <div class="exp-row row-otros">
                    <label style="color:#0369a1; font-weight:700;">Otros Gastos</label> 
                    <input type="number" step="0.01" name="gastos_operativos" id="gastos_admin" value="<?php echo htmlspecialchars($d['gastos_operativos']); ?>" oninput="recalc()" placeholder="0.00">
                </div>
                
                <div class="exp-row" style="border-top:2px solid var(--border); font-weight:700; color:var(--text-main); margin-top:5px;">
                    <label>SUBTOTAL GASTOS</label> 
                    <span id="lbl_subtotal_gastos">S/ 0.00</span>
                </div>
            </div>
        </div>
    </div>

    <div class="master-footer">
        <div class="footer-grid">
            <div><span class="kpi-lbl">Kg Neto</span><div class="kpi-val" id="lbl_kg_total">0</div></div>
            <div><span class="kpi-lbl">A Pagar</span><div class="kpi-val" id="lbl_pago_prod">0</div></div>
            <div><span class="kpi-lbl">Inversión</span><div class="kpi-val" id="lbl_inversion">0</div></div>
            <div><span class="kpi-lbl" style="color:var(--accent);">Costo/Kg</span><div class="kpi-val gold" id="lbl_costo_kilo">0</div></div>
            <div>
                <button type="submit" class="btn-save-float">💾 Guardar Cierre</button>
            </div>
        </div>
    </div>
</form>

<script>
const count = <?php echo (int)count($proveedores); ?>;
const gastosBaseFijos = <?php echo (float)$gastos_base_fijos; ?>;

// Variables PHP pasadas a JS para cálculos unitarios
const valCosecha = <?php echo round($sub_cosecha, 2); ?>;
const valCarga = <?php echo round($sub_cargadores, 2); ?>;
const valInsp = <?php echo round($sub_inspectores, 2); ?>;

function openModal(src) { document.getElementById('modalImg').src = src; document.getElementById('imgModal').classList.add('open'); }
function closeModal() { document.getElementById('imgModal').classList.remove('open'); }

// ===== CLP =====
function toggleCLP() {
    const active = document.getElementById('check_clp_global').checked;
    document.getElementById('head_clp_activo').value = active ? '1' : '0';

    for (let i = 0; i < count; i++) {
        const box = document.getElementById('clp_box_' + i);
        if (box) box.style.display = active ? 'block' : 'none';
    }
    recalc();
}

function recalc() {
    let totalKgFisico = 0; 
    let totalNuevoPagoProd = 0; 

    // CLP total
    let totalCLP = 0;
    const clpActive = document.getElementById('check_clp_global') ? document.getElementById('check_clp_global').checked : false;

    for (let i = 0; i < count; i++) {
        // Data Base
        const k1 = parseFloat(document.querySelectorAll('.h-k1')[i].value) || 0;
        const p1 = parseFloat(document.querySelectorAll('.h-p1')[i].value) || 0;
        const k2 = parseFloat(document.querySelectorAll('.h-k2')[i].value) || 0;
        const p2 = parseFloat(document.querySelectorAll('.h-p2')[i].value) || 0;
        const kr = parseFloat(document.querySelectorAll('.h-kr')[i].value) || 0;
        const pr = parseFloat(document.querySelectorAll('.h-pr')[i].value) || 0;
        const kgTotalOrig = k1 + k2 + kr; 

        // Inputs
        const pct = parseFloat(document.getElementById('pct_' + i).value) || 0;
        const pMerma = parseFloat(document.getElementById('prm_' + i).value) || 0;

        const factorUtil = (100 - pct) / 100;

        // Cálculos
        const kgMermaTotal = kgTotalOrig * (pct / 100);
        const pagoMerma = kgMermaTotal * pMerma;

        let htmlBreakdown = "";
        let pagoNetaTotal = 0;
        let kgUtilTotal = 0; 

        const addRow = (label, color, kgOrig, price) => {
            let kgU = kgOrig * factorUtil; 
            let sub = kgU * price; 
            pagoNetaTotal += sub;
            kgUtilTotal += kgU;
            htmlBreakdown += `<div class="breakdown-item" style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:4px;">
                <span><span class="badge-cat" style="background:${color}">${label}</span> ${kgU.toFixed(2)} kg</span>
                <span>S/ ${sub.toFixed(2)}</span>
            </div>`;
        };

        if (k1 > 0) addRow("C1", "#15803d", k1, p1);
        if (k2 > 0) addRow("C2", "#f59e0b", k2, p2);
        if (kr > 0) addRow("RZ", "#ef4444", kr, pr);

        const nuevoPago = pagoNetaTotal + pagoMerma;

        // UI Updates
        document.getElementById('lbl_k_merma_' + i).innerText = kgMermaTotal.toFixed(2);
        document.getElementById('lbl_p_merma_' + i).innerText = pMerma.toFixed(2);
        document.getElementById('lbl_s_merma_' + i).innerText = "S/ " + pagoMerma.toFixed(2);
        
        document.getElementById('breakdown_' + i).innerHTML = htmlBreakdown;
        document.getElementById('res_pago_' + i).innerText = "S/ " + nuevoPago.toLocaleString('en-US', {minimumFractionDigits: 2});

        // HIDDEN INPUTS
        document.getElementById('inp_kg_merma_' + i).value = kgMermaTotal.toFixed(2);
        document.getElementById('inp_kg_util_' + i).value = kgUtilTotal.toFixed(2);
        document.getElementById('inp_pago_merma_' + i).value = pagoMerma.toFixed(2);
        document.getElementById('inp_pago_util_' + i).value = pagoNetaTotal.toFixed(2);
        document.getElementById('inp_pago_total_' + i).value = nuevoPago.toFixed(2);

        // ===== CLP por proveedor/categoría =====
        let subtotalCLP = 0;
        if (clpActive) {
            const clpInputs = document.querySelectorAll('.clp-in-' + i);
            const c1 = parseFloat(clpInputs[0]?.value) || 0;
            const c2 = parseFloat(clpInputs[1]?.value) || 0;
            const rz = parseFloat(clpInputs[2]?.value) || 0;
            subtotalCLP = (k1 * c1) + (k2 * c2) + (kr * rz);
        }

        const lblCLP = document.getElementById('lbl_clp_prov_' + i);
        if (lblCLP) lblCLP.innerText = "S/ " + subtotalCLP.toFixed(2);

        const hidCLP = document.getElementById('inp_clp_subtotal_' + i);
        if (hidCLP) hidCLP.value = subtotalCLP.toFixed(2);

        totalCLP += subtotalCLP;
        // ======================================

        totalKgFisico += kgTotalOrig; 
        totalNuevoPagoProd += nuevoPago; 
    }

    // GASTOS GENERALES
    const otrosGastos = parseFloat(document.getElementById('gastos_admin').value) || 0;
    const totalGastosOperativos = gastosBaseFijos + otrosGastos;

    // Actualizar Subtotal en la caja de gastos
    document.getElementById('lbl_subtotal_gastos').innerText = "S/ " + totalGastosOperativos.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    // ===== ACTUALIZAR COSTOS UNITARIOS POR KG EN GASTOS DE CAMPO =====
    // Evitar división entre cero
    const safeKg = totalKgFisico > 0 ? totalKgFisico : 1;
    
    let unitCosecha = (valCosecha / safeKg).toFixed(2);
    let unitCarga = (valCarga / safeKg).toFixed(2);
    let unitInsp = (valInsp / safeKg).toFixed(2);

    document.getElementById('lbl_row_cosecha').innerHTML = `S/ ${valCosecha.toFixed(2)} <span style='font-size:0.85em; color:#64748b; font-weight:400;'>(S/ ${unitCosecha} x kg)</span>`;
    document.getElementById('lbl_row_carga').innerHTML = `S/ ${valCarga.toFixed(2)} <span style='font-size:0.85em; color:#64748b; font-weight:400;'>(S/ ${unitCarga} x kg)</span>`;
    document.getElementById('lbl_row_inspeccion').innerHTML = `S/ ${valInsp.toFixed(2)} <span style='font-size:0.85em; color:#64748b; font-weight:400;'>(S/ ${unitInsp} x kg)</span>`;
    // ================================================================

    // CLP suma a inversión
    const inversionTotal = totalNuevoPagoProd + totalGastosOperativos + (clpActive ? totalCLP : 0);
    const costoFinalKilo = (totalKgFisico > 0) ? (inversionTotal / totalKgFisico) : 0;

    // Footer Updates
    document.getElementById('lbl_kg_total').innerText = totalKgFisico.toLocaleString('en-US', {maximumFractionDigits: 0});
    document.getElementById('lbl_pago_prod').innerText = "S/" + totalNuevoPagoProd.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    document.getElementById('lbl_inversion').innerText = "S/" + inversionTotal.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    document.getElementById('lbl_costo_kilo').innerText = "S/" + costoFinalKilo.toFixed(2);

    document.getElementById('head_kg_total').value = totalKgFisico.toFixed(2);
    document.getElementById('head_imp_total').value = totalNuevoPagoProd.toFixed(2);

    document.getElementById('head_clp_total').value = (clpActive ? totalCLP : 0).toFixed(2);
}

// Inicial
window.onload = () => { 
    toggleCLP();
    recalc(); 
};
</script>
</body>
</html>

