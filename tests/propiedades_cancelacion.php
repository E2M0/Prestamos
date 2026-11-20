<?php
declare(strict_types=1);

/** Cancelaciones por renovación sobre préstamos con historial de pagos aleatorio, con las tres bases de saldo. */
return function (int $semilla = 11, int $escenarios = 300): void {
    mt_srand($semilla);
    $planes = 0;
    for ($e = 0; $e < $escenarios; $e++) {
        $codigo = ['ABSOLUTO', 'INSOLUTO', 'FIJO'][$e % 3];
        $tipoMora = ['Porcentaje', 'Monto'][mt_rand(0, 1)];
        $p = prestamoBase([
            'monto' => mt_rand(10, 400) * 1000.0, 'plazo' => mt_rand(1, 20), 'tasa' => mt_rand(0, 40) / 10, 'codigo' => $codigo,
            'seguroTipo' => ['Fijo', 'Repartido'][mt_rand(0, 1)], 'seguroMonto' => mt_rand(0, 1) * mt_rand(100, 2000) * 1.0,
            'gastoCierre' => mt_rand(0, 1) * 3000.0, 'gastoFinanciado' => (bool) mt_rand(0, 1), 'tipoMora' => $tipoMora,
            'valorMora' => $tipoMora === 'Porcentaje' ? mt_rand(1, 100) / 10 : (float) mt_rand(1, 400),
            'fechaPrimerPago' => new DateTimeImmutable('2026-03-01'),
        ]);
        $cuotas = cuotasIniciales(AmortizacionService::calcular($p));
        $p['capitalPendiente'] = $p['monto'];
        $hoy = new DateTimeImmutable('2026-02-20');

        for ($i = 0, $k = mt_rand(0, 6); $i < $k; $i++) { // historial de pagos previo
            $hoy = $hoy->modify('+' . mt_rand(5, 40) . ' days');
            try {
                $plan = PagoService::planear($p, $cuotas, [
                    'tipoPago' => ['normal', 'normal', 'solo_mora', 'abono_capital'][mt_rand(0, 3)],
                    'monto' => round(mt_rand(100, 900000) / 100, 2), 'modalidad' => ['Reducir cuota', 'Reducir plazo'][mt_rand(0, 1)],
                ], $hoy->format('Y-m-d'));
            } catch (ErrorApi) {
                continue;
            }
            $cuotas = aplicarPlan($cuotas, $plan);
            $p['capitalPendiente'] = $plan['prestamo']['capitalPendiente'];
            if ($plan['saldaPrestamo']) {
                break;
            }
        }
        if ($p['capitalPendiente'] <= 0) {
            continue;
        }

        $hoyTexto = $hoy->modify('+' . mt_rand(0, 30) . ' days')->format('Y-m-d');
        $montos = [];
        foreach (CancelacionService::BASES as $base) {
            $plan = CancelacionService::planear($p, $cuotas, $base, $hoyTexto);
            $planes++;
            $montos[$base] = centavos($plan['monto']);
            $ctx = "$codigo/$base";

            $totales = 0;
            foreach ($plan['totales'] as $v) {
                $totales += centavos($v);
            }
            $detalle = 0;
            foreach ($plan['detalle'] as $d) {
                $detalle += centavos($d['mora']) + centavos($d['interes']) + centavos($d['seguro']) + centavos($d['gasto']) + centavos($d['capital']);
            }
            verificar("$ctx: totales = monto", $totales === centavos($plan['monto']));
            verificar("$ctx: detalle = monto", $detalle === centavos($plan['monto']));
            verificar("$ctx: se cobra todo el capital pendiente", centavos($plan['totales']['capital']) === centavos($p['capitalPendiente']));

            $despues = aplicarPlan($cuotas, $plan);
            foreach ($despues as $q) {
                verificar("$ctx: cuota {$q['numero']} queda saldada", MoraService::estaSaldada($q), $q);
                verificar("$ctx: cuota {$q['numero']} valor = suma de componentes", centavos($q['valor']) === centavos($q['capital']) + centavos($q['interes']) + centavos($q['seguro']) + centavos($q['gasto']));
            }
        }
        $capital = $montos['Capital pendiente'];
        verificar("$codigo: Capital pendiente ≤ A la fecha ≤ General", $capital <= $montos['Balance a la fecha'] && $montos['Balance a la fecha'] <= $montos['Balance general'], $montos);
        foreach (CancelacionService::BASES as $base) {
            $plan = CancelacionService::planear($p, $cuotas, $base, $hoyTexto);
            verificar("$codigo/$base: saldo + descuento = balance general", centavos($plan['monto']) + centavos($plan['descuento']) === $montos['Balance general']);
        }
        verificar("$codigo: balance general sin descuento", centavos(CancelacionService::planear($p, $cuotas, 'Balance general', $hoyTexto)['descuento']) === 0);
    }
    echo "      ($planes cancelaciones simuladas)\n";
};
