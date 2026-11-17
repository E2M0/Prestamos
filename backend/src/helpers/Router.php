<?php
declare(strict_types=1);

/** Enrutador mínimo: asocia método + ruta (con parámetros {id}) a [Controlador, método]. */
final class Router
{
    private array $rutas = [];

    public function agregar(string $metodo, string $patron, array $manejador): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $patron) . '$#';
        $this->rutas[] = [strtoupper($metodo), $regex, $manejador];
    }

    public function get(string $patron, array $manejador): void    { $this->agregar('GET', $patron, $manejador); }
    public function post(string $patron, array $manejador): void   { $this->agregar('POST', $patron, $manejador); }
    public function put(string $patron, array $manejador): void    { $this->agregar('PUT', $patron, $manejador); }
    public function patch(string $patron, array $manejador): void  { $this->agregar('PATCH', $patron, $manejador); }

    public function despachar(string $metodo, string $ruta): void
    {
        $ruta = rtrim($ruta, '/') ?: '/';
        $metodoRuta = false;

        foreach ($this->rutas as [$m, $regex, $manejador]) {
            if (!preg_match($regex, $ruta, $coincidencias)) {
                continue;
            }
            if ($m !== strtoupper($metodo)) {
                $metodoRuta = true;
                continue;
            }
            $parametros = array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);
            [$clase, $accion] = $manejador;
            (new $clase())->$accion($parametros);
            return;
        }

        if ($metodoRuta) {
            Respuesta::error('Método no permitido.', 405);
        }
        Respuesta::error('Ruta no encontrada.', 404);
    }
}
