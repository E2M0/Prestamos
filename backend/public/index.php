<?php
declare(strict_types=1);

/**
 * Front controller: todas las peticiones /api/... entran por aquí.
 * Arranque:  php -S localhost:8000 -t public public/index.php   (desde la carpeta backend)
 */

// RAIZ = carpeta con src/, lib/ y logs/.
//  - Desarrollo: backend/ (este archivo está en backend/public).
//  - Hosting:    htdocs/app/ (index.php está en htdocs y la lógica queda fuera del alcance de la web).
define('RAIZ', is_dir(__DIR__ . '/app/src') ? __DIR__ . '/app' : dirname(__DIR__));

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

// Modo "todo en uno": si existe frontend/dist (npm run build), PHP también entrega la
// aplicación React. Así un solo puerto (8000) sirve página y API, útil con un túnel.
$rutaPeticion = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if (!str_starts_with($rutaPeticion, '/api')) {
    $carpetaWeb = realpath(is_file(__DIR__ . '/index.html') ? __DIR__ : dirname(RAIZ) . '/frontend/dist');
    if ($carpetaWeb === false) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(404);
        echo 'No existe frontend/dist. Ejecute "npm run build" en la carpeta frontend.';
        exit;
    }
    $tipos = [
        'html' => 'text/html; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8', 'svg' => 'image/svg+xml', 'png' => 'image/png',
        'jpg' => 'image/jpeg', 'ico' => 'image/x-icon', 'json' => 'application/json',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'map' => 'application/json',
    ];
    $archivo = realpath($carpetaWeb . $rutaPeticion);
    $extension = $archivo === false ? '' : strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    // Solo se sirven archivos estáticos conocidos, dentro de la carpeta web y fuera de app/
    // (nunca .php ni configuración); cualquier otra ruta es de React Router y devuelve index.html.
    $permitido = $archivo !== false && is_file($archivo)
        && isset($tipos[$extension])
        && str_starts_with($archivo, $carpetaWeb . DIRECTORY_SEPARATOR)
        && !str_starts_with($archivo, $carpetaWeb . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR);
    if (!$permitido) {
        $archivo = $carpetaWeb . DIRECTORY_SEPARATOR . 'index.html';
        $extension = 'html';
    }
    header('Content-Type: ' . ($tipos[$extension] ?? 'application/octet-stream'));
    readfile($archivo);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$rutaConfig = RAIZ . '/src/config/config.php';
if (!is_file($rutaConfig)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'mensaje' => 'Falta src/config/config.php. Copie config.example.php (o config.hosting.example.php en un hosting) y ajuste los valores.',
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
