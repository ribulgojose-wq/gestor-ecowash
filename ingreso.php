<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');
ew_requiere_login();

$nombre = ew_s('ew_nombre');
$turno  = ew_s('ew_turno');

// Contar pendientes del turno
$pdo = getDB();
$tl  = DB_PREFIX . 'lavados';
$q   = $pdo->prepare("SELECT COUNT(*) FROM $tl WHERE usuario_id=? AND turno=? AND fecha=? AND estado='en_proceso'");
$q->execute([(int)ew_s('ew_uid'), $turno, date('Y-m-d')]);
$pendientes = (int)$q->fetchColumn();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Gestor Ecowash · Ingreso</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#2a8ec1">
  <link rel="apple-touch-icon" href="/img/icono-192-app.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    *{box-sizing:border-box}
    body{background:#f0f6fa !important;font-family:system-ui,sans-serif;margin:0;padding-bottom:env(safe-area-inset-bottom,20px)}
    body::before{content:'';display:block;height:env(safe-area-inset-top,0);background:#2a8ec1;position:fixed;top:0;left:0;right:0;z-index:51}
    input{
      color:#1f2937 !important;-webkit-text-fill-color:#1f2937 !important;
      background:#ffffff !important;-webkit-box-shadow:0 0 0 40px #ffffff inset !important;
      box-shadow:0 0 0 40px #ffffff inset !important;caret-color:#1e6a94 !important;
      border:2px solid #d1e8f5 !important;border-radius:10px !important;
      padding:11px 14px !important;font-size:15px !important;
      font-family:system-ui !important;width:100% !important;
      outline:none !important;-webkit-appearance:none !important;display:block !important;
    }
    input::placeholder{color:#9ca3af !important;-webkit-text-fill-color:#9ca3af !important}
    input:focus{border-color:#2a8ec1 !important;-webkit-box-shadow:0 0 0 40px #ffffff inset !important;box-shadow:0 0 0 40px #ffffff inset !important}
    .vb{display:flex;flex-direction:column;align-items:center;padding:10px 4px;border-radius:12px;border:2px solid #d1e8f5 !important;font-size:12px;font-weight:700;color:#1e6a94 !important;-webkit-text-fill-color:#1e6a94 !important;background:#ffffff !important;cursor:pointer;gap:3px;-webkit-appearance:none;font-family:system-ui;transition:all .15s}
    .vb.sel{background:#2a8ec1 !important;color:#ffffff !important;-webkit-text-fill-color:#ffffff !important;border-color:#2a8ec1 !important;transform:scale(1.04)}
    #btnReg{width:100%;padding:15px;border-radius:14px;border:none !important;background:#c7d03b !important;color:#3a4700 !important;-webkit-text-fill-color:#3a4700 !important;font-size:16px;font-weight:700;cursor:pointer;-webkit-appearance:none;font-family:system-ui;box-shadow:0 4px 16px rgba(199,208,59,.45)}
    #btnReg:disabled{background:#94a3b8 !important;color:#fff !important;-webkit-text-fill-color:#fff !important;cursor:not-allowed;box-shadow:none}
    .lbl{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#1e6a94;margin-bottom:6px}
    .lbl-o{color:#9ca3af;font-weight:400;text-transform:none}
    .campo{margin-bottom:12px}
    .card{background:#fff !important;border-radius:16px;padding:18px;margin-bottom:12px;box-shadow:0 1px 4px rgba(0,0,0,.06)}
    .toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#1e6a94;color:#fff !important;-webkit-text-fill-color:#fff !important;padding:12px 24px;border-radius:50px;font-size:14px;font-weight:600;opacity:0;transition:opacity .3s;text-align:center;font-family:system-ui;white-space:nowrap;z-index:100}
    .toast.on{opacity:1}.toast.er{background:#dc2626 !important}
    .badge-pend{background:#fef3c7;color:#92400e;-webkit-text-fill-color:#92400e;font-size:12px;font-weight:700;padding:4px 10px;border-radius:20px;display:inline-flex;align-items:center;gap:4px}
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
    <div style="display:flex;align-items:center;gap:8px">
      <a href="/logout.php" style="background:rgba(255,255,255,.2);color:#fff;font-size:11px;padding:5px 10px;border-radius:20px;font-weight:500;text-decoration:none">Salir</a>
    </div>
  </div>
  <!-- Tabs -->
  <div style="display:flex;border-top:1px solid rgba(255,255,255,.15)">
    <div style="flex:1;padding:10px;text-align:center;background:rgba(255,255,255,.15);border-bottom:3px solid #c7d03b">
      <span style="color:#fff;font-size:13px;font-weight:700">➕ Registrar ingreso</span>
    </div>
    <a href="/pendientes.php" style="flex:1;padding:10px;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:6px">
      <span style="color:rgba(255,255,255,.8);font-size:13px;font-weight:600">⏳ Pendientes</span>
      <?php if($pendientes>0): ?>
        <span style="background:#c7d03b;color:#3a4700;font-size:11px;font-weight:700;padding:2px 7px;border-radius:20px"><?= $pendientes ?></span>
      <?php endif ?>
    </a>
  </div>
</header>

<div style="max-width:500px;margin:0 auto;padding:14px">

  <div class="card">
    <div style="font-size:15px;font-weight:700;color:#1e6a94;margin-bottom:16px">Datos del vehículo</div>

    <label class="lbl">Tipo de vehículo</label>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px">
      <?php foreach(['auto'=>[26000,'🚗'],'moto'=>[15000,'🏍️'],'suv'=>[28000,'🚙'],'pickup'=>[31000,'🛻']] as $t=>[$pr,$ico]): ?>
      <button class="vb<?= $t==='auto'?' sel':'' ?>" id="vb-<?= $t ?>" onclick="sV(this,'<?= $t ?>',<?= $pr ?>)" type="button">
        <span style="font-size:24px;line-height:1"><?= $ico ?></span>
        <span><?= ucfirst($t) ?></span>
        <span style="opacity:.7;font-weight:400;font-size:11px">$<?= number_format($pr/1000) ?>K</span>
      </button>
      <?php endforeach ?>
    </div>

    <div class="campo">
      <label class="lbl">Patente *</label>
      <input id="f-pat" placeholder="Ej: ABC123" oninput="this.value=this.value.toUpperCase()" style="text-transform:uppercase;font-weight:700;letter-spacing:.12em;font-size:18px !important">
    </div>

    <div style="font-size:15px;font-weight:700;color:#1e6a94;margin-bottom:14px;margin-top:4px;padding-top:14px;border-top:1px solid #f1f5f9">Datos del cliente</div>

    <div class="campo">
      <label class="lbl">Nombre completo *</label>
      <input id="f-nom" placeholder="Ej: Juan Pérez">
    </div>
    <div class="campo">
      <label class="lbl">Teléfono *</label>
      <input id="f-tel" type="tel" placeholder="Ej: 351 123 4567">
    </div>
    <div class="campo">
      <label class="lbl">Marca / Modelo <span class="lbl-o">(opcional)</span></label>
      <input id="f-mar" placeholder="Ej: Toyota Corolla">
    </div>
    <div class="campo">
      <label class="lbl">Código campaña <span class="lbl-o">(Meta Ads)</span></label>
      <input id="f-cod" placeholder="Ej: PROMO20" oninput="this.value=this.value.toUpperCase()" style="text-transform:uppercase">
    </div>
    <div class="campo">
      <label class="lbl">Hora estimada de retiro <span class="lbl-o">(opcional)</span></label>
      <input id="f-ret" type="time">
    </div>

    <!-- Monto informativo -->
    <div style="background:#e8f4fb;border-radius:10px;padding:12px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
      <div>
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#1e6a94;margin-bottom:2px">Monto a cobrar</div>
        <div style="font-size:11px;color:#64748b">Precio fijo por categoría</div>
      </div>
      <div style="font-size:26px;font-weight:700;color:#1e6a94" id="montoDisplay">$26.000</div>
    </div>

    <button id="btnReg" onclick="registrar()" type="button">➕ Registrar ingreso</button>
  </div>

  <!-- Botón cierre de turno -->
  <button onclick="confirmarCierre()" type="button"
    style="width:100%;padding:12px;border-radius:14px;background:#f1f5f9;color:#64748b;-webkit-text-fill-color:#64748b;font-size:13px;font-weight:600;border:2px solid #e2e8f0;cursor:pointer;font-family:system-ui;-webkit-appearance:none">
    🔒 Cerrar turno <?= h($turno) ?>
  </button>
</div>

<!-- Modal cierre -->
<div id="modalCierre" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;align-items:flex-end;justify-content:center">
  <div style="background:#1e293b;border-radius:20px 20px 0 0;padding:24px;width:100%;max-width:500px;border-top:3px solid #c7d03b">
    <div style="font-size:16px;font-weight:700;color:#e2e8f0;text-align:center;margin-bottom:6px">🔒 ¿Cerrar turno <?= h($turno) ?>?</div>
    <div style="font-size:13px;color:#64748b;text-align:center;margin-bottom:20px">Vas a ir a la pantalla de conteo de efectivo y cierre</div>
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
const PRC = {auto:26000,moto:15000,suv:28000,pickup:31000};
let veh = 'auto';

function sV(el,t,pr){
  veh=t;
  document.querySelectorAll('.vb').forEach(b=>b.classList.remove('sel'));
  el.classList.add('sel');
  document.getElementById('montoDisplay').textContent='$'+pr.toLocaleString('es-AR');
}

function toast(msg,er=false){
  const t=document.getElementById('tst');
  t.textContent=msg;t.className='toast'+(er?' er':'');
  t.classList.add('on');setTimeout(()=>t.classList.remove('on'),3000);
}

function confirmarCierre(){
  const m=document.getElementById('modalCierre');
  m.style.display='flex';
  m.addEventListener('click',e=>{if(e.target===m)m.style.display='none'},{once:true});
}

async function registrar(){
  const pat=document.getElementById('f-pat').value.trim();
  const nom=document.getElementById('f-nom').value.trim();
  const tel=document.getElementById('f-tel').value.trim();
  const mar=document.getElementById('f-mar').value.trim();
  const cod=document.getElementById('f-cod').value.trim();

  if(!pat) return toast('⚠️ Ingresá la patente',true);
  if(!nom) return toast('⚠️ Ingresá el nombre del cliente',true);
  if(!tel) return toast('⚠️ Ingresá el teléfono',true);

  const btn=document.getElementById('btnReg');
  btn.disabled=true;btn.textContent='⏳ Registrando...';

  try{
    const res=await fetch('/registrar.php',{
      method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({patente:pat,nombre_cliente:nom,telefono:tel,
        marca_modelo:mar,tipo_vehiculo:veh,codigo_campana:cod,
        hora_retiro:document.getElementById('f-ret').value})
    });
    const d=await res.json();
    if(d.ok){
      btn.style.setProperty('background','#22c55e','important');
      btn.style.setProperty('color','#fff','important');
      btn.style.setProperty('-webkit-text-fill-color','#fff','important');
      btn.textContent='✅ ¡Registrado! Esperando pago';
      toast('✅ '+nom+' registrado — pendiente de pago');

      // Actualizar contador en tab
      setTimeout(()=>{
        // Limpiar formulario
        document.getElementById('f-pat').value='';
        document.getElementById('f-nom').value='';
        document.getElementById('f-tel').value='';
        document.getElementById('f-mar').value='';
        document.getElementById('f-cod').value='';
        btn.style.setProperty('background','#c7d03b','important');
        btn.style.setProperty('color','#3a4700','important');
        btn.style.setProperty('-webkit-text-fill-color','#3a4700','important');
        btn.textContent='➕ Registrar ingreso';
        btn.disabled=false;
        document.getElementById('f-pat').focus();
        // Ir a pendientes para que el cajero vea el auto en la lista
        window.location.href='/pendientes.php';
      },1500);
    }else{
      toast('⚠️ '+(d.errores?d.errores.join(' · '):d.error),true);
      btn.style.setProperty('background','#c7d03b','important');
      btn.style.setProperty('color','#3a4700','important');
      btn.style.setProperty('-webkit-text-fill-color','#3a4700','important');
      btn.textContent='➕ Registrar ingreso';
      btn.disabled=false;
    }
  }catch(e){
    toast('⚠️ Error de conexión',true);
    btn.style.setProperty('background','#c7d03b','important');
    btn.style.setProperty('color','#3a4700','important');
    btn.style.setProperty('-webkit-text-fill-color','#3a4700','important');
    btn.textContent='➕ Registrar ingreso';
    btn.disabled=false;
  }
}
</script>
</body>
</html>
