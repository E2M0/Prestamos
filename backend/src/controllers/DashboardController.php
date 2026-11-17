<?php
declare(strict_types=1);

/** Endpoint del dashboard (requiere sesión iniciada). */
final class DashboardController
{
    public function resumen(array $parametros): void
    {
        Sesion::requerir();
        Respuesta::ok((new DashboardService())->resumen());
    }
}
