<?php
declare(strict_types=1);

/**
 * Apoyo de la pantalla de apertura de préstamos.
 * (El cálculo de la tabla de amortización y el guardado se construyen en la Etapa 7.)
 */
final class PrestamoService
{
    public const TIPOS_MORA = ['Porcentaje', 'Monto'];
    public const TIPOS_SEGURO = ['Fijo', 'Repartido'];

    public function __construct(
        private CatalogoModel $catalogos = new CatalogoModel(),
        private TipoPrestamoModel $tipos = new TipoPrestamoModel(),
        private ClienteModel $clientes = new ClienteModel(),
        private GaranteModel $garantes = new GaranteModel(),
        private PrestamoModel $prestamos = new PrestamoModel(),
    ) {
    }

    /** Listas desplegables de la apertura. Solo los tipos de préstamo activos se pueden elegir. */
    public function catalogos(): array
    {
        return [
            'tiposPrestamo' => array_map(
                fn(array $t) => ['id' => (int) $t['id'], 'nombre' => $t['nombre']],
                $this->tipos->listar(['estado' => 'Activo'])
            ),
            'tiposPlazo' => array_map(fn(array $p) => [
                'id'                      => (int) $p['id'],
                'nombre'                  => $p['nombre'],
                'diasPeriodo'             => (int) $p['dias_periodo'],
                'mesesPeriodo'            => (int) $p['meses_periodo'],
                'permiteExcluirFinesSemana' => (bool) (int) $p['permite_excluir_fines_semana'],
                'plazoMinimo'             => (int) $p['plazo_minimo'],
                'plazoMaximo'             => (int) $p['plazo_maximo'],
            ], $this->catalogos->tiposPlazo()),
            'reglas' => $this->catalogos->reglas(),
            'tiposAmortizacion' => array_map(fn(array $a) => [
                'id' => (int) $a['id'], 'codigo' => $a['codigo'], 'nombre' => $a['nombre'],
            ], $this->catalogos->tiposAmortizacion()),
            'tiposMora'   => self::TIPOS_MORA,
            'tiposSeguro' => self::TIPOS_SEGURO,
            'hoy'         => date('Y-m-d'), // fecha del servidor: fecha de apertura por defecto
        ];
    }

    /**
     * Situación de crédito del cliente: límite, capital que debe en préstamos activos y disponible.
     * Disponible = límite de crédito − capital pendiente de los préstamos activos (nunca menor que cero).
     */
    public function credito(int $clienteId): array
    {
        $cliente = $this->clientes->buscarPorId($clienteId);
        if ($cliente === null || (int) $cliente['es_cliente'] !== 1) {
            throw new ErrorApi('Cliente no encontrado.', 404);
        }

        $resumen = $this->prestamos->resumenCredito($clienteId);
        $limite = round((float) $cliente['limite_credito'], 2);
        $pendiente = round((float) $resumen['capital_pendiente'], 2);

        $garante = null;
        $vigente = $this->garantes->vigente($clienteId);
        if ($vigente !== null) {
            $persona = $this->clientes->buscarPorId((int) $vigente['garante_id']);
            if ($persona !== null) {
                $garante = [
                    'id'              => (int) $persona['id'],
                    'nombreCompleto'  => $persona['nombres'] . ' ' . $persona['apellidos'],
                    'numeroDocumento' => $persona['numero_documento'],
                ];
            }
        }

        return [
            'id'                => $clienteId,
            'numeroDocumento'   => $cliente['numero_documento'],
            'nombreCompleto'    => $cliente['nombres'] . ' ' . $cliente['apellidos'],
            'estado'            => $cliente['estado'],
            'clasificacion'     => $cliente['clasificacion'] ?? '',
            'limiteCredito'     => $limite,
            'capitalPendiente'  => $pendiente,
            'disponible'        => max(0.0, round($limite - $pendiente, 2)),
            'prestamosActivos'  => (int) $resumen['cantidad'],
            'garante'           => $garante,
        ];
    }

