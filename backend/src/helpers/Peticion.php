<?php
declare(strict_types=1);

/** Lectura segura de la petición: cuerpo JSON y parámetros de la ruta. */
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

    /** Id numérico de la ruta (/api/clientes/{id}); si no es válido responde 404. */
    public static function id(array $parametros, string $clave = 'id'): int
    {
        $valor = (string) ($parametros[$clave] ?? '');
        if (!preg_match('/^\d{1,9}$/', $valor) || (int) $valor < 1) {
            throw new ErrorApi('Recurso no encontrado.', 404);
        }
        return (int) $valor;
    }
}
