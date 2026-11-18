<?php
declare(strict_types=1);

/** Tabla prestamos (cabecera). En la Etapa 7 se agregan el guardado y las cuotas. */
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
}
