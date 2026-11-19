<?php
declare(strict_types=1);

/**
 * Planificador de pagos. Dado un préstamo y sus cuotas, calcula —sin tocar la base de datos— cómo se aplicaría
 * un pago. La vista previa y el pago real usan exactamente este mismo cálculo.
 *
 * Tres tipos de pago (enunciado, sección 6):
 *  - normal: el monto se aplica a las cuotas más antiguas sin saldar. Dentro de cada cuota el orden es
 *    seguro, mora, interés, gasto de cierre financiado y capital. Saldo fijo: al saldarse una cuota nace la siguiente.
 *  - solo_mora: el monto afecta únicamente la mora acumulada.
 *  - abono_capital: reduce el capital de las cuotas FUTURAS (pendientes y no vencidas) y recalcula su interés.
 *    Criterios propios (documentados en docs/SUPUESTOS.md):
 *      · no se permite con mora pendiente sin saldar;
 *      · el abono no puede superar el capital de las cuotas futuras (Cf); las vencidas y parciales no se recalculan;
 *      · Reducir cuota: mismo número de cuotas, cada una más baja;  Reducir plazo: menos cuotas, se mantiene
 *        el capital por cuota (absoluto/fijo) o la cuota total (insoluto);
 *      · absoluto: el interés futuro baja en la misma proporción que el capital futuro (Cf' / Cf);
 *        insoluto y fijo: el interés se recalcula sobre el nuevo balance.
 * Internamente todo se calcula en centavos enteros para que ningún redondeo de coma flotante altere los totales.
 */
final class PagoService
{
    public const TIPOS = ['normal', 'abono_capital', 'solo_mora'];
    public const MODALIDADES = ['Reducir cuota', 'Reducir plazo'];

    private const CAMPOS_DINERO = [
        'capital', 'interes', 'seguro', 'gasto', 'mora', 'valor',
        'capitalPagado', 'interesPagado', 'seguroPagado', 'gastoPagado', 'moraPagada', 'balance',
    ];

    /** @var array<int, array> cuotas por número, con importes en centavos */
    private array $cuotas = [];
    private array $tocadas = [];     // número => true (cuotas que hay que guardar)
    private array $nuevas = [];      // número => true (cuotas generadas en este pago)
    private array $eliminadas = [];  // números de cuotas futuras que desaparecen
    private array $detalle = [];     // número => [mora, interes, seguro, gasto, capital] en centavos
    private int $abonoCapital = 0;
    private int $capitalPendiente;
    private int $plazo;

    private function __construct(private array $prestamo, array $cuotas, private string $hoy)
    {
        $this->capitalPendiente = self::c($prestamo['capitalPendiente']);
        $this->plazo = (int) $prestamo['plazo'];
        foreach (MoraService::aplicarEnMemoria($prestamo, $cuotas, $hoy) as $cuota) {
            $this->cuotas[$cuota['numero']] = self::aCentavos($cuota);
        }
        ksort($this->cuotas);
    }

    /**
     * @param array $prestamo capitalPendiente, plazo, codigo, tasa, plazoRow, incluyeSabado, incluyeDomingo,
     *                        seguroTipo, seguroMonto, gastoCierre, gastoFinanciado, fechaPrimerPago, tipoMora, valorMora
     * @param array $cuotas   cuotas con importes en float (ver CuotaModel::aCuota)
     * @param array $entrada  tipoPago, monto, modalidad (solo abono)
     */
    public static function planear(array $prestamo, array $cuotas, array $entrada, string $hoy): array
    {
        return (new self($prestamo, $cuotas, $hoy))->ejecutar($entrada);
    }

    private function ejecutar(array $entrada): array
    {
        $tipo = (string) ($entrada['tipoPago'] ?? '');
        $monto = self::c((float) $entrada['monto']);
        if ($monto <= 0) {
            throw new ErrorApi('Ingrese un monto mayor que cero.', 422, ['monto' => 'Ingrese un monto mayor que cero.']);
        }

        match ($tipo) {
            'normal'        => $this->pagoNormal($monto),
            'solo_mora'     => $this->soloMora($monto),
            'abono_capital' => $this->abono($monto, $entrada['modalidad'] ?? null),
            default         => throw new ErrorApi('Tipo de pago inválido.', 422, ['tipoPago' => 'Seleccione el tipo de pago.']),
        };

        if ($tipo !== 'abono_capital') {
            $this->generarSiguienteSiCorresponde();
        }
        return $this->resultado($tipo, $monto, $entrada['modalidad'] ?? null);
    }

