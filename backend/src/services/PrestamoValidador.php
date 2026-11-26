<?php
declare(strict_types=1);

/**
 * Validación de la apertura de un préstamo (el servidor es la autoridad; React repite las reglas para avisar antes).
 * No toca la base de datos: recibe en $ctx lo que necesita ya cargado.
 *
 * $ctx: plazos [id => fila tipos_plazo], amortizaciones [id => fila tipos_amortizacion],
 *       tiposPrestamo [id => true] (solo los activos), credito (array de PrestamoService::credito o null).
 * Las claves de error coinciden con las del formulario de React.
 */
final class PrestamoValidador
{
    public const MAX_DECIMAL = 999999999999.99;
    public const MAX_CUOTAS = 600;
    public const MAX_TASA = 100;

    /** Dinero con formato para los mensajes de error. */
    private static function pesos(float $valor): string
    {
        return 'RD$' . number_format($valor, 2);
    }

    /** Porcentaje legible: 10 -> "10", 0.5 -> "0.5". */
    private static function pct(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }

    /**
     * Regla de negocio sobre el interés total del préstamo (se llama después de calcular la tabla).
     * $reglas: mapa clave => valor de la tabla reglas_negocio. Devuelve el mensaje de error o null.
     */
    public static function errorInteresTotal(array $reglas, float $monto, float $interesTotal): ?string
    {
        $max = $reglas['interes_total_maximo_pct'] ?? null;
        if ($max === null || $monto <= 0) {
            return null;
        }
        $pct = $interesTotal / $monto * 100;
        if (round($pct, 2) > (float) $max) {
            return 'El interés total (' . self::pesos($interesTotal) . ', ' . self::pct($pct) . ' % del capital) supera el máximo permitido de '
                . self::pct((float) $max) . ' %. Reduzca la tasa o el plazo.';
        }
        return null;
    }

