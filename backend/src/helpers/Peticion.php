<?php
declare(strict_types=1);

/** Lectura segura del cuerpo JSON de la petición. */
final class Peticion
{
    public static function json(): array
    {
        $crudo = file_get_contents('php://input');
        if ($crudo === false || trim($crudo) === '') {
            return [];
        }
        $datos = json_decode($crudo, true);
        if (!is_array($datos)) {
            throw new ErrorApi('El cuerpo de la petición no es un JSON válido.', 400);
        }
        return $datos;
    }
}
