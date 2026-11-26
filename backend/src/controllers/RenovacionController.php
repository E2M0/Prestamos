<?php
declare(strict_types=1);

/** Endpoints de renovación de préstamos. Solo el rol Administrador puede usarlos. */
final class RenovacionController
{
    /** GET /api/clientes/{id}/prestamos-renovables */
    public function renovables(array $parametros): void
    {
        Sesion::requerirRol('Administrador');
        Respuesta::ok((new RenovacionService())->renovables(Peticion::id($parametros)));
    }

    /** POST /api/renovaciones/calcular */
    public function calcular(array $parametros): void
    {
        Sesion::requerirRol('Administrador');
        Respuesta::ok((new RenovacionService())->calcular(Peticion::json()));
    }

    /** POST /api/renovaciones */
    public function crear(array $parametros): void
    {
        $usuario = Sesion::requerirRol('Administrador');
        Respuesta::ok((new RenovacionService())->guardar(Peticion::json(), $usuario['id']), 201);
    }
}
