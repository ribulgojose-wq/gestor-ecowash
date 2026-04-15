<?php
header('Content-Type: application/json');
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');
ew_requiere_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Método no permitido.']); exit;
}

$input           = json_decode(file_get_contents('php://input'), true) ?: [];
$efectivo_fisico = floatval($input['efectivo_fisico'] ?? -1);
$gastos_insumos  = floatval($input['gastos_insumos']  ??  0);

if ($efectivo_fisico < 0) {
    http_response_code(422); echo json_encode(['ok'=>false,'error'=>'Efectivo físico inválido.']); exit;
}

$pdo        = getDB();
$uid        = (int)ew_s('ew_uid');
$turno      = ew_s('ew_turno');
$cajero     = ew_s('ew_nombre');
$tl         = DB_PREFIX . 'lavados';
$tc         = DB_PREFIX . 'cierres_caja';
$hoy        = date('Y-m-d');

// Verificar cierre previo
$existe = $pdo->prepare("SELECT id FROM $tc WHERE usuario_id=? AND turno=? AND fecha=? LIMIT 1");
$existe->execute([$uid, $turno, $hoy]);
if ($existe->fetch()) {
    echo json_encode(['ok'=>false,'error'=>'Ya existe un cierre para este turno hoy.']); exit;
}

// Registros PAGADOS del turno
$stmt = $pdo->prepare("SELECT id,patente,nombre_cliente,tipo_vehiculo,monto,
    metodo_pago,hora_ingreso,hora_pago,codigo_campana,comision
    FROM $tl WHERE usuario_id=? AND turno=? AND fecha=? AND estado='pagado'
    ORDER BY hora_pago ASC");
$stmt->execute([$uid, $turno, $hoy]);
$pagados = $stmt->fetchAll();

// Autos sin pagar
$qs = $pdo->prepare("SELECT COUNT(*) AS cnt, SUM(monto) AS total
    FROM $tl WHERE usuario_id=? AND turno=? AND fecha=? AND estado='en_proceso'");
$qs->execute([$uid, $turno, $hoy]);
$sin_pagar = $qs->fetch();

// Calcular totales
$total_autos  = count($pagados);
$total_ef_esp = 0;
$total_tj     = 0;
$comision     = 0;
foreach ($pagados as $r) {
    if ($r->metodo_pago === 'efectivo') $total_ef_esp += floatval($r->monto);
    else                                $total_tj     += floatval($r->monto);
    $comision += floatval($r->comision);
}

// Cálculo de diferencia considerando gastos
$efectivo_neto = $efectivo_fisico - $gastos_insumos;
$diferencia    = $efectivo_neto - $total_ef_esp;
$total_cobrado = $total_ef_esp + $total_tj;

// Generar ID de cierre único
$cierre_id_externo = 'EW-' . strtoupper($turno[0]) . date('dmY') . '-' . $uid;

// ── Guardar cierre en DB ──
$pdo->beginTransaction();

$ins = $pdo->prepare("INSERT INTO $tc
    (usuario_id,turno,fecha,total_autos,total_efectivo_esp,total_tarjeta,
     efectivo_fisico,diferencia,comision_jose,webhook_enviado)
    VALUES (?,?,?,?,?,?,?,?,?,0)");
$ins->execute([
    $uid, $turno, $hoy, $total_autos,
    $total_ef_esp, $total_tj, $efectivo_fisico, $diferencia, $comision
]);
$cierre_db_id = $pdo->lastInsertId();

// ── Marcar pagados como procesados ──
if (!empty($pagados)) {
    $ids = implode(',', array_map(fn($r)=>(int)$r->id, $pagados));
    $pdo->exec("UPDATE $tl SET estado='procesado', cierre_id=$cierre_db_id WHERE id IN ($ids)");
}

// ── Marcar sin pagar como procesados también ──
$pdo->prepare("UPDATE $tl SET estado='procesado', cierre_id=? WHERE usuario_id=? AND turno=? AND fecha=? AND estado='en_proceso'")
    ->execute([$cierre_db_id, $uid, $turno, $hoy]);

$pdo->commit();

// ── Webhook Make.com — Cierre de caja ──
$detalle = array_map(fn($r) => [
    'patente'        => $r->patente,
    'cliente'        => $r->nombre_cliente,
    'tipo'           => $r->tipo_vehiculo,
    'monto'          => floatval($r->monto),
    'metodo'         => $r->metodo_pago,
    'hora_pago'      => $r->hora_pago,
    'codigo_campana' => $r->codigo_campana ?: '',
], $pagados);

$body_cierre = json_encode([
    // Datos del cierre
    'fecha'                => date('d-m-Y'),
    'turno'                => $turno,
    'cajero'               => $cajero,
    'hora_cierre'          => date('H:i:s'),
    // Totales
    'total_lavados'        => $total_autos,
    'recaudacion_total'    => $total_cobrado,
    'total_efectivo_esp'   => $total_ef_esp,
    'efectivo_declarado'   => $efectivo_fisico,
    'gastos_insumos'       => $gastos_insumos,
    'efectivo_neto'        => $efectivo_neto,
    'diferencia_caja'      => $diferencia,
    'total_tarjeta'        => $total_tj,
    'comision_jose'        => $comision,
    // Autos sin pagar
    'autos_sin_pagar'      => (int)($sin_pagar->cnt ?? 0),
    'monto_sin_cobrar'     => floatval($sin_pagar->total ?? 0),
    // ID de cierre
    'id_cierre'            => $cierre_id_externo,
    'id_cierre_db'         => $cierre_db_id,
    'sucursal'             => EW_SUCURSAL,
    'status'               => 'Cierre de Turno',
    // Detalle opcional
    'detalle_cobros'       => $detalle,
]);

$ch = curl_init(EW_WEBHOOK_CIERRE);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body_cierre,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
]);
curl_exec($ch);
$wh_ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
curl_close($ch);

if ($wh_ok) {
    $pdo->exec("UPDATE $tc SET webhook_enviado=1 WHERE id=$cierre_db_id");
}

echo json_encode([
    'ok'        => true,
    'cierre_id' => $cierre_db_id,
    'comision'  => $comision,
    'diferencia'=> $diferencia,
    'webhook'   => $wh_ok,
    'msg'       => $wh_ok
        ? '¡Cierre exitoso! CRM y Google Sheets actualizados.'
        : 'Cierre guardado. El CRM no respondió — revisá Make.com.',
]);
