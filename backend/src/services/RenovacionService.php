<?php
declare(strict_types=1);

/**
 * Renovación de préstamos: cancela de 1 a 3 préstamos activos de un cliente y apertura uno nuevo, todo vinculado
 * y en una sola transacción. El préstamo nuevo se valida y calcula con el mismo proceso de la apertura.
 *
 * Datos de entrada = los de la apertura + renovar: [{ prestamoId, base }] (base: ver CancelacionService::BASES).
 * Desembolso final = desembolso del préstamo nuevo − suma de los saldos cancelados.
 */
final class RenovacionService
{
    public const MAX_PRESTAMOS = 3;

    public function __construct(
        private PrestamoService $apertura = new PrestamoService(),
        private PrestamoModel $prestamos = new PrestamoModel(),
        private CuotaModel $cuotas = new CuotaModel(),
        private ReciboModel $recibos = new ReciboModel(),
        private RenovacionModel $renovaciones = new RenovacionModel(),
    ) {
    }

    /** Préstamos activos del cliente con el saldo a cancelar según cada base (para elegir en pantalla). */
    public function renovables(int $clienteId): array
    {
        $hoy = date('Y-m-d');
        $lista = [];
        foreach ($this->prestamos->idsActivosDelCliente($clienteId) as $id) {
            $fila = $this->prestamos->buscarPorId($id);
            if ($fila === null) {
                continue;
            }
            $cuotas = $this->cuotas->delPrestamo($id);
            $saldos = [];
            foreach (CancelacionService::BASES as $base) {
                $saldos[$base] = CancelacionService::planear($this->datosMora($fila), $cuotas, $base, $hoy)['montoCancelado'];
            }
            $lista[] = [
                'id'               => $id,
                'numero'           => $fila['numero'],
                'tipoPrestamo'     => $fila['tipo_prestamo'],
                'amortizacion'     => $fila['amortizacion_nombre'],
                'monto'            => round((float) $fila['monto'], 2),
                'plazo'            => (int) $fila['plazo'],
                'capitalPendiente' => round((float) $fila['capital_pendiente'], 2),
                'saldos'           => $saldos,
            ];
        }
        return $lista;
    }

    /** Botón Calcular: el cálculo del préstamo nuevo más los saldos a cancelar. No guarda nada. */
    public function calcular(array $d): array
    {
        $items = $this->preparar($d, false);
        return $this->armarCalculo($d, $items);
    }

    /** Botón Guardar: cancela los préstamos anteriores y apertura el nuevo en una sola transacción. */
    public function guardar(array $d, int $usuarioId): array
    {
        return Database::transaccion(function () use ($d, $usuarioId): array {
            $clienteId = (int) filter_var($d['clienteId'] ?? null, FILTER_VALIDATE_INT);
            if ($clienteId > 0) {
                $this->prestamos->bloquearCliente($clienteId);
            }
            $items = $this->preparar($d, true);          // bloquea los préstamos a renovar
            $previo = $this->armarCalculo($d, $items);   // valida monto, límite y desembolso antes de escribir nada

            $cancelaciones = [];
            foreach ($items as $it) {
                $cancelaciones[] = $this->cancelar($it, $usuarioId);
            }

            // Con los préstamos anteriores ya cancelados, el límite disponible real incluye su capital liberado.
            $nuevo = $this->apertura->guardarEnTransaccion($d, $usuarioId);

            $r = $previo['renovacion'];
            $renovacionId = $this->renovaciones->insertar($clienteId, $nuevo['id'], $r['totalSaldoCancelado'], $r['desembolsoFinal'], $usuarioId);
            foreach ($cancelaciones as $c) {
                $this->renovaciones->insertarDetalle($renovacionId, $c['prestamoId'], $c['base'], $c['montoCancelado'], $c['descuento'], $c['reciboId']);
            }

            return $nuevo + ['renovacionId' => $renovacionId, 'renovacion' => $r + ['recibos' => array_map(
                fn(array $c) => ['id' => $c['reciboId'], 'numero' => $c['reciboNumero'], 'prestamo' => $c['prestamoNumero']],
                $cancelaciones
            )]];
        });
    }

    // ------------------------------------------------------------------ apoyo

