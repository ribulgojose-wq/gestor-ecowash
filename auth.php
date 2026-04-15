<?php
require_once(__DIR__ . '/config.php');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime'=>28800,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}

function ew_hash(string $pass): string {
    return password_hash($pass, PASSWORD_DEFAULT);
}

function ew_verify(string $pass, string $hash): bool {
    return password_verify($pass, $hash);
}

function ew_login(string $usuario, string $password): bool {
    $pdo = getDB();
    $t   = DB_PREFIX . 'usuarios';
    $stmt = $pdo->prepare("SELECT id,nombre,usuario,pass_hash,rol,turno FROM $t WHERE usuario=? AND activo=1 LIMIT 1");
    $stmt->execute([trim($usuario)]);
    $u = $stmt->fetch();
    if ($u && ew_verify($password, $u->pass_hash)) {
        session_regenerate_id(true);
        $_SESSION['ew_uid']    = $u->id;
        $_SESSION['ew_nombre'] = $u->nombre;
        $_SESSION['ew_user']   = $u->usuario;
        $_SESSION['ew_rol']    = $u->rol;
        $_SESSION['ew_turno']  = $u->turno;
        $_SESSION['ew_ts']     = time();
        return true;
    }
    return false;
}

function ew_requiere_login(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['ew_uid'])) {
        header('Location: /index.php?msg=sesion_expirada'); exit;
    }
}

function ew_requiere_admin(): void {
    ew_requiere_login();
    if ($_SESSION['ew_rol'] !== 'admin') {
        header('Location: /ingreso.php?msg=sin_permiso'); exit;
    }
}

function ew_logout(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = []; session_destroy();
    header('Location: /index.php'); exit;
}

function ew_s(string $k): mixed { return $_SESSION[$k] ?? null; }
