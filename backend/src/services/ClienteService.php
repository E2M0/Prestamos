<?php
declare(strict_types=1);

/**
 * Reglas de negocio del módulo de clientes y garantes.
 *  - Una persona existe UNA sola vez en `clientes` (documento único). Los roles (cliente / garante) son relaciones.
 *  - Guardar un cliente (con su garante, teléfonos, laborales, familiares y referencias) es una sola transacción.
 *  - Los clientes no se eliminan: solo cambian de estado.
 */
final class ClienteService
{
    public function __construct(
        private ClienteModel $clientes = new ClienteModel(),
        private ClienteDetalleModel $detalle = new ClienteDetalleModel(),
        private GaranteModel $garantes = new GaranteModel(),
        private CatalogoModel $catalogos = new CatalogoModel(),
    ) {
    }

    // ------------------------------------------------------------ consultas
    public function catalogos(): array
    {
        return [
            'tiposDocumento' => array_map(fn(array $t) => [
                'id'      => (int) $t['id'],
                'nombre'  => $t['nombre'],
                'mascara' => $t['mascara'],
                'longitud' => (int) $t['longitud'],
                'patron'  => $t['patron'],
            ], $this->catalogos->tiposDocumento()),
            'nacionalidades' => array_map(fn(array $n) => [
                'id' => (int) $n['id'], 'nombre' => $n['nombre'],
            ], $this->catalogos->nacionalidades()),
            'estadosCiviles' => ClienteValidador::ESTADOS_CIVILES,
            'clasificaciones' => ClienteValidador::CLASIFICACIONES,
            'tiposTelefono'  => ClienteValidador::TIPOS_TELEFONO,
        ];
    }

    public function listar(array $q): array
    {
        $filtros = [
            'rol'    => in_array($q['rol'] ?? '', ['cliente', 'garante', 'todos'], true) ? $q['rol'] : 'cliente',
            'estado' => (string) ($q['estado'] ?? ''),
            'buscar' => substr(trim((string) ($q['buscar'] ?? '')), 0, 100),
        ];

        return array_map(fn(array $f) => [
            'id'              => (int) $f['id'],
            'tipoDocumento'   => $f['tipo_documento'],
            'numeroDocumento' => $f['numero_documento'],
            'nombres'         => $f['nombres'],
            'apellidos'       => $f['apellidos'],
            'nombreCompleto'  => $f['nombres'] . ' ' . $f['apellidos'],
            'estado'          => $f['estado'],
            'esCliente'       => (bool) (int) $f['es_cliente'],
            'esGarante'       => (bool) (int) $f['es_garante'],
            'limiteCredito'   => (float) $f['limite_credito'],
            'clasificacion'   => $f['clasificacion'] ?? '',
            'telefono'        => $f['telefono'] ?? '',
        ], $this->clientes->listar($filtros));
    }

    /** Ficha completa de un cliente (con su garante vigente). */
    public function obtener(int $id): array
    {
        $fila = $this->clientes->buscarPorId($id);
        if ($fila === null || (int) $fila['es_cliente'] !== 1) {
            throw new ErrorApi('Cliente no encontrado.', 404);
        }

        $vacio = ['nombre' => '', 'parentesco' => '', 'telefono' => '', 'direccion' => ''];
        $familiares = ['cercano' => $vacio, 'noConvive' => $vacio];
        foreach ($this->detalle->familiares($id) as $f) {
            $clave = $f['tipo'] === 'Cercano' ? 'cercano' : 'noConvive';
            $familiares[$clave] = [
                'nombre'     => $f['nombre'],
                'parentesco' => $f['parentesco'],
                'telefono'   => $f['telefono'] ?? '',
                'direccion'  => $f['direccion'] ?? '',
            ];
        }

        $garante = null;
        $vigente = $this->garantes->vigente($id);
        if ($vigente !== null) {
            $filaGarante = $this->clientes->buscarPorId((int) $vigente['garante_id']);
            $garante = $filaGarante !== null ? $this->armarPersona($filaGarante) : null;
        }

        return $this->armarPersona($fila) + [
            'limiteCredito' => (float) $fila['limite_credito'],
            'clasificacion' => $fila['clasificacion'] ?? '',
            'familiares'    => $familiares,
            'referencias'   => array_map(
                fn(array $r) => ['nombre' => $r['nombre'], 'telefono' => $r['telefono']],
                $this->detalle->referencias($id)
            ),
            'garante'       => $garante,
        ];
    }

