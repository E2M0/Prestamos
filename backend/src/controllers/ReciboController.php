<?php
declare(strict_types=1);

/** Endpoints de préstamos (consulta para cobrar) y recibos de ingreso. Todos requieren sesión. */
final class ReciboController
{
    /** GET /api/prestamos?buscar=&estado= */
    public function listarPrestamos(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new ReciboService())->listarPrestamos($_GET));
    }

    /** GET /api/prestamos/{id}/cuenta */
    public function cuenta(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new ReciboService())->cuenta(Peticion::id($parametros)));
    }

    /** POST /api/recibos/calcular */
    public function calcular(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new ReciboService())->calcular(Peticion::json()));
    }

    /** POST /api/recibos */
    public function crear(array $parametros): void
    {
        $usuario = Sesion::requerir();
        Respuesta::ok((new ReciboService())->aplicar(Peticion::json(), $usuario['id']), 201);
    }

    /** GET /api/recibos/{id} */
    public function obtener(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new ReciboService())->obtener(Peticion::id($parametros)));
    }

    /** PATCH /api/recibos/{id}/formato */
    public function cambiarFormato(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new ReciboService())->cambiarFormato(Peticion::id($parametros), Peticion::json()));
    }

    /** POST /api/recibos/{id}/correo */
    public function enviarCorreo(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new ReciboService())->enviarCorreo(Peticion::id($parametros), Peticion::json()));
    }
}
