<?php
declare(strict_types=1);

/**
 * Conexión única a MySQL / MariaDB con PDO_MYSQL y ayudante de transacciones.
 * Siempre se usan consultas preparadas reales (no emuladas); los errores lanzan excepciones.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $c = Configuracion::obtener('db');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $c['servidor'],
                (int) ($c['puerto'] ?? 3306),
                $c['base']
            );
            self::$pdo = new PDO($dsn, (string) ($c['usuario'] ?? ''), (string) ($c['clave'] ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // La hora de la base (NOW()) debe coincidir con la zona horaria de la aplicación.
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$pdo;
    }

    /**
     * Siguiente número de una secuencia ('prestamo' o 'recibo'). MySQL no tiene SEQUENCE:
     * LAST_INSERT_ID(expr) asigna y lee el valor en una sola sentencia atómica, así dos
     * usuarios simultáneos nunca reciben el mismo número. Debe llamarse dentro de la transacción.
     */
    public static function siguienteNumero(string $nombre): int
    {
        $pdo = self::conexion();
        $st = $pdo->prepare('UPDATE secuencias SET valor = LAST_INSERT_ID(valor + 1) WHERE nombre = ?');
        $st->execute([$nombre]);
        if ($st->rowCount() !== 1) {
            throw new RuntimeException("Falta la secuencia '$nombre' en la tabla secuencias.");
        }
        return (int) $pdo->query('SELECT LAST_INSERT_ID()')->fetchColumn();
    }

    /**
     * Ejecuta $operacion dentro de una transacción atómica.
     * Si algo falla se hace ROLLBACK y la excepción se vuelve a lanzar.
     * Se usará en: guardar préstamo, aplicar recibo y renovar.
     */
    public static function transaccion(callable $operacion): mixed
    {
        $pdo = self::conexion();
        $pdo->beginTransaction();
        try {
            $resultado = $operacion($pdo);
            $pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