    /**
     * Busca una persona por documento (para el garante y para la búsqueda inversa).
     * Devuelve si existe, si ya es cliente, si ya es garante y sus datos.
     */
    public function buscarPersona(string $documento): array
    {
        $documento = ClienteValidador::normalizarDocumento($documento);
        if ($documento === '' || strlen($documento) > 20) {
            throw new ErrorApi('Ingrese el número de documento.', 422);
        }

        $fila = $this->clientes->buscarPorDocumento($documento);
        if ($fila === null) {
            return ['existe' => false];
        }
        return [
            'existe'    => true,
            'esCliente' => (bool) (int) $fila['es_cliente'],
            'esGarante' => $this->garantes->esGaranteVigente((int) $fila['id']),
            'estado'    => $fila['estado'],
            'persona'   => $this->armarPersona($fila),
        ];
    }

    // ------------------------------------------------------------ escritura
    /** Crea ($id = null) o modifica un cliente junto con su garante. Todo en una transacción. */
    public function guardar(?int $id, array $d, int $usuarioId): array
    {
        $tipos = [];
        foreach ($this->catalogos->tiposDocumento() as $t) {
            $tipos[(int) $t['id']] = $t;
        }
        $nacionalidades = [];
        foreach ($this->catalogos->nacionalidades() as $n) {
            $nacionalidades[(int) $n['id']] = true;
        }

        $validador = new ClienteValidador($tipos, $nacionalidades);
        $errores = $validador->cliente($d);

        // ---- Garante: obligatorio; si ya existe se toma de su registro (solo se actualiza si se pide) ----
        $g = is_array($d['garante'] ?? null) ? $d['garante'] : null;
        $garanteExistente = null;
        $actualizarGarante = false;

        if ($g === null) {
            $errores['garante.numeroDocumento'] = 'El garante es obligatorio.';
        } else {
            $docG = ClienteValidador::normalizarDocumento(ClienteValidador::texto($g['numeroDocumento'] ?? ''));
            $docC = ClienteValidador::normalizarDocumento(ClienteValidador::texto($d['numeroDocumento'] ?? ''));
            $garanteExistente = $docG !== '' ? $this->clientes->buscarPorDocumento($docG) : null;
            $actualizarGarante = !empty($g['actualizar']);

            if ($garanteExistente !== null && !$actualizarGarante) {
                $errores += $validador->documento($g, 'garante.');
            } else {
                $errores += $validador->persona($g, 'garante.');
            }
            if ($garanteExistente !== null && $garanteExistente['estado'] !== 'Activo') {
                $errores['garante.numeroDocumento'] = 'El garante está inactivo y no puede asignarse.';
            }
            if ($docG !== '' && ClienteValidador::alfanumerico($docG) === ClienteValidador::alfanumerico($docC)) {
                $errores['garante.numeroDocumento'] = 'El garante no puede tener el mismo documento que el cliente.';
            }
        }

        if ($errores) {
            throw new ErrorApi('Revise los datos del formulario.', 422, $errores);
        }

        $persona = $this->limpiarPersona($d);
        $credito = ['limite' => (float) $d['limiteCredito'], 'clasificacion' => (string) $d['clasificacion']];
        $personaGarante = $this->limpiarPersona($g);

        try {
            $clienteId = Database::transaccion(function () use (
                $id, $d, $persona, $credito, $personaGarante, $garanteExistente, $actualizarGarante, $usuarioId
            ): int {
                $existente = $this->clientes->buscarPorDocumento($persona['numero_documento']);

                if ($id === null) {
                    if ($existente !== null && (int) $existente['es_cliente'] === 1) {
                        throw new ErrorApi('Ya existe un cliente con este documento.', 422, [
                            'numeroDocumento' => 'Este documento ya pertenece a un cliente registrado.',
                        ]);
                    }
                    if ($existente !== null) {
                        // La persona ya estaba registrada solo como garante: se reutiliza (sin duplicar) y pasa a cliente.
                        $clienteId = (int) $existente['id'];
                        $this->clientes->actualizar($clienteId, $persona, $credito, $usuarioId, true);
                    } else {
                        $clienteId = $this->clientes->insertar($persona, $credito, $usuarioId);
                    }
                } else {
                    $actual = $this->clientes->buscarPorId($id);
                    if ($actual === null || (int) $actual['es_cliente'] !== 1) {
                        throw new ErrorApi('Cliente no encontrado.', 404);
                    }
                    if ($existente !== null && (int) $existente['id'] !== $id) {
                        throw new ErrorApi('Ya existe otra persona con este documento.', 422, [
                            'numeroDocumento' => 'Este documento pertenece a otra persona registrada.',
                        ]);
                    }
                    $this->clientes->actualizar($id, $persona, $credito, $usuarioId);
                    $clienteId = $id;
                }

                $this->guardarTelefonosYLaboral($clienteId, $persona);
                $this->detalle->reemplazarFamiliares($clienteId, $this->familiares($d['familiares'] ?? null));
                $this->detalle->reemplazarReferencias($clienteId, $this->referencias($d['referencias']));

                // ---- Garante ----
                if ($garanteExistente !== null) {
                    $garanteId = (int) $garanteExistente['id'];
                    if ($garanteId === $clienteId) {
                        throw new ErrorApi('El garante no puede ser el mismo cliente.', 422, [
                            'garante.numeroDocumento' => 'El garante no puede ser el mismo cliente.',
                        ]);
                    }
                    if ($actualizarGarante) {
                        $this->clientes->actualizar($garanteId, $personaGarante, null, $usuarioId);
                        $this->guardarTelefonosYLaboral($garanteId, $personaGarante);
                    }
                } else {
                    $garanteId = $this->clientes->insertar($personaGarante, null, $usuarioId);
                    $this->guardarTelefonosYLaboral($garanteId, $personaGarante);
                }
                $this->garantes->asignar($clienteId, $garanteId, $usuarioId);

                return $clienteId;
            });
        } catch (PDOException $e) {
            // 2601 / 2627: violación de índice o restricción UNIQUE (por ejemplo, dos guardados simultáneos)
            if (in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true)) {
                throw new ErrorApi('Ya existe un registro con ese documento.', 409);
            }
            throw $e;
        }