    /** Valida la selección de préstamos y calcula la cancelación de cada uno. */
    private function preparar(array $d, bool $bloquear): array
    {
        $clienteId = (int) filter_var($d['clienteId'] ?? null, FILTER_VALIDATE_INT);
        if ($clienteId < 1) {
            throw new ErrorApi('Revise los datos de la renovación.', 422, ['cliente' => 'Seleccione el cliente.']);
        }

        $lista = $d['renovar'] ?? null;
        if (!is_array($lista) || count($lista) < 1 || count($lista) > self::MAX_PRESTAMOS) {
            throw self::error('Seleccione de 1 a ' . self::MAX_PRESTAMOS . ' préstamos para renovar.');
        }

        $pedidos = [];
        foreach ($lista as $elemento) {
            $id = is_array($elemento) ? filter_var($elemento['prestamoId'] ?? null, FILTER_VALIDATE_INT) : false;
            $base = is_array($elemento) ? ($elemento['base'] ?? null) : null;
            if ($id === false || $id < 1 || !in_array($base, CancelacionService::BASES, true)) {
                throw self::error('Elija la base de saldo de cada préstamo a renovar.');
            }
            if (isset($pedidos[$id])) {
                throw self::error('No repita un mismo préstamo en la renovación.');
            }
            $pedidos[$id] = $base;
        }
        ksort($pedidos); // siempre en el mismo orden: evita bloqueos cruzados entre transacciones

        $hoy = date('Y-m-d');
        $items = [];
        foreach ($pedidos as $id => $base) {
            $fila = $this->prestamos->buscarPorId($id, $bloquear);
            if ($fila === null || (int) $fila['cliente_id'] !== $clienteId) {
                throw self::error('Uno de los préstamos no pertenece al cliente seleccionado.');
            }
            if ($fila['estado'] !== 'Activo') {
                throw self::error("El préstamo {$fila['numero']} no está activo y no se puede renovar.");
            }
            $cuotas = $this->cuotas->delPrestamo($id);
            $plan = CancelacionService::planear($this->datosMora($fila), $cuotas, $base, $hoy);
            if ($plan['montoCancelado'] <= 0) {
                throw self::error("El préstamo {$fila['numero']} no tiene saldo a cancelar con la base «{$base}».");
            }
            $items[] = ['fila' => $fila, 'cuotas' => $cuotas, 'plan' => $plan, 'base' => $base];
        }
        return $items;
    }

    /** Calcula el préstamo nuevo y valida contra los saldos a cancelar. */
    private function armarCalculo(array $d, array $items): array
    {
        $liberado = 0;
        $totalSaldo = 0;
        $totalDescuento = 0;
        $detalle = [];
        foreach ($items as $it) {
            $liberado += PagoService::c($it['fila']['capital_pendiente']);
            $totalSaldo += PagoService::c($it['plan']['montoCancelado']);
            $totalDescuento += PagoService::c($it['plan']['descuento']);
            $detalle[] = [
                'prestamoId'     => (int) $it['fila']['id'],
                'numero'         => $it['fila']['numero'],
                'base'           => $it['base'],
                'montoCancelado' => $it['plan']['montoCancelado'],
                'descuento'      => $it['plan']['descuento'],
            ];
        }

        $calculo = $this->apertura->calcular($d, PagoService::m($liberado));
        $monto = PagoService::c($calculo['resumen']['monto']);
        $desembolso = PagoService::c($calculo['resumen']['desembolso']);

        if ($monto < $totalSaldo) {
            throw new ErrorApi('Revise los datos de la renovación.', 422, [
                'monto' => 'El monto del préstamo nuevo no puede ser menor que la suma de los saldos a cancelar (RD$ '
                    . number_format(PagoService::m($totalSaldo), 2) . ').',
            ]);
        }
        if ($desembolso - $totalSaldo < 0) {
            throw new ErrorApi('Revise los datos de la renovación.', 422, [
                'monto' => 'El desembolso del préstamo nuevo (RD$ ' . number_format(PagoService::m($desembolso), 2)
                    . ') no alcanza para cancelar los saldos (RD$ ' . number_format(PagoService::m($totalSaldo), 2) . ').',
            ]);
        }

        return $calculo + ['renovacion' => [
            'prestamos'           => $detalle,
            'totalSaldoCancelado' => PagoService::m($totalSaldo),
            'totalDescuento'      => PagoService::m($totalDescuento),
            'desembolsoNuevo'     => PagoService::m($desembolso),
            'desembolsoFinal'     => PagoService::m($desembolso - $totalSaldo),
        ]];
    }

    /** Escribe la cancelación de un préstamo: cuotas, recibo con su detalle y estado «Renovado». */
    private function cancelar(array $it, int $usuarioId): array
    {
        $fila = $it['fila'];
        $plan = $it['plan'];
        $prestamoId = (int) $fila['id'];
        $ids = array_column($it['cuotas'], 'id', 'numero');

        foreach ($plan['cuotasCambiadas'] as $c) {
            $this->cuotas->actualizar($c);
        }

        $observacion = 'Cancelación por renovación (base: ' . $plan['base'] . ')';
        [$reciboId, $numero] = $this->recibos->insertar($prestamoId, $plan, 'A4', $observacion, $usuarioId);
        foreach ($plan['detalle'] as $linea) {
            $cuotaId = $linea['cuotaNumero'] === null ? null : ($ids[$linea['cuotaNumero']] ?? null);
            $this->recibos->insertarDetalle($reciboId, $cuotaId, $linea);
        }
        $this->prestamos->actualizarEstado($prestamoId, 0.0, (int) $fila['plazo'], 'Renovado');

        return [
            'prestamoId' => $prestamoId, 'prestamoNumero' => $fila['numero'], 'base' => $plan['base'],
            'montoCancelado' => $plan['montoCancelado'], 'descuento' => $plan['descuento'],
            'reciboId' => $reciboId, 'reciboNumero' => $numero,
        ];
    }

    private function datosMora(array $fila): array
    {
        return [
            'capitalPendiente' => (float) $fila['capital_pendiente'],
            'tipoMora'         => $fila['tipo_mora'],
            'valorMora'        => (float) $fila['valor_mora'],
        ];
    }

    private static function error(string $mensaje): ErrorApi
    {
        return new ErrorApi($mensaje, 422, ['renovar' => $mensaje]);
    }
}
