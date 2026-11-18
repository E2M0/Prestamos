<?php
declare(strict_types=1);

/** Endpoints de apoyo a la apertura de préstamos. Todos requieren sesión iniciada. */
final class PrestamoController
{
    /** GET /api/clientes/{id}/credito */
    public function credito(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new PrestamoService())->credito(Peticion::id($parametros)));
    }

    /** GET /api/prestamos/fecha-primer-pago */
    public function fechaPrimerPago(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new PrestamoService())->fechaPrimerPago($_GET));
    }

    /** POST /api/prestamos/calcular */
    public function calcular(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new PrestamoService())->calcular(Peticion::json()));
    }

    /** POST /api/prestamos */
    public function crear(array $parametros): void
    {
        $usuario = Sesion::requerir();
        Respuesta::ok((new PrestamoService())->guardar(Peticion::json(), $usuario['id']), 201);
    }
}