    // ------------------------------------------------------------------ pago normal

    private function pagoNormal(int $restante): void
    {
        $total = $restante;
        while ($restante > 0) {
            $numero = $this->primeraSinSaldar();
            if ($numero === null) {
                if (!$this->puedeGenerar()) {
                    break;
                }
                $this->generarSiguiente();
                continue;
            }
            $this->aplicar($numero, $restante, ['seguro', 'mora', 'interes', 'gasto', 'capital']);
        }
        if ($restante > 0) {
            throw new ErrorApi(
                'El monto excede lo adeudado del préstamo. Máximo a pagar: RD$ ' . number_format(self::m($total - $restante), 2) . '.',
                422,
                ['monto' => 'El monto excede lo adeudado.']
            );
        }
    }

    // ------------------------------------------------------------------ gestión de mora

    private function soloMora(int $restante): void
    {
        $pendiente = $this->moraPendiente();
        if ($pendiente <= 0) {
            throw new ErrorApi('El préstamo no tiene mora pendiente.', 422, ['monto' => 'No hay mora pendiente.']);
        }
        if ($restante > $pendiente) {
            throw new ErrorApi(
                'El monto excede la mora pendiente (RD$ ' . number_format(self::m($pendiente), 2) . ').',
                422,
                ['monto' => 'Máximo: RD$ ' . number_format(self::m($pendiente), 2) . '.']
            );
        }
        foreach ($this->cuotas as $numero => $c) {
            if ($restante <= 0) {
                break;
            }
            $this->aplicar($numero, $restante, ['mora']);
        }
    }

    // ------------------------------------------------------------------ abono a capital

    private function abono(int $abono, mixed $modalidad): void
    {
        if (!in_array($modalidad, self::MODALIDADES, true)) {
            throw new ErrorApi('Seleccione cómo aplicar el abono.', 422, ['modalidad' => 'Elija reducir cuota o reducir plazo.']);
        }
        if ($this->moraPendiente() > 0) {
            throw new ErrorApi(
                'No se puede abonar a capital mientras exista mora pendiente (RD$ ' . number_format(self::m($this->moraPendiente()), 2) . '). Pague primero la mora.',
                422,
                ['tipoPago' => 'Hay mora pendiente.']
            );
        }

        // Cuotas futuras: pendientes, no vencidas y sin ningún pago. Las demás no se recalculan.
        $futuras = array_keys(array_filter($this->cuotas, fn(array $c) => $c['estado'] === 'Pendiente'));
        $capitalNoFuturo = 0;
        foreach ($this->cuotas as $numero => $c) {
            if (!in_array($numero, $futuras, true)) {
                $capitalNoFuturo += $c['capital'] - $c['capitalPagado'];
            }
        }
        $cf = $this->capitalPendiente - $capitalNoFuturo; // capital que todavía se puede reducir
        if ($cf <= 0) {
            throw new ErrorApi('No hay capital futuro que abonar: las cuotas restantes ya están vencidas o en pago.', 422, ['monto' => 'No hay capital futuro.']);
        }
        if ($abono > $cf) {
            throw new ErrorApi(
                'El abono no puede superar RD$ ' . number_format(self::m($cf), 2) . ', el capital de las cuotas aún no vencidas.',
                422,
                ['monto' => 'Máximo: RD$ ' . number_format(self::m($cf), 2) . '.']
            );
        }

        if ($this->prestamo['codigo'] === 'FIJO') {
            $this->abonoFijo($abono, $cf, $futuras, $modalidad);
        } else {
            $this->abonoAbsolutoInsoluto($abono, $cf, $futuras, $modalidad);
        }
        $this->capitalPendiente -= $abono;
        $this->abonoCapital = $abono;
    }

