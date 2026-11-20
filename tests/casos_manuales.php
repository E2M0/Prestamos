<?php
declare(strict_types=1);

/** Casos con resultados calculados a mano (préstamo de RD$ 12,000, 3 cuotas mensuales, 3 % mensual). */
return function (): void {
    // --- Absoluto: capital 4,000 + interés 360 en cada cuota
    $abs = AmortizacionService::calcular(prestamoBase());
    foreach ($abs['cuotas'] as $q) {
        verificar("ABSOLUTO cuota {$q['numero']} = 4,360", $q['valorCuota'] === 4360.0, $q);
    }
    verificar('ABSOLUTO intereses totales = 1,080', $abs['resumen']['totalIntereses'] === 1080.0);
    verificar('ABSOLUTO balance final = 0', end($abs['cuotas'])['balance'] === 0.0);
    verificar('ABSOLUTO primera fecha = 2026-10-01', $abs['cuotas'][0]['fechaPago'] === '2026-10-01');
    verificar('ABSOLUTO segunda fecha = 2026-11-01', $abs['cuotas'][1]['fechaPago'] === '2026-11-01');

    // --- Insoluto: cuota ≈ 4,242.36; interés sobre el balance
    $ins = AmortizacionService::calcular(prestamoBase(['codigo' => 'INSOLUTO']));
    verificar('INSOLUTO cuota 1 = 4,242.36', $ins['cuotas'][0]['valorCuota'] === 4242.36, $ins['cuotas'][0]);
    verificar('INSOLUTO interés 1 = 360.00', $ins['cuotas'][0]['interes'] === 360.0);
    verificar('INSOLUTO interés 2 = 243.53', $ins['cuotas'][1]['interes'] === 243.53, $ins['cuotas'][1]);
    verificar('INSOLUTO interés 3 = 123.56', $ins['cuotas'][2]['interes'] === 123.56, $ins['cuotas'][2]);
    verificar('INSOLUTO balance final = 0', end($ins['cuotas'])['balance'] === 0.0);
    verificar('INSOLUTO capital total = 12,000', $ins['resumen']['totalCapital'] === 12000.0);

    // --- Saldo fijo: solo nace la primera cuota; las demás al saldarse la anterior
    $fijo = AmortizacionService::calcular(prestamoBase(['codigo' => 'FIJO']));
    verificar('FIJO solo genera la primera cuota', count($fijo['cuotas']) === 1);
    verificar('FIJO cuota 1 = 4,360', $fijo['cuotas'][0]['valorCuota'] === 4360.0);
    $c2 = AmortizacionService::siguienteCuotaFijo(prestamoBase(['codigo' => 'FIJO']), 2, 8000.0);
    $c3 = AmortizacionService::siguienteCuotaFijo(prestamoBase(['codigo' => 'FIJO']), 3, 4000.0);
    verificar('FIJO cuota 2 = 4,240', $c2['valorCuota'] === 4240.0, $c2);
    verificar('FIJO cuota 3 = 4,120 y balance 0', $c3['valorCuota'] === 4120.0 && $c3['balance'] === 0.0, $c3);

    // --- Seguro y gasto de cierre financiado
    $extra = AmortizacionService::calcular(prestamoBase(['seguroMonto' => 100.0, 'gastoCierre' => 300.0, 'gastoFinanciado' => true]));
    verificar('Seguro fijo 100 + gasto financiado 100 → cuota 4,560', $extra['cuotas'][0]['valorCuota'] === 4560.0, $extra['cuotas'][0]);
    verificar('Gasto financiado: desembolso = monto', $extra['resumen']['desembolso'] === 12000.0);
    $descontado = AmortizacionService::calcular(prestamoBase(['gastoCierre' => 300.0, 'gastoFinanciado' => false]));
    verificar('Gasto no financiado: desembolso = 11,700', $descontado['resumen']['desembolso'] === 11700.0);

    // --- Mora
    verificar('Mora 5 % de 4,360 = 218.00', MoraService::moraDe('Porcentaje', 5.0, 4360.0) === 218.0);
    verificar('Mora monto fijo 150', MoraService::moraDe('Monto', 150.0, 4360.0) === 150.0);

    // --- Pago normal: orden seguro → mora → interés → gasto → capital, cuota más antigua primero
    $p = prestamoBase(['seguroMonto' => 100.0]) + ['capitalPendiente' => 12000.0];
    $cuotas = cuotasIniciales(AmortizacionService::calcular($p));
    // El 16-nov-2026 las cuotas 1 (1-oct) y 2 (1-nov) están vencidas: mora 5 % de 4,460 = 223 cada una.
    $plan = PagoService::planear($p, $cuotas, ['tipoPago' => 'normal', 'monto' => 5000], '2026-11-16');
    verificar('Pago 5,000: cuota 1 queda saldada (4,683 con mora)', $plan['detalle'][0]['cuotaNumero'] === 1 && $plan['detalle'][0]['estadoResultante'] === 'Pagada', $plan['detalle'][0]);
    verificar('Pago 5,000: cuota 2 recibe seguro 100 y mora 217', $plan['detalle'][1]['seguro'] === 100.0 && $plan['detalle'][1]['mora'] === 217.0, $plan['detalle'][1]);
    verificar('Pago 5,000: cuota 2 queda Parcial sin tocar interés ni capital', $plan['detalle'][1]['estadoResultante'] === 'Parcial' && $plan['detalle'][1]['interes'] === 0.0);
    verificar('Pago 5,000: totales mora 440, interés 360, seguro 200, capital 4,000', $plan['totales'] === ['mora' => 440.0, 'interes' => 360.0, 'seguro' => 200.0, 'gasto' => 0.0, 'capital' => 4000.0], $plan['totales']);
    verificar('Pago 5,000: balance posterior 8,000', $plan['balancePosterior'] === 8000.0);

    // --- Un pago mayor a lo adeudado se rechaza
    $rechazo = false;
    try {
        PagoService::planear($p, $cuotas, ['tipoPago' => 'normal', 'monto' => 999999], '2026-11-16');
    } catch (ErrorApi) {
        $rechazo = true;
    }
    verificar('Pago mayor a lo adeudado se rechaza', $rechazo);

    // --- Abono a capital: no se permite con mora pendiente
    $bloqueado = false;
    try {
        PagoService::planear($p, $cuotas, ['tipoPago' => 'abono_capital', 'monto' => 1000, 'modalidad' => 'Reducir cuota'], '2026-11-16');
    } catch (ErrorApi) {
        $bloqueado = true;
    }
    verificar('Abono a capital con mora pendiente se rechaza', $bloqueado);

    // --- Cancelación por renovación (mismas cuotas, hoy = 2026-11-16)
    $capital = CancelacionService::planear($p, $cuotas, 'Capital pendiente', '2026-11-16');
    $fecha = CancelacionService::planear($p, $cuotas, 'Balance a la fecha', '2026-11-16');
    $general = CancelacionService::planear($p, $cuotas, 'Balance general', '2026-11-16');
    verificar('Saldo Capital pendiente = 12,000', $capital['montoCancelado'] === 12000.0, $capital['montoCancelado']);
    verificar('Saldo Balance a la fecha = 13,366 (descuento 460)', $fecha['montoCancelado'] === 13366.0 && $fecha['descuento'] === 460.0, [$fecha['montoCancelado'], $fecha['descuento']]);
    verificar('Saldo Balance general = 13,826 (sin descuento)', $general['montoCancelado'] === 13826.0 && $general['descuento'] === 0.0, [$general['montoCancelado'], $general['descuento']]);
};
