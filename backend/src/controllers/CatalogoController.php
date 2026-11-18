<?php
declare(strict_types=1);

/** Catálogos para los formularios (requiere sesión). */
final class CatalogoController
{
    public function clientes(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new ClienteService())->catalogos());
    }

    public function prestamos(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new PrestamoService())->catalogos());
    }
}
