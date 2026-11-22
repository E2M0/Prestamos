<?php
declare(strict_types=1);

/**
 * Reglas del módulo auxiliar "Tipos de préstamo".
 *  - El nombre es único (sin distinguir mayúsculas).
 *  - Nunca se eliminan: solo se inactivan. Un tipo inactivo no aparece al aperturar préstamos nuevos,
 *    pero los préstamos que ya lo usan lo conservan.
 */
final class TipoPrestamoService
{
    public function __construct(private TipoPrestamoModel $tipos = new TipoPrestamoModel())
    {
    }

    public function listar(array $q): array
    {
        $filtros = [
            'estado' => (string) ($q['estado'] ?? ''),
            'buscar' => substr(trim((string) ($q['buscar'] ?? '')), 0, 100),
        ];
        return array_map([$this, 'formato'], $this->tipos->listar($filtros));
    }

    public function obtener(int $id): array
    {
        $fila = $this->tipos->buscarPorId($id);
        if ($fila === null) {
            throw new ErrorApi('Tipo de préstamo no encontrado.', 404);
        }
        return $this->formato($fila);
    }

    /** Crea (id null) o modifica un tipo de préstamo. El estado no cambia aquí. */
    public function guardar(?int $id, array $datos): array
    {
        $nombre = ClienteValidador::limpiar($datos['nombre'] ?? '');
        $descripcion = ClienteValidador::limpiar($datos['descripcion'] ?? '');

        $errores = [];
        $largo = ClienteValidador::largo($nombre);
        if ($largo < 2 || $largo > 60) {
            $errores['nombre'] = 'Ingrese el nombre (entre 2 y 60 caracteres).';
        }
        if (ClienteValidador::largo($descripcion) > 250) {
            $errores['descripcion'] = 'Máximo 250 caracteres.';
        }
        if ($errores) {
            throw new ErrorApi('Revise los datos del tipo de préstamo.', 422, $errores);
        }

        if ($id !== null && $this->tipos->buscarPorId($id) === null) {
            throw new ErrorApi('Tipo de préstamo no encontrado.', 404);
        }
        if ($this->tipos->existeNombre($nombre, $id)) {
            throw new ErrorApi('Ya existe un tipo de préstamo con ese nombre.', 409, ['nombre' => 'Nombre ya registrado.']);
        }

        $descripcion = $descripcion === '' ? null : $descripcion;
        try {
            if ($id === null) {
                $id = $this->tipos->insertar($nombre, $descripcion);
            } else {
                $this->tipos->actualizar($id, $nombre, $descripcion);
            }
        } catch (PDOException $e) {
            // 1062 (MySQL) / 2601 / 2627 (SQL Server): dos guardados simultáneos con el mismo nombre
            if (in_array((int) ($e->errorInfo[1] ?? 0), [1062, 2601, 2627], true)) {
                throw new ErrorApi('Ya existe un tipo de préstamo con ese nombre.', 409, ['nombre' => 'Nombre ya registrado.']);
            }
            throw $e;
        }
        return $this->obtener($id);
    }

    public function cambiarEstado(int $id, string $estado): array
    {
        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            throw new ErrorApi('Estado inválido.', 422);
        }
        $this->obtener($id); // 404 si no existe
        $this->tipos->cambiarEstado($id, $estado);
        return $this->obtener($id);
    }

    private function formato(array $f): array
    {
        return [
            'id'          => (int) $f['id'],
            'nombre'      => $f['nombre'],
            'descripcion' => $f['descripcion'] ?? '',
            'estado'      => $f['estado'],
            'prestamos'   => (int) $f['prestamos'],
        ];
    }
}
