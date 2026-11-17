<?php
declare(strict_types=1);

/** Acceso a datos de la tabla usuarios (solo consultas preparadas). */
final class UsuarioModel
{
    public function buscarPorUsuario(string $usuario): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT TOP 1 id, usuario, nombre, clave_hash, rol, estado
               FROM usuarios
              WHERE usuario = ?'
        );
        $st->execute([$usuario]);
        return $st->fetch() ?: null;
    }
}
