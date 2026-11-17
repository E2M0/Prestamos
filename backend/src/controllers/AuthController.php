<?php
declare(strict_types=1);

/** Endpoints de sesión: login, logout y "quién soy". */
final class AuthController
{
    public function login(array $parametros): void
    {
        $datos = Peticion::json();
        $usuario = (new AuthService())->autenticar(
            trim((string) ($datos['usuario'] ?? '')),
            (string) ($datos['clave'] ?? '')
        );
        Sesion::abrir($usuario);
        Respuesta::ok($usuario);
    }

    public function logout(array $parametros): void
    {
        Sesion::cerrar();
        Respuesta::ok();
    }

    /** Devuelve el usuario conectado o null (200 en ambos casos, para que React decida qué mostrar). */
    public function yo(array $parametros): void
    {
        Respuesta::ok(Sesion::usuario());
    }
}
