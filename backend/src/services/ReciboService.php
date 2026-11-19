<?php
declare(strict_types=1);

/**
 * Recibos de ingreso: consulta de la cuenta del préstamo, vista previa y aplicación del pago.
 * Todo el cálculo lo hace PagoService; aquí solo se valida, se arma el contexto y se guarda en una transacción.
 */
final class ReciboService
{
    public const FORMATOS = ['A4', 'POS80'];

    public function __construct(
        private PrestamoModel $prestamos = new PrestamoModel(),
        private CuotaModel $cuotas = new CuotaModel(),
        private ReciboModel $recibos = new ReciboModel(),
        private MoraService $mora = new MoraService(),
    ) {
    }

    public function listarPrestamos(array $filtros): array
    {
        return array_map(fn(array $f) => [
            'id'               => (int) $f['id'],
            'numero'           => $f['numero'],
            'cliente'          => $f['nombres'] . ' ' . $f['apellidos'],
            'numeroDocumento'  => $f['numero_documento'],
            'monto'            => round((float) $f['monto'], 2),
            'capitalPendiente' => round((float) $f['capital_pendiente'], 2),
            'estado'           => $f['estado'],
            'amortizacion'     => $f['amortizacion_nombre'],
            'fechaApertura'    => substr((string) $f['fecha_apertura'], 0, 10),
        ], $this->prestamos->listar($filtros));
    }

    /** Estado actual del préstamo: cuotas con lo pendiente de cada una, mora, valores sugeridos e historial. */
    public function cuenta(int $prestamoId): array
    {
        $fila = $this->prestamos->buscarPorId($prestamoId);
        if ($fila === null) {
            throw new ErrorApi('Préstamo no encontrado.', 404);
        }
        $this->mora->sincronizar($prestamoId); // guarda la mora de las cuotas vencidas hasta hoy
        $cuotas = $this->cuotas->delPrestamo($prestamoId);
        $hoy = date('Y-m-d');

        $filas = [];
        $mora = 0;
        $vencidas = 0;
        $vencido = 0;
        $primera = null;
        $capitalNoFuturo = 0;
        foreach ($cuotas as $c) {
            $pendiente = [
                'mora'     => PagoService::c($c['mora']) - PagoService::c($c['moraPagada']),
                'interes'  => PagoService::c($c['interes']) - PagoService::c($c['interesPagado']),
                'seguro'   => PagoService::c($c['seguro']) - PagoService::c($c['seguroPagado']),
                'gasto'    => PagoService::c($c['gasto']) - PagoService::c($c['gastoPagado']),
                'capital'  => PagoService::c($c['capital']) - PagoService::c($c['capitalPagado']),
            ];
            $total = array_sum($pendiente);
            $saldada = MoraService::estaSaldada($c);
            $mora += $pendiente['mora'];
            if (!$saldada) {
                $primera ??= $total;
                if ($c['fechaPago'] < $hoy) {
                    $vencidas++;
                    $vencido += $total;
                }
            }
            if ($c['estado'] !== 'Pendiente') {
                $capitalNoFuturo += $pendiente['capital'];
            }
            $filas[] = $c + [
                'pendiente'      => PagoService::m($total),
                'moraPendiente'  => PagoService::m($pendiente['mora']),
            ];
        }

        $capitalPendiente = PagoService::c($fila['capital_pendiente']);
        $cf = max(0, $capitalPendiente - $capitalNoFuturo);

        return [
            'prestamo' => [
                'id'               => (int) $fila['id'],
                'numero'           => $fila['numero'],
                'cliente'          => $fila['nombres'] . ' ' . $fila['apellidos'],
                'numeroDocumento'  => $fila['numero_documento'],
                'correo'           => $fila['correo'],
                'tipoPrestamo'     => $fila['tipo_prestamo'],
                'amortizacion'     => $fila['amortizacion_nombre'],
                'codigo'           => $fila['amortizacion_codigo'],
                'plazoNombre'      => $fila['plazo_nombre'],
                'monto'            => round((float) $fila['monto'], 2),
                'plazo'            => (int) $fila['plazo'],
                'tasa'             => round((float) $fila['tasa'], 4),
                'capitalPendiente' => PagoService::m($capitalPendiente),
                'estado'           => $fila['estado'],
                'tipoMora'         => $fila['tipo_mora'],
                'valorMora'        => round((float) $fila['valor_mora'], 2),
            ],
            'resumen' => [
                'moraPendiente'  => PagoService::m($mora),
                'cuotasVencidas' => $vencidas,
                'totalVencido'   => PagoService::m($vencido),
                'proximaCuota'   => PagoService::m($primera ?? 0),
                'abonoMaximo'    => $mora > 0 ? 0.0 : PagoService::m($cf),
                'capitalFuturo'  => PagoService::m($cf),
                'hoy'            => $hoy,
            ],
            'cuotas'    => $filas,
            'historial' => array_map(fn(array $r) => [
                'id'              => (int) $r['id'],
                'numero'          => $r['numero'],
                'fecha'           => substr(str_replace(' ', 'T', (string) $r['fecha']), 0, 19),
                'tipoPago'        => $r['tipo_pago'],
                'monto'           => round((float) $r['monto_recibido'], 2),
                'balancePosterior' => round((float) $r['balance_posterior'], 2),
                'estado'          => $r['estado'],
            ], $this->recibos->delPrestamo($prestamoId)),
        ];
    }

