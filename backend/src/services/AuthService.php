<?php
declare(strict_types=1);

/** Reglas del inicio de sesión: validación de campos y verificación de la contraseña. */
final class AuthService
{
    public function __construct(private UsuarioModel $usuarios = new UsuarioModel())
    {
    }

    /** Devuelve los datos públicos del usuario o lanza ErrorApi. */
    public function autenticar(string $usuario, string $clave): array
    {
        $errores = [];
        if ($usuario === '') {
            $errores['usuario'] = 'Ingrese su usuario.';
        }
        if ($clave === '') {
            $errores['clave'] = 'Ingrese su contraseña.';
        }
        if ($errores) {
            throw new ErrorApi('Complete los campos obligatorios.', 422, $errores);
        }

        $fila = strlen($usuario) <= 50 ? $this->usuarios->buscarPorUsuario($usuario) : null;

        // Mismo mensaje para usuario inexistente, inactivo o clave errónea (no revela cuál falló)
        if ($fila === null || $fila['estado'] !== 'Activo' || !password_verify($clave, $fila['clave_hash'])) {
            throw new ErrorApi('Usuario o contraseña incorrectos.', 401);
        }

        return [
            'id'      => (int) $fila['id'],
            'usuario' => $fila['usuario'],
            'nombre'  => $fila['nombre'],
            'rol'     => $fila['rol'],
        ];
    }
}
