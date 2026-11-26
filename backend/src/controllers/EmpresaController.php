<?php
declare(strict_types=1);

/** Datos públicos de la empresa (config.php): se muestran en el login y en el menú. No requiere sesión. */
final class EmpresaController
{
    public function datos(array $parametros): void
    {
        Respuesta::ok([
            'nombre' => (string) Configuracion::obtener('empresa.nombre', 'Sistema de Gestión de Préstamos'),
            'lema'   => (string) Configuracion::obtener('empresa.lema', ''),
        ]);
    }
}
