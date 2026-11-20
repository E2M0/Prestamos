# Plan de pruebas

Este documento separa lo que **se ejecutó y pasó** de lo que **falta ejecutar**. No se registran resultados que no se hayan obtenido.

## 1. Pruebas automatizadas (ejecutadas)

Comando: `php tests/ejecutar.php` (no necesita SQL Server ni navegador). Última ejecución: **todas pasaron — 301.906 verificaciones, 0 fallidas**.

| Suite | Qué comprueba | Resultado |
|---|---|---|
| Casos calculados a mano | Préstamo de RD$ 12,000, 3 cuotas, 3 % mensual: absoluto (3 × 4,360; intereses 1,080), insoluto (cuota 4,242.36; intereses 360.00 / 243.53 / 123.56), saldo fijo (4,360 → 4,240 → 4,120), seguro, gasto financiado y no financiado (desembolso), mora por porcentaje y por monto, pago de 5,000 repartido entre dos cuotas vencidas (orden seguro → mora → interés → capital), rechazo de pagos excesivos, abono con mora, y los tres saldos de cancelación (12,000 / 13,366 / 13,826) | Pasó (32) |
| Pagos aleatorios | ≈ 6.200 pagos (normal, solo mora, abono, ambas modalidades) sobre los tres tipos de amortización y plazos mensual/quincenal/semanal/diario: lo aplicado = lo recibido; valor de cuota = suma de componentes; lo pagado ≤ total; sin balances negativos; numeración consecutiva; capital de las cuotas = capital pendiente; mora una sola vez por cuota; préstamo saldado ⇒ capital 0 | Pasó (284.450) |
| Cancelación por renovación | 900 cancelaciones con historial de pagos previo: totales = monto; se cobra todo el capital pendiente; las cuotas quedan saldadas; capital ≤ a la fecha ≤ general; saldo + descuento = balance general | Pasó (17.424) |

Las pruebas **detectan errores**: al alterar a propósito el cálculo del interés en una copia, seis verificaciones fallaron.

Además se ejecutó, durante el desarrollo: comparación del motor de amortización contra una referencia exacta (aritmética de fracciones) en
4.009 casos, sin diferencias; revisión de sintaxis de todo el PHP y de los archivos JSX; y comprobación de que las rutas protegidas devuelven 401 sin sesión.

**Auditoría de código:** sin `console.*` en el frontend (no se imprimen datos sensibles por consola); sin credenciales en el código
(solo en `config.php`, fuera de Git); sin SQL concatenado; sin funciones no disponibles en el PHP del proyecto.

## 2. Estado de la verificación manual (por etapa)

| Etapa | Verificada por el usuario |
|---|---|
| 2 Base de datos | Sí (14 personas, 11 clientes, 9 préstamos, 72 cuotas, 35 recibos) |
| 3 Entorno | Sí |
| 4 Layout, login, dashboard | Sí |
| 5 Clientes y garantes | Sí |
| 6 Tipos de préstamo y apertura | Parcial |
| 7 Amortización y guardado | Sí |
| 8 Recibos | Sí (el correo no se ha probado) |
| 9 Renovación | Pendiente |

## 3. Lista de pruebas manuales

Marque cada caso cuando lo ejecute (`✔` pasó, `✘` falló + nota). Se recomienda repetir estas pruebas sobre una base recién creada con los tres scripts.

| # | Módulo | Pasos | Resultado esperado | Resultado |
|---|---|---|---|---|
| M1 | Clientes | Registrar cliente con 3 referencias | Se guarda y aparece en la lista | |
| M2 | Clientes | Registrar con cédula inválida | Error claro en el campo; no se guarda | |
| M3 | Clientes | Garante con documento existente | Datos cargados automáticamente, sin duplicar | |
| M4 | Clientes | Inactivar y volver a activar | Cambia de estado; no se elimina | |
| M5 | Apertura | Cliente + absoluto 12,000 / 3 / 3 % → Calcular | Cuotas de 4,360 | |
| M6 | Apertura | Igual con insoluto | Cuota ≈ 4,242.36 | |
| M7 | Apertura | Igual con saldo fijo | Solo la primera cuota (4,360) | |
| M8 | Apertura | Monto mayor al disponible | Rechazado con mensaje | |
| M9 | Apertura | Calcular, cambiar un dato | El botón Guardar se bloquea hasta recalcular | |
| M10 | Apertura | Guardar | Número `PR-…`; el disponible del cliente baja | |
| M11 | Recibo | Pago normal de la próxima cuota | Recibo generado; cuota Pagada; capital pendiente baja | |
| M12 | Recibo | Pago parcial de una cuota | Cuota Parcial; recibo sin capital hasta cubrir seguro e interés | |
| M13 | Recibo | Préstamo con cuotas vencidas | La mora aparece una vez por cuota; no se duplica al recargar | |
| M14 | Recibo | Solo mora | Capital sin cambios | |
| M15 | Recibo | Abono con mora pendiente | Rechazado | |
| M16 | Recibo | Abono sin mora: reducir cuota / reducir plazo | Cuotas futuras recalculadas / menos cuotas | |
| M17 | Recibo | Pago mayor a lo adeudado | Rechazado indicando el máximo | |
| M18 | Recibo | Saldo fijo: pagar una cuota completa | Nace la cuota siguiente | |
| M19 | Recibo | Saldar un préstamo completo | Estado Saldado; ya no admite pagos | |
| M20 | Recibo | Imprimir en A4 y en POS 80 | Solo se imprime el recibo, con el ancho correcto | |
| M21 | Recibo | Compartir y copiar resumen | Se abre el menú de compartir / WhatsApp; el texto se copia | |
| M22 | Recibo | Correo con SMTP configurado | Llega el recibo (si no hay SMTP: mensaje claro) | |
| M23 | Renovación | Renovar 1 préstamo con «Capital pendiente» | Recibo de cancelación; anterior «Renovado»; nuevo activo; desembolso final correcto | |
| M24 | Renovación | Renovar 2 préstamos con bases distintas | Saldos y descuento por préstamo; un solo préstamo nuevo | |
| M25 | Renovación | Monto nuevo menor que los saldos | Rechazado | |
| M26 | Renovación | Intentar un cuarto préstamo | Bloqueado | |
| M27 | Renovación | Renovar uno de saldo fijo | Funciona; capital pendiente cobrado completo | |
| M28 | Integridad | Forzar un error a mitad de una operación (por ejemplo, detener SQL Server al renovar) | No queda nada guardado a medias | |
| M29 | Seguridad | Abrir una pantalla sin iniciar sesión | Redirige al login | |
| M30 | Dashboard | Abrir tras los cobros | Carga; los totales cuadran con los datos | |
