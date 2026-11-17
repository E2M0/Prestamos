<?php
declare(strict_types=1);

/** CORS: solo se aceptan los orígenes listados en config.php (cors.origenes). */
final class Cors
{
    public static function aplicar(): void
    {
        $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
        $permitidos = (array) Configuracion::obtener('cors.origenes', []);

        header('Vary: Origin');
        if ($origen !== '' && in_array($origen, $permitidos, true)) {
            header("Access-Control-Allow-Origin: $origen");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Max-Age: 600');
        }

        // Las peticiones de verificación previa (preflight) terminan aquí
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
