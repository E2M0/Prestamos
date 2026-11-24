<?php
declare(strict_types=1);

/** Rango de cuotas por tipo de plazo (tipos_plazo.plazo_minimo / plazo_maximo) en la validación de apertura. */
foreach (['PrestamoService', 'PrestamoValidador'] as $clase) {
    require_once dirname(__DIR__) . "/backend/src/services/$clase.php";
}

return function (): void {
    $plazos = [
        1 => ['id' => 1, 'nombre' => 'Diario',  'dias_periodo' => 1,  'meses_periodo' => 0, 'permite_excluir_fines_semana' => 1, 'plazo_minimo' => 15, 'plazo_maximo' => 365],
        2 => ['id' => 2, 'nombre' => 'Mensual', 'dias_periodo' => 30, 'meses_periodo' => 1, 'permite_excluir_fines_semana' => 0, 'plazo_minimo' => 1, 'plazo_maximo' => 120],
    ];
    $ctx = [
        'credito' => ['id' => 1, 'estado' => 'Activo', 'disponible' => 1e9],
        'tiposPrestamo' => [1 => ['id' => 1]],
        'plazos' => $plazos,
        'amortizaciones' => [1 => ['id' => 1, 'codigo' => 'ABSOLUTO']],
    ];
    $base = [
        'tipoPrestamoId' => 1, 'monto' => 10000, 'tasa' => 3, 'tipoAmortizacionId' => 1,
        'tipoMora' => 'Porcentaje', 'valorMora' => 5, 'seguroTipo' => 'Fijo', 'seguroMonto' => 0, 'gastoCierre' => 0,
        'fechaApertura' => '2026-11-23', 'fechaPrimerPago' => '2026-11-24',
    ];
    $prueba = function (int $plazoId, int $cuotas) use ($base, $ctx): array {
        return PrestamoValidador::validar($base + ['tipoPlazoId' => $plazoId, 'plazo' => $cuotas], $ctx)[0];
    };
    foreach ([[1, 15, false], [1, 14, true], [1, 365, false], [1, 366, true], [2, 1, false], [2, 120, false], [2, 121, true]] as [$p, $n, $debeFallar]) {
        $e = $prueba($p, $n);
        verificar("plazo $p con $n cuotas " . ($debeFallar ? 'rechazado' : 'aceptado'), isset($e['plazo']) === $debeFallar, $e);
    }
    $e = $prueba(1, 14);
    verificar('mensaje del rango', ($e['plazo'] ?? '') === 'El plazo Diario permite de 15 a 365 cuotas.', $e);
};
