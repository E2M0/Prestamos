# Guía para la defensa

Preguntas que probablemente harán y cómo responderlas con lo que realmente hace el sistema. Si no recuerda algo, abra el archivo indicado.

## 1. Arquitectura

**¿Cómo está organizado el backend?**
Un punto de entrada (`public/index.php`) → router propio → **controlador** (recibe y responde) → **servicio** (reglas y cálculos) → **modelo** (SQL preparado).
Así cada archivo hace una sola cosa. No se usó framework ni Composer.

**¿Por qué los cálculos están en PHP y no en React?**
Porque el servidor es la única fuente de verdad: React solo muestra. Si el cálculo estuviera en el navegador, cualquiera podría alterarlo.
React además repite las validaciones para dar respuesta inmediata, pero el servidor vuelve a validar todo.

**¿Cómo se comunican React y PHP?**
Por una API REST con JSON. En desarrollo, Vite reenvía `/api` al puerto 8000 (proxy), así el navegador usa un solo origen. El CORS también está configurado en `config.php`.

**¿Cómo manejan la sesión?**
Cookie `HttpOnly` y `SameSite=Lax`; los datos del usuario quedan en el servidor; se regenera el id al iniciar sesión; las contraseñas se guardan con `password_hash`.

## 2. Base de datos e integridad

**¿Qué garantiza la integridad?**
Llaves primarias y foráneas, `UNIQUE` (documentos, números de préstamo y recibo), y `CHECK`: estados válidos, importes no negativos,
`valor_cuota = capital + interés + seguro + gasto`, `monto_recibido = suma de sus componentes`, lo pagado ≤ total. La base rechaza datos incoherentes aunque falle el código.

**¿Por qué no se borran clientes ni préstamos?**
Lo pide el enunciado y es lo correcto contablemente: se inactivan o cambian de estado, y el historial se conserva.

**¿Cómo se generan `PR-000001` y `RC-000001`?**
Con secuencias de SQL Server (`NEXT VALUE FOR`). Si una transacción falla puede haber saltos en la numeración; es normal y está documentado.

**¿Qué es la auditoría básica?**
Cada tabla de movimiento guarda el usuario y la fecha y hora de creación.

## 3. Transacciones

**¿Qué operaciones son atómicas y por qué?**
Guardar un préstamo con sus cuotas, aplicar un recibo y procesar una renovación. Si algo falla a mitad, `rollback` y no queda nada guardado.
Ejemplo: en una renovación se cancelan hasta tres préstamos, se generan sus recibos y se crea el nuevo; no puede quedar un préstamo cancelado sin el nuevo.

**¿Qué pasa si dos cajeros cobran el mismo préstamo a la vez?**
El préstamo se bloquea (`UPDLOCK, HOLDLOCK`) dentro de la transacción: el segundo espera y trabaja con los datos ya actualizados.
Para la apertura se bloquea el cliente y así el límite de crédito no se puede sobrepasar con dos aperturas simultáneas.

**¿Cómo evitan la inyección SQL?**
Todas las consultas son preparadas (parámetros `?`); no hay SQL concatenado.

## 4. Amortización (lo más probable que pregunten)

**Explique los tres tipos.** (Ejemplo: RD$ 12,000, 3 cuotas, 3 % mensual)
- **Absoluto:** capital = M/n, interés = M·i sobre el monto original → 3 cuotas de 4,360.
- **Insoluto:** cuota fija = M·i / (1 − (1+i)^−n) ≈ 4,242.36; el interés se calcula sobre el balance, por eso baja (360 → 243.53 → 123.56).
- **Saldo fijo:** capital = balance / cuotas restantes; solo se genera la primera cuota y cada siguiente nace al saldar la anterior → 4,360, 4,240, 4,120.

**¿Cómo se adapta la tasa a plazos distintos al mensual?**
`i = tasa mensual × días del período / 30` (quincenal = × 15/30, semanal = × 7/30…).

**¿Cómo evitan errores de redondeo?**
Cada importe se redondea a 2 decimales y el último ajuste cae en la última cuota (balance final exactamente 0). Los pagos se calculan en centavos enteros.

**¿Cómo saben que el cálculo es correcto?**
Se comparó contra una referencia exacta (fracciones) en 4.009 casos sin diferencias, y hay pruebas automáticas (`php tests/ejecutar.php`) con casos calculados a mano.

## 5. Recibos

**¿En qué orden se aplica un pago normal?**
A la cuota más antigua sin saldar; dentro de ella: seguro → mora → interés → gasto de cierre → capital. Es una decisión documentada (`SUPUESTOS.md`, punto 36).

**¿Cómo funciona la mora?**
Se cobra **una sola vez por cuota vencida** (porcentaje de la cuota o monto fijo). Una cuota está vencida si su fecha es anterior a hoy. Se guarda en la cuota (`mora_acumulada`) al consultar, cobrar o abrir el dashboard.

**¿Qué es el abono a capital y qué reglas tiene?**
Reduce el capital de las cuotas futuras. El enunciado no detalla las reglas, así que se definieron (`SUPUESTOS.md`, punto 41): no se permite con mora pendiente; solo toca cuotas no vencidas y sin pagos;
**reducir cuota** mantiene el plazo, **reducir plazo** quita las últimas cuotas. En absoluto el interés futuro baja en proporción al capital que queda (una desviación consciente, justificada allí).

**¿La vista previa y el cobro pueden dar resultados distintos?**
No: usan exactamente el mismo planificador (`PagoService`) y el servidor recalcula al cobrar.

**¿Cómo funciona la impresión POS 80 / A4?**
El recibo es un componente con dos diseños; al imprimir, el CSS oculta el resto de la página y `@page` cambia el tamaño (A4 u 80 mm de ancho).
Compartir usa la API del navegador o WhatsApp; el correo usa PHPMailer con SMTP configurable.

## 6. Renovación

**¿Qué son las tres bases de saldo?**
**Capital pendiente:** solo el capital. **Balance a la fecha:** capital + mora, interés, seguro y gasto de las cuotas con fecha ≤ hoy. **Balance general:** todo lo pendiente.
Lo que no se cobra queda como **descuento** (condonación): el importe pendiente de esas cuotas se reduce a lo pagado, y el préstamo anterior queda en *Renovado*.

**¿Cómo se calcula el desembolso final?**
Desembolso del préstamo nuevo − suma de los saldos cancelados. Se exige que el monto nuevo cubra los saldos.

**¿Por qué el límite de crédito no bloquea la renovación?**
Porque el capital de los préstamos que se cancelan queda libre: el límite se valida contando ese capital como disponible.

## 7. Calidad, seguridad y ámbito de variables

**«Sin variables sensibles por consola»:** el frontend no tiene ninguna llamada a `console.*`, no hay credenciales en el código ni en el navegador (solo en `config.php`, fuera de Git), y los errores internos van a un log del servidor, no al usuario.

**¿Qué no está terminado o probado?**
Sea honesto: el correo requiere SMTP y no se probó con un servidor real; no hay pantalla de anulación de recibos; la renovación y parte de la apertura tienen verificación manual pendiente (ver `PLAN_DE_PRUEBAS.md`).

## 8. Demostración sugerida (10 minutos)

1. Login → dashboard.
2. Crear un cliente con garante (mostrar validación y búsqueda automática).
3. Aperturar un préstamo: calcular los tres tipos y comparar tablas; guardar uno.
4. Cobrar: pago normal parcial, ver el reparto; abono a capital; imprimir en A4 y POS 80.
5. Renovar un préstamo con distintas bases y explicar el desembolso final.
6. Mostrar `php tests/ejecutar.php` y la carpeta `tests/`.