        return $this->obtener($clienteId);
    }

    /** Activa o inactiva un cliente (nunca se elimina). No se puede inactivar con préstamos activos. */
    public function cambiarEstado(int $id, string $estado, int $usuarioId): array
    {
        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            throw new ErrorApi('Estado inválido.', 422);
        }
        $fila = $this->clientes->buscarPorId($id);
        if ($fila === null || (int) $fila['es_cliente'] !== 1) {
            throw new ErrorApi('Cliente no encontrado.', 404);
        }
        if ($estado === 'Inactivo') {
            $activos = $this->clientes->contarPrestamosActivos($id);
            if ($activos > 0) {
                throw new ErrorApi(
                    "No se puede inactivar: el cliente tiene $activos préstamo(s) activo(s).",
                    409
                );
            }
        }
        $this->clientes->cambiarEstado($id, $estado, $usuarioId);
        return $this->obtener($id);
    }

    // ------------------------------------------------------------ apoyo
    private function guardarTelefonosYLaboral(int $personaId, array $persona): void
    {
        $this->detalle->reemplazarTelefonos($personaId, $persona['telefonos']);
        $this->detalle->guardarLaboral($personaId, $persona['laboral']);
    }

    /** Persona (fila + teléfonos + laboral) en el formato que usa el frontend. */
    private function armarPersona(array $r): array
    {
        $id = (int) $r['id'];
        $lab = $this->detalle->laboral($id);

        return [
            'id'                  => $id,
            'tipoDocumentoId'     => (int) $r['tipo_documento_id'],
            'numeroDocumento'     => $r['numero_documento'],
            'nombres'             => $r['nombres'],
            'apellidos'           => $r['apellidos'],
            'nacionalidadId'      => (int) $r['nacionalidad_id'],
            'fechaNacimiento'     => substr((string) $r['fecha_nacimiento'], 0, 10),
            'estadoCivil'         => $r['estado_civil'],
            'correo'              => $r['correo'] ?? '',
            'direccion'           => $r['direccion'],
            'referenciaDireccion' => $r['referencia_direccion'] ?? '',
            'esCliente'           => (bool) (int) $r['es_cliente'],
            'estado'              => $r['estado'],
            'telefonos'           => array_map(fn(array $t) => [
                'tipo' => $t['tipo'], 'numero' => $t['numero'], 'principal' => (bool) (int) $t['principal'],
            ], $this->detalle->telefonos($id)),
            'laboral'             => $lab === null ? null : [
                'empresa'          => $lab['empresa'],
                'cargoOcupacion'   => $lab['cargo_ocupacion'],
                'tiempoLaborando'  => $lab['tiempo_laborando'],
                'sueldo'           => (float) $lab['sueldo'],
                'telefonoEmpresa'  => $lab['telefono_empresa'] ?? '',
                'direccionEmpresa' => $lab['direccion_empresa'] ?? '',
            ],
        ];
    }

    /** Payload de la API -> arreglo con nombres de columna, textos limpios y vacíos como NULL. */
    private function limpiarPersona(array $p): array
    {
        $c = fn(mixed $v) => ClienteValidador::limpiar($v);
        $opcional = fn(mixed $v) => ($t = ClienteValidador::limpiar($v)) === '' ? null : $t;
        $l = is_array($p['laboral'] ?? null) ? $p['laboral'] : [];

        return [
            'tipo_documento_id'    => (int) $p['tipoDocumentoId'],
            'numero_documento'     => ClienteValidador::normalizarDocumento(ClienteValidador::texto($p['numeroDocumento'])),
            'nombres'              => $c($p['nombres']),
            'apellidos'            => $c($p['apellidos']),
            'nacionalidad_id'      => (int) $p['nacionalidadId'],
            'fecha_nacimiento'     => ClienteValidador::texto($p['fechaNacimiento']),
            'estado_civil'         => (string) $p['estadoCivil'],
            'correo'               => $opcional($p['correo'] ?? ''),
            'direccion'            => $c($p['direccion']),
            'referencia_direccion' => $opcional($p['referenciaDireccion'] ?? ''),
            'telefonos'            => array_map(fn(array $t) => [
                'tipo'      => (string) $t['tipo'],
                'numero'    => ClienteValidador::texto($t['numero']),
                'principal' => !empty($t['principal']),
            ], array_values($p['telefonos'])),
            'laboral'              => [
                'empresa'           => $c($l['empresa']),
                'cargo_ocupacion'   => $c($l['cargoOcupacion']),
                'tiempo_laborando'  => $c($l['tiempoLaborando']),
                'sueldo'            => (float) $l['sueldo'],
                'telefono_empresa'  => ClienteValidador::texto($l['telefonoEmpresa']),
                'direccion_empresa' => $c($l['direccionEmpresa']),
            ],
        ];
    }

    private function familiares(mixed $f): array
    {
        $lista = [];
        if (!is_array($f)) {
            return $lista;
        }
        foreach (['cercano' => 'Cercano', 'noConvive' => 'No convive'] as $clave => $tipo) {
            $c = $f[$clave] ?? null;
            if (!is_array($c) || ClienteValidador::familiarVacio($c)) {
                continue;
            }
            $tel = ClienteValidador::texto($c['telefono'] ?? '');
            $dir = ClienteValidador::limpiar($c['direccion'] ?? '');
            $lista[] = [
                'tipo'       => $tipo,
                'nombre'     => ClienteValidador::limpiar($c['nombre']),
                'parentesco' => ClienteValidador::limpiar($c['parentesco']),
                'telefono'   => $tel === '' ? null : $tel,
                'direccion'  => $dir === '' ? null : $dir,
            ];
        }
        return $lista;
    }

    private function referencias(array $lista): array
    {
        return array_map(fn(array $r) => [
            'nombre'   => ClienteValidador::limpiar($r['nombre']),
            'telefono' => ClienteValidador::texto($r['telefono']),
        ], array_values($lista));
    }
}
