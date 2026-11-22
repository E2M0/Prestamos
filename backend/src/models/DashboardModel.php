<?php
declare(strict_types=1);

/** Consultas reales del dashboard. Ninguna cifra se inventa: todo sale de SQL Server. */
final class DashboardModel
{
    /**
     * Indicadores generales.
     * - Los pagos recibidos EXCLUYEN los recibos de cancelación por renovación (no entra dinero).
     * - Cuotas vencidas: de préstamos activos, con fecha anterior a hoy y no pagadas por completo.
     */
    public function indicadores(string $inicioMes, string $inicioMesSiguiente, string $hoy): array
    {
        $sql = <<<'SQL'
SELECT
  (SELECT COUNT(*) FROM clientes WHERE es_cliente = 1 AND estado = 'Activo')            AS total_clientes,
  (SELECT COUNT(*) FROM prestamos WHERE estado = 'Activo')                               AS prestamos_activos,
  (SELECT IFNULL(SUM(monto), 0) FROM prestamos WHERE estado = 'Activo')                  AS capital_colocado,
  (SELECT IFNULL(SUM(capital_pendiente), 0) FROM prestamos WHERE estado = 'Activo')      AS capital_pendiente,
  (SELECT IFNULL(SUM(r.monto_recibido), 0)
     FROM recibos_ingreso r
    WHERE r.estado = 'Activo'
      AND NOT EXISTS (SELECT 1 FROM renovaciones_detalle rd WHERE rd.recibo_id = r.id)
      AND r.fecha >= CAST(? AS DATE) AND r.fecha < CAST(? AS DATE))                       AS pagos_mes,
  (SELECT COUNT(*)
     FROM prestamos_cuotas c JOIN prestamos p ON p.id = c.prestamo_id
    WHERE p.estado = 'Activo'
      AND c.estado IN ('Pendiente', 'Parcial', 'Vencida')
      AND c.fecha_pago < CAST(? AS DATE))                                                 AS cuotas_vencidas,
  (SELECT IFNULL(SUM(c.mora_acumulada - c.mora_pagada), 0)
     FROM prestamos_cuotas c JOIN prestamos p ON p.id = c.prestamo_id
    WHERE p.estado = 'Activo')                                                           AS mora_pendiente
SQL;
        $st = Database::conexion()->prepare($sql);
        $st->execute([$inicioMes, $inicioMesSiguiente, $hoy]);
        return $st->fetch();
    }

    public function prestamosPorEstado(): array
    {
        return Database::conexion()
            ->query('SELECT estado, COUNT(*) AS cantidad FROM prestamos GROUP BY estado ORDER BY cantidad DESC')
            ->fetchAll();
    }

    /** Ingresos agrupados por mes desde la fecha indicada (sin cancelaciones por renovación). */
    public function ingresosDesde(string $desde): array
    {
        $sql = <<<'SQL'
SELECT YEAR(r.fecha) AS anio, MONTH(r.fecha) AS mes, SUM(r.monto_recibido) AS total
  FROM recibos_ingreso r
 WHERE r.estado = 'Activo'
   AND NOT EXISTS (SELECT 1 FROM renovaciones_detalle rd WHERE rd.recibo_id = r.id)
   AND r.fecha >= CAST(? AS DATE)
 GROUP BY YEAR(r.fecha), MONTH(r.fecha)
SQL;
        $st = Database::conexion()->prepare($sql);
        $st->execute([$desde]);
        return $st->fetchAll();
    }

    public function distribucionTipos(): array
    {
        return Database::conexion()->query(
            'SELECT tp.nombre, COUNT(*) AS cantidad
               FROM prestamos p JOIN tipos_prestamo tp ON tp.id = p.tipo_prestamo_id
              GROUP BY tp.nombre
              ORDER BY cantidad DESC, tp.nombre'
        )->fetchAll();
    }

    /** Últimos movimientos: recibos cobrados y préstamos aperturados. */
    public function actividadReciente(): array
    {
        $sql = <<<'SQL'
SELECT a.tipo, a.fecha, a.referencia, a.cliente, a.monto
  FROM (
        SELECT 'recibo' AS tipo, r.fecha AS fecha, r.numero AS referencia,
               CONCAT(c.nombres, ' ', c.apellidos) AS cliente, r.monto_recibido AS monto
          FROM recibos_ingreso r
          JOIN prestamos p ON p.id = r.prestamo_id
          JOIN clientes  c ON c.id = p.cliente_id
         WHERE r.estado = 'Activo'
        UNION ALL
        SELECT 'prestamo', p.fecha_creacion, p.numero,
               CONCAT(c.nombres, ' ', c.apellidos), p.monto
          FROM prestamos p
          JOIN clientes c ON c.id = p.cliente_id
       ) a
 ORDER BY a.fecha DESC
 LIMIT 8
SQL;
        return Database::conexion()->query($sql)->fetchAll();
    }
}
