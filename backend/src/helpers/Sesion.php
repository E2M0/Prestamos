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
        // La cookie es "secure" solo si la petición llegó por HTTPS (directo o a través de un
        // túnel que envía X-Forwarded-Proto). En http://localhost sigue funcionando sin HTTPS.
        $esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        session_name('prestamos_sid');
        session_set_cookie_params([
            'lifetime' => 0,          // se borra al cerrar el navegador
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $esHttps,   // true automáticamente cuando se usa HTTPS
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

    /**
     * Para operaciones que solo puede hacer cierto rol (ej.: 'Administrador'). Exige sesión (401)
     * y, si el rol del usuario no está permitido, responde 403. Es la protección real:
     * ocultar un botón en React no impide llamar a la API.
     */
    public static function requerirRol(string ...$roles): array
    {
        $usuario = self::requerir();
        if (!in_array($usuario['rol'] ?? '', $roles, true)) {
            Respuesta::error('No tiene permiso para realizar esta operación.', 403);
        }
        return $usuario;
    }
}
