# Supuestos y decisiones de diseño

El enunciado (sección 10) permite definir libremente las reglas no especificadas, siempre que queden
documentadas y se apliquen de forma consistente. Estas son las del proyecto hasta ahora.

## Base de datos
1. **Una persona se registra una sola vez** en `clientes` (documento único). Los roles son relaciones:
   es cliente si `es_cliente = 1`; es garante si aparece en `clientes_garantes`.
2. **`id_garante` no está en `clientes`**: se usa solo `clientes_garantes` (con `vigente`) para evitar datos duplicados
   y conservar el historial de garantes. Un cliente tiene un solo garante vigente.
3. El documento es único en todo el sistema, sin importar el tipo (cédula, RNC o pasaporte).
4. Los clientes y préstamos nunca se eliminan: cambian de estado.
5. Numeración de préstamos y recibos con `SEQUENCE` de SQL Server (sin duplicados).

## Módulo de clientes
6. **Validación del documento** con expresión regular guardada en `tipos_documento.patron`
   (la misma en React y en PHP): cédula `000-0000000-0`, RNC `0-00-00000-0`, pasaporte alfanumérico de 6 a 12.
   Se valida el formato, no el dígito verificador.
7. Teléfonos con formato `809-000-0000`, máximo 5 por persona y exactamente uno principal.
8. Correo electrónico opcional (el recibo puede enviarse a un correo digitado manualmente).
9. Las **referencias personales** (hasta tres) y los **familiares** son **opcionales**, como marca el punto 3.1 del enunciado
   (que en otro párrafo menciona «las tres referencias» como requisito; se siguió la marca OPCIONAL, a petición del estudiante).
   Una fila vacía se ignora; si se llena nombre o teléfono, ambos son obligatorios. Se validan en PHP y en React.
10. Se exige mayoría de edad (18 años) al cliente y al garante.
11. Clasificación del cliente: A (Excelente), B (Bueno), C (Regular).
12. Un cliente con préstamos activos no puede inactivarse.
13. Un garante inactivo no puede asignarse.
14. Si se registra como cliente a una persona que ya era garante, se reutiliza su registro (no se duplica)
    y se reactiva si estaba inactiva.
15. El garante existente se carga en solo lectura; el botón "Editar datos del garante" permite actualizar
    el registro de esa persona.

## Tipos de préstamo y apertura (Etapa 6)
16. El nombre del tipo de préstamo es único. Los tipos no se eliminan: se inactivan; un tipo inactivo no se ofrece
    en préstamos nuevos, pero los préstamos que ya lo usan lo conservan.
17. El tipo de préstamo puede crearse desde la propia apertura (botón "Nuevo tipo"), sin salir de la pantalla.
18. Solo se aperturan préstamos a clientes activos.
19. La fecha sugerida del primer pago la calcula el servidor (`CalendarioService`): mensual, trimestral, semestral
    y anual avanzan por meses de calendario (31 de enero + 1 mes = 28 o 29 de febrero); los demás plazos, por días.
    Las fechas se miden siempre desde la fecha base, para que el fin de mes no arrastre el error a las cuotas siguientes.
20. Solo el plazo diario permite excluir sábados y domingos; los días excluidos se saltan.
21. Reglas de validación de la apertura: cuotas enteras de 1 a 600; tasa mensual de 0 a 100; mora en porcentaje de
    0 a 100; el primer pago no puede ser anterior a la apertura; si el gasto de cierre no se financia debe ser menor
    que el monto (para que exista desembolso).

## Cálculo de amortización y apertura (Etapa 7)
Todo el cálculo está en PHP (`AmortizacionService`); React solo muestra lo que devuelve el servidor.
22. La tasa es **porcentaje mensual nominal** y se convierte al período: `i = tasa/100 × días del período / 30`
    (trimestral = 3 meses, anual = 12 meses, etc.).
23. **Absoluto:** capital = monto / n e interés = monto × i, iguales en todas las cuotas.
    **Insoluto:** cuota = M·i / (1 − (1+i)^−n); interés = balance anterior × i; capital = cuota − interés.
    **Saldo fijo:** capital = monto / n; interés = capital pendiente × i; en la apertura solo se genera la 1.ª cuota.
24. Con tasa 0 la fórmula del saldo insoluto divide entre cero; en ese caso la cuota es monto / n.
25. Cada importe se redondea a 2 decimales (mitad hacia arriba) y el ajuste final cae en la **última cuota**,
    para que el balance termine exactamente en cero.
26. Seguro **fijo**: el mismo monto en cada cuota. Seguro **repartido**: monto total / n por cuota; la última absorbe la diferencia.
27. Gasto de cierre **financiado**: se reparte entre las cuotas como el seguro repartido y el desembolso es el monto completo.
    **No financiado:** desembolso = monto − gasto de cierre.
