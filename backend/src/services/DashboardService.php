<?php
declare(strict_types=1);

/** Arma la respuesta del dashboard: da forma a los datos y completa los meses sin ingresos con 0. */
final class DashboardService
{
    private const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public function __construct(
        private DashboardModel $modelo = new DashboardModel(),
        private MoraService $mora = new MoraService(),
    ) {
    }

    public function resumen(): array
    {
        $this->mora->sincronizar(); // la mora y el estado de las cuotas vencidas se actualizan a la fecha de hoy
        $hoy = new DateTimeImmutable('today');
        $inicioMes = $hoy->modify('first day of this month');
        $inicioSiguiente = $inicioMes->modify('+1 month');

        $ind = $this->modelo->indicadores(
            $inicioMes->format('Y-m-d'),
            $inicioSiguiente->format('Y-m-d'),
            $hoy->format('Y-m-d')
        );

        return [
            'indicadores' => [
                'mesActual'        => self::MESES[(int) $hoy->format('n') - 1] . ' ' . $hoy->format('Y'),
                'totalClientes'    => (int) $ind['total_clientes'],
                'prestamosActivos' => (int) $ind['prestamos_activos'],
                'capitalColocado'  => round((float) $ind['capital_colocado'], 2),
                'capitalPendiente' => round((float) $ind['capital_pendiente'], 2),
                'pagosMes'         => round((float) $ind['pagos_mes'], 2),
                'cuotasVencidas'   => (int) $ind['cuotas_vencidas'],
                'moraPendiente'    => round((float) $ind['mora_pendiente'], 2),
            ],
            'prestamosPorEstado' => array_map(
                fn(array $f) => ['estado' => $f['estado'], 'cantidad' => (int) $f['cantidad']],
                $this->modelo->prestamosPorEstado()
            ),
            'ingresosPorMes'   => $this->ingresosUltimosMeses($inicioMes, 6),
            'tiposPrestamo'    => array_map(
                fn(array $f) => ['nombre' => $f['nombre'], 'cantidad' => (int) $f['cantidad']],
                $this->modelo->distribucionTipos()
            ),
            'actividadReciente' => array_map(
                fn(array $f) => [
                    'tipo'       => $f['tipo'],
                    'fecha'      => substr(str_replace(' ', 'T', (string) $f['fecha']), 0, 19),
                    'referencia' => $f['referencia'],
                    'cliente'    => $f['cliente'],
                    'monto'      => round((float) $f['monto'], 2),
                ],
                $this->modelo->actividadReciente()
            ),
        ];
    }

    /** Los últimos $cantidad meses (incluido el actual); los meses sin cobros salen con total 0. */
    private function ingresosUltimosMeses(DateTimeImmutable $inicioMes, int $cantidad): array
    {
        $desde = $inicioMes->modify('-' . ($cantidad - 1) . ' months');

        $totales = [];
        foreach ($this->modelo->ingresosDesde($desde->format('Y-m-d')) as $f) {
            $totales[sprintf('%04d-%02d', (int) $f['anio'], (int) $f['mes'])] = (float) $f['total'];
        }

        $serie = [];
        for ($k = 0; $k < $cantidad; $k++) {
            $mes = $desde->modify("+$k months");
            $indice = (int) $mes->format('n') - 1;
            $clave = $mes->format('Y-m');
            $serie[] = [
                'periodo'      => $clave,
                'etiqueta'     => self::MESES_CORTOS[$indice],
                'periodoTexto' => self::MESES[$indice] . ' ' . $mes->format('Y'),
                'total'        => round($totales[$clave] ?? 0.0, 2),
            ];
        }
        return $serie;
    }
}
