<?php
declare(strict_types=1);

/** Tablas recibos_ingreso y recibos_ingreso_detalle. Consultas preparadas. */
final class ReciboModel
{
    /** Inserta el recibo con el número de la secuencia (RC-000036). Devuelve [id, numero]. */
    public function insertar(int $prestamoId, array $plan, string $formato, ?string $observacion, int $usuarioId): array
    {
        $pdo = Database::conexion();
        $numero = sprintf('RC-%06d', (int) $pdo->query('SELECT NEXT VALUE FOR seq_recibo')->fetchColumn());
        $t = $plan['totales'];

        $st = $pdo->prepare(
            'INSERT INTO recibos_ingreso
                (numero, prestamo_id, tipo_pago, monto_recibido, total_mora, total_interes, total_seguro,
                 total_gasto_cierre, total_capital, balance_posterior, modalidad_abono, formato_impreso,
                 observacion, usuario_creacion_id)
             OUTPUT INSERTED.id
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $numero, $prestamoId, $plan['tipoPago'], self::dinero($plan['monto']), self::dinero($t['mora']),
            self::dinero($t['interes']), self::dinero($t['seguro']), self::dinero($t['gasto']), self::dinero($t['capital']),
            self::dinero($plan['balancePosterior']), $plan['modalidad'], $formato, $observacion, $usuarioId,
        ]);
        return [(int) $st->fetchColumn(), $numero];
    }

    public function insertarDetalle(int $reciboId, ?int $cuotaId, array $d): void
    {
        Database::conexion()->prepare(
            'INSERT INTO recibos_ingreso_detalle
                (recibo_id, cuota_id, mora_aplicada, interes_aplicado, seguro_aplicado, gasto_cierre_aplicado, capital_aplicado)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $reciboId, $cuotaId, self::dinero($d['mora']), self::dinero($d['interes']), self::dinero($d['seguro']),
            self::dinero($d['gasto']), self::dinero($d['capital']),
        ]);
    }

    /** Recibo con datos del préstamo, cliente y cajero. */
    public function buscarPorId(int $id): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT r.*, p.numero AS prestamo_numero, p.plazo, c.nombres, c.apellidos, c.numero_documento, c.correo,
                    tp.nombre AS tipo_prestamo, u.nombre AS cajero
               FROM recibos_ingreso r
               JOIN prestamos p ON p.id = r.prestamo_id
               JOIN clientes c ON c.id = p.cliente_id
               JOIN tipos_prestamo tp ON tp.id = p.tipo_prestamo_id
               JOIN usuarios u ON u.id = r.usuario_creacion_id
              WHERE r.id = ?'
        );
        $st->execute([$id]);
        $fila = $st->fetch();
        return $fila === false ? null : $fila;
    }

    /** Líneas del recibo con el número y la fecha de la cuota (null = abono a capital). */
    public function detalle(int $reciboId): array
    {
        $st = Database::conexion()->prepare(
            'SELECT d.*, q.numero_cuota, q.fecha_pago
               FROM recibos_ingreso_detalle d
               LEFT JOIN prestamos_cuotas q ON q.id = d.cuota_id
              WHERE d.recibo_id = ?
              ORDER BY CASE WHEN q.numero_cuota IS NULL THEN 1 ELSE 0 END, q.numero_cuota, d.id'
        );
        $st->execute([$reciboId]);
        return $st->fetchAll();
    }

    public function delPrestamo(int $prestamoId): array
    {
        $st = Database::conexion()->prepare(
            'SELECT id, numero, fecha, tipo_pago, monto_recibido, balance_posterior, estado
               FROM recibos_ingreso WHERE prestamo_id = ? ORDER BY id DESC'
        );
        $st->execute([$prestamoId]);
        return $st->fetchAll();
    }

    public function cambiarFormato(int $id, string $formato): void
    {
        Database::conexion()->prepare('UPDATE recibos_ingreso SET formato_impreso = ? WHERE id = ?')->execute([$formato, $id]);
    }

    private static function dinero(float|int $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