28. Si el primer pago cae en un sábado o domingo excluido (plazo diario), se rechaza; no se corre solo a otro día.
29. Al guardar, el servidor **vuelve a validar y a calcular** (no confía en la tabla del navegador) dentro de una
    transacción que bloquea la fila del cliente, para que dos aperturas simultáneas no superen el límite de crédito.
30. El número del préstamo (`PR-000010`) sale de una secuencia de SQL Server; si una transacción falla, ese número
    se pierde (queda un hueco). Es normal y garantiza que nunca se repita.
31. Un cálculo solo es válido mientras el formulario no cambie: al modificar cualquier dato hay que volver a calcular.

## Reglas financieras (etapas 8 y 9)
32. Mora: se cobra **una sola vez por cuota vencida**, como porcentaje del valor de la cuota o como monto fijo.
33. Límite disponible = límite de crédito − capital pendiente de los préstamos activos del cliente.
34. Saldo fijo: al saldarse la cuota k se genera la k+1 en la misma transacción del recibo. Capital de la cuota k =
    capital pendiente / cuotas restantes (n − k + 1); la última cuota toma el balance restante. En la 1.ª cuota
    equivale a monto / n.
35. Los pagos recibidos del dashboard excluyen los recibos de cancelación por renovación (no entra dinero).

## Recibos de ingreso (Etapa 8)
36. Orden de aplicación de un pago normal, cuota por cuota (de la más antigua sin saldar a la más reciente):
    seguro → mora → interés → gasto de cierre financiado → capital. Un pago puede cubrir varias cuotas o dejar una en «Parcial».
37. Una cuota está vencida si su fecha de pago es **anterior a hoy** y no está saldada. La mora se calcula y se guarda
    **una sola vez** por cuota (columna `mora_acumulada`) al consultar el préstamo, al cobrar o al abrir el dashboard
    (`MoraService::sincronizar`). Por eso los indicadores del dashboard pueden cambiar respecto a los datos de prueba iniciales.
38. Una cuota con mora queda «Pagada» solo cuando también se pagó su mora. Estado: Pagada · Parcial (hay pagos de
    seguro/interés/gasto/capital) · Vencida (fecha pasada, sin pagos) · Pendiente.
39. Pago «solo mora»: afecta únicamente la mora acumulada; no puede superar la mora pendiente.
40. Pago normal: no puede superar lo adeudado. En saldo fijo, al saldarse una cuota nace la siguiente aunque no quede dinero
    en ese pago; el préstamo pasa a «Saldado» cuando todas las cuotas (hasta el plazo) están saldadas.
41. **Abono a capital** (criterios propios, el enunciado no los detalla):
    a) no se permite con mora pendiente (se paga primero la mora);
    b) solo reduce el capital de las cuotas **futuras** (pendientes, sin pagos y no vencidas); el máximo es ese capital futuro (Cf);
    c) «Reducir cuota»: mismo número de cuotas, cada una con menos capital; «Reducir plazo»: desaparecen las últimas cuotas
       y se conserva el capital por cuota (absoluto/fijo) o la cuota total (insoluto);
    d) en **absoluto** el interés de las cuotas futuras baja en proporción al capital que queda (Cf' / Cf) — desviación
       del enunciado, necesaria porque el interés absoluto es fijo sobre el monto original; en insoluto y fijo el interés se
       recalcula sobre el nuevo balance;
    e) si el abono cancela todo el capital futuro, esas cuotas desaparecen.
42. Los importes se calculan en centavos enteros en PHP; React solo muestra y copia los valores sugeridos que envía el servidor.
43. Cada recibo se guarda con su detalle por cuota (o una línea sin cuota para el abono a capital) en una transacción
    con el préstamo bloqueado; la vista previa usa exactamente el mismo cálculo que el cobro.
44. Los números de recibo (`RC-000036`) salen de una secuencia: puede haber saltos si una transacción falla.
45. El correo usa PHPMailer (descarga manual en `backend/lib/PHPMailer/src`, sin Composer) y el bloque `correo` de
    `config.php`; sin esa configuración el servidor responde que el envío no está disponible. Compartir usa la API de
    compartir del navegador o WhatsApp Web. La impresión usa `@page` (A4 u 80 mm).
46. No se anulan recibos en esta etapa (el estado «Anulado» existe en la base de datos pero no hay pantalla para anular).

## Renovación de préstamos (Etapa 9)
47. La renovación reutiliza el formulario de apertura (mismos campos, cálculo y tabla virtual) y agrega la selección de
    1 a 3 préstamos **activos del mismo cliente**, cada uno con su base de saldo. Un préstamo ya renovado no vuelve a renovarse.
48. Bases de saldo: **Capital pendiente** = capital que falta (de todas las cuotas); **Balance a la fecha** = capital +
    mora, interés, seguro y gasto de cierre de las cuotas con fecha de pago ≤ hoy; **Balance general** = todo lo pendiente,
    incluidos intereses, seguros y gastos futuros. Los tres saldos los calcula PHP (`CancelacionService`).