    /** Vista previa: calcula la aplicación del pago sin guardar nada. */
    public function calcular(array $datos): array
    {
        $e = $this->validar($datos);
        $fila = $this->prestamos->buscarPorId($e['prestamo']);
        if ($fila === null) {
            throw new ErrorApi('Préstamo no encontrado.', 404);
        }
        $this->exigirActivo($fila);
        $this->mora->sincronizar($e['prestamo']);
        $plan = PagoService::planear(
            $this->contexto($fila),
            $this->cuotas->delPrestamo($e['prestamo']),
            $e,
            date('Y-m-d')
        );
        unset($plan['cuotasCambiadas']);
        $plan['cuotasNuevas'] = array_map(fn(array $c) => [
            'numero' => $c['numero'], 'fechaPago' => $c['fechaPago'], 'valor' => $c['valor'],
        ], $plan['cuotasNuevas']);
        return $plan;
    }

    /** Aplica el pago y genera el recibo, todo en una sola transacción. */
    public function aplicar(array $datos, int $usuarioId): array
    {
        $e = $this->validar($datos);
        $formato = $e['formato'];

        $reciboId = Database::transaccion(function () use ($e, $formato, $usuarioId): int {
            $fila = $this->prestamos->buscarPorId($e['prestamo'], true); // bloquea el préstamo
            if ($fila === null) {
                throw new ErrorApi('Préstamo no encontrado.', 404);
            }
            $this->exigirActivo($fila);
            $this->mora->sincronizar($e['prestamo']);
            $plan = PagoService::planear(
                $this->contexto($fila),
                $this->cuotas->delPrestamo($e['prestamo']),
                $e,
                date('Y-m-d')
            );

            foreach ($plan['cuotasEliminadas'] as $numero) {
                $this->cuotas->eliminar($e['prestamo'], $numero);
            }
            $ids = [];
            foreach ($plan['cuotasCambiadas'] as $c) {
                $this->cuotas->actualizar($c);
                $ids[$c['numero']] = $c['id'];
            }
            foreach ($plan['cuotasNuevas'] as $c) {
                $ids[$c['numero']] = $this->cuotas->insertar($e['prestamo'], $c);
            }

            [$reciboId] = $this->recibos->insertar($e['prestamo'], $plan, $formato, $e['observacion'], $usuarioId);
            foreach ($plan['detalle'] as $d) {
                $cuotaId = $d['cuotaNumero'] === null ? null : ($ids[$d['cuotaNumero']] ?? null);
                $this->recibos->insertarDetalle($reciboId, $cuotaId, $d);
            }
            $this->prestamos->actualizarEstado(
                $e['prestamo'], $plan['prestamo']['capitalPendiente'], $plan['prestamo']['plazo'], $plan['prestamo']['estado']
            );
            return $reciboId;
        });

        return $this->obtener($reciboId);
    }

    public function obtener(int $id): array
    {
        $r = $this->recibos->buscarPorId($id);
        if ($r === null) {
            throw new ErrorApi('Recibo no encontrado.', 404);
        }
        return [
            'id'              => (int) $r['id'],
            'numero'          => $r['numero'],
            'fecha'           => substr(str_replace(' ', 'T', (string) $r['fecha']), 0, 19),
            'tipoPago'        => $r['tipo_pago'],
            'modalidad'       => $r['modalidad_abono'],
            'estado'          => $r['estado'],
            'formato'         => $r['formato_impreso'],
            'observacion'     => $r['observacion'],
            'monto'           => round((float) $r['monto_recibido'], 2),
            'totalMora'       => round((float) $r['total_mora'], 2),
            'totalInteres'    => round((float) $r['total_interes'], 2),
            'totalSeguro'     => round((float) $r['total_seguro'], 2),
            'totalGasto'      => round((float) $r['total_gasto_cierre'], 2),
            'totalCapital'    => round((float) $r['total_capital'], 2),
            'balancePosterior' => round((float) $r['balance_posterior'], 2),
            'prestamoId'      => (int) $r['prestamo_id'],
            'prestamoNumero'  => $r['prestamo_numero'],
            'tipoPrestamo'    => $r['tipo_prestamo'],
            'cliente'         => $r['nombres'] . ' ' . $r['apellidos'],
            'numeroDocumento' => $r['numero_documento'],
            'correo'          => $r['correo'],
            'cajero'          => $r['cajero'],
            'empresa'         => (string) Configuracion::obtener('empresa.nombre', 'Sistema de Gestión de Préstamos'),
            'detalle'         => array_map(fn(array $d) => [
                'cuotaNumero' => $d['numero_cuota'] === null ? null : (int) $d['numero_cuota'],
                'fechaPago'   => $d['fecha_pago'] === null ? null : substr((string) $d['fecha_pago'], 0, 10),
                'mora'        => round((float) $d['mora_aplicada'], 2),
                'interes'     => round((float) $d['interes_aplicado'], 2),
                'seguro'      => round((float) $d['seguro_aplicado'], 2),
                'gasto'       => round((float) $d['gasto_cierre_aplicado'], 2),
                'capital'     => round((float) $d['capital_aplicado'], 2),
            ], $this->recibos->detalle($id)),
        ];
    }

