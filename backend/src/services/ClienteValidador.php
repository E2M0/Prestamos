<?php
declare(strict_types=1);

/**
 * Validaciones del módulo de clientes (se repiten en React; el servidor es la autoridad).
 * No toca la base de datos: recibe los catálogos ya cargados.
 * Las claves de error usan notación de puntos: 'nombres', 'telefonos.0.numero', 'garante.laboral.empresa'.
 */
final class ClienteValidador
{
    public const ESTADOS_CIVILES = ['Soltero/a', 'Casado/a', 'Unión libre', 'Divorciado/a', 'Viudo/a'];
    public const CLASIFICACIONES = ['A', 'B', 'C'];
    public const TIPOS_TELEFONO = ['Celular', 'Casa', 'Trabajo'];
    public const MAX_DECIMAL = 999999999999.99;

    private const PATRON_NOMBRE = '/^[\p{L}][\p{L} .\'\-]{1,99}$/u';
    private const PATRON_TELEFONO = '/^\d{3}-\d{3}-\d{4}$/';

    /**
     * @param array $tiposDocumento [id => ['nombre', 'mascara', 'patron']]
     * @param array $nacionalidades [id => true]
     */
    public function __construct(private array $tiposDocumento, private array $nacionalidades)
    {
    }

    // ------------------------------------------------------------ utilidades
    public static function texto(mixed $valor): string
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    /** Recorta y colapsa espacios repetidos. */
    public static function limpiar(mixed $valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', self::texto($valor)) ?? '');
    }

    /** Cantidad de caracteres (no de bytes); no depende de la extensión mbstring. */
    public static function largo(string $texto): int
    {
        return (int) preg_match_all('/./us', $texto);
    }

    public static function normalizarDocumento(string $documento): string
    {
        return strtoupper(trim($documento));
    }

