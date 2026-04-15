<?php
header('Content-Type: application/json');
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');
ew_requiere_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Método no permitido.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$precios = ['moto'=>15000,'auto'=>26000,'suv'=>28000,'pickup'=>31000];

$patente        = strtoupper(trim($input['patente']       ?? ''));
$nombre_cliente = trim($input['nombre_cliente']           ?? '');
$telefono       = trim($input['telefono']                 ?? '');
$marca_modelo   = trim($input['marca_modelo']             ?? '');
$tipo_vehiculo  = strtolower(trim($input['tipo_vehiculo'] ?? ''));
$codigo_campana = strtoupper(trim($input['codigo_campana']?? ''));
$hora_retiro    = trim($input['hora_retiro']              ?? '');

$errores = [];
if (!$patente || strlen($patente)<5 || strlen($patente)>10) $errores[] = 'Patente inválida.';
if (!$nombre_cliente) $errores[] = 'El nombre del cliente es obligatorio.';
if (!$telefono)       $errores[] = 'El teléfono es obligatorio.';
if (!array_key_exists($tipo_vehiculo, $precios)) $errores[] = 'Tipo de vehículo inválido.';
if ($errores) { http_response_code(422); echo json_encode(['ok'=>false,'errores'=>$errores]); exit; }

$monto = $precios[$tipo_vehiculo];
$pdo   = getDB();
$tl    = DB_PREFIX . 'lavados';

$stmt = $pdo->prepare("INSERT INTO $tl
    (usuario_id,turno,fecha,hora_ingreso,patente,nombre_cliente,telefono,
     marca_modelo,tipo_vehiculo,monto,metodo_pago,hora_retiro,codigo_campana,comision,estado)
    VALUES (?,?,?,?,?,?,?,?,?,?,NULL,?,?,?,?)");
$stmt->execute([
    (int)ew_s('ew_uid'), ew_s('ew_turno'), date('Y-m-d'), date('H:i:s'),
    $patente, $nombre_cliente, $telefono, $marca_modelo ?: null,
    $tipo_vehiculo, $monto, $hora_retiro ?: null, $codigo_campana ?: null, EW_COMISION, 'en_proceso',
]);
$nuevo_id = $pdo->lastInsertId();

// ── Webhook Make.com — Registro de cliente ──
$wh_body = json_encode([
    'evento'         => 'Registro de cliente',
    'fecha_hora'     => date('d-m-Y H:i:s'),
    'nombre'         => $nombre_cliente,
    'telefono'       => $telefono,
    'turno'          => ew_s('ew_turno'),
    'cajero'         => ew_s('ew_nombre'),
    'patente'        => $patente,
    'tipo_vehiculo'  => $tipo_vehiculo,
    'marca_modelo'   => $marca_modelo ?: '',
    'codigo_campana' => $codigo_campana ?: '',
    'hora_retiro'    => $hora_retiro ?: '',
]);
$ch = curl_init(EW_WEBHOOK_CLIENTES);
curl_setopt_array($ch,[
    CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$wh_body,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8,
]);
curl_exec($ch); curl_close($ch);

echo json_encode(['ok'=>true,'id'=>$nuevo_id,'monto'=>$monto,'msg'=>'Vehículo registrado.']);
