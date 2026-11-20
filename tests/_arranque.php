<?php
declare(strict_types=1);

/**
 * Arranque de las pruebas automatizadas. Carga solo los servicios de cálculo (no usan base de datos ni sesión),
 * por eso las pruebas corren sin SQL Server:   php tests/ejecutar.php
 */
$src = dirname(__DIR__) . '/backend/src/';
foreach (['helpers/ErrorApi', 'services/CalendarioService', 'services/AmortizacionService', 'services/MoraService',
          'services/PagoService', 'services/CancelacionService'] as $archivo) {
    require_once $src . $archivo . '.php';
}

$GLOBALS['prueba'] = ['ok' => 0, 'fallas' => []];

/** Registra el resultado de una verificación. */
function verificar(string $nombre, bool $condicion, mixed $detalle = null): void
{
    if ($condicion) {
        $GLOBALS['prueba']['ok']++;
        return;
    }
    $GLOBALS['prueba']['fallas'][] = $nombre . ($detalle !== null ? ' → ' . json_encode($detalle, JSON_UNESCAPED_UNICODE) : '');
}

function centavos(float|int $pesos): int
{
    return (int) round($pesos * 100);
}

/** Préstamo de ejemplo con valores sobrescribibles. */
function prestamoBase(array $cambios = []): array
{
    return $cambios + [
        'monto' => 12000.0, 'plazo' => 3, 'tasa' => 3.0, 'codigo' => 'ABSOLUTO',
        'plazoRow' => ['dias_periodo' => 30, 'meses_periodo' => 1, 'permite_excluir_fines_semana' => 0],
        'incluyeSabado' => true, 'incluyeDomingo' => true, 'seguroTipo' => 'Fijo', 'seguroMonto' => 0.0,
        'gastoCierre' => 0.0, 'gastoFinanciado' => false, 'fechaPrimerPago' => new DateTimeImmutable('2026-10-01'),
        'tipoMora' => 'Porcentaje', 'valorMora' => 5.0,
    ];
}

/** Cuotas del motor en la forma que usan los servicios de pago (camelCase, sin pagos). */
function cuotasIniciales(array $calculo): array
{
    $cuotas = [];
    foreach ($calculo['cuotas'] as $q) {
        $cuotas[] = [
            'id' => $q['numero'], 'numero' => $q['numero'], 'fechaPago' => $q['fechaPago'], 'capital' => $q['capital'],
            'interes' => $q['interes'], 'seguro' => $q['seguro'], 'gasto' => $q['gastoCierre'], 'mora' => 0.0,
            'valor' => $q['valorCuota'], 'capitalPagado' => 0.0, 'interesPagado' => 0.0, 'seguroPagado' => 0.0,
            'gastoPagado' => 0.0, 'moraPagada' => 0.0, 'balance' => $q['balance'], 'estado' => 'Pendiente',
        ];
    }
    return $cuotas;
}

/** Aplica el resultado de PagoService::planear a la lista de cuotas (lo que hace el servidor al guardar). */
function aplicarPlan(array $cuotas, array $plan): array
{
    $porNumero = [];
    foreach ($cuotas as $q) {
        $porNumero[$q['numero']] = $q;
    }
    foreach ($plan['cuotasEliminadas'] ?? [] as $numero) {
        unset($porNumero[$numero]);
    }
    foreach ($plan['cuotasCambiadas'] as $q) {
        $porNumero[$q['numero']] = $q;
    }
    foreach ($plan['cuotasNuevas'] ?? [] as $q) {
        $porNumero[$q['numero']] = $q;
    }
    ksort($porNumero);
    return array_values($porNumero);
}