    /** Fecha sugerida del primer pago (parámetros: fechaApertura, tipoPlazoId, incluyeSabado, incluyeDomingo). */
    public function fechaPrimerPago(array $q): array
    {
        $apertura = CalendarioService::fechaDeTexto((string) ($q['fechaApertura'] ?? ''));
        if ($apertura === null) {
            throw new ErrorApi('Fecha de apertura inválida.', 422, ['fechaApertura' => 'Ingrese una fecha válida.']);
        }

        $plazo = null;
        foreach ($this->catalogos->tiposPlazo() as $fila) {
            if ((int) $fila['id'] === (int) ($q['tipoPlazoId'] ?? 0)) {
                $plazo = $fila;
            }
        }
        if ($plazo === null) {
            throw new ErrorApi('Tipo de plazo inválido.', 422, ['tipoPlazoId' => 'Seleccione el tipo de plazo.']);
        }

        $booleano = fn(string $clave) => filter_var($q[$clave] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $fecha = CalendarioService::primerPago($apertura, $plazo, $booleano('incluyeSabado'), $booleano('incluyeDomingo'));

        return ['fechaPrimerPago' => $fecha->format('Y-m-d')];
    }

    /** Botón Calcular: valida y devuelve la tabla de amortización y el resumen, sin guardar nada. */
    public function calcular(array $d, float $capitalLiberado = 0.0): array
    {
        $datos = $this->validar($d, $capitalLiberado);
        $calculo = AmortizacionService::calcular($datos);
        $this->verificarInteresTotal($datos, $calculo);
        return $calculo;
    }

    /** Regla de negocio: el interés total no puede pasar de cierto % del capital (reglas_negocio). */
    private function verificarInteresTotal(array $datos, array $calculo): void
    {
        $error = PrestamoValidador::errorInteresTotal($this->catalogos->reglas(), (float) $datos['monto'], (float) $calculo['resumen']['totalIntereses']);
        if ($error !== null) {
            throw new ErrorApi('Revise los datos del préstamo.', 422, ['tasa' => $error]);
        }
    }

    /**
     * Botón Guardar: una sola transacción que valida de nuevo (con el límite disponible real, bloqueando al cliente),
     * recalcula las cuotas en el servidor —nunca se confía en una tabla enviada por el navegador— y guarda
     * la cabecera del préstamo y sus cuotas.
     */
    public function guardar(array $d, int $usuarioId): array
    {
        return Database::transaccion(fn(): array => $this->guardarEnTransaccion($d, $usuarioId));
    }

    /**
     * Cuerpo del guardado, sin abrir transacción: lo usa guardar() y la renovación, que ya tiene una transacción
     * abierta (cancelación de los préstamos anteriores + apertura del nuevo).
     */
    public function guardarEnTransaccion(array $d, int $usuarioId): array
    {
        $clienteId = (int) filter_var($d['clienteId'] ?? null, FILTER_VALIDATE_INT);
        if ($clienteId > 0) {
            $this->prestamos->bloquearCliente($clienteId);
        }

        $datos = $this->validar($d);
        $calculo = AmortizacionService::calcular($datos);
        $this->verificarInteresTotal($datos, $calculo);
        [$id, $numero] = $this->prestamos->insertar($datos, $calculo['resumen'], $usuarioId);
        $this->prestamos->insertarCuotas($id, $calculo['cuotas']);

        return ['id' => $id, 'numero' => $numero, 'clienteId' => $clienteId] + $calculo;
    }

    /** Valida los datos de la apertura contra los catálogos y el crédito del cliente. Lanza 422 si hay errores. */
    private function validar(array $d, float $capitalLiberado = 0.0): array
    {
        $clienteId = (int) filter_var($d['clienteId'] ?? null, FILTER_VALIDATE_INT);
        try {
            $credito = $clienteId > 0 ? $this->credito($clienteId) : null;
        } catch (ErrorApi) {
            $credito = null; // cliente inexistente: la validación lo reporta como "Seleccione el cliente"
        }
        // Renovación: el capital de los préstamos que se cancelan queda libre para el préstamo nuevo.
        if ($credito !== null && $capitalLiberado > 0) {
            $credito['disponible'] = round($credito['disponible'] + $capitalLiberado, 2);
        }

        $ctx = [
            'credito'        => $credito,
            'plazos'         => array_column($this->catalogos->tiposPlazo(), null, 'id'),
            'amortizaciones' => array_column($this->catalogos->tiposAmortizacion(), null, 'id'),
            'reglas'         => $this->catalogos->reglas(),
            'tiposPrestamo'  => array_fill_keys(
                array_map('intval', array_column($this->tipos->listar(['estado' => 'Activo']), 'id')),
                true
            ),
        ];

        [$errores, $datos] = PrestamoValidador::validar($d, $ctx);
        if ($errores) {
            throw new ErrorApi('Revise los datos del préstamo.', 422, $errores);
        }
        return $datos;
    }
}
