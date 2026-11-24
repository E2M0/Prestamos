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

    /** Tipos de plazo (diario … anual). El orden es el de los períodos, de menor a mayor. */
    public function tiposPlazo(): array
    {
        return Database::conexion()
            ->query('SELECT id, nombre, dias_periodo, meses_periodo, permite_excluir_fines_semana,
                            plazo_minimo, plazo_maximo
                       FROM tipos_plazo ORDER BY dias_periodo, id')
            ->fetchAll();
    }

    public function tiposAmortizacion(): array
    {
        return Database::conexion()
            ->query('SELECT id, codigo, nombre FROM tipos_amortizacion ORDER BY id')
            ->fetchAll();
    }
}