    /** Absoluto e insoluto: las cuotas futuras son las últimas y ya existen todas. */
    private function abonoAbsolutoInsoluto(int $abono, int $cf, array $futuras, string $modalidad): void
    {
        $nF = count($futuras);
        $primera = $this->cuotas[$futuras[0]];
        $cf2 = $cf - $abono;
        $i = AmortizacionService::tasaPeriodo((float) $this->prestamo['tasa'], (int) $this->prestamo['plazoRow']['dias_periodo']);

        if ($cf2 === 0) {
            $nuevoN = 0; // se cancela todo el capital futuro: desaparecen esas cuotas
        } elseif ($modalidad === 'Reducir cuota') {
            $nuevoN = $nF;
        } elseif ($this->prestamo['codigo'] === 'INSOLUTO') {
            $v = self::m($primera['capital'] + $primera['interes']); // se mantiene la cuota total (capital + interés)
            $x = self::m($cf2);
            $n = ($i > 0 && $x * $i < $v) ? (int) ceil(-log(1 - $x * $i / $v) / log(1 + $i) - 1e-9) : (int) ceil($x / $v - 1e-9);
            $nuevoN = max(1, min($nF, $n));
        } else { // ABSOLUTO: se mantiene el capital por cuota
            $nuevoN = max(1, min($nF, (int) ceil($cf2 / $primera['capital'] - 1e-9)));
        }

        // Cuotas que desaparecen: las últimas de las futuras.
        foreach (array_slice($futuras, $nuevoN) as $numero) {
            $this->eliminadas[] = $numero;
            unset($this->cuotas[$numero]);
        }
        $this->plazo -= ($nF - $nuevoN);
        if ($nuevoN === 0) {
            return;
        }

        $x2 = self::m($cf2);
        $filas = [];
        if ($this->prestamo['codigo'] === 'ABSOLUTO') {
            $capitalCuota = round($x2 / $nuevoN, 2);
            $interesCuota = round(self::m($primera['interes']) * $cf2 / $cf, 2); // proporcional al capital que queda
            $balance = $x2;
            for ($j = 0; $j < $nuevoN; $j++) {
                $capital = ($j === $nuevoN - 1) ? $balance : $capitalCuota;
                $balance = round($balance - $capital, 2);
                $filas[] = [$capital, $interesCuota, $balance];
            }
        } else { // INSOLUTO
            $cuota = $i > 0 ? round($x2 * $i / (1 - pow(1 + $i, -$nuevoN)), 2) : round($x2 / $nuevoN, 2);
            $balance = $x2;
            for ($j = 0; $j < $nuevoN; $j++) {
                $interes = round($balance * $i, 2);
                $capital = ($j === $nuevoN - 1) ? $balance : round($cuota - $interes, 2);
                $balance = round($balance - $capital, 2);
                $filas[] = [$capital, $interes, $balance];
            }
        }

        foreach (array_slice($futuras, 0, $nuevoN) as $j => $numero) {
            [$capital, $interes, $balance] = $filas[$j];
            $c = &$this->cuotas[$numero];
            $c['capital'] = self::c($capital);
            $c['interes'] = self::c($interes);
            $c['valor'] = $c['capital'] + $c['interes'] + $c['seguro'] + $c['gasto'];
            $c['balance'] = self::c($balance);
            unset($c);
            $this->tocadas[$numero] = true;
        }
    }

