<?php
declare(strict_types=1);

/**
 * Fechas de pago según el tipo de plazo. Es la ÚNICA fuente de este cálculo:
 * la apertura la usa para sugerir la fecha del primer pago y la Etapa 7 la reutilizará
 * para fechar todas las cuotas.
 *
 * $plazo = fila de tipos_plazo: dias_periodo, meses_periodo, permite_excluir_fines_semana.
 *  - meses_periodo > 0  : se avanza por meses de calendario (31 de enero + 1 mes = 28/29 de febrero).
 *  - en otro caso       : se avanza dias_periodo días por período.
 *  - Diario sin sábado y/o domingo: esos días se saltan.
 * El avance siempre se mide desde la fecha base (no encadenado), para que un fin de mes no arrastre el error.
 */
final class CalendarioService
{
    public static function avanzar(
        DateTimeImmutable $base,
        array $plazo,
        int $periodos,
        bool $incluyeSabado = true,
        bool $incluyeDomingo = true
    ): DateTimeImmutable {
        $meses = (int) $plazo['meses_periodo'];
        if ($meses > 0) {
            return self::sumarMeses($base, $meses * $periodos);
        }

        $dias = (int) $plazo['dias_periodo'];
        $excluye = (bool) $plazo['permite_excluir_fines_semana'] && (!$incluyeSabado || !$incluyeDomingo);
        if (!$excluye) {
            return $base->modify('+' . ($dias * $periodos) . ' days');
        }

        $fecha = $base;
        for ($i = 0; $i < $periodos; $i++) {
            do {
                $fecha = $fecha->modify('+1 day');
                $diaSemana = (int) $fecha->format('N'); // 6 = sábado, 7 = domingo
            } while (($diaSemana === 6 && !$incluyeSabado) || ($diaSemana === 7 && !$incluyeDomingo));
        }
        return $fecha;
    }

    /** Fecha por defecto del primer pago: un período después de la apertura. */
    public static function primerPago(
        DateTimeImmutable $apertura,
        array $plazo,
        bool $incluyeSabado = true,
        bool $incluyeDomingo = true
    ): DateTimeImmutable {
        return self::avanzar($apertura, $plazo, 1, $incluyeSabado, $incluyeDomingo);
    }

    /** Convierte 'AAAA-MM-DD' en fecha, o null si no es una fecha real (rechaza 2026-02-30). */
    public static function fechaDeTexto(string $texto): ?DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
        return ($fecha !== false && $fecha->format('Y-m-d') === $texto) ? $fecha : null;
    }

    private static function sumarMeses(DateTimeImmutable $base, int $meses): DateTimeImmutable
    {
        $anio = (int) $base->format('Y');
        $mes = (int) $base->format('n') - 1 + $meses;
        $anio += intdiv($mes, 12);
        $mes = $mes % 12 + 1;
        $dia = min((int) $base->format('j'), (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes)))->format('t'));
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $anio, $mes, $dia));
    }
}
