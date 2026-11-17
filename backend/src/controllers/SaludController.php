<?php
declare(strict_types=1);

/** Endpoints de diagnóstico para verificar que React, PHP y SQL Server se comunican. */
final class SaludController
{
    public function ping(array $parametros): void
    {
        Respuesta::ok(['estado' => 'API en línea', 'hora' => date('c')]);
    }

    public function salud(array $parametros): void
    {
        $pdo = Database::conexion();
        $fila = $pdo->query(
            'SELECT (SELECT COUNT(*) FROM clientes WHERE es_cliente = 1) AS clientes,
                    (SELECT COUNT(*) FROM prestamos)                     AS prestamos,
                    (SELECT COUNT(*) FROM prestamos_cuotas)              AS cuotas'
        )->fetch();

        Respuesta::ok([
            'api'       => 'en línea',
            'baseDatos' => 'conectada',
            'clientes'  => (int) $fila['clientes'],
            'prestamos' => (int) $fila['prestamos'],
            'cuotas'    => (int) $fila['cuotas'],
        ]);
    }
}
