<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');
ew_requiere_admin();

$pdo = getDB();
$tu  = DB_PREFIX . 'usuarios';
$tl  = DB_PREFIX . 'lavados';
$tc  = DB_PREFIX . 'cierres_caja';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Filtros
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$fecha_disp   = date('d/m/Y', strtotime($fecha_filtro));

// ── Resumen del día ──
$q = $pdo->prepare("SELECT
    COUNT(*) AS total_autos,
    SUM(CASE WHEN metodo_pago='efectivo' THEN monto ELSE 0 END) AS total_ef,
    SUM(CASE WHEN metodo_pago='tarjeta'  THEN monto ELSE 0 END) AS total_tj,
    SUM(monto) AS total,
    SUM(comision) AS comision
    FROM $tl WHERE fecha=? AND estado IN ('en_proceso','pagado','procesado')");
$q->execute([$fecha_filtro]);
$resumen = $q->fetch();

// ── Cierres del día ──
$q = $pdo->prepare("SELECT cc.*, u.nombre AS cajero
    FROM $tc cc JOIN $tu u ON u.id=cc.usuario_id
    WHERE cc.fecha=? ORDER BY cc.hora_cierre ASC");
$q->execute([$fecha_filtro]);
$cierres = $q->fetchAll();

// ── Lavados del día ──
$q = $pdo->prepare("SELECT l.*, u.nombre AS cajero
    FROM $tl l JOIN $tu u ON u.id=l.usuario_id
    WHERE l.fecha=? ORDER BY l.hora_ingreso ASC");
$q->execute([$fecha_filtro]);
$lavados = $q->fetchAll();

// ── Resumen del mes ──
$mes_inicio = date('Y-m-01');
$mes_fin    = date('Y-m-t');
$q = $pdo->prepare("SELECT COUNT(*) AS autos, SUM(monto) AS total, SUM(comision) AS comision
    FROM $tl WHERE fecha BETWEEN ? AND ? AND estado IN ('en_proceso','pagado','procesado')");
$q->execute([$mes_inicio, $mes_fin]);
$mes = $q->fetch();

// ── Últimos 7 días para el gráfico ──
$q = $pdo->prepare("SELECT fecha, COUNT(*) AS autos, SUM(monto) AS total
    FROM $tl WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    AND estado IN ('en_proceso','pagado','procesado')
    GROUP BY fecha ORDER BY fecha ASC");
$q->execute();
$graf = $q->fetchAll();

$iconos = ['moto'=>'🏍️','auto'=>'🚗','suv'=>'🚙','pickup'=>'🛻'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Gestor Ecowash · Admin</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#1e3a5f">
  <link rel="apple-touch-icon" href="/img/icono-192-app.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    *{box-sizing:border-box}
    body{background:#0f172a !important;font-family:system-ui,sans-serif;margin:0;padding-bottom:20px;color:#e2e8f0}
    .card{background:#1e293b !important;border-radius:16px;padding:16px;margin-bottom:12px;box-shadow:0 2px 8px rgba(0,0,0,.3)}
    .card-light{background:#ffffff !important;border-radius:16px;padding:16px;margin-bottom:12px;box-shadow:0 1px 4px rgba(0,0,0,.1)}
    .stit{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:12px}
    .kpi{background:#0f172a !important;border-radius:12px;padding:12px;text-align:center}
    .kpi-lbl{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:4px}
    .kpi-val{font-size:20px;font-weight:700;color:#e2e8f0}
    .kpi-val.green{color:#4ade80 !important}
    .kpi-val.yellow{color:#c7d03b !important}
    .kpi-val.blue{color:#60a5fa !important}
    .badge{font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px;display:inline-block}
    .badge-ok{background:rgba(74,222,128,.15);color:#4ade80}
    .badge-err{background:rgba(248,113,113,.15);color:#f87171}
    .badge-warn{background:rgba(251,191,36,.15);color:#fbbf24}
    .row-t{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #1e293b}
    .row-t:last-child{border:none}
    input[type=date]{
      color:#e2e8f0 !important;-webkit-text-fill-color:#e2e8f0 !important;
      background:#0f172a !important;border:2px solid #334155 !important;
      border-radius:10px !important;padding:8px 12px !important;
      font-size:14px !important;font-family:system-ui !important;
      outline:none !important;-webkit-appearance:none !important;
    }
    input[type=date]:focus{border-color:#3b82f6 !important}
    .tab{padding:8px 16px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;border:none;font-family:system-ui;transition:all .15s}
    .tab.on{background:#2a8ec1 !important;color:#fff !important;-webkit-text-fill-color:#fff !important}
    .tab.off{background:#1e293b !important;color:#64748b !important;-webkit-text-fill-color:#64748b !important}
    /* Bar chart */
    .bar-wrap{display:flex;align-items:flex-end;gap:6px;height:80px;padding-top:8px}
    .bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
    .bar{width:100%;border-radius:4px 4px 0 0;background:#2a8ec1;min-height:4px;transition:height .3s}
    .bar.today{background:#c7d03b !important}
    .bar-lbl{font-size:9px;color:#64748b;white-space:nowrap}
    .bar-val{font-size:9px;color:#94a3b8}
  </style>
</head>
<body>

<!-- HEADER -->
<header style="background:#1e293b;position:sticky;top:0;z-index:50;box-shadow:0 2px 8px rgba(0,0,0,.4);padding-top:env(safe-area-inset-top,0)">
  <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 14px">
    <div style="display:flex;align-items:center;gap:10px">
      <img src="/img/icono-192-app.png" style="width:34px;height:34px;border-radius:50%;background:white;padding:2px;object-fit:contain" onerror="this.style.display='none'">
      <div>
        <div style="color:#fff;font-weight:700;font-size:13px">Panel Admin</div>
        <div style="color:#64748b;font-size:11px">José · <?= date('d/m/Y') ?></div>
      </div>
    </div>
    <a href="/logout.php" style="background:rgba(255,255,255,.1);color:#94a3b8;font-size:11px;padding:5px 10px;border-radius:20px;font-weight:500;text-decoration:none">Salir</a>
  </div>
</header>

<div style="max-width:500px;margin:0 auto;padding:12px 14px">

  <!-- RESUMEN MES -->
  <div class="card">
    <div class="stit">📅 <?= date('F Y') ?></div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
      <div class="kpi"><div class="kpi-lbl">Autos</div><div class="kpi-val blue"><?= number_format($mes->autos??0) ?></div></div>
      <div class="kpi"><div class="kpi-lbl">Recaudado</div><div class="kpi-val green">$<?= number_format($mes->total??0,0,',','.') ?></div></div>
      <div class="kpi"><div class="kpi-lbl">Tu comisión</div><div class="kpi-val yellow">$<?= number_format($mes->comision??0,0,',','.') ?></div></div>
    </div>
  </div>

  <!-- GRÁFICO 7 DÍAS -->
  <?php if(!empty($graf)):
    $maxAutos = max(array_map(fn($r)=>$r->autos,$graf));
    $hoy = date('Y-m-d');
  ?>
  <div class="card">
    <div class="stit">📊 Últimos 7 días</div>
    <div class="bar-wrap">
      <?php foreach($graf as $g):
        $pct = $maxAutos>0 ? round(($g->autos/$maxAutos)*72) : 4;
        $isHoy = $g->fecha === $hoy;
      ?>
      <div class="bar-col">
        <div class="bar-val"><?= $g->autos ?></div>
        <div class="bar <?= $isHoy?'today':'' ?>" style="height:<?= $pct ?>px"></div>
        <div class="bar-lbl"><?= date('d/m',strtotime($g->fecha)) ?></div>
      </div>
      <?php endforeach ?>
    </div>
  </div>
  <?php endif ?>

  <!-- SELECTOR DE FECHA -->
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <div class="stit" style="margin-bottom:0">🔍 Ver día específico</div>
    </div>
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <input type="date" name="fecha" value="<?= h($fecha_filtro) ?>" style="flex:1">
      <button type="submit" style="padding:9px 16px;border-radius:10px;background:#2a8ec1 !important;color:#fff;border:none;font-size:13px;font-weight:700;cursor:pointer;font-family:system-ui;-webkit-appearance:none">Ver</button>
    </form>
  </div>

  <!-- RESUMEN DEL DÍA SELECCIONADO -->
  <div class="card">
    <div class="stit">📋 <?= $fecha_disp ?></div>
    <?php if(($resumen->total_autos??0)==0): ?>
      <p style="text-align:center;color:#475569;font-size:13px;padding:12px 0">Sin registros para esta fecha</p>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:10px">
      <div class="kpi"><div class="kpi-lbl">Autos</div><div class="kpi-val blue"><?= $resumen->total_autos ?></div></div>
      <div class="kpi"><div class="kpi-lbl">Total</div><div class="kpi-val green">$<?= number_format($resumen->total??0,0,',','.') ?></div></div>
      <div class="kpi"><div class="kpi-lbl">💵 Efectivo</div><div class="kpi-val"><?= '$'.number_format($resumen->total_ef??0,0,',','.') ?></div></div>
      <div class="kpi"><div class="kpi-lbl">💳 Tarjeta</div><div class="kpi-val"><?= '$'.number_format($resumen->total_tj??0,0,',','.') ?></div></div>
    </div>
    <div style="background:rgba(199,208,59,.1);border:1px solid rgba(199,208,59,.3);border-radius:10px;padding:10px;text-align:center">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#c7d03b;margin-bottom:2px">Tu comisión del día</div>
      <div style="font-size:24px;font-weight:700;color:#c7d03b">$<?= number_format($resumen->comision??0,0,',','.') ?></div>
      <div style="font-size:11px;color:#64748b;margin-top:2px"><?= $resumen->total_autos ?> autos × $100</div>
    </div>
    <?php endif ?>
  </div>

  <!-- CIERRES DEL DÍA -->
  <?php if(!empty($cierres)): ?>
  <div class="card">
    <div class="stit">🔒 Cierres de turno</div>
    <?php foreach($cierres as $c):
      $dif = floatval($c->diferencia);
      $difClass = $dif==0?'badge-ok':($dif>0?'badge-warn':'badge-err');
      $difTxt   = $dif==0?'✅ Cuadró':($dif>0?'⬆️ +$'.number_format(abs($dif),0,',','.'):'⬇️ -$'.number_format(abs($dif),0,',','.'));
    ?>
    <div style="background:#0f172a;border-radius:12px;padding:12px;margin-bottom:8px">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
        <div>
          <div style="font-size:13px;font-weight:700;color:#e2e8f0"><?= h($c->cajero) ?> · <span style="color:#c7d03b">Turno <?= h($c->turno) ?></span></div>
          <div style="font-size:11px;color:#64748b"><?= date('H:i',strtotime($c->hora_cierre)) ?> · <?= $c->total_autos ?> autos</div>
        </div>
        <span class="badge <?= $difClass ?>"><?= $difTxt ?></span>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px">
        <div style="text-align:center">
          <div style="font-size:10px;color:#64748b;margin-bottom:2px">Esperado</div>
          <div style="font-size:13px;font-weight:700;color:#e2e8f0">$<?= number_format($c->total_efectivo_esp,0,',','.') ?></div>
        </div>
        <div style="text-align:center">
          <div style="font-size:10px;color:#64748b;margin-bottom:2px">Declarado</div>
          <div style="font-size:13px;font-weight:700;color:#e2e8f0">$<?= number_format($c->efectivo_fisico,0,',','.') ?></div>
        </div>
        <div style="text-align:center">
          <div style="font-size:10px;color:#64748b;margin-bottom:2px">Tarjeta</div>
          <div style="font-size:13px;font-weight:700;color:#60a5fa">$<?= number_format($c->total_tarjeta,0,',','.') ?></div>
        </div>
      </div>
      <?php if(!$c->webhook_enviado): ?>
      <div style="margin-top:8px;background:rgba(251,191,36,.1);border-radius:8px;padding:6px 10px;font-size:11px;color:#fbbf24;text-align:center">
        ⚠️ No se envió al CRM — <a href="/cerrar_reintento.php?id=<?= $c->id ?>" style="color:#fbbf24;font-weight:700">Reintentar</a>
      </div>
      <?php endif ?>
    </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <!-- DETALLE DE LAVADOS -->
  <?php if(!empty($lavados)): ?>
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <div class="stit" style="margin-bottom:0">🚗 Detalle de vehículos</div>
      <span style="background:rgba(42,142,193,.2);color:#60a5fa;font-size:11px;font-weight:700;padding:3px 8px;border-radius:20px"><?= count($lavados) ?> autos</span>
    </div>
    <?php foreach($lavados as $l): ?>
    <div class="row-t">
      <div style="display:flex;align-items:center;gap:8px">
        <span style="font-size:18px;line-height:1"><?= $iconos[$l->tipo_vehiculo]??'🚗' ?></span>
        <div>
          <div style="font-weight:700;font-size:12px;color:#e2e8f0"><?= h($l->patente) ?> <span style="font-weight:400;color:#64748b">· <?= h($l->nombre_cliente) ?></span></div>
          <div style="font-size:11px;color:#475569">
            <?= h($l->hora_ingreso) ?>
            · <?= h($l->cajero) ?>
            <?= $l->codigo_campana ? ' · 📣'.h($l->codigo_campana) : '' ?>
          </div>
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <div style="font-weight:700;font-size:12px;color:#c7d03b">$<?= number_format($l->monto,0,',','.') ?></div>
        <div style="font-size:11px;color:<?= $l->metodo_pago==='efectivo'?'#4ade80':'#60a5fa' ?>">
          <?= $l->metodo_pago==='efectivo'?'💵':'💳' ?>
          <span style="color:<?= $l->estado==='procesado'?'#4ade80':'#fbbf24' ?>;margin-left:2px"><?= $l->estado==='procesado'?'✓':'⏳' ?></span>
        </div>
      </div>
    </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <!-- BOTÓN IR A CAJA -->
  <a href="/ingreso.php" style="display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:13px;border-radius:14px;background:#2a8ec1;color:#fff;-webkit-text-fill-color:#fff;font-size:13px;font-weight:700;text-decoration:none;margin-bottom:8px">
    📱 Ir a pantalla de caja
  </a>

  <!-- BOTÓN REINICIAR PRUEBA -->
  <button onclick="reiniciarPrueba()"
    style="width:100%;padding:12px;border-radius:14px;background:#0f172a;color:#94a3b8;-webkit-text-fill-color:#94a3b8;font-size:12px;font-weight:600;border:1px dashed #334155;cursor:pointer;font-family:system-ui;-webkit-appearance:none;margin-bottom:4px">
    🔄 Reiniciar datos de prueba del día
  </button>
  <div style="text-align:center;font-size:11px;color:#475569;margin-bottom:20px">Solo borra los datos de hoy · No afecta días anteriores</div>

  <div class="toast" id="tst" style="position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:12px 24px;border-radius:50px;font-size:13px;font-weight:600;opacity:0;transition:opacity .3s;text-align:center;font-family:system-ui;white-space:nowrap;z-index:100"></div>

</div>

<script>
function toast(msg,er=false){
  const t=document.getElementById('tst');
  t.textContent=msg;
  t.style.background=er?'#dc2626':'#1e293b';
  t.style.opacity='1';
  setTimeout(()=>t.style.opacity='0',3500);
}

async function reiniciarPrueba(){
  if(!confirm('⚠️ ¿Reiniciar todos los datos de HOY?\n\nSe borrarán todos los registros de lavados y cierres de hoy.\nLos días anteriores no se tocan.')) return;

  try{
    const res = await fetch('/reiniciar.php',{method:'POST'});
    const d   = await res.json();
    if(d.ok){
      toast('✅ ' + d.msg);
      setTimeout(()=>window.location.reload(), 2000);
    } else {
      toast('⚠️ ' + (d.error||'Error al reiniciar'), true);
    }
  } catch(e){
    toast('⚠️ Error de conexión', true);
  }
}
</script>
</body>
</html>
