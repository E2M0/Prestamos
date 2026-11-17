<?php
declare(strict_types=1);

/** Endpoints de clientes y garantes. Todos requieren sesión iniciada. */
final class ClienteController
{
    public function listar(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new ClienteService())->listar($_GET));
    }

    public function obtener(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new ClienteService())->obtener(Peticion::id($parametros)));
    }

    public function crear(array $parametros): void
    {
        $usuario = Sesion::requerir();
        Respuesta::ok((new ClienteService())->guardar(null, Peticion::json(), $usuario['id']), 201);
    }

    public function actualizar(array $parametros): void
    {
        $usuario = Sesion::requerir();
        $id = Peticion::id($parametros);
        Respuesta::ok((new ClienteService())->guardar($id, Peticion::json(), $usuario['id']));
    }

    public function cambiarEstado(array $parametros): void
    {
        $usuario = Sesion::requerir();
        $id = Peticion::id($parametros);
        $datos = Peticion::json();
        Respuesta::ok((new ClienteService())->cambiarEstado($id, (string) ($datos['estado'] ?? ''), $usuario['id']));
    }

    /** GET /api/personas/buscar?documento=... (garante y búsqueda inversa). */
    public function buscarPersona(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new ClienteService())->buscarPersona((string) ($_GET['documento'] ?? '')));
    }
}
