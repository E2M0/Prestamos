<?php
declare(strict_types=1);

/** Tablas prestamos (cabecera) y prestamos_cuotas (detalle). Consultas preparadas. */
final class PrestamoModel
{
    /** Préstamos activos del cliente y capital que aún debe (base del límite disponible). */
    public function resumenCredito(int $clienteId): array
    {
        $st = Database::conexion()->prepare(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(capital_pendiente), 0) AS capital_pendiente
               FROM prestamos
              WHERE cliente_id = ? AND estado = N'Activo'"
        );
        $st->execute([$clienteId]);
        return $st->fetch();
    }

    /**
     * Bloquea la fila del cliente hasta terminar la transacción. Así, dos aperturas simultáneas del mismo cliente
     * se ejecutan una detrás de otra y ambas ven el límite disponible real. Debe llamarse dentro de una transacción.
     */
    public function bloquearCliente(int $clienteId): void
    {
        $st = Database::conexion()->prepare('SELECT id FROM clientes WITH (UPDLOCK, HOLDLOCK) WHERE id = ?');
        $st->execute([$clienteId]);
    }

    /** Inserta la cabecera con el número de la secuencia (PR-000010). Devuelve [id, numero]. */
    public function insertar(array $d, array $resumen, int $usuarioId): array
    {
        $pdo = Database::conexion();
        $numero = sprintf('PR-%06d', (int) $pdo->query('SELECT NEXT VALUE FOR seq_prestamo')->fetchColumn());

        $st = $pdo->prepare(
            'INSERT INTO prestamos
                (numero, cliente_id, tipo_prestamo_id, monto, plazo, tasa, tipo_plazo_id, tipo_amortizacion_id,
                 incluye_sabado, incluye_domingo, tipo_mora, valor_mora, seguro_tipo, seguro_monto,
                 gasto_cierre, gasto_cierre_financiado, desembolso, fecha_apertura, fecha_primer_pago,
                 capital_pendiente, usuario_creacion_id)
             OUTPUT INSERTED.id
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $numero, $d['clienteId'], $d['tipoPrestamoId'], self::dinero($d['monto']), $d['plazo'],
            number_format($d['tasa'], 4, '.', ''), $d['tipoPlazoId'], $d['tipoAmortizacionId'],
            $d['incluyeSabado'] ? 1 : 0, $d['incluyeDomingo'] ? 1 : 0, $d['tipoMora'], self::dinero($d['valorMora']),
            $d['seguroTipo'], self::dinero($d['seguroMonto']), self::dinero($d['gastoCierre']),
            $d['gastoFinanciado'] ? 1 : 0, self::dinero($resumen['desembolso']),
            $d['fechaApertura']->format('Y-m-d'), $d['fechaPrimerPago']->format('Y-m-d'),
            self::dinero($d['monto']), // capital pendiente inicial = monto
            $usuarioId,
        ]);
        return [(int) $st->fetchColumn(), $numero];
    }

    /** Inserta las cuotas generadas (en saldo fijo, solo la primera). */
    public function insertarCuotas(int $prestamoId, array $cuotas): void
    {
        $st = Database::conexion()->prepare(
            'INSERT INTO prestamos_cuotas
                (prestamo_id, numero_cuota, fecha_pago, capital, interes, seguro, gasto_cierre, valor_cuota, balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($cuotas as $c) {
            $st->execute([
                $prestamoId, $c['numero'], $c['fechaPago'], self::dinero($c['capital']), self::dinero($c['interes']),
                self::dinero($c['seguro']), self::dinero($c['gastoCierre']), self::dinero($c['valorCuota']),
                self::dinero($c['balance']),
            ]);
        }
    }

    private const SELECT_COMPLETO = "SELECT p.*, c.nombres, c.apellidos, c.numero_documento, c.correo,
                tp.nombre AS tipo_prestamo, pl.nombre AS plazo_nombre, pl.dias_periodo, pl.meses_periodo,
                pl.permite_excluir_fines_semana, ta.codigo AS amortizacion_codigo, ta.nombre AS amortizacion_nombre";

    /** Préstamo con cliente, tipo, plazo y amortización. Con $bloquear toma un bloqueo hasta terminar la transacción. */
    public function buscarPorId(int $id, bool $bloquear = false): ?array
    {
        $hint = $bloquear ? ' WITH (UPDLOCK, HOLDLOCK)' : '';
        $st = Database::conexion()->prepare(
            self::SELECT_COMPLETO . "
               FROM prestamos AS p$hint
               JOIN clientes c ON c.id = p.cliente_id
               JOIN tipos_prestamo tp ON tp.id = p.tipo_prestamo_id
               JOIN tipos_plazo pl ON pl.id = p.tipo_plazo_id
               JOIN tipos_amortizacion ta ON ta.id = p.tipo_amortizacion_id
              WHERE p.id = ?"
        );
        $st->execute([$id]);
        $fila = $st->fetch();
        return $fila === false ? null : $fila;
    }

    /** Búsqueda para el modal de consulta: por número, nombre o documento del cliente. Máximo 100. */
    public function listar(array $filtros): array
    {
        $sql = "SELECT TOP 100 p.id, p.numero, p.monto, p.capital_pendiente, p.estado, p.fecha_apertura,
                       c.nombres, c.apellidos, c.numero_documento, ta.nombre AS amortizacion_nombre
                  FROM prestamos p
                  JOIN clientes c ON c.id = p.cliente_id
                  JOIN tipos_amortizacion ta ON ta.id = p.tipo_amortizacion_id
                 WHERE 1 = 1";
        $params = [];
        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        if ($buscar !== '') {
            $like = Sql::contiene($buscar);
            $sql .= " AND (p.numero LIKE ? OR c.nombres LIKE ? OR c.apellidos LIKE ? OR c.numero_documento LIKE ?
                           OR (c.nombres + N' ' + c.apellidos) LIKE ?)";
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (in_array($filtros['estado'] ?? '', ['Activo', 'Saldado', 'Renovado'], true)) {
            $sql .= ' AND p.estado = ?';
            $params[] = $filtros['estado'];
        }
        $sql .= ' ORDER BY p.id DESC';
        $st = Database::conexion()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Ids de los préstamos activos de un cliente, de menor a mayor. */
    public function idsActivosDelCliente(int $clienteId): array
    {
        $st = Database::conexion()->prepare("SELECT id FROM prestamos WHERE cliente_id = ? AND estado = N'Activo' ORDER BY id");
        $st->execute([$clienteId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Actualiza capital pendiente, plazo y estado tras un recibo. */
    public function actualizarEstado(int $id, float $capitalPendiente, int $plazo, string $estado): void
    {
        Database::conexion()
            ->prepare('UPDATE prestamos SET capital_pendiente = ?, plazo = ?, estado = ? WHERE id = ?')
            ->execute([self::dinero($capitalPendiente), $plazo, $estado, $id]);
    }

    /** Texto con 2 decimales exactos: evita que un float como 1234.5600000001 choque con los CHECK de la tabla. */
    private static function dinero(float|int $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