    /** Saldo fijo: solo existe (a lo sumo) una cuota futura; las demás se generan después con el capital pendiente. */
    private function abonoFijo(int $abono, int $cf, array $futuras, string $modalidad): void
    {
        $cf2 = $cf - $abono;
        $i = AmortizacionService::tasaPeriodo((float) $this->prestamo['tasa'], (int) $this->prestamo['plazoRow']['dias_periodo']);
        $noFuturas = count($this->cuotas) - count($futuras);   // cuotas ya generadas que no se recalculan
        $restantes = $this->plazo - $noFuturas;                // cuotas que faltan por pagar con el capital futuro

        if ($cf2 === 0) {
            $nuevoRestantes = 0;
        } elseif ($modalidad === 'Reducir cuota') {
            $nuevoRestantes = $restantes;
        } else { // Reducir plazo: se mantiene el capital por cuota
            $referencia = $futuras ? $this->cuotas[$futuras[0]]['capital'] : end($this->cuotas)['capital'];
            $nuevoRestantes = max(1, min($restantes, (int) ceil($cf2 / max(1, $referencia) - 1e-9)));
        }
        $this->plazo -= ($restantes - $nuevoRestantes);

        foreach ($futuras as $numero) { // a lo sumo una
            if ($nuevoRestantes === 0) {
                $this->eliminadas[] = $numero;
                unset($this->cuotas[$numero]);
                continue;
            }
            $x2 = self::m($cf2);
            $capital = ($nuevoRestantes === 1) ? $x2 : round($x2 / $nuevoRestantes, 2);
            $c = &$this->cuotas[$numero];
            $c['capital'] = self::c($capital);
            $c['interes'] = self::c(round($x2 * $i, 2));
            $c['valor'] = $c['capital'] + $c['interes'] + $c['seguro'] + $c['gasto'];
            $c['balance'] = $cf2 - $c['capital'];
            unset($c);
            $this->tocadas[$numero] = true;
        }
    }

    // ------------------------------------------------------------------ utilidades del planificador

    /** Aplica $restante a una cuota siguiendo el orden de componentes dado. */
    private function aplicar(int $numero, int &$restante, array $orden): void
    {
        $c = &$this->cuotas[$numero];
        $pagado = ['seguro' => 'seguroPagado', 'mora' => 'moraPagada', 'interes' => 'interesPagado', 'gasto' => 'gastoPagado', 'capital' => 'capitalPagado'];
        foreach ($orden as $comp) {
            $falta = $c[$comp] - $c[$pagado[$comp]];
            $toma = min($falta, $restante);
            if ($toma <= 0) {
                continue;
            }
            $c[$pagado[$comp]] += $toma;
            $restante -= $toma;
            $this->detalle[$numero] ??= ['mora' => 0, 'interes' => 0, 'seguro' => 0, 'gasto' => 0, 'capital' => 0];
            $this->detalle[$numero][$comp] += $toma;
            if ($comp === 'capital') {
                $this->capitalPendiente -= $toma;
            }
            $this->tocadas[$numero] = true;
        }
        $c['estado'] = MoraService::estadoDe(self::aFloat($c), $this->hoy);
        unset($c);
    }

    private function primeraSinSaldar(): ?int
    {
        foreach ($this->cuotas as $numero => $c) {
            if (!MoraService::estaSaldada(self::aFloat($c))) {
                return $numero;
            }
        }
        return null;
    }

    private function moraPendiente(): int
    {
        $total = 0;
        foreach ($this->cuotas as $c) {
            $total += $c['mora'] - $c['moraPagada'];
        }
        return $total;
    }

    private function totalAplicado(): int
    {
        $total = 0;
        foreach ($this->detalle as $d) {
            $total += array_sum($d);
        }
        return $total;
    }

    private function puedeGenerar(): bool
    {
        return $this->prestamo['codigo'] === 'FIJO' && $this->cuotas && max(array_keys($this->cuotas)) < $this->plazo;
    }

    /** Saldo fijo: tras saldar una cuota se genera la siguiente (aunque no quede dinero para pagarla). */
    private function generarSiguienteSiCorresponde(): void
    {
        if ($this->puedeGenerar() && $this->primeraSinSaldar() === null) {
            $this->generarSiguiente();
        }
    }

    private function generarSiguiente(): void
    {
        $k = max(array_keys($this->cuotas)) + 1;
        $params = $this->prestamo + [];
        $params['plazo'] = $this->plazo;
        $fila = AmortizacionService::siguienteCuotaFijo($params, $k, self::m($this->capitalPendiente));
        $cuota = [
            'id' => null, 'numero' => $k, 'fechaPago' => $fila['fechaPago'], 'capital' => $fila['capital'],
            'interes' => $fila['interes'], 'seguro' => $fila['seguro'], 'gasto' => $fila['gastoCierre'], 'mora' => 0.0,
            'valor' => $fila['valorCuota'], 'capitalPagado' => 0.0, 'interesPagado' => 0.0, 'seguroPagado' => 0.0,
            'gastoPagado' => 0.0, 'moraPagada' => 0.0, 'balance' => $fila['balance'], 'estado' => 'Pendiente',
        ];
        $this->cuotas[$k] = self::aCentavos(MoraService::aplicarACuota($this->prestamo, $cuota, $this->hoy));
        $this->nuevas[$k] = true;
        $this->tocadas[$k] = true;
    }

