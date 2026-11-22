<?php
declare(strict_types=1);

/** Tablas renovaciones y renovaciones_detalle. Consultas preparadas. */
final class RenovacionModel
{
    public function insertar(int $clienteId, int $prestamoNuevoId, float $totalSaldo, float $desembolsoFinal, int $usuarioId): int
    {
        $st = Database::conexion()->prepare(
            'INSERT INTO renovaciones (cliente_id, prestamo_nuevo_id, total_saldo_cancelado, desembolso_final, usuario_creacion_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([$clienteId, $prestamoNuevoId, self::dinero($totalSaldo), self::dinero($desembolsoFinal), $usuarioId]);
        return (int) Database::conexion()->lastInsertId();
    }

    public function insertarDetalle(int $renovacionId, int $prestamoAnteriorId, string $base, float $montoCancelado, float $descuento, int $reciboId): void
    {
        Database::conexion()->prepare(
            'INSERT INTO renovaciones_detalle (renovacion_id, prestamo_anterior_id, base_saldo, monto_cancelado, descuento, recibo_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$renovacionId, $prestamoAnteriorId, $base, self::dinero($montoCancelado), self::dinero($descuento), $reciboId]);
    }

    private static function dinero(float|int $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
