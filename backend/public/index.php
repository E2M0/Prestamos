<?php
declare(strict_types=1);

/**
 * Front controller: todas las peticiones /api/... entran por aquí.
 * Arranque:  php -S localhost:8000 -t public public/index.php   (desde la carpeta backend)
 */

define('RAIZ', dirname(__DIR__));

// Autocarga sencilla: busca la clase en las carpetas de src/
spl_autoload_register(function (string $clase): void {
    foreach (['config', 'helpers', 'controllers', 'models', 'services'] as $carpeta) {
        $ruta = RAIZ . "/src/$carpeta/$clase.php";
        if (is_file($ruta)) {
            require $ruta;
            return;
        }
    }
});

header('Content-Type: application/json; charset=utf-8');

$rutaConfig = RAIZ . '/src/config/config.php';
if (!is_file($rutaConfig)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Falta backend/src/config/config.php. Copie config.example.php y ajuste los valores.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

Configuracion::cargar(require $rutaConfig);
date_default_timezone_set((string) Configuracion::obtener('zona_horaria', 'America/Santo_Domingo'));

ManejadorErrores::registrar();
Cors::aplicar();
Sesion::iniciar();

$router = new Router();
require RAIZ . '/src/routes/api.php';

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$router->despachar($_SERVER['REQUEST_METHOD'] ?? 'GET', $ruta);
