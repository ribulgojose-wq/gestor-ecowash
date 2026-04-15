<?php
if (defined('EW_LOADED')) return;

// ============================================================
// CREDENCIALES MySQL
// ============================================================
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'u773413067_appecowash');
define('DB_USER', 'u773413067_appecowash_use');
define('DB_PASS', '1234@Libertad');  // ← reemplazá con tu contraseña

// ============================================================
// CONSTANTES DE LA APP
// ============================================================
define('EW_LOADED',           true);
define('EW_WEBHOOK_CLIENTES', 'https://hook.us2.make.com/9nf6hvpmi51wzibksjze8ir4a87entjj');
define('EW_WEBHOOK_CIERRE',   'https://hook.us2.make.com/cvg0zwdsc1och9icrmhgzeaclk3s1k9t');
define('EW_COMISION',         100.00);
define('EW_SUCURSAL',         'Eco Wash Service');
define('DB_PREFIX',           'ew_');

// ============================================================
// CONEXIÓN PDO
// ============================================================
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error'=>true,'msg'=>'Error de conexión a la base de datos.']));
        }
    }
    return $pdo;
}
