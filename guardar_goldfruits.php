<?php
// guardar_goldfruits.php
// FIX v2 (DB real acopio.sql):
// - Inserta/actualiza PROVEEDORES (tabla proveedores)
// - Inserta ORÍGENES (tabla acopios_origenes) con proveedor_id
// - Inserta PESADAS con origen_id (match real) + origen_referencia + categoria
// - Calcula y guarda totales (kg/jabas/categorías)

header("Access-Control-Allow-Origin: *");
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Error: Sesión no iniciada.");
}

function gf_num($v) {
    if ($v === null) return 0;
    if (is_string($v)) {
        $v = str_replace(['S/', 's/', ' ', ','], ['', '', '', ''], $v);
    }
    return is_numeric($v) ? (float)$v : 0.0;
}

function gf_norm($s) {
    $s = (string)$s;
    $s = trim(mb_strtolower($s, 'UTF-8'));
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
}


function gf_has_column(PDO $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $stmt = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $stmt->execute([$table, $column]);
        $cache[$key] = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

function upsertProveedor(PDO $conn, string $nombre, string $cuenta = ''): int {
    $nombre = trim($nombre);
    if ($nombre === '') return 0;

    $stmtSel = $conn->prepare("SELECT id, cuenta_bancaria FROM proveedores WHERE nombre = ? LIMIT 1");
    $stmtSel->execute([$nombre]);
    $row = $stmtSel->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['id'])) {
        $id = (int)$row['id'];
        if ($cuenta !== '' && (string)$row['cuenta_bancaria'] !== $cuenta) {
            $stmtUpd = $conn->prepare("UPDATE proveedores SET cuenta_bancaria = ? WHERE id = ?");
            $stmtUpd->execute([$cuenta, $id]);
        }
        return $id;
    }

    $stmtIns = $conn->prepare("INSERT INTO proveedores (nombre, cuenta_bancaria, activo, created_at) VALUES (?, ?, 1, NOW())");
    $stmtIns->execute([$nombre, $cuenta !== '' ? $cuenta : null]);
    return (int)$conn->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido";
    exit;
}

