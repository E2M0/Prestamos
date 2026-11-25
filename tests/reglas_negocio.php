<?php
declare(strict_types=1);

/** Reglas de negocio (tabla reglas_negocio): tasa, monto mínimo, mora, seguro, gasto de cierre e interés total. */
foreach (['PrestamoService', 'PrestamoValidador'] as $clase) {
    require_once dirname(__DIR__) . "/backend/src/services/$clase.php";
}

return function (): void {
    $reglas = [
        'tasa_minima' => 0.5, 'tasa_maxima' => 10, 'monto_minimo' => 1000, 'mora_porcentaje_maxima' => 10,
        'mora_fija_maxima_pct' => 5, 'seguro_total_maximo_pct' => 10, 'gasto_cierre_maximo_pct' => 10,
        'interes_total_maximo_pct' => 100,
    ];
    $ctx = [
        'credito' => ['id' => 1, 'estado' => 'Activo', 'disponible' => 1e9],
        'tiposPrestamo' => [1 => ['id' => 1]],
        'plazos' => [2 => ['id' => 2, 'nombre' => 'Mensual', 'dias_periodo' => 30, 'meses_periodo' => 1, 'permite_excluir_fines_semana' => 0, 'plazo_minimo' => 1, 'plazo_maximo' => 120]],
        'amortizaciones' => [1 => ['id' => 1, 'codigo' => 'ABSOLUTO']],
        'reglas' => $reglas,
    ];
    $base = [
        'tipoPrestamoId' => 1, 'monto' => 10000, 'plazo' => 12, 'tasa' => 3, 'tipoPlazoId' => 2, 'tipoAmortizacionId' => 1,
        'tipoMora' => 'Porcentaje', 'valorMora' => 5, 'seguroTipo' => 'Fijo', 'seguroMonto' => 0, 'gastoCierre' => 0,
        'fechaApertura' => '2026-11-23', 'fechaPrimerPago' => '2026-12-23',
    ];
    $errores = fn(array $cambios) => PrestamoValidador::validar($cambios + $base, $ctx)[0];

    verificar('préstamo normal sin errores', $errores([]) === [], $errores([]));
    $casos = [
        'monto bajo' => [['monto' => 999], 'monto', true], 'monto justo' => [['monto' => 1000], 'monto', false],
        'tasa baja' => [['tasa' => 0.4], 'tasa', true], 'tasa mínima' => [['tasa' => 0.5], 'tasa', false],
        'tasa alta' => [['tasa' => 10.01], 'tasa', true], 'tasa máxima' => [['tasa' => 10], 'tasa', false],
        'mora % alta' => [['valorMora' => 10.5], 'valorMora', true], 'mora % tope' => [['valorMora' => 10], 'valorMora', false],
        'mora fija alta' => [['tipoMora' => 'Monto', 'valorMora' => 501], 'valorMora', true],
        'mora fija tope' => [['tipoMora' => 'Monto', 'valorMora' => 500], 'valorMora', false],
        'seguro fijo ×12 alto' => [['seguroMonto' => 100], 'seguroMonto', true],   // 1,200 > 1,000
        'seguro fijo ×12 tope' => [['seguroMonto' => 83], 'seguroMonto', false],   // 996
        'seguro repartido alto' => [['seguroTipo' => 'Repartido', 'seguroMonto' => 1001], 'seguroMonto', true],
        'seguro repartido tope' => [['seguroTipo' => 'Repartido', 'seguroMonto' => 1000], 'seguroMonto', false],
        'gasto alto' => [['gastoCierre' => 1001], 'gastoCierre', true], 'gasto tope' => [['gastoCierre' => 1000], 'gastoCierre', false],
    ];
    foreach ($casos as $nombre => [$cambios, $clave, $falla]) {
        $e = $errores($cambios);
        verificar("regla: $nombre " . ($falla ? 'rechazado' : 'aceptado'), isset($e[$clave]) === $falla, $e);
    }
    $sinReglas = PrestamoValidador::validar(['tasa' => 50, 'monto' => 10] + $base, ['reglas' => []] + $ctx)[0];
    verificar('sin reglas no hay límites de negocio', !isset($sinReglas['tasa']) && !isset($sinReglas['monto']), $sinReglas);

    verificar('interés total dentro del tope', PrestamoValidador::errorInteresTotal($reglas, 10000, 10000.0) === null);
    verificar('interés total sobre el tope', PrestamoValidador::errorInteresTotal($reglas, 10000, 10001.0) !== null);
    verificar('interés total sin regla', PrestamoValidador::errorInteresTotal([], 10000, 99999.0) === null);
};
