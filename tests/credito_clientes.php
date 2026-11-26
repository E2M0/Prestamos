<?php
declare(strict_types=1);

/** Límite de crédito y clasificación de los clientes (rangos y coherencia). */
require_once dirname(__DIR__) . '/backend/src/services/ClienteValidador.php';

return function (): void {
    $v = new ClienteValidador([1 => ['id' => 1, 'nombre' => 'Cédula', 'longitud' => 11]], [1 => true]);
    $e = fn($limite, $clasif) => array_intersect_key(
        $v->cliente(['limiteCredito' => $limite, 'clasificacion' => $clasif]),
        ['limiteCredito' => 1, 'clasificacion' => 1]
    );

    // Cortes entre clasificaciones
    $casos = [
        [5000, 'C'], [50000, 'C'], [100000, 'C'], [100000.5, 'B'], [100001, 'B'], [1000000, 'B'],
        [1000000.5, 'A'], [1000001, 'A'], [5000000, 'A'], [10000000, 'A'],
    ];
    foreach ($casos as [$l, $c]) {
        verificar("límite $l → $c", ClienteValidador::clasificacionPara((float) $l) === $c);
        verificar("límite $l con $c es válido", $e($l, $c) === [], json_encode($e($l, $c)));
        foreach (['A', 'B', 'C'] as $otra) {
            if ($otra !== $c) {
                verificar("límite $l con $otra se rechaza", array_keys($e($l, $otra)) === ['clasificacion']);
            }
        }
    }

    // Fuera de rango
    foreach ([0, 4999.99, -1, -5000, 10000000.01, 10000000000, 'abc', ''] as $l) {
        verificar('límite fuera de rango se rechaza: ' . var_export($l, true), isset($e($l, 'C')['limiteCredito']));
        verificar('límite fuera de rango no tiene clasificación', ClienteValidador::clasificacionPara((float) $l) === null || $l === '');
    }

    // Clasificación inválida
    foreach (['', 'D', 'a', 'AB', null] as $c) {
        verificar('clasificación inválida se rechaza: ' . var_export($c, true), isset($e(5000, $c)['clasificacion']));
    }
    verificar('mensaje de coherencia en español', str_contains($e(5000, 'A')['clasificacion'], 'corresponde la clasificación C'));
};
