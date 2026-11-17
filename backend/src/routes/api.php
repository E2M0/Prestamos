<?php
declare(strict_types=1);

/**
 * Tabla de endpoints de la API. Cada línea: método, ruta, [Controlador, acción].
 * Los módulos se irán agregando aquí en las etapas siguientes.
 */

/** @var Router $router */

// Diagnóstico (Etapa 3)
$router->get('/api/ping',  [SaludController::class, 'ping']);   // API viva, sin base de datos
$router->get('/api/salud', [SaludController::class, 'salud']);  // API + conexión a SQL Server

// Sesión (Etapa 4)
$router->post('/api/auth/login',  [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/auth/yo',      [AuthController::class, 'yo']);

// Dashboard (Etapa 4)
$router->get('/api/dashboard', [DashboardController::class, 'resumen']);
