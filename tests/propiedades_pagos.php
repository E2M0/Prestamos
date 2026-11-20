<?php
declare(strict_types=1);

/**
 * Pagos aleatorios (normal, solo mora, abono a capital) sobre préstamos absolutos, insolutos y de saldo fijo.
 * Después de cada pago se comprueban las reglas contables que nunca deben romperse.
 */
return function (int $semilla = 7, int $escenarios = 300): void {
    mt_srand($semilla);
    $plazos = [
        ['dias_periodo' => 30, 'meses_periodo' => 1, 'permite_excluir_fines_semana' => 0],
        ['dias_periodo' => 15, 'meses_periodo' => 0, 'permite_excluir_fines_semana' => 0],
        ['dias_periodo' => 7, 'meses_periodo' => 0, 'permite_excluir_fines_semana' => 0],
        ['dias_periodo' => 1, 'meses_periodo' => 0, 'permite_excluir_fines_semana' => 1],
    ];
    $pagos = 0;
    $saldados = 0;

    for ($e = 0; $e < $escenarios; $e++) {
        $codigo = ['ABSOLUTO', 'INSOLUTO', 'FIJO'][$e % 3];
        $tipoMora = ['Porcentaje', 'Monto'][mt_rand(0, 1)];
        $p = prestamoBase([
            'monto' => mt_rand(5, 500) * 1000.0, 'plazo' => mt_rand(1, 12), 'tasa' => mt_rand(0, 50) / 10,
            'plazoRow' => $plazos[mt_rand(0, 3)], 'codigo' => $codigo,
            'seguroTipo' => ['Fijo', 'Repartido'][mt_rand(0, 1)], 'seguroMonto' => mt_rand(0, 1) * mt_rand(100, 3000) * 1.0,
            'gastoCierre' => mt_rand(0, 1) * mt_rand(100, 5000) * 1.0, 'gastoFinanciado' => (bool) mt_rand(0, 1),
            'tipoMora' => $tipoMora, 'valorMora' => $tipoMora === 'Porcentaje' ? mt_rand(1, 100) / 10 : (float) mt_rand(1, 500),
            'fechaPrimerPago' => new DateTimeImmutable('2026-01-15'),
        ]);
        $cuotas = cuotasIniciales(AmortizacionService::calcular($p));
        $p['capitalPendiente'] = round($p['monto'], 2);
        $hoy = new DateTimeImmutable('2026-01-10');

        for ($paso = 0; $paso < 30; $paso++) {
            $hoy = $hoy->modify('+' . mt_rand(0, 45) . ' days');
            $tipo = ['normal', 'normal', 'normal', 'solo_mora', 'abono_capital'][mt_rand(0, 4)];
            $monto = max(0.01, round(mt_rand(1, 400000) / 100, 2));
            try {
                $plan = PagoService::planear($p, $cuotas, [
                    'tipoPago' => $tipo, 'monto' => $monto, 'modalidad' => ['Reducir cuota', 'Reducir plazo'][mt_rand(0, 1)],
                ], $hoy->format('Y-m-d'));
            } catch (ErrorApi) {
                continue; // pago no permitido (excede lo adeudado, hay mora, etc.): es un rechazo correcto
            }
            $pagos++;
            $cuotas = aplicarPlan($cuotas, $plan);
            $p['capitalPendiente'] = $plan['prestamo']['capitalPendiente'];
            $p['plazo'] = $plan['prestamo']['plazo'];
            $ctx = "$codigo/$tipo/" . $hoy->format('Y-m-d') . "/$monto";

            $suma = 0;
            foreach ($plan['totales'] as $v) {
                $suma += centavos($v);
            }
            verificar("$ctx: lo aplicado = lo recibido", $suma === centavos($plan['monto']), $plan['totales']);

            $pendienteCuotas = 0;
            foreach ($cuotas as $n => $q) {
                verificar("$ctx: cuota {$q['numero']} valor = capital+interés+seguro+gasto", centavos($q['valor']) === centavos($q['capital']) + centavos($q['interes']) + centavos($q['seguro']) + centavos($q['gasto']));
                foreach (['capital' => 'capitalPagado', 'interes' => 'interesPagado', 'seguro' => 'seguroPagado', 'gasto' => 'gastoPagado', 'mora' => 'moraPagada'] as $total => $pagado) {
                    verificar("$ctx: cuota {$q['numero']} $pagado ≤ $total", centavos($q[$pagado]) <= centavos($q[$total]));
                }
                verificar("$ctx: balance de la cuota {$q['numero']} no negativo", centavos($q['balance']) >= 0);
                verificar("$ctx: numeración consecutiva", $q['numero'] === $n + 1);
                $pendienteCuotas += centavos($q['capital']) - centavos($q['capitalPagado']);
            }
            $cp = centavos($p['capitalPendiente']);
            verificar("$ctx: capital pendiente no negativo", $cp >= 0);
            if ($codigo === 'FIJO') {
                verificar("$ctx: capital de las cuotas ≤ capital pendiente", $pendienteCuotas <= $cp);
            } else {
                verificar("$ctx: capital de las cuotas = capital pendiente", $pendienteCuotas === $cp, [$pendienteCuotas, $cp]);
                verificar("$ctx: última cuota con balance 0", centavos(end($cuotas)['balance']) === 0);
            }
            foreach ($cuotas as $q) {
                $maxMora = MoraService::moraDe($p['tipoMora'], (float) $p['valorMora'], $q['valor']);
                verificar("$ctx: mora de la cuota {$q['numero']} una sola vez", centavos($q['mora']) <= centavos($maxMora));
            }
            if ($plan['saldaPrestamo']) {
                $saldados++;
                verificar("$ctx: saldado ⇒ capital 0", $cp === 0);
                break;
            }
        }
    }
    echo "      ($pagos pagos simulados, $saldados préstamos saldados)\n";
};
