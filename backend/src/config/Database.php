<?php
declare(strict_types=1);

/**
 * Conexión única a SQL Server con PDO_SQLSRV y ayudante de transacciones.
 * Siempre se usan consultas preparadas; los errores lanzan excepciones.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $c = Configuracion::obtener('db');
            $dsn = sprintf(
                'sqlsrv:Server=%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s',
                $c['servidor'],
                $c['base'],
                $c['cifrar'] ? 'yes' : 'no',
                $c['confiar_certificado'] ? 'yes' : 'no'
            );
            self::$pdo = new PDO($dsn, $c['usuario'] ?: null, $c['clave'] ?: null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
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
