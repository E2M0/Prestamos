<?php
declare(strict_types=1);

/**
 * Cancelación de un préstamo por renovación. Dado un préstamo, sus cuotas y la base de saldo elegida, calcula
 * —sin tocar la base de datos— qué se cobra y qué se condona. Todo en centavos enteros.
 *
 * Bases de saldo (enunciado, sección 7.1):
 *  - Capital pendiente: solo el capital que falta (de todas las cuotas).
 *  - Balance a la fecha: capital + mora, interés, seguro y gasto de cierre de las cuotas exigibles hoy
 *    (fecha de pago ≤ hoy). No incluye lo que vence después.
 *  - Balance general: todo lo pendiente, incluidos los intereses, seguros y gastos futuros.
 * Lo que la base elegida no cubre se registra como descuento (condonación): el importe pendiente de esas cuotas se
 * reduce a lo ya pagado, de modo que el préstamo anterior queda saldado por completo.
 * En saldo fijo solo existen las cuotas ya generadas: el capital que aún no está en ninguna cuota se cobra
 * como una línea sin cuota (igual que un abono) y los intereses futuros no generados no existen todavía.
 */
final class CancelacionService
{
    public const BASES = ['Capital pendiente', 'Balance a la fecha', 'Balance general'];

    private const COMPONENTES = [
        'mora'    => ['mora', 'moraPagada'],
        'interes' => ['interes', 'interesPagado'],
        'seguro'  => ['seguro', 'seguroPagado'],
        'gasto'   => ['gasto', 'gastoPagado'],
    ];

    /**
     * @param array $prestamo capitalPendiente, tipoMora, valorMora
     * @param array $cuotas   cuotas del préstamo (ver CuotaModel::aCuota)
     */
    public static function planear(array $prestamo, array $cuotas, string $base, string $hoy): array
    {
        if (!in_array($base, self::BASES, true)) {
            throw new LogicException('Base de saldo inválida.');
        }
        $cuotas = MoraService::aplicarEnMemoria($prestamo, $cuotas, $hoy);

        $totales = ['mora' => 0, 'interes' => 0, 'seguro' => 0, 'gasto' => 0, 'capital' => 0];
        $detalle = [];
        $cambiadas = [];
        $descuento = 0;
        $capitalEnCuotas = 0;

        foreach ($cuotas as $c) {
            $exigible = $c['fechaPago'] <= $hoy;
            $incluye = match ($base) {
                'Balance general'    => true,
                'Balance a la fecha' => $exigible,
                default              => false,
            };

            $nuevo = $c;
            $fila = ['mora' => 0, 'interes' => 0, 'seguro' => 0, 'gasto' => 0, 'capital' => 0];
            $toco = false;

            // Capital: siempre se cobra completo.
            $capPend = PagoService::c($c['capital']) - PagoService::c($c['capitalPagado']);
            $capitalEnCuotas += $capPend;
            if ($capPend > 0) {
                $fila['capital'] = $capPend;
                $nuevo['capitalPagado'] = PagoService::m(PagoService::c($c['capitalPagado']) + $capPend);
                $toco = true;
            }

            foreach (self::COMPONENTES as $comp => [$total, $pagado]) {
                $pend = PagoService::c($c[$total]) - PagoService::c($c[$pagado]);
                if ($pend <= 0) {
                    continue;
                }
                $toco = true;
                if ($incluye) {
                    $fila[$comp] = $pend;
                    $nuevo[$pagado] = PagoService::m(PagoService::c($c[$pagado]) + $pend);
                } else {
                    $descuento += $pend;
                    $nuevo[$total] = $c[$pagado]; // condonado: lo pendiente se reduce a lo ya pagado
                }
            }
            if (!$toco) {
                continue;
            }

            $nuevo['valor'] = PagoService::m(
                PagoService::c($nuevo['capital']) + PagoService::c($nuevo['interes'])
                + PagoService::c($nuevo['seguro']) + PagoService::c($nuevo['gasto'])
            );
            $nuevo['estado'] = MoraService::estadoDe($nuevo, $hoy);
            $cambiadas[] = $nuevo;

            if (array_sum($fila) > 0) {
                foreach ($fila as $k => $v) {
                    $totales[$k] += $v;
                }
                $detalle[] = self::lineaDetalle($c['numero'], $c['fechaPago'], $fila, $nuevo['estado']);
            }
        }

        // Capital que todavía no está en ninguna cuota (saldo fijo): se cobra sin cuota.
        $resto = PagoService::c($prestamo['capitalPendiente']) - $capitalEnCuotas;
        if ($resto < 0) {
            throw new LogicException('El capital de las cuotas supera el capital pendiente del préstamo.');
        }
        if ($resto > 0) {
            $totales['capital'] += $resto;
            $detalle[] = self::lineaDetalle(null, null, ['mora' => 0, 'interes' => 0, 'seguro' => 0, 'gasto' => 0, 'capital' => $resto], null);
        }

        $monto = array_sum($totales);
        return [
            'tipoPago'        => 'normal',
            'modalidad'       => null,
            'base'            => $base,
            'monto'           => PagoService::m($monto),
            'montoCancelado'  => PagoService::m($monto),
            'descuento'       => PagoService::m($descuento),
            'totales'         => array_map([PagoService::class, 'm'], $totales),
            'detalle'         => $detalle,
            'cuotasCambiadas' => $cambiadas,
            'balancePosterior' => 0.0,
        ];
    }

    private static function lineaDetalle(?int $numero, ?string $fecha, array $f, ?string $estado): array
    {
        return [
            'cuotaNumero'      => $numero,
            'fechaPago'        => $fecha,
            'mora'             => PagoService::m($f['mora']),
            'interes'          => PagoService::m($f['interes']),
            'seguro'           => PagoService::m($f['seguro']),
            'gasto'            => PagoService::m($f['gasto']),
            'capital'          => PagoService::m($f['capital']),
            'estadoResultante' => $estado,
        ];
    }
}
