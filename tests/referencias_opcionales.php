<?php
declare(strict_types=1);

/** Referencias personales y familiares opcionales en el registro de clientes. */
require_once dirname(__DIR__) . '/backend/src/services/ClienteValidador.php';

return function (): void {
    $v = new ClienteValidador([1 => ['id' => 1, 'nombre' => 'Cédula', 'longitud' => 11]], [1 => true]);
    $claves = fn(array $d) => array_values(array_filter(array_keys($v->cliente($d)), fn($k) => str_starts_with($k, 'referencias')));
    $vacia = ['nombre' => '', 'telefono' => ''];
    $ok = ['nombre' => 'Ana Pérez', 'telefono' => '809-555-1234'];

    verificar('sin referencias es válido', $claves([]) === [], $claves([]));
    verificar('tres filas vacías son válidas', $claves(['referencias' => [$vacia, $vacia, $vacia]]) === []);
    verificar('una sola referencia completa es válida', $claves(['referencias' => [$ok, $vacia, $vacia]]) === []);
    verificar('tres completas son válidas', $claves(['referencias' => [$ok, $ok, $ok]]) === []);
    verificar('solo nombre se rechaza', $claves(['referencias' => [['nombre' => 'Ana Pérez', 'telefono' => ''], $vacia, $vacia]]) === ['referencias.0.telefono']);
    verificar('solo teléfono se rechaza', $claves(['referencias' => [$vacia, ['nombre' => '', 'telefono' => '809-555-1234'], $vacia]]) === ['referencias.1.nombre']);
    verificar('teléfono mal formado se rechaza', $claves(['referencias' => [['nombre' => 'Ana Pérez', 'telefono' => '123'], $vacia, $vacia]]) === ['referencias.0.telefono']);
    verificar('más de tres se rechaza', $claves(['referencias' => [$ok, $ok, $ok, $ok]]) === ['referencias']);
    verificar('fila vacía detectada', ClienteValidador::referenciaVacia($vacia) && !ClienteValidador::referenciaVacia($ok));
};
