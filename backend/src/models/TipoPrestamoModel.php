<?php
declare(strict_types=1);

/** Tabla tipos_prestamo (módulo auxiliar). Consultas preparadas. */
final class TipoPrestamoModel
{
    public function buscarPorId(int $id): ?array
    {
        $st = Database::conexion()->prepare($this->selectBase() . ' WHERE t.id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** $f: buscar (nombre o descripción), estado (Activo|Inactivo|''). */
    public function listar(array $f): array
    {
        $where = [];
        $params = [];

        if (in_array($f['estado'] ?? '', ['Activo', 'Inactivo'], true)) {
            $where[] = 't.estado = ?';
            $params[] = $f['estado'];
        }
        $buscar = trim((string) ($f['buscar'] ?? ''));
        if ($buscar !== '') {
            $patron = Sql::contiene($buscar);
            $where[] = '(t.nombre LIKE ? OR t.descripcion LIKE ?)';
            array_push($params, $patron, $patron);
        }

        $sql = 'SELECT TOP (200) ' . substr($this->selectBase(), strlen('SELECT '))
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY t.nombre';
        $st = Database::conexion()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** ¿Existe otro tipo con ese nombre? (la comparación no distingue mayúsculas, según la colación de la BD). */
    public function existeNombre(string $nombre, ?int $exceptoId = null): bool
    {
        $st = Database::conexion()->prepare('SELECT COUNT(*) FROM tipos_prestamo WHERE nombre = ? AND id <> ?');
        $st->execute([$nombre, $exceptoId ?? 0]);
        return (int) $st->fetchColumn() > 0;
    }

    public function insertar(string $nombre, ?string $descripcion): int
    {
        $st = Database::conexion()->prepare(
            "INSERT INTO tipos_prestamo (nombre, descripcion, estado) OUTPUT INSERTED.id VALUES (?, ?, N'Activo')"
        );
        $st->execute([$nombre, $descripcion]);
        return (int) $st->fetchColumn();
    }

    public function actualizar(int $id, string $nombre, ?string $descripcion): void
    {
        Database::conexion()
            ->prepare('UPDATE tipos_prestamo SET nombre = ?, descripcion = ? WHERE id = ?')
            ->execute([$nombre, $descripcion, $id]);
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        Database::conexion()
            ->prepare('UPDATE tipos_prestamo SET estado = ? WHERE id = ?')
            ->execute([$estado, $id]);
    }

    /** Base de las consultas; "prestamos" cuenta los préstamos que usan este tipo (informativo). */
    private function selectBase(): string
    {
        return 'SELECT t.id, t.nombre, t.descripcion, t.estado,
                       (SELECT COUNT(*) FROM prestamos p WHERE p.tipo_prestamo_id = t.id) AS prestamos
                  FROM tipos_prestamo t';
    }
}
