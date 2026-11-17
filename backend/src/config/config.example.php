<?php
declare(strict_types=1);

/**
 * PLANTILLA de configuración (esta sí se sube a Git).
 * Copie este archivo como config.php y ajuste los valores. config.php NO se sube a Git.
 */
return [
    'db' => [
        'servidor'            => 'NOMBRE_PC\\INSTANCIA',   // ej.: PC\SQLEXPRESS
        'base'                => 'prestamos_db',
        'cifrar'              => true,
        'confiar_certificado' => true,   // solo desarrollo local
        'usuario'             => null,   // null = Autenticación de Windows
        'clave'               => null,
    ],
    'cors' => [
        'origenes' => ['http://localhost:5173', 'http://127.0.0.1:5173'],
    ],
    'zona_horaria' => 'America/Santo_Domingo',
];