    /** @return array{0: array, 1: array} [errores, datos normalizados] */
    public static function validar(array $d, array $ctx): array
    {
        $e = [];
        $credito = $ctx['credito'];

        if ($credito === null) {
            $e['cliente'] = 'Seleccione el cliente.';
        } elseif ($credito['estado'] !== 'Activo') {
            $e['cliente'] = 'El cliente está inactivo.';
        }

        $tipoPrestamoId = (int) filter_var($d['tipoPrestamoId'] ?? null, FILTER_VALIDATE_INT);
        if (!isset($ctx['tiposPrestamo'][$tipoPrestamoId])) {
            $e['tipoPrestamoId'] = 'Seleccione un tipo de préstamo activo.';
        }

        $monto = self::numero($d['monto'] ?? null);
        if ($monto === null || $monto <= 0 || $monto > self::MAX_DECIMAL) {
            $e['monto'] = 'Ingrese un monto mayor que cero.';
        } elseif ($credito !== null && round($monto, 2) > $credito['disponible']) {
            $e['monto'] = 'El monto supera el límite de crédito disponible del cliente.';
        }

        $plazo = filter_var($d['plazo'] ?? null, FILTER_VALIDATE_INT);
        if ($plazo === false || $plazo < 1 || $plazo > self::MAX_CUOTAS) {
            $e['plazo'] = 'Ingrese un número de cuotas entre 1 y ' . self::MAX_CUOTAS . '.';
        }

        $reglas = $ctx['reglas'] ?? [];   // reglas de negocio (tabla reglas_negocio); sin regla = sin límite

        if ($monto !== null && $monto > 0 && isset($reglas['monto_minimo']) && $monto < (float) $reglas['monto_minimo'] && !isset($e['monto'])) {
            $e['monto'] = 'El monto mínimo de un préstamo es ' . self::pesos((float) $reglas['monto_minimo']) . '.';
        }

        $tasa = self::numero($d['tasa'] ?? null);
        if ($tasa === null || $tasa < 0 || $tasa > self::MAX_TASA) {
            $e['tasa'] = 'Ingrese una tasa mensual entre 0 y ' . self::MAX_TASA . '.';
        } elseif ((isset($reglas['tasa_minima']) && $tasa < (float) $reglas['tasa_minima'])
            || (isset($reglas['tasa_maxima']) && $tasa > (float) $reglas['tasa_maxima'])) {
            $e['tasa'] = 'La tasa mensual debe estar entre ' . self::pct((float) ($reglas['tasa_minima'] ?? 0)) . ' % y '
                . self::pct((float) ($reglas['tasa_maxima'] ?? self::MAX_TASA)) . ' %.';
        }

        $plazoRow = $ctx['plazos'][(int) ($d['tipoPlazoId'] ?? 0)] ?? null;
        if ($plazoRow === null) {
            $e['tipoPlazoId'] = 'Seleccione el tipo de plazo.';
        } elseif ($plazo !== false && !isset($e['plazo'])) {
            // Rango de cuotas por tipo de plazo (tipos_plazo.plazo_minimo / plazo_maximo).
            $minimo = (int) ($plazoRow['plazo_minimo'] ?? 1);
            $maximo = (int) ($plazoRow['plazo_maximo'] ?? self::MAX_CUOTAS);
            if ($plazo < $minimo || $plazo > $maximo) {
                $e['plazo'] = 'El plazo ' . $plazoRow['nombre'] . ' permite de ' . $minimo . ' a ' . $maximo . ' cuotas.';
            }
        }
        $amortizacion = $ctx['amortizaciones'][(int) ($d['tipoAmortizacionId'] ?? 0)] ?? null;
        if ($amortizacion === null) {
            $e['tipoAmortizacionId'] = 'Seleccione el tipo de amortización.';
        }

        $tipoMora = (string) ($d['tipoMora'] ?? '');
        $valorMora = self::numero($d['valorMora'] ?? null);
        if (!in_array($tipoMora, PrestamoService::TIPOS_MORA, true)) {
            $e['tipoMora'] = 'Tipo de mora inválido.';
        }
        if ($valorMora === null || $valorMora < 0 || $valorMora > self::MAX_DECIMAL) {
            $e['valorMora'] = 'Ingrese el valor de la mora (0 o mayor).';
        } elseif ($tipoMora === 'Porcentaje' && $valorMora > 100) {
            $e['valorMora'] = 'El porcentaje no puede pasar de 100.';
        } elseif ($tipoMora === 'Porcentaje' && isset($reglas['mora_porcentaje_maxima']) && $valorMora > (float) $reglas['mora_porcentaje_maxima']) {
            $e['valorMora'] = 'La mora no puede pasar de ' . self::pct((float) $reglas['mora_porcentaje_maxima']) . ' % de la cuota.';
        } elseif ($tipoMora === 'Monto' && $monto !== null && $monto > 0 && isset($reglas['mora_fija_maxima_pct'])
            && $valorMora > $monto * (float) $reglas['mora_fija_maxima_pct'] / 100) {
            $e['valorMora'] = 'La mora fija no puede pasar de ' . self::pesos(round($monto * (float) $reglas['mora_fija_maxima_pct'] / 100, 2))
                . ' (' . self::pct((float) $reglas['mora_fija_maxima_pct']) . ' % del monto).';
        }

        $seguroTipo = (string) ($d['seguroTipo'] ?? '');
        $seguroMonto = self::numero($d['seguroMonto'] ?? null);
        if (!in_array($seguroTipo, PrestamoService::TIPOS_SEGURO, true)) {
            $e['seguroTipo'] = 'Tipo de seguro inválido.';
        }
        if ($seguroMonto === null || $seguroMonto < 0 || $seguroMonto > self::MAX_DECIMAL) {
            $e['seguroMonto'] = 'Ingrese el monto del seguro (0 si no aplica).';
        } elseif ($monto !== null && $monto > 0 && $plazo !== false && isset($reglas['seguro_total_maximo_pct'])) {
            // Seguro total de todo el préstamo: 'Fijo' es por cuota; 'Repartido' ya es el total.
            $seguroTotal = $seguroTipo === 'Fijo' ? $seguroMonto * $plazo : $seguroMonto;
            $tope = $monto * (float) $reglas['seguro_total_maximo_pct'] / 100;
            if ($seguroTotal > $tope) {
                $e['seguroMonto'] = 'El seguro total (' . self::pesos(round($seguroTotal, 2)) . ') no puede pasar de '
                    . self::pct((float) $reglas['seguro_total_maximo_pct']) . ' % del monto (' . self::pesos(round($tope, 2)) . ').';
            }
        }

        $gastoFinanciado = self::booleano($d['gastoFinanciado'] ?? false, false);
        $gasto = self::numero($d['gastoCierre'] ?? null);
        if ($gasto === null || $gasto < 0 || $gasto > self::MAX_DECIMAL) {
            $e['gastoCierre'] = 'Ingrese el gasto de cierre (0 si no aplica).';
        } elseif (!$gastoFinanciado && $monto !== null && $gasto >= $monto) {
            $e['gastoCierre'] = 'El gasto de cierre debe ser menor que el monto para que quede desembolso.';
        } elseif ($monto !== null && $monto > 0 && isset($reglas['gasto_cierre_maximo_pct'])
            && $gasto > $monto * (float) $reglas['gasto_cierre_maximo_pct'] / 100) {
            $e['gastoCierre'] = 'El gasto de cierre no puede pasar de ' . self::pct((float) $reglas['gasto_cierre_maximo_pct'])
                . ' % del monto (' . self::pesos(round($monto * (float) $reglas['gasto_cierre_maximo_pct'] / 100, 2)) . ').';
        }

        $apertura = CalendarioService::fechaDeTexto((string) ($d['fechaApertura'] ?? ''));
        if ($apertura === null) {
            $e['fechaApertura'] = 'Ingrese una fecha de apertura válida.';
        }
        $primerPago = CalendarioService::fechaDeTexto((string) ($d['fechaPrimerPago'] ?? ''));
        if ($primerPago === null) {
            $e['fechaPrimerPago'] = 'Ingrese la fecha del primer pago.';
        } elseif ($apertura !== null && $primerPago < $apertura) {
            $e['fechaPrimerPago'] = 'El primer pago no puede ser anterior a la apertura.';
        }

        // Solo el plazo diario permite excluir fines de semana; en los demás se ignoran esas opciones.
        $permiteExcluir = $plazoRow !== null && (int) $plazoRow['permite_excluir_fines_semana'] === 1;
        $incluyeSabado = $permiteExcluir ? self::booleano($d['incluyeSabado'] ?? true, true) : true;
        $incluyeDomingo = $permiteExcluir ? self::booleano($d['incluyeDomingo'] ?? true, true) : true;

        if ($primerPago !== null && !isset($e['fechaPrimerPago'])) {
            $dia = (int) $primerPago->format('N'); // 6 = sábado, 7 = domingo
            if (($dia === 6 && !$incluyeSabado) || ($dia === 7 && !$incluyeDomingo)) {
                $e['fechaPrimerPago'] = 'La fecha del primer pago cae en un día excluido (sábado o domingo).';
            }
        }

        if ($e) {
            return [$e, []];
        }

        return [[], [
            'clienteId'       => (int) $credito['id'],
            'tipoPrestamoId'  => $tipoPrestamoId,
            'monto'           => round($monto, 2),
            'plazo'           => $plazo,
            'tasa'            => round($tasa, 4),
            'plazoRow'        => $plazoRow,
            'tipoPlazoId'     => (int) $plazoRow['id'],
            'tipoAmortizacionId' => (int) $amortizacion['id'],
            'codigo'          => $amortizacion['codigo'],
            'incluyeSabado'   => $incluyeSabado,
            'incluyeDomingo'  => $incluyeDomingo,
            'tipoMora'        => $tipoMora,
            'valorMora'       => round($valorMora, 2),
            'seguroTipo'      => $seguroTipo,
            'seguroMonto'     => round($seguroMonto, 2),
            'gastoCierre'     => round($gasto, 2),
            'gastoFinanciado' => $gastoFinanciado,
            'fechaApertura'   => $apertura,
            'fechaPrimerPago' => $primerPago,
        ]];
    }

    private static function numero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric($valor))) {
            $n = (float) $valor;
            return is_finite($n) ? $n : null;
        }
        return null;
    }

    private static function booleano(mixed $valor, bool $porDefecto): bool
    {
        return filter_var($valor, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $porDefecto;
    }
}
