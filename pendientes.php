<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');
ew_requiere_login();

$pdo        = getDB();
$nombre     = ew_s('ew_nombre');
$turno      = ew_s('ew_turno');
$usuario_id = (int) ew_s('ew_uid');
$tl         = DB_PREFIX . 'lavados';
$hoy        = date('Y-m-d');

// Todos los autos en_proceso del turno actual
$q = $pdo->prepare("SELECT id, patente, nombre_cliente, telefono, tipo_vehiculo,
    monto, hora_ingreso, hora_retiro, marca_modelo, codigo_campana
    FROM $tl
    WHERE usuario_id=? AND turno=? AND fecha=? AND estado='en_proceso'
    ORDER BY hora_ingreso ASC");
$q->execute([$usuario_id, $turno, $hoy]);
$pendientes = $q->fetchAll();

// Autos pagados del turno (para el resumen)
$q2 = $pdo->prepare("SELECT COUNT(*) AS cnt, SUM(monto) AS total,
    SUM(CASE WHEN metodo_pago='efectivo' THEN monto ELSE 0 END) AS ef,
    SUM(CASE WHEN metodo_pago='tarjeta' THEN monto ELSE 0 END) AS tj
    FROM $tl WHERE usuario_id=? AND turno=? AND fecha=? AND estado='pagado'");
$q2->execute([$usuario_id, $turno, $hoy]);
$pagados = $q2->fetch();

$iconos = ['moto'=>'🏍️','auto'=>'🚗','suv'=>'🚙','pickup'=>'🛻'];
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Gestor Ecowash · Pendientes</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#2a8ec1">
  <link rel="apple-touch-icon" href="/img/icono-192-app.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    *{box-sizing:border-box}
    body{background:#f0f6fa !important;font-family:system-ui,sans-serif;margin:0;padding-bottom:env(safe-area-inset-bottom,20px)}
    body::before{content:'';display:block;height:env(safe-area-inset-top,0);background:#2a8ec1;position:fixed;top:0;left:0;right:0;z-index:51}
    input#buscador{
      color:#1f2937 !important;-webkit-text-fill-color:#1f2937 !important;
      background:#ffffff !important;-webkit-box-shadow:0 0 0 40px #ffffff inset !important;
      box-shadow:0 0 0 40px #ffffff inset !important;caret-color:#1e6a94 !important;
      border:2px solid #d1e8f5 !important;border-radius:12px !important;
      padding:11px 14px 11px 40px !important;font-size:15px !important;
      font-family:system-ui !important;width:100% !important;
      outline:none !important;-webkit-appearance:none !important;display:block !important;
    }
    input#buscador::placeholder{color:#9ca3af !important;-webkit-text-fill-color:#9ca3af !important}
    input#buscador:focus{border-color:#2a8ec1 !important}
    .card-auto{background:#fff;border-radius:14px;padding:14px;margin-bottom:10px;box-shadow:0 1px 4px rgba(0,0,0,.06);display:flex;align-items:center;justify-content:space-between;gap:10px}
    .btn-cobrar{background:#22c55e !important;color:#ffffff !important;-webkit-text-fill-color:#ffffff !important;border:none !important;border-radius:10px;padding:10px 16px;font-size:13px;font-weight:700;cursor:pointer;font-family:system-ui;-webkit-appearance:none;white-space:nowrap;flex-shrink:0}
    .btn-cobrar:active{background:#16a34a !important}
    .toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#1e6a94;color:#fff !important;padding:12px 24px;border-radius:50px;font-size:14px;font-weight:600;opacity:0;transition:opacity .3s;text-align:center;font-family:system-ui;white-space:nowrap;z-index:100}
    .toast.on{opacity:1}.toast.er{background:#dc2626 !important}
    .empty-state{text-align:center;padding:40px 20px;color:#94a3b8;font-size:14px}
  </style>
</head>
<body>

<!-- HEADER -->
<header style="background:#2a8ec1;position:sticky;top:0;z-index:50;box-shadow:0 2px 8px rgba(0,0,0,.15);padding-top:env(safe-area-inset-top,44px)">
  <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 14px">
    <div style="display:flex;align-items:center;gap:10px">
      <img src="/img/icono-192-app.png" style="width:34px;height:34px;border-radius:50%;background:white;padding:2px;object-fit:contain;flex-shrink:0" onerror="this.style.display='none'">
      <div>
        <div style="color:#fff;font-weight:700;font-size:13px"><?= h($nombre) ?></div>
        <div style="color:rgba(255,255,255,.7);font-size:11px">Turno <span style="color:#c7d03b"><?= h($turno) ?></span></div>
      </div>
    </div>
    <a href="/logout.php" style="background:rgba(255,255,255,.2);color:#fff;font-size:11px;padding:5px 10px;border-radius:20px;font-weight:500;text-decoration:none">Salir</a>
  </div>
  <!-- Tabs -->
  <div style="display:flex;border-top:1px solid rgba(255,255,255,.15)">
    <a href="/ingreso.php" style="flex:1;padding:10px;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center">
      <span style="color:rgba(255,255,255,.8);font-size:13px;font-weight:600">➕ Registrar ingreso</span>
    </a>
    <div style="flex:1;padding:10px;text-align:center;background:rgba(255,255,255,.15);border-bottom:3px solid #c7d03b;display:flex;align-items:center;justify-content:center;gap:6px">
      <span style="color:#fff;font-size:13px;font-weight:700">⏳ Pendientes</span>
      <?php if(count($pendientes)>0): ?>
        <span style="background:#c7d03b;color:#3a4700;font-size:11px;font-weight:700;padding:2px 7px;border-radius:20px"><?= count($pendientes) ?></span>
      <?php endif ?>
    </div>
  </div>
</header>

<div style="max-width:500px;margin:0 auto;padding:14px">

  <!-- Resumen cobrado -->
  <?php if(($pagados->cnt??0) > 0): ?>
  <div style="background:#ffffff;border-radius:14px;padding:14px;margin-bottom:12px;box-shadow:0 1px 4px rgba(0,0,0,.06)">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#1e6a94;margin-bottom:10px">💰 Cobrado este turno</div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
      <div style="background:#e8f4fb;border-radius:10px;padding:10px;text-align:center">
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#1e6a94;margin-bottom:3px">Autos</div>
        <div style="font-size:20px;font-weight:700;color:#1e6a94"><?= $pagados->cnt ?></div>
      </div>
      <div style="background:#e8f4fb;border-radius:10px;padding:10px;text-align:center">
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#1e6a94;margin-bottom:3px">💵 Efectivo</div>
        <div style="font-size:14px;font-weight:700;color:#1e6a94">$<?= number_format($pagados->ef??0,0,',','.') ?></div>
      </div>
      <div style="background:#e8f4fb;border-radius:10px;padding:10px;text-align:center">
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#1e6a94;margin-bottom:3px">💳 Tarjeta</div>
        <div style="font-size:14px;font-weight:700;color:#1e6a94">$<?= number_format($pagados->tj??0,0,',','.') ?></div>
      </div>
    </div>
  </div>
  <?php endif ?>

  <!-- Buscador -->
  <div style="position:relative;margin-bottom:12px">
    <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:18px;pointer-events:none">🔍</span>
    <input type="text" id="buscador" placeholder="Buscar por nombre del cliente..." oninput="filtrar(this.value)">
  </div>

  <!-- Lista de pendientes -->
  <div id="lista">
    <?php if(empty($pendientes)): ?>
    <div class="empty-state">
      <div style="font-size:48px;margin-bottom:12px">✅</div>
      <div style="font-weight:700;color:#64748b;margin-bottom:6px">Sin pendientes de pago</div>
      <div style="font-size:13px">Todos los autos del turno fueron cobrados</div>
      <a href="/ingreso.php" style="display:inline-block;margin-top:16px;background:#2a8ec1;color:#fff;padding:10px 24px;border-radius:20px;font-size:13px;font-weight:700;text-decoration:none">➕ Registrar nuevo ingreso</a>
    </div>
    <?php else: ?>
      <?php foreach($pendientes as $p): ?>
      <div class="card-auto" id="card-<?= $p->id ?>" data-nombre="<?= strtolower(h($p->nombre_cliente)) ?>">
        <div style="flex:1;min-width:0">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
            <span style="font-size:22px;line-height:1"><?= $iconos[$p->tipo_vehiculo]??'🚗' ?></span>
            <div>
              <div style="font-weight:700;font-size:14px;color:#1f2937"><?= h($p->nombre_cliente) ?></div>
              <div style="font-size:12px;color:#64748b"><?= h($p->patente) ?> <?= $p->marca_modelo?' · '.h($p->marca_modelo):'' ?></div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;margin-left:30px">
            <span style="font-size:11px;color:#94a3b8">⏱ <?= h($p->hora_ingreso) ?></span>
            <?php if($p->hora_retiro): ?>
              <span style="font-size:11px;color:#f59e0b">🕐 Retira: <?= h($p->hora_retiro) ?></span>
            <?php endif ?>
            <?php if($p->codigo_campana): ?>
              <span style="font-size:11px;color:#2a8ec1">📣 <?= h($p->codigo_campana) ?></span>
            <?php endif ?>
            <span style="font-size:13px;font-weight:700;color:#1e6a94">$<?= number_format($p->monto,0,',','.') ?></span>
          </div>
        </div>
        <a href="/cobrar.php?id=<?= $p->id ?>" class="btn-cobrar">
          💰 Cobrar
        </a>
      </div>
      <?php endforeach ?>
    <?php endif ?>
  </div>

  <!-- Botón cierre de turno -->
  <?php if(count($pendientes) > 0 || ($pagados->cnt??0) > 0): ?>
  <button onclick="confirmarCierre()" type="button"
    style="width:100%;padding:12px;border-radius:14px;background:#f1f5f9;color:#64748b;-webkit-text-fill-color:#64748b;font-size:13px;font-weight:600;border:2px solid #e2e8f0;cursor:pointer;font-family:system-ui;-webkit-appearance:none;margin-top:4px">
    🔒 Cerrar turno <?= h($turno) ?>
  </button>
  <?php endif ?>
</div>

<!-- Modal cierre -->
<div id="modalCierre" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;align-items:flex-end;justify-content:center">
  <div style="background:#1e293b;border-radius:20px 20px 0 0;padding:24px;width:100%;max-width:500px;border-top:3px solid #c7d03b">
    <div style="font-size:16px;font-weight:700;color:#e2e8f0;text-align:center;margin-bottom:6px">🔒 ¿Cerrar turno <?= h($turno) ?>?</div>
    <?php if(count($pendientes)>0): ?>
    <div style="background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3);border-radius:10px;padding:10px;margin-bottom:16px;text-align:center">
      <span style="font-size:13px;color:#fbbf24">⚠️ Hay <?= count($pendientes) ?> auto<?= count($pendientes)>1?'s':'' ?> pendiente<?= count($pendientes)>1?'s':'' ?> de pago</span>
    </div>
    <?php endif ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <button onclick="document.getElementById('modalCierre').style.display='none'"
        style="padding:13px;border-radius:12px;border:2px solid #334155;font-size:14px;font-weight:700;color:#94a3b8;background:#0f172a;cursor:pointer;font-family:system-ui;-webkit-appearance:none">
        Cancelar
      </button>
      <a href="/cierre.php"
        style="padding:13px;border-radius:12px;background:#c7d03b;color:#3a4700;-webkit-text-fill-color:#3a4700;font-size:14px;font-weight:700;text-decoration:none;text-align:center;display:block">
        Sí, cerrar turno
      </a>
    </div>
  </div>
</div>

<div class="toast" id="tst"></div>

<script>
function filtrar(q){
  const txt = q.toLowerCase().trim();
  document.querySelectorAll('.card-auto').forEach(c=>{
    const nom = c.dataset.nombre || '';
    c.style.display = (!txt || nom.includes(txt)) ? '' : 'none';
  });
}

function confirmarCierre(){
  const m=document.getElementById('modalCierre');
  m.style.display='flex';
  m.addEventListener('click',e=>{if(e.target===m)m.style.display='none'},{once:true});
}

function toast(msg,er=false){
  const t=document.getElementById('tst');
  t.textContent=msg;t.className='toast'+(er?' er':'');
  t.classList.add('on');setTimeout(()=>t.classList.remove('on'),3000);
}

// Mostrar confirmación si viene de cobro exitoso
const params = new URLSearchParams(window.location.search);
if(params.get('ok')==='1'){
  const nombre  = params.get('nombre') || '';
  const metodo  = params.get('metodo') === 'efectivo' ? '💵 Efectivo' : '💳 Tarjeta';
  setTimeout(()=>toast('✅ ' + (nombre ? nombre + ' — ' : '') + metodo + ' cobrado'), 400);
  history.replaceState(null,'','/pendientes.php');
}
if(params.get('msg')==='ya_cobrado'){
  setTimeout(()=>toast('ℹ️ Este auto ya fue cobrado', false), 400);
  history.replaceState(null,'','/pendientes.php');
}
</script>
</body>
</html>
