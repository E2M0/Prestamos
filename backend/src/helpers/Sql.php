<?php
declare(strict_types=1);

/** Utilidades SQL (MySQL / MariaDB). */
final class Sql
{
    /**
     * Convierte un texto en patrón LIKE "contiene", escapando los comodines de MySQL
     * (%, _ y la barra invertida) para que el usuario no pueda alterar la búsqueda.
     */
    public static function contiene(string $texto): string
    {
        return '%' . strtr($texto, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
    }
}
