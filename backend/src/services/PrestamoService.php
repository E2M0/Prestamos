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
            ], $this->catalogos->tiposPlazo()),
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
}
