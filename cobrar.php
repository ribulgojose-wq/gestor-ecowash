<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');
ew_requiere_login();

$pdo = getDB();
$tl  = DB_PREFIX . 'lavados';
$id  = (int)($_GET['id'] ?? 0);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!$id) { header('Location: /pendientes.php'); exit; }

// Buscar el registro
$stmt = $pdo->prepare("SELECT * FROM $tl WHERE id=? AND estado='en_proceso' LIMIT 1");
$stmt->execute([$id]);
$reg = $stmt->fetch();

if (!$reg) { header('Location: /pendientes.php?msg=ya_cobrado'); exit; }

$iconos  = ['moto'=>'🏍️','auto'=>'🚗','suv'=>'🚙','pickup'=>'🛻'];
$nombres = ['moto'=>'Moto','auto'=>'Auto','suv'=>'SUV','pickup'=>'Pickup'];

// Procesar el pago si viene el POST
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metodo = strtolower(trim($_POST['metodo_pago'] ?? ''));
    if (!in_array($metodo, ['efectivo','tarjeta'])) {
        $error = 'Seleccioná un método de pago.';
    } else {
        // Actualizar en DB
        $pdo->prepare("UPDATE $tl SET estado='pagado', metodo_pago=?, hora_pago=? WHERE id=?")
            ->execute([$metodo, date('H:i:s'), $id]);

        // Webhook Make.com
        $body = json_encode([
            'evento'         => 'Pago registrado',
            'fecha_hora'     => date('d-m-Y H:i:s'),
            'sucursal'       => EW_SUCURSAL,
            'cajero'         => ew_s('ew_nombre'),
            'turno'          => ew_s('ew_turno'),
            'patente'        => $reg->patente,
            'nombre_cliente' => $reg->nombre_cliente,
            'telefono'       => $reg->telefono,
            'marca_modelo'   => $reg->marca_modelo ?: '',
            'tipo_vehiculo'  => $reg->tipo_vehiculo,
            'monto'          => floatval($reg->monto),
            'metodo_pago'    => $metodo,
            'hora_ingreso'   => $reg->hora_ingreso,
            'hora_pago'      => date('H:i:s'),
            'hora_retiro'    => $reg->hora_retiro ?: '',
            'codigo_campana' => $reg->codigo_campana ?: '',
            'comision'       => floatval($reg->comision),
            'status'         => 'Pago confirmado',
        ]);
        $ch = curl_init(EW_WEBHOOK_CLIENTES);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$body,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10,
        ]);
        curl_exec($ch); curl_close($ch);

        // Redirigir a pendientes con confirmación
        header('Location: /pendientes.php?ok=1&nombre='.urlencode($reg->nombre_cliente).'&metodo='.$metodo);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Gestor Ecowash · Cobrar</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#2a8ec1">
  <link rel="apple-touch-icon" href="/img/icono-192-app.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    *{box-sizing:border-box}
    body{background:#f0f6fa !important;font-family:system-ui,sans-serif;margin:0;min-height:100vh;padding-bottom:env(safe-area-inset-bottom,20px)}
    body::before{content:'';display:block;height:env(safe-area-inset-top,0);background:#2a8ec1;position:fixed;top:0;left:0;right:0;z-index:51}
    .btn-pago{
      width:100%;padding:20px;border-radius:16px;border:2px solid #d1e8f5 !important;
      font-size:16px;font-weight:700;cursor:pointer;
      color:#1e6a94 !important;-webkit-text-fill-color:#1e6a94 !important;
      background:#ffffff !important;-webkit-appearance:none;font-family:system-ui;
      display:flex;flex-direction:column;align-items:center;gap:8px;
      transition:all .15s;
    }
    .btn-pago:active{transform:scale(.97)}
    .btn-ef{border-color:#86efac !important}
    .btn-ef:active{background:#f0fdf4 !important}
    .btn-tj{border-color:#93c5fd !important}
    .btn-tj:active{background:#eff6ff !important}
    /* Ocultar el input radio pero usar el botón como label */
    input[type=radio]{display:none}
    .btn-pago.selected-ef{background:#f0fdf4 !important;border-color:#16a34a !important;color:#16a34a !important;-webkit-text-fill-color:#16a34a !important}
    .btn-pago.selected-tj{background:#eff6ff !important;border-color:#2563eb !important;color:#2563eb !important;-webkit-text-fill-color:#2563eb !important}
    #btnConfirmar{
      width:100%;padding:16px;border-radius:14px;border:none !important;
      background:#c7d03b !important;color:#3a4700 !important;-webkit-text-fill-color:#3a4700 !important;
      font-size:16px;font-weight:700;cursor:pointer;-webkit-appearance:none;font-family:system-ui;
      box-shadow:0 4px 16px rgba(199,208,59,.45);
      display:none;
    }
    #btnConfirmar.visible{display:block}
  </style>
</head>
<body>

<!-- HEADER -->
<header style="background:#2a8ec1;position:sticky;top:0;z-index:50;box-shadow:0 2px 8px rgba(0,0,0,.15);padding-top:env(safe-area-inset-top,44px)">
  <div style="display:flex;align-items:center;gap:10px;padding:11px 14px">
    <a href="/pendientes.php" style="color:rgba(255,255,255,.8);font-size:22px;text-decoration:none;line-height:1">←</a>
    <img src="/img/icono-192-app.png" style="width:32px;height:32px;border-radius:50%;background:white;padding:2px;object-fit:contain" onerror="this.style.display='none'">
    <div>
      <div style="color:#fff;font-weight:700;font-size:13px">Registrar cobro</div>
      <div style="color:rgba(255,255,255,.7);font-size:11px"><?= h(ew_s('ew_nombre')) ?> · Turno <span style="color:#c7d03b"><?= h(ew_s('ew_turno')) ?></span></div>
    </div>
  </div>
</header>

<div style="max-width:500px;margin:0 auto;padding:20px 16px">

  <?php if ($error): ?>
  <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:14px;color:#dc2626;font-weight:600">
    ⚠️ <?= h($error) ?>
  </div>
  <?php endif ?>

  <!-- DATOS DEL AUTO -->
  <div style="background:#fff;border-radius:20px;padding:20px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.06);text-align:center">
    <div style="font-size:56px;line-height:1;margin-bottom:12px"><?= $iconos[$reg->tipo_vehiculo] ?? '🚗' ?></div>
    <div style="font-size:22px;font-weight:700;color:#1f2937;letter-spacing:.08em;margin-bottom:4px"><?= h($reg->patente) ?></div>
    <div style="font-size:18px;font-weight:700;color:#1e6a94;margin-bottom:4px"><?= h($reg->nombre_cliente) ?></div>
    <div style="font-size:13px;color:#64748b;margin-bottom:16px">
      <?= h($nombres[$reg->tipo_vehiculo] ?? 'Vehículo') ?>
      <?= $reg->marca_modelo ? ' · '.h($reg->marca_modelo) : '' ?>
      · Ingresó <?= h($reg->hora_ingreso) ?>
      <?php if ($reg->hora_retiro): ?>
        · <span style="color:#f59e0b">Retira <?= h($reg->hora_retiro) ?></span>
      <?php endif ?>
    </div>

    <!-- Monto -->
    <div style="background:#e8f4fb;border-radius:14px;padding:16px">
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#1e6a94;margin-bottom:4px">Monto a cobrar</div>
      <div style="font-size:36px;font-weight:700;color:#1e6a94">$<?= number_format($reg->monto,0,',','.') ?></div>
      <div style="font-size:11px;color:#94a3b8;margin-top:2px">Precio fijo · no modificable</div>
    </div>
  </div>

  <!-- FORMULARIO DE PAGO -->
  <form method="POST" id="formPago">
    <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:12px">¿Cómo paga?</div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
      <label onclick="seleccionar('efectivo')">
        <input type="radio" name="metodo_pago" value="efectivo">
        <div class="btn-pago btn-ef" id="btn-ef">
          <span style="font-size:36px">💵</span>
          <span>Efectivo</span>
        </div>
      </label>
      <label onclick="seleccionar('tarjeta')">
        <input type="radio" name="metodo_pago" value="tarjeta">
        <div class="btn-pago btn-tj" id="btn-tj">
          <span style="font-size:36px">💳</span>
          <span>Tarjeta</span>
        </div>
      </label>
    </div>

    <button type="submit" id="btnConfirmar" onclick="this.textContent='⏳ Procesando...'">
      ✅ Confirmar cobro
    </button>
  </form>

  <!-- Cancelar -->
  <a href="/pendientes.php" style="display:block;text-align:center;margin-top:16px;color:#94a3b8;font-size:14px;text-decoration:none;padding:10px">
    Cancelar — volver a pendientes
  </a>

</div>

<script>
function seleccionar(metodo){
  document.getElementById('btn-ef').className = 'btn-pago btn-ef' + (metodo==='efectivo'?' selected-ef':'');
  document.getElementById('btn-tj').className = 'btn-pago btn-tj' + (metodo==='tarjeta'?' selected-tj':'');
  document.getElementById('btnConfirmar').classList.add('visible');
  // Marcar el radio correcto
  document.querySelector('input[value="'+metodo+'"]').checked = true;
}
</script>
</body>
</html>