    public function cambiarFormato(int $id, array $datos): array
    {
        $formato = (string) ($datos['formato'] ?? '');
        if (!in_array($formato, self::FORMATOS, true)) {
            throw new ErrorApi('Formato inválido.', 422, ['formato' => 'Elija A4 o POS80.']);
        }
        $this->obtener($id); // 404 si no existe
        $this->recibos->cambiarFormato($id, $formato);
        return $this->obtener($id);
    }

    public function enviarCorreo(int $id, array $datos): array
    {
        $recibo = $this->obtener($id);
        $destino = trim((string) ($datos['correo'] ?? ''));
        if ($destino === '' || !filter_var($destino, FILTER_VALIDATE_EMAIL) || strlen($destino) > 150) {
            throw new ErrorApi('Ingrese un correo válido.', 422, ['correo' => 'Ingrese un correo válido.']);
        }
        (new CorreoService())->enviarRecibo($recibo, $destino);
        return ['enviado' => true, 'correo' => $destino];
    }

    // ------------------------------------------------------------------ apoyo

    private function validar(array $d): array
    {
        $errores = [];

        $prestamo = $d['prestamo'] ?? null;
        if (!is_int($prestamo) && !(is_string($prestamo) && ctype_digit($prestamo))) {
            $errores['prestamo'] = 'Seleccione un préstamo.';
        }

        $tipo = $d['tipoPago'] ?? '';
        if (!in_array($tipo, PagoService::TIPOS, true)) {
            $errores['tipoPago'] = 'Seleccione el tipo de pago.';
        }

        $monto = $d['monto'] ?? null;
        if (!is_numeric($monto) || (float) $monto <= 0) {
            $errores['monto'] = 'Ingrese un monto mayor que cero.';
        } elseif (abs((float) $monto * 100 - round((float) $monto * 100)) > 1e-6) {
            $errores['monto'] = 'El monto admite como máximo 2 decimales.';
        } elseif ((float) $monto > 999999999.99) {
            $errores['monto'] = 'El monto es demasiado grande.';
        }

        $modalidad = $d['modalidad'] ?? null;
        if ($tipo === 'abono_capital' && !in_array($modalidad, PagoService::MODALIDADES, true)) {
            $errores['modalidad'] = 'Elija reducir cuota o reducir plazo.';
        }

        $formato = $d['formato'] ?? 'A4';
        if (!in_array($formato, self::FORMATOS, true)) {
            $errores['formato'] = 'Elija A4 o POS80.';
        }

        $observacion = trim((string) ($d['observacion'] ?? ''));
        if (ClienteValidador::largo($observacion) > 250) {
            $errores['observacion'] = 'Máximo 250 caracteres.';
        }

        if ($errores) {
            throw new ErrorApi('Revise los datos del pago.', 422, $errores);
        }
        return [
            'prestamo'    => (int) $prestamo,
            'tipoPago'    => $tipo,
            'monto'       => round((float) $monto, 2),
            'modalidad'   => $tipo === 'abono_capital' ? $modalidad : null,
            'formato'     => $formato,
            'observacion' => $observacion === '' ? null : $observacion,
        ];
    }

    private function exigirActivo(array $fila): void
    {
        if ($fila['estado'] !== 'Activo') {
            throw new ErrorApi('El préstamo está en estado ' . $fila['estado'] . ' y no admite pagos.', 409);
        }
    }

    /** Datos del préstamo en la forma que usan AmortizacionService, MoraService y PagoService. */
    private function contexto(array $f): array
    {
        $fecha = CalendarioService::fechaDeTexto(substr((string) $f['fecha_primer_pago'], 0, 10));
        return [
            'monto'            => (float) $f['monto'],
            'plazo'            => (int) $f['plazo'],
            'tasa'             => (float) $f['tasa'],
            'codigo'           => $f['amortizacion_codigo'],
            'plazoRow'         => [
                'dias_periodo' => (int) $f['dias_periodo'], 'meses_periodo' => (int) $f['meses_periodo'],
                'permite_excluir_fines_semana' => (int) $f['permite_excluir_fines_semana'],
            ],
            'incluyeSabado'    => (bool) (int) $f['incluye_sabado'],
            'incluyeDomingo'   => (bool) (int) $f['incluye_domingo'],
            'seguroTipo'       => $f['seguro_tipo'],
            'seguroMonto'      => (float) $f['seguro_monto'],
            'gastoCierre'      => (float) $f['gasto_cierre'],
            'gastoFinanciado'  => (bool) (int) $f['gasto_cierre_financiado'],
            'fechaPrimerPago'  => $fecha,
            'tipoMora'         => $f['tipo_mora'],
            'valorMora'        => (float) $f['valor_mora'],
            'capitalPendiente' => (float) $f['capital_pendiente'],
        ];
    }
}
