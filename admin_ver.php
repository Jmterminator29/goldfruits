<?php
require_once 'auth_admin.php';
require_once 'db_connect.php';

$id = $_GET['id'];

// 1. Datos de Cabecera
$stmt = $conn->prepare("SELECT * FROM acopios_cabecera WHERE id = ?");
$stmt->execute([$id]);
$d = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$d) die("Solicitud no encontrada.");

// 2. Datos Detallados por Proveedor + Liquidación Guardada (LEFT JOIN)
$stmtOri = $conn->prepare("
    SELECT ao.id as origen_id, p.nombre, ao.tara_asignada, 
           ao.k_cat1, ao.p_cat1, 
           ao.k_cat2, ao.p_cat2, 
           ao.k_rastrojo, ao.p_rastrojo, 
           ao.subtotal,
           /* Datos guardados previamente en la nueva tabla */
           al.porc_merma as liq_porc,
           al.precio_merma as liq_precio_m,
           /* Datos físicos de balanza */
           COALESCE((SELECT SUM(peso_bruto) FROM acopios_pesadas WHERE origen_id = ao.id), 0) as total_bruto,
           COALESCE((SELECT SUM(jabas) FROM acopios_pesadas WHERE origen_id = ao.id), 0) as total_jabas
    FROM acopios_origenes ao
    JOIN proveedores p ON ao.proveedor_id = p.id
    LEFT JOIN acopios_liquidaciones al ON al.origen_id = ao.id
    WHERE ao.acopio_id = ?
");
$stmtOri->execute([$id]);
$proveedores = $stmtOri->fetchAll(PDO::FETCH_ASSOC);

// 3. Fotos de Evidencia
$stmtPes = $conn->prepare("SELECT * FROM acopios_pesadas WHERE acopio_id = ? ORDER BY numero_tanda ASC");
$stmtPes->execute([$id]);
$todas_pesadas = $stmtPes->fetchAll(PDO::FETCH_ASSOC);

$pesadas_por_origen = [];
foreach ($todas_pesadas as $p) {
    if ($p['origen_id']) $pesadas_por_origen[$p['origen_id']][] = $p;
}

// 4. Gastos Operativos de Campo (Fijos)
$gastos_fijos = $d['precio_flete'] + $d['subtotal_cosecha'] + $d['subtotal_cargadores'] + $d['subtotal_inspectores'] + $d['viaticos'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liquidación Gerencial | <?php echo $d['codigo_unico']; ?></title>
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
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); color: var(--text-main); margin: 0; padding-bottom: 140px; font-size: 14px; }

        .top-bar { background: var(--surface); border-bottom: 1px solid var(--border); padding: 15px 30px; position: sticky; top: 0; z-index: 50; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .brand { font-weight: 800; font-size: 1.2rem; color: var(--primary-dark); display: flex; align-items: center; gap: 10px; }
        .brand span { color: var(--text-muted); font-weight: 400; font-size: 0.9rem; }
        .btn-back { text-decoration: none; color: var(--text-muted); font-weight: 600; padding: 8px 16px; border-radius: 8px; transition: all 0.2s; background: #f1f5f9; }
        .btn-back:hover { background: #e2e8f0; color: var(--text-main); }

        .container { max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        .section-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); font-weight: 700; margin-bottom: 15px; display: block; }

        /* CARDS */
        .prov-card { background: var(--surface); border-radius: 16px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.03), 0 4px 6px -2px rgba(0,0,0,0.02); border: 1px solid var(--border); margin-bottom: 40px; overflow: hidden; }
        .prov-header { padding: 20px 25px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: linear-gradient(to right, #ffffff, #f8fafc); }
        .prov-name { font-size: 1.1rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
        .prov-badge { background: #dbeafe; color: #1e40af; font-size: 0.7rem; padding: 4px 10px; border-radius: 20px; font-weight: 600; }

        .data-grid { display: grid; grid-template-columns: 2fr 3fr; border-bottom: 1px solid var(--border); }
        .col-phys { padding: 25px; border-right: 1px solid var(--border); }
        
        /* TABLA IZQ */
        .stat-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.9rem; }
        .stat-label { color: var(--text-muted); }
        .stat-val { font-weight: 600; }
        .stat-val.bruto { color: var(--text-main); }
        .stat-val.tara { color: var(--danger); }
        .stat-val.neto { color: var(--primary); font-size: 1.1rem; font-weight: 800; }
        .cat-list { margin-top: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid var(--border); }
        .cat-item { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.85rem; }
        .badge-cat { padding: 2px 6px; border-radius: 4px; color: white; font-weight: 700; font-size: 0.7rem; margin-right: 5px; }

        /* COLUMNA DER */
        .col-fin { padding: 25px; background: #fffbeb; }
        .sim-header { font-size: 0.85rem; font-weight: 700; color: #b45309; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .sim-controls { display: flex; gap: 15px; margin-bottom: 20px; }
        .input-wrap { flex: 1; }
        .input-wrap label { display: block; font-size: 0.7rem; font-weight: 700; margin-bottom: 5px; color: #92400e; }
        .input-wrap input { width: 100%; padding: 10px; border: 1px solid #fcd34d; border-radius: 8px; font-weight: 700; color: #78350f; text-align: center; font-size: 1rem; outline: none; background: white; transition: 0.2s; }
        .input-wrap input:focus { box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); border-color: var(--accent); }

        .fin-result { background: white; padding: 20px; border-radius: 12px; border: 1px dashed #fcd34d; }
        .breakdown-list { margin: 10px 0; font-size: 0.85rem; border-left: 3px solid #e2e8f0; padding-left: 15px; }
        .breakdown-item { display: flex; justify-content: space-between; margin-bottom: 6px; color: #475569; align-items: center; }
        
        .res-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 0.9rem; }
        .res-row.merma { color: var(--danger); font-size: 0.85rem; padding-bottom: 8px; border-bottom: 1px dashed #e2e8f0; margin-bottom: 12px; }
        .res-row.final { margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 1.2rem; }
        .money { font-family: 'Consolas', monospace; font-weight: 700; }

        /* GALERIA FOTOS */
        .gallery-strip { padding: 15px 25px; background: #f8fafc; display: flex; gap: 12px; overflow-x: auto; white-space: nowrap; scrollbar-width: thin; }
        .img-card { display: inline-block; position: relative; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); width: 120px; flex-shrink: 0; transition: transform 0.2s; cursor: zoom-in; }
        .img-card:hover { transform: translateY(-3px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .img-card img { width: 100%; height: 80px; object-fit: cover; display: block; }
        .img-meta { font-size: 0.65rem; background: rgba(255,255,255,0.9); padding: 4px; text-align: center; font-weight: 600; color: var(--text-muted); }

        /* MODAL */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 2000; display: none; justify-content: center; align-items: center; }
        .modal-overlay.open { display: flex; }
        .modal-content { max-width: 90%; max-height: 90vh; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.5); }

        /* GASTOS */
        .expenses-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 40px; }
        .expense-box { background: var(--surface); padding: 25px; border-radius: 16px; border: 1px solid var(--border); }
        .exp-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed var(--border); font-size: 0.9rem; }
        .exp-row:last-child { border-bottom: none; }
        .admin-exp input { width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1.2rem; font-weight: 700; color: var(--primary-dark); margin-top: 10px; outline: none; transition: 0.2s; }
        .admin-exp input:focus { border-color: var(--primary); }

        /* FOOTER FLOTANTE */
        .master-footer { position: fixed; bottom: 0; left: 0; width: 100%; background: #0f172a; color: white; padding: 15px 0; box-shadow: 0 -10px 30px rgba(0,0,0,0.15); z-index: 100; backdrop-filter: blur(10px); }
        .footer-grid { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; align-items: center; }
        .kpi-item { text-align: center; border-right: 1px solid rgba(255,255,255,0.1); }
        .kpi-item:last-child { border-right: none; }
        .kpi-label { display: block; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 4px; }
        .kpi-value { font-size: 1.4rem; font-weight: 800; color: white; }
        .kpi-value.gold { color: var(--accent); font-size: 1.8rem; text-shadow: 0 2px 10px rgba(245, 158, 11, 0.2); }
        .btn-save-float { background: var(--accent); color: #0f172a; border: none; padding: 10px 25px; border-radius: 50px; font-weight: 800; cursor: pointer; transition: transform 0.2s; font-size: 0.9rem; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); }
        .btn-save-float:hover { transform: scale(1.05); background: #fbbf24; }

        @media(max-width: 900px) { .data-grid, .expenses-wrapper, .footer-grid { grid-template-columns: 1fr; } .col-phys { border-right: none; border-bottom: 1px solid var(--border); } .kpi-item { border-right: none; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; display: flex; justify-content: space-between; align-items: center; } .footer-grid { gap: 10px; } }
    </style>
</head>
<body>

<div class="modal-overlay" id="imgModal" onclick="closeModal()">
    <img src="" class="modal-content" id="modalImg" onclick="event.stopPropagation()">
</div>

<div class="top-bar">
    <div class="brand">GoldFruits <span>| Liquidación #<?php echo $d['codigo_unico']; ?></span></div>
    <a href="admin_panel.php" class="btn-back">← Volver</a>
</div>

<form action="admin_guardar_cierre.php" method="POST" id="formCierre">
    <input type="hidden" name="id" value="<?php echo $id; ?>">
    <input type="hidden" name="total_kilos_neto" id="head_kg_total">
    <input type="hidden" name="importe_total_fruta" id="head_imp_total">

    <div class="container">
        <span class="section-label">1. Detalle por Proveedor y Ajuste de Calidad</span>

        <?php 
        foreach($proveedores as $idx => $p): 
            $origen_id = $p['origen_id'];
            $mis_fotos = $pesadas_por_origen[$origen_id] ?? [];
            
            $peso_neto_campo = $p['k_cat1'] + $p['k_cat2'] + $p['k_rastrojo'];
            $subtotal_campo = $p['subtotal'];
            
            // Valores recuperados si existen
            $val_pct = $p['liq_porc'] ?? 0;
            $val_prc = $p['liq_precio_m'] ?? 0;
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

            <div class="prov-header">
                <div class="prov-name"><span style="font-size:1.5rem;">👤</span> <?php echo $p['nombre']; ?></div>
                <div class="prov-badge">Tara: <?php echo $p['tara_asignada']; ?> kg</div>
            </div>

            <div class="data-grid">
                <div class="col-phys">
                    <div class="stat-row"><span class="stat-label">Total Jabas</span> <span class="stat-val"><?php echo $p['total_jabas']; ?> un.</span></div>
                    <div class="stat-row"><span class="stat-label">Peso Bruto</span> <span class="stat-val bruto"><?php echo number_format($p['total_bruto'], 2); ?> kg</span></div>
                    <div class="stat-row"><span class="stat-label">Descuento Tara</span> <span class="stat-val tara">- <?php echo number_format($p['total_jabas'] * $p['tara_asignada'], 2); ?> kg</span></div>
                    <div class="stat-row" style="margin-top:10px; border-top:1px dashed var(--border); padding-top:10px;">
                        <span class="stat-label" style="font-weight:700;">PESO NETO (CAMPO)</span>
                        <span class="stat-val neto"><?php echo number_format($peso_neto_campo, 2); ?> kg</span>
                    </div>

                    <div class="cat-list">
                        <div style="font-size:0.7rem; font-weight:700; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Composición Registrada</div>
                        <?php if($p['k_cat1']>0): ?><div class="cat-item"><span><span class="badge-cat" style="background:#15803d">C1</span> Grande</span> <strong><?php echo $p['k_cat1']; ?> kg</strong></div><?php endif; ?>
                        <?php if($p['k_cat2']>0): ?><div class="cat-item"><span><span class="badge-cat" style="background:#f59e0b">C2</span> Chico</span> <strong><?php echo $p['k_cat2']; ?> kg</strong></div><?php endif; ?>
                        <?php if($p['k_rastrojo']>0): ?><div class="cat-item"><span><span class="badge-cat" style="background:#ef4444">RZ</span> Rastrojo</span> <strong><?php echo $p['k_rastrojo']; ?> kg</strong></div><?php endif; ?>
                    </div>
                </div>

                <div class="col-fin">
                    <div class="sim-header">⚡ Ajuste de Liquidación</div>
                    <div class="sim-controls">
                        <div class="input-wrap">
                            <label>% MERMA</label>
                            <input type="number" step="0.1" name="liq[<?php echo $origen_id; ?>][porc_merma]" id="pct_<?php echo $idx; ?>" value="<?php echo $val_pct; ?>" oninput="recalc()">
                        </div>
                        <div class="input-wrap">
                            <label>PRECIO MERMA (S/)</label>
                            <input type="number" step="0.01" name="liq[<?php echo $origen_id; ?>][precio_merma]" id="prm_<?php echo $idx; ?>" value="<?php echo $val_prc; ?>" oninput="recalc()">
                        </div>
                    </div>

                    <div class="fin-result">
                        <div class="res-row merma">
                            <span>Pago Merma:</span>
                            <span><span id="lbl_k_merma_<?php echo $idx; ?>">0</span> kg x S/ <span id="lbl_p_merma_<?php echo $idx; ?>">0.00</span> = <strong id="lbl_s_merma_<?php echo $idx; ?>">S/ 0.00</strong></span>
                        </div>

                        <div style="font-size:0.8rem; font-weight:700; color:#0f172a; margin-bottom:5px;">Pago Fruta Neta (Útil):</div>
                        <div class="breakdown-list" id="breakdown_<?php echo $idx; ?>">
                            </div>

                        <div class="res-row final">
                            <span style="color:var(--primary-dark); font-weight:800;">TOTAL A PAGAR</span>
                            <strong class="money" style="color:var(--primary); font-size:1.3rem;" id="res_pago_<?php echo $idx; ?>">S/ 0.00</strong>
                        </div>
                    </div>
                    
                    <input type="hidden" class="h-k1" value="<?php echo $p['k_cat1']; ?>">
                    <input type="hidden" class="h-p1" value="<?php echo $p['p_cat1']; ?>">
                    <input type="hidden" class="h-k2" value="<?php echo $p['k_cat2']; ?>">
                    <input type="hidden" class="h-p2" value="<?php echo $p['p_cat2']; ?>">
                    <input type="hidden" class="h-kr" value="<?php echo $p['k_rastrojo']; ?>">
                    <input type="hidden" class="h-pr" value="<?php echo $p['p_rastrojo']; ?>">
                </div>
            </div>

            <?php if(!empty($mis_fotos)): ?>
            <div class="gallery-strip">
                <?php foreach($mis_fotos as $f): ?>
                <div class="img-card" onclick="openModal('<?php echo $f['foto_url']; ?>')">
                    <img src="<?php echo $f['foto_url']; ?>">
                    <div style="font-size:0.65rem; color:#666; margin-top:3px;">#<?php echo $f['numero_tanda']; ?> | <?php echo $f['peso']; ?>kg</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="expenses-wrapper">
            <div class="expense-box">
                <div style="font-weight:700; color:var(--text-main); margin-bottom:15px;">Gastos Fijos (Campo)</div>
                <div class="exp-row"><label>Flete</label> <span>S/ <?php echo number_format($d['precio_flete'], 2); ?></span></div>
                <div class="exp-row"><label>Cosecha</label> <span>S/ <?php echo number_format($d['subtotal_cosecha'], 2); ?></span></div>
                <div class="exp-row"><label>Carga</label> <span>S/ <?php echo number_format($d['subtotal_cargadores'], 2); ?></span></div>
                <div class="exp-row"><label>Inspección</label> <span>S/ <?php echo number_format($d['subtotal_inspectores'], 2); ?></span></div>
                <div class="exp-row"><label>Viáticos</label> <span>S/ <?php echo number_format($d['viaticos'], 2); ?></span></div>
                <div class="exp-row" style="border-top:2px solid var(--border); font-weight:700;"><label>TOTAL</label> <span>S/ <?php echo number_format($gastos_fijos, 2); ?></span></div>
            </div>
            <div class="expense-box admin-exp" style="border-color:#bae6fd; background:#f0f9ff;">
                <div style="font-weight:700; color:#0369a1;">Gastos Administrativos / Extras</div>
                <p style="font-size:0.8rem; color:#0c4a6e; margin-bottom:10px;">Peajes, alimentos extra, imprevistos.</p>
                <input type="number" step="0.01" name="gastos_operativos" id="gastos_admin" value="<?php echo $d['gastos_operativos']; ?>" oninput="recalc()">
            </div>
        </div>
    </div>

    <div class="master-footer">
        <div class="footer-grid">
            <div><span class="kpi-lbl">Kg Útiles Venta</span><div class="kpi-val" id="lbl_kg_util">0.00</div></div>
            <div><span class="kpi-lbl">Total Pagar</span><div class="kpi-val" id="lbl_pago_prod">S/ 0.00</div></div>
            <div><span class="kpi-lbl">Inversión Total</span><div class="kpi-val" id="lbl_inversion">S/ 0.00</div></div>
            <div style="border:none;"><span class="kpi-lbl" style="color:var(--accent);">COSTO REAL / KG</span><div class="kpi-val gold" id="lbl_costo_kilo">S/ 0.00</div></div>
            <div style="text-align:right;">
                <button type="submit" class="btn-save-float">💾 GUARDAR</button>
            </div>
        </div>
    </div>
</form>

<script>
const count = <?php echo count($proveedores); ?>;
const gastosCampo = <?php echo $gastos_fijos; ?>;

function openModal(src) { document.getElementById('modalImg').src = src; document.getElementById('imgModal').classList.add('open'); }
function closeModal() { document.getElementById('imgModal').classList.remove('open'); }

function recalc() {
    let totalKgUtil = 0;
    let totalNuevoPagoProd = 0;

    for (let i = 0; i < count; i++) {
        // Base Data
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

        // Calcs
        const factorUtil = (100 - pct) / 100;
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
            htmlBreakdown += `<div class="breakdown-item">
                <span><span class="badge-cat" style="background:${color}">${label}</span> ${kgU.toFixed(2)} kg</span>
                <span>x S/ ${price.toFixed(2)} = <strong>S/ ${sub.toFixed(2)}</strong></span>
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

        // HIDDEN INPUTS UPDATE (Para enviar al backend)
        document.getElementById('inp_kg_merma_' + i).value = kgMermaTotal.toFixed(2);
        document.getElementById('inp_kg_util_' + i).value = kgUtilTotal.toFixed(2);
        document.getElementById('inp_pago_merma_' + i).value = pagoMerma.toFixed(2);
        document.getElementById('inp_pago_util_' + i).value = pagoNetaTotal.toFixed(2);
        document.getElementById('inp_pago_total_' + i).value = nuevoPago.toFixed(2);

        // Accumulate Global
        totalKgUtil += kgUtilTotal;
        totalNuevoPagoProd += nuevoPago;
    }

    const gastosAdmin = parseFloat(document.getElementById('gastos_admin').value) || 0;
    const inversionTotal = totalNuevoPagoProd + gastosCampo + gastosAdmin;
    const costoFinalKilo = (totalKgUtil > 0) ? (inversionTotal / totalKgUtil) : 0;

    // Footer Updates
    document.getElementById('lbl_kg_util').innerText = totalKgUtil.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('lbl_pago_prod').innerText = "S/ " + totalNuevoPagoProd.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('lbl_inversion').innerText = "S/ " + inversionTotal.toLocaleString('en-US', {minimumFractionDigits: 2});
    document.getElementById('lbl_costo_kilo').innerText = "S/ " + costoFinalKilo.toFixed(3);

    // Global Hidden Inputs Update
    document.getElementById('head_kg_total').value = totalKgUtil.toFixed(2);
    document.getElementById('head_imp_total').value = totalNuevoPagoProd.toFixed(2);
}

window.onload = recalc;
</script>
</body>
</html>