<?php
declare(strict_types=1);

/** Tabla clientes_garantes: relación cliente -> garante (el garante es otra persona de `clientes`). */
final class GaranteModel
{
    /** Garante vigente del cliente, o null. */
    public function vigente(int $clienteId): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT TOP 1 id, garante_id FROM clientes_garantes WHERE cliente_id = ? AND vigente = 1'
        );
        $st->execute([$clienteId]);
        return $st->fetch() ?: null;
    }

    /** ¿Esta persona es garante vigente de algún cliente? */
    public function esGaranteVigente(int $personaId): bool
    {
        $st = Database::conexion()->prepare(
            'SELECT COUNT(*) FROM clientes_garantes WHERE garante_id = ? AND vigente = 1'
        );
        $st->execute([$personaId]);
        return (int) $st->fetchColumn() > 0;
    }

    /**
     * Asigna el garante. Si ya es el vigente no hace nada; si cambia, el anterior queda
     * como histórico (vigente = 0) y se registra la nueva asignación.
     */
    public function asignar(int $clienteId, int $garanteId, int $usuarioId): void
    {
        $actual = $this->vigente($clienteId);
        if ($actual !== null && (int) $actual['garante_id'] === $garanteId) {
            return;
        }
        $pdo = Database::conexion();
        $pdo->prepare('UPDATE clientes_garantes SET vigente = 0 WHERE cliente_id = ? AND vigente = 1')
            ->execute([$clienteId]);
        $pdo->prepare(
            'INSERT INTO clientes_garantes (cliente_id, garante_id, vigente, usuario_creacion_id) VALUES (?, ?, 1, ?)'
        )->execute([$clienteId, $garanteId, $usuarioId]);
    }
}
