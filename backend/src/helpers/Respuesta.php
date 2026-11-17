<?php
declare(strict_types=1);

/** Respuestas JSON uniformes: {ok:true,datos:...} o {ok:false,mensaje:...,errores:{...}} */
final class Respuesta
{
    public static function ok(mixed $datos = null, int $codigo = 200): never
    {
        self::enviar(['ok' => true, 'datos' => $datos], $codigo);
    }

    public static function error(string $mensaje, int $codigo = 400, array $errores = []): never
    {
        $cuerpo = ['ok' => false, 'mensaje' => $mensaje];
        if ($errores) {
            $cuerpo['errores'] = $errores;
        }
        self::enviar($cuerpo, $codigo);
    }

    private static function enviar(array $cuerpo, int $codigo): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
