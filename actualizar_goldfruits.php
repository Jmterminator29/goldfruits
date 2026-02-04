<?php
// actualizar_goldfruits.php
// FIX v3:
// - Corregido: Detecta correctamente 'es_nueva_fila' para subir fotos nuevas.
// - Compatible con acopio.sql
// - UPSERT en proveedores y manejo de acopios_origenes / acopios_pesadas

header("Access-Control-Allow-Origin: *");
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
    die("Método no permitido");
}

try {
    if (!$conn->inTransaction()) $conn->beginTransaction();

    $uid = (int)$_SESSION['user_id'];
    $id  = (int)($_POST['id_acopio'] ?? 0);
    if ($id <= 0) throw new Exception("ID inválido");

    // Permisos
    $chk = $conn->prepare("SELECT usuario_id FROM acopios_cabecera WHERE id = ?");
    $chk->execute([$id]);
    $owner = $chk->fetchColumn();
    if (!$owner || (int)$owner !== $uid) {
        throw new Exception("No tienes permiso para editar este registro.");
    }

    // Orígenes
    $origenes_json = $_POST['origenes_json'] ?? '[]';
    $origenes_array = json_decode($origenes_json, true);
    if (!is_array($origenes_array)) $origenes_array = [];

    // Proveedores (texto para cabecera): nombres con comas
    $proveedores_nombres = [];
    if (!empty($origenes_array) && is_array($origenes_array)) {
        foreach ($origenes_array as $o) {
            $n = trim((string)($o['proveedor'] ?? ''));
            if ($n !== '') $proveedores_nombres[] = $n;
        }
    }
    $proveedores_nombres = array_values(array_unique($proveedores_nombres));
    $proveedor_texto = '';
    if (!empty($proveedores_nombres)) {
        $proveedor_texto = implode(', ', $proveedores_nombres);
    } elseif (!empty($_POST['proveedor'])) {
        $proveedor_texto = trim((string)$_POST['proveedor']);
    } else {
        $proveedor_texto = 'Desconocido';
    }

    // Para compatibilidad interna: "principal" = el primero
    $proveedor_principal = $proveedores_nombres[0] ?? $proveedor_texto;

    // Pesadas
    $detalles = json_decode($_POST['detalle_pesadas_json'] ?? '[]', true);
    if (!is_array($detalles)) $detalles = [];

    // Totales
    $total_jabas = 0; $total_peso = 0.0;
    $total_cat1 = 0.0; $total_cat2 = 0.0; $total_rastrojo = 0.0;

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

    // Precios + importes
    $precio_cat1 = gf_num($_POST['precio_cat1'] ?? 0);
    $precio_cat2 = gf_num($_POST['precio_cat2'] ?? 0);
    $precio_rastrojo = gf_num($_POST['precio_rastrojo'] ?? 0);

    $importe_total = gf_num($_POST['total_pagar_texto'] ?? 0);
    if ($importe_total <= 0) {
        $importe_total = ($total_cat1 * $precio_cat1) + ($total_cat2 * $precio_cat2) + ($total_rastrojo * $precio_rastrojo);
    }
    $precio_x_kg = ($total_peso > 0) ? ($importe_total / $total_peso) : 0.0;

    // 1) Actualizar cabecera
    $sqlUp = "UPDATE acopios_cabecera SET
        proveedor = ?,
        origenes_detalle = ?,
        cuenta_bancaria = ?,
        conductor = ?,
        placa = ?,
        precio_flete = ?,
        adelanto_flete = ?,

        total_jabas = ?,
        total_peso_bruto = ?,
        total_kilos_neto = ?,
        precio_x_kg = ?,
        importe_total_fruta = ?,

        total_cat1 = ?, precio_cat1 = ?,
        total_cat2 = ?, precio_cat2 = ?,
        total_rastrojo = ?, precio_rastrojo = ?,

        cosecha_personas = ?, cosecha_dias = ?, cosecha_precio = ?, subtotal_cosecha = ?,
        cargadores_personas = ?, cargadores_dias = ?, cargadores_precio = ?, subtotal_cargadores = ?,
        inspectores_personas = ?, inspectores_dias = ?, inspectores_precio = ?, subtotal_inspectores = ?,
        viaticos = ?, gastos_operativos = ?, latitud = ?, longitud = ?
    WHERE id = ? AND usuario_id = ?";

    $stmtUp = $conn->prepare($sqlUp);
    $stmtUp->execute([
        $proveedor_principal,
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
        $_POST['longitud'] ?? '',
        $id,
        $uid
    ]);

    // 2) Proveedores + Orígenes: reemplazamos los del acopio
    $conn->prepare("DELETE FROM acopios_origenes WHERE acopio_id = ?")->execute([$id]);

    $stmtInsOrigen = $conn->prepare("INSERT INTO acopios_origenes (acopio_id, proveedor_id, campo) VALUES (?, ?, ?)");
    $mapOrigenNormToId = [];

    foreach ($origenes_array as $ori) {
        $p_nombre = trim((string)($ori['proveedor'] ?? ''));
        $p_campo  = trim((string)($ori['campo'] ?? ''));
        $p_cuenta = trim((string)($ori['cuenta'] ?? ($_POST['cuenta'] ?? '')));

        if ($p_nombre === '') continue;

        $prov_id = upsertProveedor($conn, $p_nombre, $p_cuenta);

        $stmtInsOrigen->execute([$id, $prov_id > 0 ? $prov_id : null, ($p_campo !== '' ? $p_campo : null)]);
        $origen_id = (int)$conn->lastInsertId();

        $label = $p_nombre . (($p_campo !== '') ? (' - ' . $p_campo) : '');
        $mapOrigenNormToId[gf_norm($label)] = $origen_id;
        $mapOrigenNormToId[gf_norm($p_nombre)] = $origen_id;
    }

    // 3) Pesadas: reemplazamos todas por lo enviado
    $conn->prepare("DELETE FROM acopios_pesadas WHERE acopio_id = ?")->execute([$id]);

    $carpeta = "fotos_acopio/";
    if (!is_dir($carpeta)) mkdir($carpeta, 0755, true);

    $stmtDet = $conn->prepare("
        INSERT INTO acopios_pesadas (acopio_id, origen_id, numero_tanda, jabas, peso, foto_url, origen_referencia, categoria)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($detalles as $index => $item) {
        $tanda = $index + 1;

        // Foto existente (si no hay nueva)
        $ruta_final = (string)($item['foto_url'] ?? '');

        // --- CORRECCIÓN AQUÍ: Usamos 'es_nueva_fila' ---
        if (!empty($item['es_nueva_fila'])) {
            $keyPost = 'foto_file_' . $index;
            if (isset($_FILES[$keyPost]) && $_FILES[$keyPost]['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES[$keyPost]['name'], PATHINFO_EXTENSION);
                $nombre_final = "ID{$id}_TANDA{$tanda}_" . time() . "." . $ext;
                $destino = $carpeta . $nombre_final;
                if (move_uploaded_file($_FILES[$keyPost]['tmp_name'], $destino)) {
                    $ruta_final = $destino;
                }
            }
        }

        $cat = (string)($item['categoria'] ?? 'cat1');
        $origen_ref = trim((string)($item['origen'] ?? ''));
        $origen_id = null;

        if ($origen_ref !== '') {
            $k = gf_norm($origen_ref);
            if (isset($mapOrigenNormToId[$k])) $origen_id = (int)$mapOrigenNormToId[$k];
            else {
                $k2 = gf_norm(str_replace(['(',')'], ['',''], $origen_ref));
                if (isset($mapOrigenNormToId[$k2])) $origen_id = (int)$mapOrigenNormToId[$k2];
            }
        }

        $stmtDet->execute([
            $id,
            $origen_id,
            $tanda,
            (int)gf_num($item['jabas'] ?? 0),
            gf_num($item['peso'] ?? 0),
            $ruta_final,
            $origen_ref,
            $cat
        ]);
    }

    if ($conn->inTransaction()) $conn->commit();
    echo "✅ Actualización Exitosa";

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
?>