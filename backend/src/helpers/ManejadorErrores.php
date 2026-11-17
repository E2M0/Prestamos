<?php
declare(strict_types=1);

/**
 * Errores controlados:
 *  - ErrorApi: se responde con su mensaje y su código HTTP.
 *  - Cualquier otro error: el detalle técnico se guarda en backend/logs/error.log
 *    y al cliente solo se le devuelve un mensaje genérico (sin información sensible).
 */
final class ManejadorErrores
{
    public static function registrar(): void
    {
        ini_set('display_errors', '0');
        error_reporting(E_ALL);

        // Convierte avisos y advertencias de PHP en excepciones
        set_error_handler(function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
            if (!(error_reporting() & $nivel)) {
                return false;
            }
            throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
        });

        set_exception_handler(function (Throwable $e): void {
            if ($e instanceof ErrorApi) {
                Respuesta::error($e->getMessage(), $e->estado, $e->errores);
            }
            self::registrarEnLog($e);
            Respuesta::error('Ocurrió un error interno. Intente de nuevo o contacte al administrador.', 500);
        });
    }

    private static function registrarEnLog(Throwable $e): void
    {
        $carpeta = RAIZ . '/logs';
        if (!is_dir($carpeta)) {
            @mkdir($carpeta, 0775, true);
        }
        $linea = sprintf("[%s] %s: %s en %s:%d\n", date('Y-m-d H:i:s'), get_class($e), $e->getMessage(), $e->getFile(), $e->getLine());
        @file_put_contents($carpeta . '/error.log', $linea, FILE_APPEND);
    }
}
