<?php
declare(strict_types=1);

/** Guarda la configuración cargada y permite leerla con claves tipo "db.servidor". */
final class Configuracion
{
    private static array $valores = [];

    public static function cargar(array $valores): void
    {
        self::$valores = $valores;
    }

    public static function obtener(string $clave, mixed $porDefecto = null): mixed
    {
        $actual = self::$valores;
        foreach (explode('.', $clave) as $parte) {
            if (!is_array($actual) || !array_key_exists($parte, $actual)) {
                return $porDefecto;
            }
            $actual = $actual[$parte];
        }
        return $actual;
    }
}
