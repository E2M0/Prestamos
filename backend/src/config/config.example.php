<?php
declare(strict_types=1);

/**
 * PLANTILLA de configuración (esta sí se sube a Git).
 * Copie este archivo como config.php y ajuste los valores. config.php NO se sube a Git.
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
        'origenes' => ['http://localhost:5173', 'http://127.0.0.1:5173'],
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
