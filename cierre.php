<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');
ew_requiere_login();

$pdo        = getDB();
$nombre     = ew_s('ew_nombre');
$turno      = ew_s('ew_turno');
$usuario_id = (int)ew_s('ew_uid');
$tl         = DB_PREFIX . 'lavados';
$tc         = DB_PREFIX . 'cierres_caja';
$tu         = DB_PREFIX . 'usuarios';
$hoy        = date('Y-m-d');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Verificar cierre previo
$existe = $pdo->prepare("SELECT id FROM $tc WHERE usuario_id=? AND turno=? AND fecha=? LIMIT 1");
$existe->execute([$usuario_id, $turno, $hoy]);
$cierreExiste = $existe->fetch();

// Autos PAGADOS del turno
$qp = $pdo->prepare("SELECT id, patente, nombre_cliente, tipo_vehiculo, monto,
    metodo_pago, hora_ingreso, hora_pago
    FROM $tl WHERE usuario_id=? AND turno=? AND fecha=? AND estado='pagado'
    ORDER BY hora_pago ASC");
$qp->execute([$usuario_id, $turno, $hoy]);
$pagados = $qp->fetchAll();

// Autos SIN PAGAR (en_proceso) - solo lectura
$qs = $pdo->prepare("SELECT id, patente, nombre_cliente, tipo_vehiculo, monto, hora_ingreso
    FROM $tl WHERE usuario_id=? AND turno=? AND fecha=? AND estado='en_proceso'
    ORDER BY hora_ingreso ASC");
$qs->execute([$usuario_id, $turno, $hoy]);
$sin_pagar = $qs->fetchAll();

// Totales
$total_autos     = count($pagados);
$total_ef_esp    = 0;
$total_tarjeta   = 0;
$comision        = 0;
foreach ($pagados as $r) {
    if ($r->metodo_pago === 'efectivo') $total_ef_esp += floatval($r->monto);
    else                                $total_tarjeta += floatval($r->monto);
    $comision += EW_COMISION;
}
$total_cobrado = $total_ef_esp + $total_tarjeta;
$iconos = ['moto'=>'🏍️','auto'=>'🚗','suv'=>'🚙','pickup'=>'🛻'];

// Cierre anterior (referencia)
$cierreAnt = null;
if ($turno === 'tarde') {
    $qa = $pdo->prepare("SELECT cc.efectivo_fisico, cc.diferencia, cc.hora_cierre, u.nombre AS cajero
        FROM $tc cc JOIN $tu u ON u.id=cc.usuario_id
        WHERE cc.turno='mañana' AND cc.fecha=? ORDER BY cc.id DESC LIMIT 1");
    $qa->execute([$hoy]); $cierreAnt = $qa->fetch();
} elseif ($turno === 'mañana') {
    $qa = $pdo->prepare("SELECT cc.efectivo_fisico, cc.diferencia, cc.hora_cierre, u.nombre AS cajero
        FROM $tc cc JOIN $tu u ON u.id=cc.usuario_id
        WHERE cc.turno='tarde' AND cc.fecha=DATE_SUB(?,INTERVAL 1 DAY) ORDER BY cc.id DESC LIMIT 1");
    $qa->execute([$hoy]); $cierreAnt = $qa->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Gestor Ecowash · Cierre</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#2a8ec1">
  <link rel="apple-touch-icon" href="/img/icono-192-app.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    *{box-sizing:border-box}
    body{background:#f0f6fa !important;font-family:system-ui,sans-serif;margin:0;padding-bottom:env(safe-area-inset-bottom,20px)}
    body::before{content:'';display:block;height:env(safe-area-inset-top,0);background:#1e293b;position:fixed;top:0;left:0;right:0;z-index:51}
    input{
      color:#1f2937 !important;-webkit-text-fill-color:#1f2937 !important;
      background:#ffffff !important;-webkit-box-shadow:0 0 0 40px #ffffff inset !important;
      box-shadow:0 0 0 40px #ffffff inset !important;caret-color:#1e6a94 !important;
      border:2px solid #d1e8f5 !important;border-radius:10px !important;
      padding:11px 14px !important;font-family:system-ui !important;
      width:100% !important;outline:none !important;-webkit-appearance:none !important;display:block !important;
    }
    input::placeholder{color:#9ca3af !important;-webkit-text-fill-color:#9ca3af !important}
    input:focus{border-color:#2a8ec1 !important;-webkit-box-shadow:0 0 0 40px #ffffff inset !important;box-shadow:0 0 0 40px #ffffff inset !important}
    .inp-lg{font-size:22px !important;font-weight:700 !important;padding-left:34px !important}
    .inp-md{font-size:16px !important;font-weight:600 !important;padding-left:34px !important}
    .card{background:#fff !important;border-radius:16px;padding:16px;margin-bottom:12px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
    .stit{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:12px}
    .kpi{background:#e8f4fb;border-radius:10px;padding:10px;text-align:center}
    .kpi-lbl{font-size:10px;font-weight:700;text-transform:uppercase;color:#1e6a94;margin-bottom:3px}
    .kpi-val{font-size:16px;font-weight:700;color:#1e6a94}
    .pfx{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-weight:700;color:#1e6a94;font-size:18px;pointer-events:none}
    .dif-box{border-radius:12px;padding:12px 14px;display:flex;justify-content:space-between;align-items:center;margin-top:8px}
    .dif-pos{background:#f0fdf4;border:1px solid #86efac}
    .dif-neg{background:#fef2f2;border:1px solid #fca5a5}
    .dif-zer{background:#eff6ff;border:1px solid #93c5fd}
    #btnCerrar{
      width:100%;padding:16px;border-radius:14px;border:none !important;
      background:#c7d03b !important;color:#3a4700 !important;-webkit-text-fill-color:#3a4700 !important;
      font-size:16px;font-weight:700;cursor:pointer;-webkit-appearance:none;font-family:system-ui;
      box-shadow:0 4px 16px rgba(199,208,59,.45);
    }
    #btnCerrar:disabled{background:#94a3b8 !important;color:#fff !important;-webkit-text-fill-color:#fff !important;cursor:not-allowed;box-shadow:none}
    .toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#1e6a94;color:#fff !important;padding:12px 24px;border-radius:50px;font-size:14px;font-weight:600;opacity:0;transition:opacity .3s;text-align:center;font-family:system-ui;white-space:nowrap;z-index:100;-webkit-text-fill-color:#fff !important}
    .toast.on{opacity:1}.toast.er{background:#dc2626 !important}
    .row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9}
    .row:last-child{border:none}
  </style>
</head>
<body>

<!-- HEADER oscuro para diferenciar del turno normal -->
<header style="background:#1e293b;position:sticky;top:0;z-index:50;box-shadow:0 2px 8px rgba(0,0,0,.3);padding-top:env(safe-area-inset-top,44px)">
  <div style="display:flex;align-items:center;gap:10px;padding:11px 14px">
    <a href="/pendientes.php" style="color:rgba(255,255,255,.7);font-size:20px;text-decoration:none">←</a>
    <img src="/img/icono-192-app.png" style="width:32px;height:32px;border-radius:50%;background:white;padding:2px;object-fit:contain" onerror="this.style.display='none'">
    <div>
      <div style="color:#fff;font-weight:700;font-size:13px">Cierre de turno</div>
      <div style="color:rgba(255,255,255,.6);font-size:11px">
        <?= h($nombre) ?> · <span style="color:#c7d03b">Turno <?= h($turno) ?></span> · <?= date('d/m/Y') ?>
      </div>
    </div>
  </div>
</header>

<div style="max-width:500px;margin:0 auto;padding:14px">

  <?php if ($cierreExiste): ?>
  <!-- Ya fue cerrado -->
  <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:16px;padding:20px;text-align:center">
    <div style="font-size:32px;margin-bottom:8px">⚠️</div>
    <div style="font-weight:700;color:#92400e;margin-bottom:6px">Este turno ya fue cerrado</div>
    <a href="/ingreso.php" style="display:inline-block;margin-top:12px;background:#2a8ec1;color:#fff;padding:10px 24px;border-radius:20px;font-size:13px;font-weight:700;text-decoration:none">Volver</a>
  </div>

  <?php elseif (empty($pagados)): ?>
  <!-- Sin cobros -->
  <div style="background:#fff;border-radius:16px;padding:32px;text-align:center">
    <div style="font-size:40px;margin-bottom:10px">🚫</div>
    <div style="font-weight:700;color:#374151;margin-bottom:6px">Sin cobros registrados</div>
    <div style="font-size:13px;color:#9ca3af;margin-bottom:16px">Necesitás al menos un cobro para cerrar el turno</div>
    <a href="/pendientes.php" style="display:inline-block;background:#2a8ec1;color:#fff;padding:10px 24px;border-radius:20px;font-size:13px;font-weight:700;text-decoration:none">Volver</a>
  </div>

  <?php else: ?>

  <!-- Efectivo recibido del turno anterior -->
  <?php if ($cierreAnt): ?>
  <div style="border-radius:14px;border:2px solid rgba(42,142,193,0.3);background:#e8f4fb;padding:12px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between">
    <div>
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#1e6a94">🔒 Recibido del <?= $turno==='tarde'?'turno mañana':'turno tarde (ayer)' ?></div>
      <div style="font-size:11px;color:#64748b;margin-top:2px"><?= h($cierreAnt->cajero) ?> · <?= date('H:i',strtotime($cierreAnt->hora_cierre)) ?></div>
    </div>
    <div style="text-align:right">
      <div style="font-size:22px;font-weight:700;color:#1e6a94">$<?= number_format($cierreAnt->efectivo_fisico,0,',','.') ?></div>
      <div style="font-size:10px;color:#94a3b8">Solo lectura</div>
    </div>
  </div>
  <?php endif ?>

  <!-- RESUMEN DEL TURNO -->
  <div class="card">
    <div class="stit">📋 Resumen del turno <?= h($turno) ?></div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px">
      <div class="kpi"><div class="kpi-lbl">Autos cobrados</div><div class="kpi-val" style="font-size:24px"><?= $total_autos ?></div></div>
      <div class="kpi"><div class="kpi-lbl">💵 Efectivo esp.</div><div class="kpi-val">$<?= number_format($total_ef_esp,0,',','.') ?></div></div>
      <div class="kpi"><div class="kpi-lbl">💳 Tarjeta</div><div class="kpi-val" style="color:#3b82f6">$<?= number_format($total_tarjeta,0,',','.') ?></div></div>
    </div>

    <!-- Detalle cobros -->
    <div style="border:1px solid #e2e8f0;border-radius:10px;overflow:hidden">
      <?php foreach ($pagados as $i => $r): ?>
      <div class="row" style="padding:8px 10px;<?= $i%2===0?'background:#f8fafc':'' ?>">
        <div style="display:flex;align-items:center;gap:7px">
          <span style="font-size:16px"><?= $iconos[$r->tipo_vehiculo]??'🚗' ?></span>
          <div>
            <div style="font-weight:700;font-size:12px;color:#1f2937"><?= h($r->patente) ?> <span style="font-weight:400;color:#6b7280">· <?= h($r->nombre_cliente) ?></span></div>
            <div style="font-size:11px;color:#9ca3af"><?= h($r->hora_pago) ?></div>
          </div>
        </div>
        <div style="text-align:right">
          <div style="font-weight:700;font-size:12px;color:#1e6a94">$<?= number_format($r->monto,0,',','.') ?></div>
          <div style="font-size:11px;color:<?= $r->metodo_pago==='efectivo'?'#16a34a':'#3b82f6' ?>"><?= $r->metodo_pago==='efectivo'?'💵':'💳' ?></div>
        </div>
      </div>
      <?php endforeach ?>
    </div>
  </div>

  <!-- AUTOS SIN PAGAR - solo lectura -->
  <?php if (!empty($sin_pagar)): ?>
  <div class="card" style="border:2px solid #fca5a5">
    <div class="stit" style="color:#dc2626">⚠️ Autos sin pagar (<?= count($sin_pagar) ?>)</div>
    <div style="font-size:12px;color:#64748b;margin-bottom:10px;margin-top:-6px">Calculado automáticamente · no modificable</div>
    <?php foreach ($sin_pagar as $r): ?>
    <div class="row">
      <div style="display:flex;align-items:center;gap:7px">
        <span style="font-size:16px"><?= $iconos[$r->tipo_vehiculo]??'🚗' ?></span>
        <div>
          <div style="font-weight:700;font-size:12px;color:#1f2937"><?= h($r->patente) ?> · <?= h($r->nombre_cliente) ?></div>
          <div style="font-size:11px;color:#9ca3af">Ingresó: <?= h($r->hora_ingreso) ?></div>
        </div>
      </div>
      <div style="font-weight:700;font-size:12px;color:#dc2626">$<?= number_format($r->monto,0,',','.') ?></div>
    </div>
    <?php endforeach ?>
    <div style="background:#fef2f2;border-radius:8px;padding:8px 10px;margin-top:8px;text-align:center;font-size:12px;color:#dc2626;font-weight:600">
      Notificá el faltante por WhatsApp al encargado
    </div>
  </div>
  <?php endif ?>

  <!-- DECLARACIÓN DE CAJA -->
  <div class="card">
    <div class="stit">💵 Declaración de caja</div>

    <!-- Efectivo físico -->
    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#1e6a94;margin-bottom:6px">Efectivo físico en caja *</label>
    <div style="position:relative;margin-bottom:14px">
      <span class="pfx">$</span>
      <input type="number" id="ef_fisico" class="inp-lg" placeholder="0" min="0" step="100" oninput="calcular()">
    </div>

    <!-- Gastos de insumos -->
    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#1e6a94;margin-bottom:6px">
      Gastos de insumos <span style="color:#9ca3af;font-weight:400;text-transform:none">(opcional)</span>
    </label>
    <div style="position:relative;margin-bottom:14px">
      <span class="pfx">$</span>
      <input type="number" id="gastos" class="inp-md" placeholder="0" min="0" step="100" oninput="calcular()">
    </div>

    <!-- Tarjeta (solo lectura) -->
    <div style="background:#eff6ff;border-radius:10px;padding:12px;display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#3b82f6;margin-bottom:2px">💳 Total tarjeta</div>
        <div style="font-size:11px;color:#64748b">Tomado del sistema · no modificable</div>
      </div>
      <div style="font-size:20px;font-weight:700;color:#3b82f6">$<?= number_format($total_tarjeta,0,',','.') ?></div>
    </div>

    <!-- Diferencia en tiempo real -->
    <div id="difBox" style="display:none"></div>
  </div>

  <!-- COMISIÓN -->
  <div style="background:rgba(199,208,59,.1);border:1px solid rgba(199,208,59,.3);border-radius:14px;padding:14px;text-align:center;margin-bottom:12px">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#a16207;margin-bottom:4px">Tu comisión del turno</div>
    <div style="font-size:28px;font-weight:700;color:#c7d03b">$<?= number_format($comision,0,',','.') ?></div>
    <div style="font-size:11px;color:#64748b;margin-top:2px"><?= $total_autos ?> autos cobrados × $100</div>
  </div>

  <button id="btnCerrar" onclick="procesarCierre()">🔒 Cerrar turno y enviar resumen</button>

  <!-- MODAL CONFIRMACIÓN -->
  <div id="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;align-items:flex-end;justify-content:center">
    <div style="background:#1e293b;border-radius:20px 20px 0 0;padding:24px;width:100%;max-width:500px;border-top:3px solid #c7d03b">
      <div style="font-weight:700;font-size:16px;color:#e2e8f0;text-align:center;margin-bottom:8px">🔒 ¿Confirmás el cierre?</div>
      <div style="font-size:13px;color:#64748b;text-align:center;margin-bottom:20px;line-height:1.6" id="modalTxt"></div>
      <div style="background:rgba(255,193,7,.1);border:1px solid rgba(255,193,7,.3);border-radius:10px;padding:10px;text-align:center;margin-bottom:16px">
        <div style="font-size:12px;color:#fbbf24;font-weight:600">⚠️ Esta acción es irreversible</div>
        <div style="font-size:11px;color:#64748b;margin-top:2px">Se cerrarán todos los registros del turno y se cerrará la sesión</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <button onclick="document.getElementById('modal').style.display='none'"
          style="padding:13px;border-radius:12px;border:2px solid #334155;font-size:14px;font-weight:700;color:#94a3b8;background:#0f172a;cursor:pointer;font-family:system-ui;-webkit-appearance:none">
          Cancelar
        </button>
        <button id="btnConf" onclick="confirmarCierre()"
          style="padding:13px;border-radius:12px;border:none;background:#c7d03b;color:#3a4700;-webkit-text-fill-color:#3a4700;font-size:14px;font-weight:700;cursor:pointer;font-family:system-ui;-webkit-appearance:none">
          Confirmar cierre
        </button>
      </div>
    </div>
  </div>

  <?php endif ?>
</div>

<div class="toast" id="tst"></div>

<script>
const TOTAL_EF_ESP  = <?= $total_ef_esp ?>;
const TOTAL_TJ      = <?= $total_tarjeta ?>;
const TOTAL_AUTOS   = <?= $total_autos ?>;
const SIN_PAGAR     = <?= count($sin_pagar) ?>;
const COMISION      = <?= $comision ?>;

function fmt(n){ return '$'+Math.round(n).toLocaleString('es-AR') }
function fabs(n){ return '$'+Math.abs(Math.round(n)).toLocaleString('es-AR') }

function calcular(){
  const ef     = parseFloat(document.getElementById('ef_fisico').value)||0;
  const gastos = parseFloat(document.getElementById('gastos').value)||0;
  const ef_neto = ef - gastos;
  const dif    = ef_neto - TOTAL_EF_ESP;
  const box    = document.getElementById('difBox');
  box.style.display = 'block';

  let cls, lbl, val, color, dsc;
  if (dif === 0) {
    cls='dif-zer'; lbl='✅ Cuadra perfectamente'; val='$0'; color='#2563eb';
    dsc='El efectivo neto coincide con lo esperado.';
  } else if (dif > 0) {
    cls='dif-pos'; lbl='⬆️ Sobrante'; val=fabs(dif); color='#16a34a';
    dsc=`Hay ${fabs(dif)} más de lo esperado.`;
  } else {
    cls='dif-neg'; lbl='⬇️ Faltante'; val=fabs(dif); color='#dc2626';
    dsc=`Faltan ${fabs(dif)} del total esperado.`;
  }

  box.innerHTML=`<div class="dif-box ${cls}">
    <div>
      <div style="font-size:13px;font-weight:600;color:#374151">${lbl}</div>
      <div style="font-size:11px;color:#6b7280;margin-top:3px">${dsc}${gastos>0?' (descontando '+fmt(gastos)+' de gastos)':''}</div>
    </div>
    <div style="font-size:20px;font-weight:700;color:${color}">${val}</div>
  </div>`;
}

function procesarCierre(){
  const ef = document.getElementById('ef_fisico').value.trim();
  if (!ef || parseFloat(ef) < 0) {
    toast('⚠️ Ingresá el efectivo físico en caja', true); return;
  }
  const efVal  = parseFloat(ef);
  const gastos = parseFloat(document.getElementById('gastos').value)||0;
  const efNeto = efVal - gastos;
  const dif    = efNeto - TOTAL_EF_ESP;
  const difTxt = dif===0 ? '✅ Cuadra exacto' : dif>0 ? `⬆️ Sobrante: ${fabs(dif)}` : `⬇️ Faltante: ${fabs(dif)}`;

  let txt = `<strong>${TOTAL_AUTOS} autos cobrados</strong><br>`;
  txt += `💵 Efectivo esperado: <strong>${fmt(TOTAL_EF_ESP)}</strong><br>`;
  if (gastos > 0) txt += `🔧 Gastos insumos: <strong>${fmt(gastos)}</strong><br>`;
  txt += `💵 Efectivo neto: <strong>${fmt(efNeto)}</strong><br>`;
  txt += `💳 Tarjeta: <strong>${fmt(TOTAL_TJ)}</strong><br>`;
  txt += `${difTxt}`;
  if (SIN_PAGAR > 0) txt += `<br><span style="color:#f87171">⚠️ ${SIN_PAGAR} auto${SIN_PAGAR>1?'s':''} sin cobrar</span>`;

  document.getElementById('modalTxt').innerHTML = txt;
  const m = document.getElementById('modal');
  m.style.display='flex';
  m.addEventListener('click', e=>{if(e.target===m) m.style.display='none'},{once:true});
}

async function confirmarCierre(){
  const ef     = parseFloat(document.getElementById('ef_fisico').value)||0;
  const gastos = parseFloat(document.getElementById('gastos').value)||0;
  const btn    = document.getElementById('btnConf');
  btn.disabled=true; btn.textContent='Procesando...';

  try{
    const res = await fetch('/cerrar.php',{
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        efectivo_fisico: ef,
        gastos_insumos: gastos,
      })
    });
    const d = await res.json();
    document.getElementById('modal').style.display='none';
    if (d.ok) {
      document.getElementById('btnCerrar').disabled=true;
      document.getElementById('btnCerrar').style.setProperty('background','#22c55e','important');
      document.getElementById('btnCerrar').style.setProperty('color','#fff','important');
      document.getElementById('btnCerrar').style.setProperty('-webkit-text-fill-color','#fff','important');
      document.getElementById('btnCerrar').textContent='✅ Turno cerrado — cerrando sesión...';
      toast('✅ Turno cerrado · ' + (d.webhook ? 'CRM actualizado' : 'Guardado localmente'));
      // Logout automático después de 2.5 segundos
      setTimeout(()=>window.location.href='/logout.php', 2500);
    } else {
      toast('⚠️ '+(d.error||'Error al procesar'), true);
      btn.disabled=false; btn.textContent='Confirmar cierre';
    }
  } catch(e) {
    toast('⚠️ Error de conexión', true);
    btn.disabled=false; btn.textContent='Confirmar cierre';
  }
}

function toast(msg,er=false){
  const t=document.getElementById('tst');
  t.textContent=msg; t.className='toast'+(er?' er':'');
  t.classList.add('on'); setTimeout(()=>t.classList.remove('on'),3500);
}
</script>
</body>
</html>
