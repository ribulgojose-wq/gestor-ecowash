<?php
header('Content-Type: application/json');
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');
ew_requiere_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Método no permitido.']); exit;
}

$pdo = getDB();
$hoy = date('Y-m-d');
$tl  = DB_PREFIX . 'lavados';
$tc  = DB_PREFIX . 'cierres_caja';

// Borrar todo lo del día actual
$lavados  = $pdo->exec("DELETE FROM $tl WHERE fecha = '$hoy'");
$cierres  = $pdo->exec("DELETE FROM $tc WHERE fecha = '$hoy'");

echo json_encode([
    'ok'      => true,
    'lavados' => $lavados,
    'cierres' => $cierres,
    'msg'     => "✅ Reinicio completo — $lavados registro(s) y $cierres cierre(s) eliminados para hoy $hoy.",
]);
