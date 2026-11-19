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

// Clientes y garantes (Etapa 5)
$router->get('/api/catalogos/clientes',    [CatalogoController::class, 'clientes']);
$router->get('/api/personas/buscar',       [ClienteController::class, 'buscarPersona']);
$router->get('/api/clientes',              [ClienteController::class, 'listar']);
$router->post('/api/clientes',             [ClienteController::class, 'crear']);
$router->get('/api/clientes/{id}',         [ClienteController::class, 'obtener']);
$router->put('/api/clientes/{id}',         [ClienteController::class, 'actualizar']);
$router->patch('/api/clientes/{id}/estado', [ClienteController::class, 'cambiarEstado']);

// Tipos de préstamo y apertura (Etapa 6)
$router->get('/api/tipos-prestamo',               [TipoPrestamoController::class, 'listar']);
$router->post('/api/tipos-prestamo',              [TipoPrestamoController::class, 'crear']);
$router->get('/api/tipos-prestamo/{id}',          [TipoPrestamoController::class, 'obtener']);
$router->put('/api/tipos-prestamo/{id}',          [TipoPrestamoController::class, 'actualizar']);
$router->patch('/api/tipos-prestamo/{id}/estado', [TipoPrestamoController::class, 'cambiarEstado']);
$router->get('/api/catalogos/prestamos',          [CatalogoController::class, 'prestamos']);
$router->get('/api/clientes/{id}/credito',        [PrestamoController::class, 'credito']);
$router->get('/api/prestamos/fecha-primer-pago',  [PrestamoController::class, 'fechaPrimerPago']);

// Apertura de préstamos: cálculo y guardado (Etapa 7)
$router->post('/api/prestamos/calcular', [PrestamoController::class, 'calcular']);
$router->post('/api/prestamos',          [PrestamoController::class, 'crear']);

// Recibos de ingreso (Etapa 8)
$router->get('/api/prestamos',                  [ReciboController::class, 'listarPrestamos']);
$router->get('/api/prestamos/{id}/cuenta',      [ReciboController::class, 'cuenta']);
$router->post('/api/recibos/calcular',          [ReciboController::class, 'calcular']);
$router->post('/api/recibos',                   [ReciboController::class, 'crear']);
$router->get('/api/recibos/{id}',               [ReciboController::class, 'obtener']);
$router->patch('/api/recibos/{id}/formato',     [ReciboController::class, 'cambiarFormato']);
$router->post('/api/recibos/{id}/correo',       [ReciboController::class, 'enviarCorreo']);

// Renovación de préstamos (Etapa 9)
$router->get('/api/clientes/{id}/prestamos-renovables', [RenovacionController::class, 'renovables']);
$router->post('/api/renovaciones/calcular',             [RenovacionController::class, 'calcular']);
$router->post('/api/renovaciones',                      [RenovacionController::class, 'crear']);
