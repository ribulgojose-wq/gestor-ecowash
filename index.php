<?php
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/auth.php');

if (!empty($_SESSION['ew_uid'])) {
    header('Location: /' . ($_SESSION['ew_rol']==='admin' ? 'admin.php' : 'ingreso.php')); exit;
}

$error  = '';
$msgs   = ['sesion_expirada'=>'Tu sesión expiró. Ingresá nuevamente.','sin_permiso'=>'Sin permiso para esa sección.'];
$info   = $msgs[$_GET['msg'] ?? ''] ?? '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $u = trim($_POST['usuario']  ?? '');
    $p = trim($_POST['password'] ?? '');
    if (!$u || !$p) {
        $error = 'Completá usuario y contraseña.';
    } elseif (ew_login($u, $p)) {
        header('Location: /' . ($_SESSION['ew_rol']==='admin' ? 'admin.php' : 'ingreso.php')); exit;
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Gestor Ecowash</title>
  <link rel="manifest" href="/manifest.json">
  <meta name="theme-color" content="#2a8ec1">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Gestor Ecowash">
  <link rel="apple-touch-icon" href="/img/icono-192-app.png">
  <link rel="apple-touch-startup-image" href="/img/splash-screen.png">
  <link rel="icon" type="image/png" href="/img/icono-192-app.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    *{box-sizing:border-box}
    html,body{height:100%;margin:0;padding:0}
    body{
      background:linear-gradient(160deg,#1e6a94 0%,#2a8ec1 55%,#3aa0d4 100%) !important;
      min-height:100vh;min-height:100dvh;
      display:flex;flex-direction:column;align-items:center;justify-content:center;
      font-family:system-ui,sans-serif;
      padding:env(safe-area-inset-top,0) 20px env(safe-area-inset-bottom,0);
    }
    input{
      color:#1f2937 !important;-webkit-text-fill-color:#1f2937 !important;
      background-color:#ffffff !important;
      -webkit-box-shadow:0 0 0 40px #ffffff inset !important;
      box-shadow:0 0 0 40px #ffffff inset !important;
      caret-color:#1e6a94 !important;
    }
    input::placeholder{color:#9ca3af !important;-webkit-text-fill-color:#9ca3af !important}
    input:focus{border-color:#2a8ec1 !important;outline:none !important}
    #btnLogin{
      width:100%;padding:14px;border-radius:12px;border:none !important;
      background-color:#2a8ec1 !important;color:#fff !important;
      -webkit-text-fill-color:#fff !important;
      font-size:15px;font-weight:700;cursor:pointer;font-family:system-ui;
      -webkit-appearance:none;
    }
    #btnLogin:hover{background-color:#1e6a94 !important}
    #btnLogin:active{transform:scale(.97)}
  </style>
</head>
<body>
  <div style="width:100%;max-width:380px">

    <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:28px">
      <div style="width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.18);padding:4px;margin-bottom:14px">
        <img src="/img/logo-ecowash-512x512.png" alt="Ecowash"
             style="width:92px;height:92px;border-radius:50%;object-fit:contain;background:white;padding:4px;display:block"
             onerror="this.src='/img/icono-192-app.png'">
      </div>
      <h1 style="color:#fff;font-size:22px;font-weight:700;margin:0">Gestor Ecowash</h1>
      <p style="color:rgba(255,255,255,.7);font-size:13px;margin:4px 0 0">Sistema de control de caja</p>
    </div>

    <div style="background:white;border-radius:20px;padding:24px;box-shadow:0 20px 50px rgba(0,0,0,.25)">

      <?php if ($info): ?>
        <div style="background:#e8f4fb;border-radius:10px;padding:10px 14px;font-size:13px;color:#1e6a94;margin-bottom:16px">
          <?= htmlspecialchars($info) ?>
        </div>
      <?php endif ?>

      <?php if ($error): ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px 14px;font-size:13px;color:#dc2626;margin-bottom:16px">
          ⚠️ <?= htmlspecialchars($error) ?>
        </div>
      <?php endif ?>

      <form method="POST" autocomplete="off" novalidate>
        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#1e6a94;margin-bottom:6px">Usuario</label>
        <input type="text" name="usuario" autocapitalize="none" placeholder="Tu nombre de usuario"
               value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>"
               style="width:100%;padding:12px 14px;border-radius:12px;border:2px solid #d1e8f5;font-size:15px;margin-bottom:14px;display:block"
               required>

        <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#1e6a94;margin-bottom:6px">Contraseña</label>
        <div style="position:relative;margin-bottom:20px">
          <input type="password" name="password" id="pwd" placeholder="••••••••"
                 style="width:100%;padding:12px 46px 12px 14px;border-radius:12px;border:2px solid #d1e8f5;font-size:15px;display:block"
                 required>
          <button type="button" onclick="togglePwd()"
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;font-size:18px;padding:4px">
            👁
          </button>
        </div>
        <button type="submit" id="btnLogin">Ingresar</button>
      </form>
    </div>

    <div style="text-align:center;margin-top:16px">
      <span style="background:#c7d03b;color:#4a5000;-webkit-text-fill-color:#4a5000;font-size:12px;font-weight:700;padding:4px 14px;border-radius:20px;display:inline-block">
        Hiper Libertad · Córdoba
      </span>
    </div>
  </div>

  <script>
    function togglePwd(){const i=document.getElementById('pwd');i.type=i.type==='password'?'text':'password'}
    if('serviceWorker' in navigator){
      window.addEventListener('load',()=>navigator.serviceWorker.register('/sw.js'));
    }
  </script>
</body>
</html>
