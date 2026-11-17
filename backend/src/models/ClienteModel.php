<?php
declare(strict_types=1);

/**
 * Tabla clientes: almacena PERSONAS (clientes y garantes).
 * es_cliente = 1 -> puede recibir préstamos; es_cliente = 0 -> persona registrada solo como garante.
 * Todas las consultas son preparadas.
 */
final class ClienteModel
{
    public function buscarPorId(int $id): ?array
    {
        $st = Database::conexion()->prepare('SELECT * FROM clientes WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function buscarPorDocumento(string $documento): ?array
    {
        $st = Database::conexion()->prepare('SELECT * FROM clientes WHERE numero_documento = ?');
        $st->execute([$documento]);
        return $st->fetch() ?: null;
    }

    /**
     * Lista para el modal de consulta.
     * $f: rol (cliente|garante|todos), estado (Activo|Inactivo|''), buscar (documento o nombre).
     */
    public function listar(array $f): array
    {
        $where = [];
        $params = [];

        $rol = $f['rol'] ?? 'cliente';
        if ($rol === 'cliente') {
            $where[] = 'c.es_cliente = 1';
        } elseif ($rol === 'garante') {
            $where[] = 'EXISTS (SELECT 1 FROM clientes_garantes g WHERE g.garante_id = c.id AND g.vigente = 1)';
        }

        if (in_array($f['estado'] ?? '', ['Activo', 'Inactivo'], true)) {
            $where[] = 'c.estado = ?';
            $params[] = $f['estado'];
        }

        $buscar = trim((string) ($f['buscar'] ?? ''));
        if ($buscar !== '') {
            $patron = Sql::contiene($buscar);
            $where[] = "(c.numero_documento LIKE ?
                         OR c.nombres + N' ' + c.apellidos LIKE ?
                         OR c.apellidos + N' ' + c.nombres LIKE ?)";
            array_push($params, $patron, $patron, $patron);
        }

        $sql = "SELECT TOP (100)
                       c.id, c.numero_documento, td.nombre AS tipo_documento,
                       c.nombres, c.apellidos, c.estado, c.es_cliente,
                       c.limite_credito, c.clasificacion,
                       (SELECT TOP 1 t.numero FROM clientes_telefonos t
                         WHERE t.cliente_id = c.id ORDER BY t.principal DESC, t.id) AS telefono,
                       CASE WHEN EXISTS (SELECT 1 FROM clientes_garantes g
                                          WHERE g.garante_id = c.id AND g.vigente = 1)
                            THEN 1 ELSE 0 END AS es_garante
                  FROM clientes c
                  JOIN tipos_documento td ON td.id = c.tipo_documento_id"
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY c.apellidos, c.nombres';

        $st = Database::conexion()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Inserta una persona. $credito null = solo garante (es_cliente = 0). Devuelve el id nuevo. */
    public function insertar(array $p, ?array $credito, int $usuarioId): int
    {
        $st = Database::conexion()->prepare(
            'INSERT INTO clientes
                (tipo_documento_id, numero_documento, nombres, apellidos, nacionalidad_id, fecha_nacimiento,
                 estado_civil, correo, direccion, referencia_direccion,
                 es_cliente, limite_credito, clasificacion, estado, usuario_creacion_id)
             OUTPUT INSERTED.id
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, N\'Activo\', ?)'
        );
        $st->execute([
            $p['tipo_documento_id'], $p['numero_documento'], $p['nombres'], $p['apellidos'],
            $p['nacionalidad_id'], $p['fecha_nacimiento'], $p['estado_civil'], $p['correo'],
            $p['direccion'], $p['referencia_direccion'],
            $credito === null ? 0 : 1,
            $credito === null ? '0.00' : number_format($credito['limite'], 2, '.', ''),
            $credito['clasificacion'] ?? null,
            $usuarioId,
        ]);
        return (int) $st->fetchColumn();
    }

    /**
     * Actualiza una persona. Si $credito no es null también fija es_cliente = 1 y los datos crediticios.
     * $promover = true: una persona que era solo garante pasa a cliente y se reactiva.
     */
    public function actualizar(int $id, array $p, ?array $credito, int $usuarioId, bool $promover = false): void
    {
        $sql = 'UPDATE clientes SET
                    tipo_documento_id = ?, numero_documento = ?, nombres = ?, apellidos = ?,
                    nacionalidad_id = ?, fecha_nacimiento = ?, estado_civil = ?, correo = ?,
                    direccion = ?, referencia_direccion = ?,
                    usuario_modificacion_id = ?, fecha_modificacion = SYSDATETIME()';
        $params = [
            $p['tipo_documento_id'], $p['numero_documento'], $p['nombres'], $p['apellidos'],
            $p['nacionalidad_id'], $p['fecha_nacimiento'], $p['estado_civil'], $p['correo'],
            $p['direccion'], $p['referencia_direccion'], $usuarioId,
        ];

        if ($credito !== null) {
            $sql .= ', es_cliente = 1, limite_credito = ?, clasificacion = ?';
            $params[] = number_format($credito['limite'], 2, '.', '');
            $params[] = $credito['clasificacion'];
        }
        if ($promover) {
            $sql .= ", estado = N'Activo'";
        }

        $sql .= ' WHERE id = ?';
        $params[] = $id;
        Database::conexion()->prepare($sql)->execute($params);
    }

    public function cambiarEstado(int $id, string $estado, int $usuarioId): void
    {
        Database::conexion()->prepare(
            'UPDATE clientes
                SET estado = ?, usuario_modificacion_id = ?, fecha_modificacion = SYSDATETIME()
              WHERE id = ?'
        )->execute([$estado, $usuarioId, $id]);
    }

    public function contarPrestamosActivos(int $clienteId): int
    {
        $st = Database::conexion()->prepare(
            "SELECT COUNT(*) FROM prestamos WHERE cliente_id = ? AND estado = N'Activo'"
        );
        $st->execute([$clienteId]);
        return (int) $st->fetchColumn();
    }
}