    /** Solo letras y números: sirve para comparar documentos ignorando guiones. */
    public static function alfanumerico(string $documento): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($documento)) ?? '';
    }

    public static function familiarVacio(array $c): bool
    {
        foreach (['nombre', 'parentesco', 'telefono', 'direccion'] as $campo) {
            if (self::texto($c[$campo] ?? '') !== '') {
                return false;
            }
        }
        return true;
    }

    private function numero(mixed $valor): ?float
    {
        if (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric($valor))) {
            $n = (float) $valor;
            return is_finite($n) ? $n : null;
        }
        return null;
    }

    // ------------------------------------------------------------ cliente completo
    public function cliente(array $d): array
    {
        $e = $this->persona($d, '');

        $limite = $this->numero($d['limiteCredito'] ?? null);
        if ($limite === null || $limite < 0 || $limite > self::MAX_DECIMAL) {
            $e['limiteCredito'] = 'Ingrese un límite de crédito válido (0 o mayor).';
        }
        if (!in_array($d['clasificacion'] ?? '', self::CLASIFICACIONES, true)) {
            $e['clasificacion'] = 'Seleccione la clasificación del cliente.';
        }

        $this->referencias($d['referencias'] ?? null, $e);
        $this->familiares($d['familiares'] ?? null, $e);
        return $e;
    }

    // ------------------------------------------------------------ persona (cliente o garante)
    public function persona(array $p, string $pre = ''): array
    {
        $e = $this->documento($p, $pre);

        if (!preg_match(self::PATRON_NOMBRE, self::limpiar($p['nombres'] ?? ''))) {
            $e[$pre . 'nombres'] = 'Ingrese los nombres (solo letras, mínimo 2).';
        }
        if (!preg_match(self::PATRON_NOMBRE, self::limpiar($p['apellidos'] ?? ''))) {
            $e[$pre . 'apellidos'] = 'Ingrese los apellidos (solo letras, mínimo 2).';
        }
        if (!isset($this->nacionalidades[(int) ($p['nacionalidadId'] ?? 0)])) {
            $e[$pre . 'nacionalidadId'] = 'Seleccione la nacionalidad.';
        }

        $this->fechaNacimiento(self::texto($p['fechaNacimiento'] ?? ''), $pre, $e);

        if (!in_array($p['estadoCivil'] ?? '', self::ESTADOS_CIVILES, true)) {
            $e[$pre . 'estadoCivil'] = 'Seleccione el estado civil.';
        }

        $correo = self::texto($p['correo'] ?? '');
        if ($correo !== '' && (strlen($correo) > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL))) {
            $e[$pre . 'correo'] = 'Ingrese un correo electrónico válido.';
        }

        $largoDir = strlen(self::limpiar($p['direccion'] ?? ''));
        if ($largoDir < 5 || $largoDir > 250) {
            $e[$pre . 'direccion'] = 'Ingrese la dirección (mínimo 5 caracteres).';
        }
        if (strlen(self::limpiar($p['referenciaDireccion'] ?? '')) > 250) {
            $e[$pre . 'referenciaDireccion'] = 'Máximo 250 caracteres.';
        }

        $this->telefonos($p['telefonos'] ?? null, $pre, $e);
        $this->laboral($p['laboral'] ?? null, $pre, $e);
        return $e;
    }

    /** Valida solo tipo y número de documento según el patrón (expresión regular) del tipo. */
    public function documento(array $p, string $pre = ''): array
    {
        $e = [];
        $tipo = $this->tiposDocumento[(int) ($p['tipoDocumentoId'] ?? 0)] ?? null;
        if ($tipo === null) {
            $e[$pre . 'tipoDocumentoId'] = 'Seleccione el tipo de documento.';
        }

        $numero = self::normalizarDocumento(self::texto($p['numeroDocumento'] ?? ''));
        if ($numero === '') {
            $e[$pre . 'numeroDocumento'] = 'Ingrese el número de documento.';
        } elseif ($tipo !== null && !preg_match('~' . $tipo['patron'] . '~u', $numero)) {
            $e[$pre . 'numeroDocumento'] = 'Formato inválido. Use ' . $tipo['mascara'] . '.';
        }
        return $e;
    }

    private function fechaNacimiento(string $valor, string $pre, array &$e): void
    {
        $clave = $pre . 'fechaNacimiento';
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor) {
            $e[$clave] = 'Ingrese una fecha de nacimiento válida.';
            return;
        }
        $hoy = new DateTimeImmutable('today');
        if ($fecha > $hoy) {
            $e[$clave] = 'La fecha de nacimiento no puede ser futura.';
        } elseif ($fecha->diff($hoy)->y < 18) {
            $e[$clave] = 'La persona debe ser mayor de 18 años.';
        } elseif ($fecha->diff($hoy)->y > 110) {
            $e[$clave] = 'Revise la fecha de nacimiento.';
        }
    }

    private function telefonos(mixed $lista, string $pre, array &$e): void
    {
        $k = $pre . 'telefonos';
        if (!is_array($lista) || count($lista) < 1) {
            $e[$k] = 'Registre al menos un teléfono.';
            return;
        }
        if (count($lista) > 5) {
            $e[$k] = 'Registre como máximo 5 teléfonos.';
            return;
        }

        $principales = 0;
        $vistos = [];
        foreach (array_values($lista) as $i => $t) {
            $t = is_array($t) ? $t : [];
            if (!in_array($t['tipo'] ?? '', self::TIPOS_TELEFONO, true)) {
                $e["$k.$i.tipo"] = 'Seleccione el tipo.';
            }
            $numero = self::texto($t['numero'] ?? '');
            if (!preg_match(self::PATRON_TELEFONO, $numero)) {
                $e["$k.$i.numero"] = 'Use el formato 809-000-0000.';
            } elseif (isset($vistos[$numero])) {
                $e["$k.$i.numero"] = 'Teléfono repetido.';
            }
            $vistos[$numero] = true;
            if (!empty($t['principal'])) {
                $principales++;
            }
        }
        if ($principales !== 1) {
            $e[$k] = 'Marque exactamente un teléfono como principal.';
        }
    }

    private function laboral(mixed $l, string $pre, array &$e): void
    {
        $l = is_array($l) ? $l : [];
        $k = $pre . 'laboral.';

        $this->cadena($l, 'empresa', $k . 'empresa', 2, 120, 'Ingrese la empresa.', $e);
        $this->cadena($l, 'cargoOcupacion', $k . 'cargoOcupacion', 2, 80, 'Ingrese la ocupación o cargo.', $e);
        $this->cadena($l, 'tiempoLaborando', $k . 'tiempoLaborando', 1, 40, 'Indique el tiempo laborando.', $e);
        $this->cadena($l, 'direccionEmpresa', $k . 'direccionEmpresa', 5, 250, 'Ingrese la dirección del trabajo.', $e);

        $sueldo = $this->numero($l['sueldo'] ?? null);
        if ($sueldo === null || $sueldo <= 0 || $sueldo > self::MAX_DECIMAL) {
            $e[$k . 'sueldo'] = 'Ingrese un sueldo mayor que cero.';
        }
        if (!preg_match(self::PATRON_TELEFONO, self::texto($l['telefonoEmpresa'] ?? ''))) {
            $e[$k . 'telefonoEmpresa'] = 'Use el formato 809-000-0000.';
        }
    }

    private function referencias(mixed $lista, array &$e): void
    {
        if (!is_array($lista) || count($lista) !== 3) {
            $e['referencias'] = 'Registre exactamente tres referencias personales.';
            return;
        }
        foreach (array_values($lista) as $i => $r) {
            $r = is_array($r) ? $r : [];
            $this->cadena($r, 'nombre', "referencias.$i.nombre", 2, 100, 'Ingrese el nombre.', $e);
            if (!preg_match(self::PATRON_TELEFONO, self::texto($r['telefono'] ?? ''))) {
                $e["referencias.$i.telefono"] = 'Use el formato 809-000-0000.';
            }
        }
    }

    /** Familiares: opcionales. Si se llena alguno de sus campos, nombre y parentesco son obligatorios. */
    private function familiares(mixed $f, array &$e): void
    {
        if ($f === null) {
            return;
        }
        if (!is_array($f)) {
            $e['familiares'] = 'Datos de familiares inválidos.';
            return;
        }
        foreach (['cercano', 'noConvive'] as $clave) {
            $c = $f[$clave] ?? null;
            if (!is_array($c) || self::familiarVacio($c)) {
                continue;
            }
            $k = "familiares.$clave.";
            $this->cadena($c, 'nombre', $k . 'nombre', 2, 100, 'Ingrese el nombre.', $e);
            $this->cadena($c, 'parentesco', $k . 'parentesco', 2, 40, 'Ingrese el parentesco.', $e);
            $tel = self::texto($c['telefono'] ?? '');
            if ($tel !== '' && !preg_match(self::PATRON_TELEFONO, $tel)) {
                $e[$k . 'telefono'] = 'Use el formato 809-000-0000.';
            }
            if (strlen(self::limpiar($c['direccion'] ?? '')) > 250) {
                $e[$k . 'direccion'] = 'Máximo 250 caracteres.';
            }
        }
    }

    private function cadena(array $a, string $campo, string $clave, int $min, int $max, string $mensaje, array &$e): void
    {
        $largo = strlen(self::limpiar($a[$campo] ?? ''));
        if ($largo < $min || $largo > $max) {
            $e[$clave] = $mensaje;
        }
    }
}
