<?php
declare(strict_types=1);

/**
 * Sesión PHP del usuario autenticado. La cookie es HttpOnly (JavaScript no puede leerla)
 * y SameSite=Lax. Los datos del usuario viven en el servidor, no en el navegador.
 */
final class Sesion
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('prestamos_sid');
        session_set_cookie_params([
            'lifetime' => 0,          // se borra al cerrar el navegador
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => false,      // en producción con HTTPS debe ser true
        ]);
        session_start();
    }

    /** Datos públicos del usuario conectado, o null. */
    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function abrir(array $usuario): void
    {
        session_regenerate_id(true);   // evita la fijación de sesión
        $_SESSION['usuario'] = $usuario;
    }

    public static function cerrar(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Para los endpoints protegidos: corta con 401 si no hay sesión. */
    public static function requerir(): array
    {
        $usuario = self::usuario();
        if ($usuario === null) {
            Respuesta::error('Debe iniciar sesión.', 401);
        }
        return $usuario;
    }
}
