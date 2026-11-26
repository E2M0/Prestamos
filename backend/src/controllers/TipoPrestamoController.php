<?php
declare(strict_types=1);

/** Endpoints de tipos de préstamo. Consultar requiere sesión; crear, editar y cambiar estado, rol Administrador. */
final class TipoPrestamoController
{
    public function listar(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new TipoPrestamoService())->listar($_GET));
    }

    public function obtener(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new TipoPrestamoService())->obtener(Peticion::id($parametros)));
    }

    public function crear(array $parametros): void
    {
        Sesion::requerirRol('Administrador');
        Respuesta::ok((new TipoPrestamoService())->guardar(null, Peticion::json()), 201);
    }

    public function actualizar(array $parametros): void
    {
        Sesion::requerirRol('Administrador');
        Respuesta::ok((new TipoPrestamoService())->guardar(Peticion::id($parametros), Peticion::json()));
    }

    public function cambiarEstado(array $parametros): void
    {
        Sesion::requerirRol('Administrador');
        $id = Peticion::id($parametros);
        $datos = Peticion::json();
        Respuesta::ok((new TipoPrestamoService())->cambiarEstado($id, (string) ($datos['estado'] ?? '')));
    }
}
