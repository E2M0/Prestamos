<?php
declare(strict_types=1);

/**
 * PLANTILLA de configuración para el HOSTING (esta sí se sube a Git).
 * Copie este archivo como despliegue/config.hosting.php y complete los datos de su base MySQL del panel.
 * config.hosting.php NO se sube a Git; publicar.ps1 lo copia como app/src/config/config.php.
 */
return [
    'db' => [
        'servidor' => 'localhost',        // en un hosting: el "MySQL Hostname" del panel (ej.: sql123.infinityfree.com)
        'puerto'   => 3306,
        'base'     => 'prestamos_db',     // nombre completo de la base (en hostings lleva prefijo: if0_12345_prestamos)
        'usuario'  => 'root',
        'clave'    => '',
    ],
    'cors' => [
        'origenes' => [],   // página y API comparten dominio: no hace falta CORS
    ],
    'empresa' => [
        'nombre' => 'Sistema de Gestión de Préstamos',   // aparece en el recibo impreso
    ],
    // Envío de recibos por correo (PHPMailer en backend/lib/PHPMailer/src). Con 'activo' => false el envío se desactiva.
    'correo' => [
        'activo'           => false,
        'servidor'         => 'smtp.gmail.com',
        'puerto'           => 587,
        'seguridad'        => 'tls',          // tls (587) o ssl (465)
        'usuario'          => '',             // cuenta que envía
        'clave'            => '',             // contraseña de aplicación (no la clave normal)
        'remitente'        => '',             // vacío = igual al usuario
        'nombre_remitente' => 'Sistema de Préstamos',
    ],
    'zona_horaria' => 'America/Santo_Domingo',
];
