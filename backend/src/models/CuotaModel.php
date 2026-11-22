<?php
declare(strict_types=1);

/** Tabla prestamos_cuotas. Consultas preparadas. */
final class CuotaModel
{
    /** Convierte una fila de la base de datos en el arreglo de cuota que usan los servicios. */
    public static function aCuota(array $f): array
    {
        return [
            'id'            => (int) $f['id'],
            'numero'        => (int) $f['numero_cuota'],
            'fechaPago'     => substr((string) $f['fecha_pago'], 0, 10),
            'capital'       => (float) $f['capital'],
            'interes'       => (float) $f['interes'],
            'seguro'        => (float) $f['seguro'],
            'gasto'         => (float) $f['gasto_cierre'],
            'mora'          => (float) $f['mora_acumulada'],
            'valor'         => (float) $f['valor_cuota'],
            'capitalPagado' => (float) $f['capital_pagado'],
            'interesPagado' => (float) $f['interes_pagado'],
            'seguroPagado'  => (float) $f['seguro_pagado'],
            'gastoPagado'   => (float) $f['gasto_cierre_pagado'],
            'moraPagada'    => (float) $f['mora_pagada'],
            'balance'       => (float) $f['balance'],
            'estado'        => $f['estado'],
        ];
    }

    /** Todas las cuotas de un préstamo, de la más antigua a la más reciente. */
    public function delPrestamo(int $prestamoId): array
    {
        $st = Database::conexion()->prepare('SELECT * FROM prestamos_cuotas WHERE prestamo_id = ? ORDER BY numero_cuota');
        $st->execute([$prestamoId]);
        return array_map([self::class, 'aCuota'], $st->fetchAll());
    }

    /** Cuotas vencidas sin saldar de préstamos activos, con los datos de mora del préstamo. */
    public function candidatasAMora(string $hoy, ?int $prestamoId): array
    {
        $sql = "SELECT c.*, p.tipo_mora, p.valor_mora
                  FROM prestamos_cuotas c
                  JOIN prestamos p ON p.id = c.prestamo_id
                 WHERE p.estado = 'Activo' AND c.estado <> 'Pagada' AND c.fecha_pago < ?";
        $params = [$hoy];
        if ($prestamoId !== null) {
            $sql .= ' AND c.prestamo_id = ?';
            $params[] = $prestamoId;
        }
        $st = Database::conexion()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function actualizarMoraYEstado(int $cuotaId, float $mora, string $estado): void
    {
        Database::conexion()
            ->prepare('UPDATE prestamos_cuotas SET mora_acumulada = ?, estado = ? WHERE id = ?')
            ->execute([self::dinero($mora), $estado, $cuotaId]);
    }

    /** Guarda todos los importes y el estado de una cuota existente (pagos, abono a capital). */
    public function actualizar(array $c): void
    {
        Database::conexion()->prepare(
            'UPDATE prestamos_cuotas
                SET capital = ?, interes = ?, seguro = ?, gasto_cierre = ?, mora_acumulada = ?, valor_cuota = ?,
                    capital_pagado = ?, interes_pagado = ?, seguro_pagado = ?, gasto_cierre_pagado = ?,
                    mora_pagada = ?, balance = ?, estado = ?
              WHERE id = ?'
        )->execute([
            self::dinero($c['capital']), self::dinero($c['interes']), self::dinero($c['seguro']), self::dinero($c['gasto']),
            self::dinero($c['mora']), self::dinero($c['valor']), self::dinero($c['capitalPagado']),
            self::dinero($c['interesPagado']), self::dinero($c['seguroPagado']), self::dinero($c['gastoPagado']),
            self::dinero($c['moraPagada']), self::dinero($c['balance']), $c['estado'], $c['id'],
        ]);
    }

    /** Inserta una cuota generada (saldo fijo) y devuelve su id. */
    public function insertar(int $prestamoId, array $c): int
    {
        $st = Database::conexion()->prepare(
            'INSERT INTO prestamos_cuotas
                (prestamo_id, numero_cuota, fecha_pago, capital, interes, seguro, gasto_cierre, mora_acumulada,
                 valor_cuota, capital_pagado, interes_pagado, seguro_pagado, gasto_cierre_pagado, mora_pagada, balance, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $prestamoId, $c['numero'], $c['fechaPago'], self::dinero($c['capital']), self::dinero($c['interes']),
            self::dinero($c['seguro']), self::dinero($c['gasto']), self::dinero($c['mora']), self::dinero($c['valor']),
            self::dinero($c['capitalPagado']), self::dinero($c['interesPagado']), self::dinero($c['seguroPagado']),
            self::dinero($c['gastoPagado']), self::dinero($c['moraPagada']), self::dinero($c['balance']), $c['estado'],
        ]);
        return (int) Database::conexion()->lastInsertId();
    }

    /** Elimina cuotas futuras sin pagos (al reducir el plazo con un abono a capital). */
    public function eliminar(int $prestamoId, int $numero): void
    {
        Database::conexion()
            ->prepare('DELETE FROM prestamos_cuotas WHERE prestamo_id = ? AND numero_cuota = ?')
            ->execute([$prestamoId, $numero]);
    }

    private static function dinero(float|int $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
