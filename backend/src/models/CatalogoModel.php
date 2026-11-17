<?php
declare(strict_types=1);

/** Catálogos del módulo de clientes. */
final class CatalogoModel
{
    public function tiposDocumento(): array
    {
        return Database::conexion()
            ->query('SELECT id, nombre, mascara, longitud, patron FROM tipos_documento ORDER BY id')
            ->fetchAll();
    }

    public function nacionalidades(): array
    {
        return Database::conexion()
            ->query('SELECT id, nombre FROM nacionalidades ORDER BY nombre')
            ->fetchAll();
    }
}
