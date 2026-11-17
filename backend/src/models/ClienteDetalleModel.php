<?php
declare(strict_types=1);

/**
 * Tablas dependientes de la persona: teléfonos, datos laborales, familiares y referencias.
 * Las listas (teléfonos, familiares, referencias) se reemplazan completas dentro de la
 * transacción del servicio; los registros maestros (clientes) nunca se eliminan.
 */
final class ClienteDetalleModel
{
    // ---------- Teléfonos ----------
    public function telefonos(int $personaId): array
    {
        $st = Database::conexion()->prepare(
            'SELECT tipo, numero, principal FROM clientes_telefonos WHERE cliente_id = ? ORDER BY principal DESC, id'
        );
        $st->execute([$personaId]);
        return $st->fetchAll();
    }

    public function reemplazarTelefonos(int $personaId, array $telefonos): void
    {
        $pdo = Database::conexion();
        $pdo->prepare('DELETE FROM clientes_telefonos WHERE cliente_id = ?')->execute([$personaId]);
        $ins = $pdo->prepare('INSERT INTO clientes_telefonos (cliente_id, tipo, numero, principal) VALUES (?, ?, ?, ?)');
        foreach ($telefonos as $t) {
            $ins->execute([$personaId, $t['tipo'], $t['numero'], $t['principal'] ? 1 : 0]);
        }
    }

    // ---------- Datos laborales ----------
    public function laboral(int $personaId): ?array
    {
        $st = Database::conexion()->prepare(
            'SELECT TOP 1 empresa, cargo_ocupacion, tiempo_laborando, sueldo, telefono_empresa, direccion_empresa
               FROM clientes_laborales WHERE cliente_id = ?'
        );
        $st->execute([$personaId]);
        return $st->fetch() ?: null;
    }

    public function guardarLaboral(int $personaId, array $l): void
    {
        $pdo = Database::conexion();
        $valores = [
            $l['empresa'], $l['cargo_ocupacion'], $l['tiempo_laborando'],
            number_format($l['sueldo'], 2, '.', ''), $l['telefono_empresa'], $l['direccion_empresa'],
        ];

        if ($this->laboral($personaId) !== null) {
            $pdo->prepare(
                'UPDATE clientes_laborales
                    SET empresa = ?, cargo_ocupacion = ?, tiempo_laborando = ?, sueldo = ?,
                        telefono_empresa = ?, direccion_empresa = ?
                  WHERE cliente_id = ?'
            )->execute([...$valores, $personaId]);
        } else {
            $pdo->prepare(
                'INSERT INTO clientes_laborales
                    (empresa, cargo_ocupacion, tiempo_laborando, sueldo, telefono_empresa, direccion_empresa, cliente_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([...$valores, $personaId]);
        }
    }

    // ---------- Familiares ----------
    public function familiares(int $personaId): array
    {
        $st = Database::conexion()->prepare(
            'SELECT tipo, nombre, parentesco, telefono, direccion FROM clientes_familiares WHERE cliente_id = ?'
        );
        $st->execute([$personaId]);
        return $st->fetchAll();
    }

    public function reemplazarFamiliares(int $personaId, array $familiares): void
    {
        $pdo = Database::conexion();
        $pdo->prepare('DELETE FROM clientes_familiares WHERE cliente_id = ?')->execute([$personaId]);
        $ins = $pdo->prepare(
            'INSERT INTO clientes_familiares (cliente_id, tipo, nombre, parentesco, telefono, direccion)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($familiares as $f) {
            $ins->execute([$personaId, $f['tipo'], $f['nombre'], $f['parentesco'], $f['telefono'], $f['direccion']]);
        }
    }

    // ---------- Referencias personales ----------
    public function referencias(int $personaId): array
    {
        $st = Database::conexion()->prepare(
            'SELECT nombre, telefono FROM clientes_referencias WHERE cliente_id = ? ORDER BY id'
        );
        $st->execute([$personaId]);
        return $st->fetchAll();
    }

    public function reemplazarReferencias(int $personaId, array $referencias): void
    {
        $pdo = Database::conexion();
        $pdo->prepare('DELETE FROM clientes_referencias WHERE cliente_id = ?')->execute([$personaId]);
        $ins = $pdo->prepare('INSERT INTO clientes_referencias (cliente_id, nombre, telefono) VALUES (?, ?, ?)');
        foreach ($referencias as $r) {
            $ins->execute([$personaId, $r['nombre'], $r['telefono']]);
        }
    }
}
