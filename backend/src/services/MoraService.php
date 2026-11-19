<?php
declare(strict_types=1);

/**
 * Mora y estado de las cuotas. Una sola fuente de estas reglas para la consulta, los recibos y el dashboard.
 *  - Una cuota está vencida si su fecha de pago es anterior a hoy y no está saldada.
 *  - La mora se cobra UNA sola vez por cuota vencida: un porcentaje del valor de la cuota o un monto fijo.
 *  - Estado: Pagada (todo pagado, incluida la mora) · Parcial (hay pagos de seguro/interés/gasto/capital)
 *    · Vencida (fecha pasada sin pagos) · Pendiente.
 * Los importes de las cuotas son float con 2 decimales; para comparar se usan centavos enteros.
 */
final class MoraService
{
    private const COMPONENTES = ['capital', 'interes', 'seguro', 'gasto', 'mora'];

    public function __construct(private CuotaModel $cuotas = new CuotaModel())
    {
    }

    /** Mora de una cuota: porcentaje (con aritmética entera, mitad hacia arriba) o monto fijo. */
    public static function moraDe(string $tipoMora, float $valorMora, float $valorCuota): float
    {
        if ($tipoMora === 'Monto') {
            return round($valorMora, 2);
        }
        $centavos = (int) round($valorCuota * 100);
        $centesimas = (int) round($valorMora * 100); // el porcentaje tiene 2 decimales
        return intdiv($centavos * $centesimas + 5000, 10000) / 100;
    }

    /** ¿Todo lo de la cuota está pagado? Cuota con mora 0 queda saldada sin pagar mora. */
    public static function estaSaldada(array $c): bool
    {
        foreach (self::COMPONENTES as $k) {
            $pagado = $k === 'gasto' ? 'gastoPagado' : $k . ($k === 'mora' ? 'Pagada' : 'Pagado');
            if ((int) round($c[$k] * 100) > (int) round($c[$pagado] * 100)) {
                return false;
            }
        }
        return true;
    }

    public static function estadoDe(array $c, string $hoy): string
    {
        if (self::estaSaldada($c)) {
            return 'Pagada';
        }
        if ($c['capitalPagado'] + $c['interesPagado'] + $c['seguroPagado'] + $c['gastoPagado'] > 0) {
            return 'Parcial';
        }
        return $c['fechaPago'] < $hoy ? 'Vencida' : 'Pendiente';
    }

    /** Aplica la mora (si corresponde) y recalcula el estado de UNA cuota. */
    public static function aplicarACuota(array $prestamo, array $c, string $hoy): array
    {
        $vencida = $c['fechaPago'] < $hoy;
        if ($vencida && !self::estaSaldada($c) && $c['mora'] == 0 && $c['moraPagada'] == 0) {
            $c['mora'] = self::moraDe($prestamo['tipoMora'], (float) $prestamo['valorMora'], (float) $c['valor']);
        }
        $c['estado'] = self::estadoDe($c, $hoy);
        return $c;
    }

    /** Aplica la mora a todas las cuotas de un préstamo (solo en memoria; no escribe en la base de datos). */
    public static function aplicarEnMemoria(array $prestamo, array $cuotas, string $hoy): array
    {
        return array_map(fn(array $c) => self::aplicarACuota($prestamo, $c, $hoy), $cuotas);
    }

    /**
     * Guarda en la base de datos la mora y el estado de las cuotas vencidas de los préstamos activos
     * (de un préstamo si se indica $prestamoId). Es idempotente: solo escribe lo que cambió.
     * Devuelve cuántas cuotas se actualizaron.
     */
    public function sincronizar(?int $prestamoId = null, ?string $hoy = null): int
    {
        $hoy ??= date('Y-m-d');
        $cambiadas = 0;
        foreach ($this->cuotas->candidatasAMora($hoy, $prestamoId) as $fila) {
            $prestamo = ['tipoMora' => $fila['tipo_mora'], 'valorMora' => (float) $fila['valor_mora']];
            $antes = CuotaModel::aCuota($fila);
            $despues = self::aplicarACuota($prestamo, $antes, $hoy);
            if ($despues['mora'] !== $antes['mora'] || $despues['estado'] !== $antes['estado']) {
                $this->cuotas->actualizarMoraYEstado((int) $fila['id'], $despues['mora'], $despues['estado']);
                $cambiadas++;
            }
        }
        return $cambiadas;
    }
}