try {
    // OJO: acopios_cabecera y acopios_pesadas son MyISAM (sin transacciones).
    // Aun así usamos transacción para InnoDB (proveedores/acopios_origenes) para minimizar inconsistencias.
    if (!$conn->inTransaction()) $conn->beginTransaction();

    $uid = (int)$_SESSION['user_id'];

    // Orígenes (desde frontend)
    $origenes_json = $_POST['origenes_json'] ?? '[]';
    $origenes_array = json_decode($origenes_json, true);
    if (!is_array($origenes_array)) $origenes_array = [];
    // Proveedor (texto para listados): lista profesional con comas (sin "grupo", sin "y otros").
    $proveedor_lista = [];
    foreach ($origenes_array as $ori) {
        $n = trim((string)($ori['proveedor'] ?? ''));
        if ($n !== '') $proveedor_lista[] = $n;
    }
    $proveedor_lista = array_values(array_unique($proveedor_lista));
    $proveedor_principal = !empty($proveedor_lista) ? implode(', ', $proveedor_lista) : "Desconocido";
// Detalle pesadas
    $detalles = json_decode($_POST['detalle_pesadas_json'] ?? '[]', true);
    if (!is_array($detalles)) $detalles = [];

    // Totales
    $total_jabas = 0;
    $total_peso  = 0.0;
    $total_cat1 = 0.0;
    $total_cat2 = 0.0;
    $total_rastrojo = 0.0;

    foreach ($detalles as $d) {
        $j = (int)gf_num($d['jabas'] ?? 0);
        $p = gf_num($d['peso'] ?? 0);
        $cat = (string)($d['categoria'] ?? 'cat1');

        $total_jabas += $j;
        $total_peso  += $p;

        if ($cat === 'cat1') $total_cat1 += $p;
        elseif ($cat === 'cat2') $total_cat2 += $p;
        elseif ($cat === 'rastrojo') $total_rastrojo += $p;
        else $total_cat1 += $p;
    }

    // Precios
    $precio_cat1 = gf_num($_POST['precio_cat1'] ?? 0);
    $precio_cat2 = gf_num($_POST['precio_cat2'] ?? 0);
    $precio_rastrojo = gf_num($_POST['precio_rastrojo'] ?? 0);

    $importe_total = gf_num($_POST['total_pagar_texto'] ?? 0);
    if ($importe_total <= 0) {
        $importe_total = ($total_cat1 * $precio_cat1) + ($total_cat2 * $precio_cat2) + ($total_rastrojo * $precio_rastrojo);
    }

    $precio_x_kg = ($total_peso > 0) ? ($importe_total / $total_peso) : 0.0;

    
    // Insert cabecera (compatibilidad: si existe proveedor_comercial en la BD, lo seteamos NULL; si no existe, lo omitimos)
    $has_prov_comercial = gf_has_column($conn, 'acopios_cabecera', 'proveedor_comercial');

    $cols = [
        'usuario_id','codigo_unico','proveedor','origenes_detalle','cuenta_bancaria',
        'conductor','placa','precio_flete','adelanto_flete',
        'total_jabas','total_peso_bruto','total_kilos_neto',
        'precio_x_kg','importe_total_fruta',
        'total_cat1','precio_cat1',
        'total_cat2','precio_cat2',
        'total_rastrojo','precio_rastrojo',
        'cosecha_personas','cosecha_dias','cosecha_precio','subtotal_cosecha',
        'cargadores_personas','cargadores_dias','cargadores_precio','subtotal_cargadores',
        'inspectores_personas','inspectores_dias','inspectores_precio','subtotal_inspectores',
        'viaticos','gastos_operativos','latitud','longitud'
    ];
    if ($has_prov_comercial) {
        // Ya no se usa "nombre comercial", se guarda NULL para mantener compatibilidad si la columna existe.
        array_splice($cols, 3, 0, ['proveedor_comercial']); // después de proveedor
    }

    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $sql = "INSERT INTO acopios_cabecera (" . implode(',', $cols) . ") VALUES (" . $placeholders . ")";
    $stmt = $conn->prepare($sql);

    $params = [
        $uid,
        $_POST['codigo_unico'] ?? ('GF-' . time()),
        $proveedor_principal,
    ];
    if ($has_prov_comercial) $params[] = null; // proveedor_comercial

    $params = array_merge($params, [
        $origenes_json,
        $_POST['cuenta'] ?? '',
        $_POST['conductor_nombre'] ?? '',
        $_POST['vehiculo_placa'] ?? '',
        gf_num($_POST['flete'] ?? 0),
        gf_num($_POST['adelanto_flete'] ?? 0),

        $total_jabas,
        $total_peso,
        $total_peso,
        $precio_x_kg,
        $importe_total,

        $total_cat1, $precio_cat1,
        $total_cat2, $precio_cat2,
        $total_rastrojo, $precio_rastrojo,

        (int)gf_num($_POST['cosecha_personas'] ?? 0), (int)gf_num($_POST['cosecha_dias'] ?? 0), gf_num($_POST['cosecha_precio'] ?? 0), gf_num($_POST['sub_cosecha'] ?? 0),
        (int)gf_num($_POST['cargadores_personas'] ?? 0), (int)gf_num($_POST['cargadores_dias'] ?? 0), gf_num($_POST['cargadores_precio'] ?? 0), gf_num($_POST['sub_cargadores'] ?? 0),
        (int)gf_num($_POST['inspectores_personas'] ?? 0), (int)gf_num($_POST['inspectores_dias'] ?? 0), gf_num($_POST['inspectores_precio'] ?? 0), gf_num($_POST['sub_inspectores'] ?? 0),

        gf_num($_POST['viaticos'] ?? 0),
        gf_num($_POST['operativos'] ?? 0),
        $_POST['latitud'] ?? '',
        $_POST['longitud'] ?? ''
    ]);

    $stmt->execute($params);

    $acopio_id = (int)$conn->lastInsertId();
$acopio_id = (int)$conn->lastInsertId();

    // ---------------------------------------------------------------------
    // PROVEEDORES + ORÍGENES (MATCH)
    // ---------------------------------------------------------------------
    $stmtInsOrigen = $conn->prepare("INSERT INTO acopios_origenes (acopio_id, proveedor_id, campo) VALUES (?, ?, ?)");
    $mapOrigenNormToId = []; // "proveedor - campo" normalizado => origen_id

    foreach ($origenes_array as $ori) {
        $p_nombre = trim((string)($ori['proveedor'] ?? ''));
        $p_campo  = trim((string)($ori['campo'] ?? ''));
        $p_cuenta = trim((string)($ori['cuenta'] ?? ($_POST['cuenta'] ?? '')));

        if ($p_nombre === '') continue;

        $prov_id = upsertProveedor($conn, $p_nombre, $p_cuenta);

        $stmtInsOrigen->execute([$acopio_id, $prov_id > 0 ? $prov_id : null, ($p_campo !== '' ? $p_campo : null)]);
        $origen_id = (int)$conn->lastInsertId();

        $label = $p_nombre . (($p_campo !== '') ? (' - ' . $p_campo) : '');
        $mapOrigenNormToId[gf_norm($label)] = $origen_id;
        // También mapear solo proveedor, por si el frontend no manda campo
        $mapOrigenNormToId[gf_norm($p_nombre)] = $origen_id;
    }

    // ---------------------------------------------------------------------
    // PESADAS
    // ---------------------------------------------------------------------
    $carpeta = "fotos_acopio/";
    if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);

    // Nota: en BD existe origen_id
    $stmtDet = $conn->prepare("
        INSERT INTO acopios_pesadas (acopio_id, origen_id, numero_tanda, jabas, peso, foto_url, origen_referencia, categoria)
        VALUES (?,?,?,?,?,?,?,?)
    ");

    foreach ($detalles as $i => $d) {
        $foto = "";
        if (isset($_FILES['fotos_pesadas']['name'][$i])) {
            $ext = pathinfo($_FILES['fotos_pesadas']['name'][$i], PATHINFO_EXTENSION);
            $name = "UID{$uid}_ID{$acopio_id}_T{$i}_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['fotos_pesadas']['tmp_name'][$i], $carpeta . $name)) {
                $foto = $carpeta . $name;
            }
        }

        $cat = (string)($d['categoria'] ?? 'cat1');
        $origen_ref = trim((string)($d['origen'] ?? ''));
        $origen_id = null;

        if ($origen_ref !== '') {
            $k = gf_norm($origen_ref);
            if (isset($mapOrigenNormToId[$k])) $origen_id = (int)$mapOrigenNormToId[$k];
            else {
                // Intento: si viene "Proveedor (Campo)" o "Proveedor - Campo"
                $k2 = gf_norm(str_replace(['(',')'], ['',''], $origen_ref));
                if (isset($mapOrigenNormToId[$k2])) $origen_id = (int)$mapOrigenNormToId[$k2];
            }
        }

        $stmtDet->execute([
            $acopio_id,
            $origen_id,
            $i + 1,
            (int)gf_num($d['jabas'] ?? 0),
            gf_num($d['peso'] ?? 0),
            $foto,
            $origen_ref,
            $cat
        ]);
    }

    // Commit InnoDB (si hay). MyISAM queda igual, pero al menos no dejamos proveedores/orígenes a medias.
    if ($conn->inTransaction()) $conn->commit();

    echo "✅ Registro Exitoso con Liquidación";

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
?>