49. Lo que la base elegida no cubre se registra como **descuento**: el importe pendiente de esas cuotas se reduce a lo ya
    pagado (condonación), por lo que sus cuotas quedan «Pagada» y el préstamo anterior pasa a **Renovado** con capital pendiente 0.
50. Cada préstamo cancelado genera su recibo de ingreso (tipo «normal», observación «Cancelación por renovación»), con el
    detalle por cuota. Esos recibos no suman en los ingresos del dashboard (no entra dinero).
51. En saldo fijo solo existen las cuotas ya generadas: el capital que aún no está en ninguna cuota se cobra como una línea
    sin cuota, y los intereses futuros no generados no existen todavía (el balance general no los incluye).
52. Validaciones: monto del préstamo nuevo ≥ suma de saldos a cancelar; desembolso del préstamo nuevo ≥ suma de saldos
    (desembolso final = desembolso nuevo − suma de saldos, nunca negativo). El límite de crédito se valida contando
    como libre el capital de los préstamos que se cancelan.
53. Todo ocurre en una sola transacción con los préstamos y el cliente bloqueados: si algo falla no se guarda nada
    (ni recibos, ni cambios en los préstamos anteriores, ni el préstamo nuevo).
54. **Versión para publicar en MySQL.** El estudiante no puede llevar su equipo a la corrección, así que se publicará en un hosting gratuito; según informó, el profesor aceptó MySQL. Se mantiene la versión SQL Server en `database/` y se agrega `database/mysql/` y el backend adaptado (ver `docs/PUBLICAR_EN_HOSTING.md`). La adaptación no se ha probado contra un MySQL real.
55. **Permisos por rol.** El **Cajero** opera el día a día (dashboard, clientes y garantes, apertura de préstamos y recibos de ingreso; puede consultar los tipos de préstamo al abrir un préstamo). El **Administrador** además define las reglas y autoriza lo delicado: crear/editar/desactivar tipos de préstamo y hacer renovaciones (que pueden condonar montos). Se exige en la API (403 «No tiene permiso…», `Sesion::requerirRol`) y además se oculta del menú y de las rutas de React. No hay pantalla de gestión de usuarios.
56. **Rango de cuotas por tipo de plazo.** La tabla `tipos_plazo` tiene `plazo_minimo` y `plazo_maximo` (Diario 15–365, Interdiario 10–180, Semanal 4–156, Intersemanal 2–130, Quincenal 2–120, Mensual 1–120, Trimestral 1–40, Semestral 1–20, Anual 1–15). Se valida en PHP (`PrestamoValidador`) y en React, y aplica también a los préstamos creados por renovación. Los valores se cambian con `UPDATE`; no hay pantalla para editarlos. Los préstamos ya existentes no se modifican.
57. **Reglas de negocio en la base (`reglas_negocio`).** Para que no entren datos poco realistas, se validan en PHP (y se avisa antes en React) estos límites, guardados como clave/valor y editables con `UPDATE`: tasa mensual 0.5–10 %, monto mínimo RD$1,000, mora en porcentaje máx. 10 % de la cuota, mora fija máx. 5 % del monto, seguro total máx. 10 % del monto, gasto de cierre máx. 10 % del monto e interés total máx. 100 % del capital (este último se comprueba en el servidor tras calcular la tabla; con amortización de saldo fijo solo se conoce la primera cuota, así que el control es más débil). Si una regla no existe, no hay límite. Los valores son propuestos por el desarrollador, no vienen del enunciado.
58. **Límite de crédito y clasificación de clientes.** El límite de un cliente debe estar entre RD$5,000 y RD$10,000,000 y la clasificación (solo A, B o C) debe corresponder al límite: C (Regular) hasta 100,000, B (Bueno) de 100,001 a 1,000,000 y A (Excelente) de 1,000,001 a 10,000,000. Los cortes se aplican con `<=` (100,000 y 1,000,000) para no dejar huecos con centavos. Se valida en React (aviso en vivo, el límite sugiere la clasificación), en PHP (`ClienteValidador`, error 422; si la clasificación llega vacía se asigna según el límite) y en la base (`ck_clientes_limite_rango` y `ck_clientes_clasif_limite`). Las restricciones solo aplican a `es_cliente = 1`: un garante sin crédito conserva límite 0 y clasificación NULL. Al crear un préstamo ya se verifica que el capital pendiente de sus préstamos activos más el nuevo no supere el límite. La migración `06_limites_credito_clientes.sql` (una por motor) sube a 5,000 los límites menores, baja a 10,000,000 los mayores y corrige la clasificación de los clientes existentes según su límite; los datos de prueba se ajustaron igual. Los tramos son propuestos por el desarrollador.
