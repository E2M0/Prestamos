<?php
declare(strict_types=1);

/**
 * Motor de amortización. Es la ÚNICA fuente de los cálculos de cuotas: React solo muestra lo que devuelve.
 * No toca la base de datos ni la sesión: recibe datos ya validados (ver PrestamoValidador) y devuelve cuotas.
 *
 * Fórmulas (enunciado, sección 5.1). M = monto, n = cuotas, i = tasa por período:
 *   i = (tasa mensual / 100) × (días del período / 30)
 *   - ABSOLUTO: capital = M / n;  interés = M × i;  cuota = capital + interés
 *   - INSOLUTO: cuota = M × i / (1 − (1 + i)^−n);  interés = balance anterior × i;  capital = cuota − interés
 *   - FIJO    : capital = capital pendiente / cuotas restantes (= M / n en la 1.ª cuota);
 *               interés = capital pendiente × i;  en la apertura solo se genera la 1.ª cuota y cada
 *               siguiente nace al saldarse la anterior (ver siguienteCuotaFijo, usada por los recibos)
 *   - Seguro "Fijo": el mismo monto en cada cuota. "Repartido": monto total / n en cada cuota.
 *   - Gasto de cierre financiado: gasto / n en cada cuota (igual que el seguro repartido).
 *   - Cada importe se redondea a 2 decimales; el último ajuste de redondeo cae en la última cuota,
 *     de modo que el balance final sea exactamente cero.
 */
final class AmortizacionService
{
    /** Tasa del período, como fracción (0.03 = 3 %). */
    public static function tasaPeriodo(float $tasaMensual, int $diasPeriodo): float
    {
        return $tasaMensual / 100 * $diasPeriodo / 30;
    }

    /**
     * @param array $p datos validados: monto, plazo, tasa, plazoRow (fila de tipos_plazo), codigo
     *                 (ABSOLUTO|INSOLUTO|FIJO), incluyeSabado, incluyeDomingo, seguroTipo, seguroMonto,
     *                 gastoCierre, gastoFinanciado, fechaPrimerPago (DateTimeImmutable).
     * @return array{cuotas: array, resumen: array}
     */
    public static function calcular(array $p): array
    {
        $monto = (float) $p['monto'];
        $n = (int) $p['plazo'];
        $codigo = (string) $p['codigo'];
        $i = self::tasaPeriodo((float) $p['tasa'], (int) $p['plazoRow']['dias_periodo']);

        $capitalBase = round($monto / $n, 2);
        $cuotaFija = 0.0;
        if ($codigo === 'INSOLUTO') {
            // Con tasa 0 la fórmula divide entre cero: la cuota es simplemente M / n.
            $cuotaFija = $i > 0 ? round($monto * $i / (1 - pow(1 + $i, -$n)), 2) : $capitalBase;
        }

        $generar = $codigo === 'FIJO' ? 1 : $n; // saldo fijo: las demás cuotas se generan al saldar la anterior

        $cuotas = [];
        $balance = $monto;
        for ($k = 1; $k <= $generar; $k++) {
            $ultima = ($k === $n);

            if ($codigo === 'ABSOLUTO') {
                $interes = round($monto * $i, 2);
                $capital = $ultima ? $balance : $capitalBase;
            } elseif ($codigo === 'FIJO') {
                $interes = round($balance * $i, 2);
                $capital = $ultima ? $balance : round($balance / ($n - $k + 1), 2);
            } else { // INSOLUTO
                $interes = round($balance * $i, 2);
                $capital = $ultima ? $balance : round($cuotaFija - $interes, 2);
            }

            $cuota = self::armarCuota($p, $k, $capital, $interes, $balance);
            $balance = $cuota['balance'];
            $cuotas[] = $cuota;
        }

        return ['cuotas' => $cuotas, 'resumen' => self::resumen($p, $i, $cuotas)];
    }

    /**
     * Saldo fijo: genera la cuota $k (2..n) cuando la anterior queda saldada.
     * $capitalPendiente es el capital que aún debe el préstamo en ese momento.
     */
    public static function siguienteCuotaFijo(array $p, int $k, float $capitalPendiente): array
    {
        $n = (int) $p['plazo'];
        $i = self::tasaPeriodo((float) $p['tasa'], (int) $p['plazoRow']['dias_periodo']);
        $capitalPendiente = round($capitalPendiente, 2);
        $interes = round($capitalPendiente * $i, 2);
        $capital = ($k === $n) ? $capitalPendiente : round($capitalPendiente / ($n - $k + 1), 2);
        return self::armarCuota($p, $k, $capital, $interes, $capitalPendiente);
    }

    /** Completa una cuota con seguro, gasto financiado, fecha y balance. */
    private static function armarCuota(array $p, int $k, float $capital, float $interes, float $balanceAnterior): array
    {
        $n = (int) $p['plazo'];
        $ultima = ($k === $n);

        $seguroPorCuota = round((float) $p['seguroMonto'] / $n, 2);
        if ($p['seguroTipo'] === 'Fijo') {
            $seguro = round((float) $p['seguroMonto'], 2);
        } else {
            $seguro = $ultima ? round((float) $p['seguroMonto'] - $seguroPorCuota * ($n - 1), 2) : $seguroPorCuota;
        }

        if ($p['gastoFinanciado']) {
            $gastoPorCuota = round((float) $p['gastoCierre'] / $n, 2);
            $gasto = $ultima ? round((float) $p['gastoCierre'] - $gastoPorCuota * ($n - 1), 2) : $gastoPorCuota;
        } else {
            $gasto = 0.0;
        }

        $fecha = CalendarioService::avanzar(
            $p['fechaPrimerPago'], $p['plazoRow'], $k - 1, (bool) $p['incluyeSabado'], (bool) $p['incluyeDomingo']
        );

        return [
            'numero'          => $k,
            'fechaPago'       => $fecha->format('Y-m-d'),
            'capital'         => round($capital, 2),
            'interes'         => $interes,
            'seguro'          => $seguro,
            'gastoCierre'     => $gasto,
            'valorCuota'      => round($capital + $interes + $seguro + $gasto, 2),
            'balance'         => round($balanceAnterior - $capital, 2),
            'cuotasRestantes' => $n - $k,
        ];
    }

    private static function resumen(array $p, float $i, array $cuotas): array
    {
        $suma = fn(string $campo) => round(array_sum(array_column($cuotas, $campo)), 2);
        $monto = round((float) $p['monto'], 2);
        $gasto = round((float) $p['gastoCierre'], 2);

        return [
            'monto'              => $monto,
            'plazo'              => (int) $p['plazo'],
            'tasaPeriodo'        => round($i * 100, 4),                       // % por período (informativo)
            'desembolso'         => $p['gastoFinanciado'] ? $monto : round($monto - $gasto, 2),
            'fechaPrimerPago'    => $cuotas[0]['fechaPago'],
            'fechaUltimaCuota'   => $cuotas[count($cuotas) - 1]['fechaPago'], // de las cuotas generadas
            'valorPrimeraCuota'  => $cuotas[0]['valorCuota'],
            'cuotasGeneradas'    => count($cuotas),
            'soloPrimeraCuota'   => count($cuotas) < (int) $p['plazo'],       // saldo fijo
            'totalCapital'       => $suma('capital'),
            'totalIntereses'     => $suma('interes'),
            'totalSeguro'        => $suma('seguro'),
            'totalGastoFinanciado' => $suma('gastoCierre'),
            'totalCuotas'        => $suma('valorCuota'),
        ];
    }
}
