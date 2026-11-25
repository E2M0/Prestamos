<?php
declare(strict_types=1);

/**
 * Ejecuta todas las pruebas automatizadas del cálculo financiero (no necesitan SQL Server ni navegador).
 * Uso, desde la carpeta del proyecto:   php tests/ejecutar.php
 * Termina con código 0 si todo pasa y 1 si algo falla.
 */
require __DIR__ . '/_arranque.php';

$suites = [
    'Casos con resultados calculados a mano' => __DIR__ . '/casos_manuales.php',
    'Pagos aleatorios (reglas contables)' => __DIR__ . '/propiedades_pagos.php',
    'Cancelación por renovación (tres bases de saldo)' => __DIR__ . '/propiedades_cancelacion.php',
    'Límite de cuotas por tipo de plazo' => __DIR__ . '/limites_plazo.php',
    'Reglas de negocio' => __DIR__ . '/reglas_negocio.php',
    'Referencias personales opcionales' => __DIR__ . '/referencias_opcionales.php',
];

$inicio = microtime(true);
foreach ($suites as $nombre => $archivo) {
    $antes = ['ok' => $GLOBALS['prueba']['ok'], 'fallas' => count($GLOBALS['prueba']['fallas'])];
    echo "• $nombre\n";
    (require $archivo)();
    $ok = $GLOBALS['prueba']['ok'] - $antes['ok'];
    $fallas = count($GLOBALS['prueba']['fallas']) - $antes['fallas'];
    echo '  ' . ($fallas === 0 ? 'OK' : 'FALLA') . " — $ok verificaciones correctas, $fallas fallidas\n";
}

$fallas = $GLOBALS['prueba']['fallas'];
echo "\nTotal: {$GLOBALS['prueba']['ok']} verificaciones correctas, " . count($fallas) . ' fallidas (' . round(microtime(true) - $inicio, 1) . " s)\n";
foreach (array_slice($fallas, 0, 15) as $f) {
    echo "  ✗ $f\n";
}
exit($fallas ? 1 : 0);
