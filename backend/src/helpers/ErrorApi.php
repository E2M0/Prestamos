<?php
declare(strict_types=1);

/**
 * Error "esperado" de la API (validación, credenciales, regla de negocio).
 * El mensaje SÍ se muestra al usuario; el código HTTP y los errores por campo viajan en la respuesta.
 * Ejemplo: throw new ErrorApi('Datos inválidos.', 422, ['monto' => 'Debe ser mayor que cero.']);
 */
final class ErrorApi extends RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly int $estado = 400,
        public readonly array $errores = []
    ) {
        parent::__construct($mensaje);
    }
}