    private function resultado(string $tipo, int $monto, mixed $modalidad): array
    {
        $totales = ['mora' => 0, 'interes' => 0, 'seguro' => 0, 'gasto' => 0, 'capital' => $this->abonoCapital];
        $detalle = [];
        foreach ($this->detalle as $numero => $d) {
            foreach ($d as $k => $v) {
                $totales[$k] += $v;
            }
            $detalle[] = [
                'cuotaNumero'       => $numero,
                'fechaPago'         => $this->cuotas[$numero]['fechaPago'],
                'mora'              => self::m($d['mora']),
                'interes'           => self::m($d['interes']),
                'seguro'            => self::m($d['seguro']),
                'gasto'             => self::m($d['gasto']),
                'capital'           => self::m($d['capital']),
                'estadoResultante'  => $this->cuotas[$numero]['estado'],
            ];
        }
        if ($this->abonoCapital > 0) {
            $detalle[] = [
                'cuotaNumero' => null, 'fechaPago' => null, 'mora' => 0.0, 'interes' => 0.0, 'seguro' => 0.0,
                'gasto' => 0.0, 'capital' => self::m($this->abonoCapital), 'estadoResultante' => null,
            ];
        }

        // Control interno: lo cobrado debe ser exactamente lo recibido (el CHECK de la tabla lo exige también).
        if (array_sum($totales) !== $monto) {
            throw new LogicException('La aplicación del pago no cuadra con el monto recibido.');
        }
        if ($this->capitalPendiente < 0) {
            throw new LogicException('El capital pendiente no puede ser negativo.');
        }

        $todasSaldadas = true;
        foreach ($this->cuotas as $c) {
            $todasSaldadas = $todasSaldadas && MoraService::estaSaldada(self::aFloat($c));
        }
        $salda = $todasSaldadas && count($this->cuotas) === $this->plazo;

        $cambiadas = [];
        $creadas = [];
        foreach (array_keys($this->tocadas) as $numero) {
            if (!isset($this->cuotas[$numero])) {
                continue; // cuota eliminada después de tocarla
            }
            $fila = self::aFloat($this->cuotas[$numero]);
            if (isset($this->nuevas[$numero])) {
                $creadas[] = $fila;
            } else {
                $cambiadas[] = $fila;
            }
        }

        return [
            'tipoPago'          => $tipo,
            'monto'             => self::m($monto),
            'modalidad'         => $tipo === 'abono_capital' ? $modalidad : null,
            'totales'           => array_map([self::class, 'm'], $totales),
            'detalle'           => $detalle,
            'cuotasCambiadas'   => $cambiadas,
            'cuotasNuevas'      => $creadas,
            'cuotasEliminadas'  => $this->eliminadas,
            'prestamo'          => [
                'capitalPendiente' => self::m($this->capitalPendiente),
                'plazo'            => $this->plazo,
                'estado'           => $salda ? 'Saldado' : 'Activo',
            ],
            'balancePosterior'  => self::m($this->capitalPendiente),
            'saldaPrestamo'     => $salda,
        ];
    }

    // ------------------------------------------------------------------ centavos <-> pesos

    /** Pesos (número o texto decimal que devuelve SQL Server) a centavos enteros. */
    public static function c(float|int|string $pesos): int
    {
        return (int) round((float) $pesos * 100);
    }

    public static function m(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    private static function aCentavos(array $cuota): array
    {
        foreach (self::CAMPOS_DINERO as $k) {
            $cuota[$k] = self::c($cuota[$k]);
        }
        return $cuota;
    }

    private static function aFloat(array $cuota): array
    {
        foreach (self::CAMPOS_DINERO as $k) {
            $cuota[$k] = self::m($cuota[$k]);
        }
        return $cuota;
    }
}
