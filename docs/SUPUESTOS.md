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
9. Las **tres referencias personales son obligatorias** y los **familiares son opcionales**
   (el enunciado marca ambos como opcionales en 3.1, pero exige las tres referencias para guardar).
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

## Reglas financieras (se implementarán en las etapas 8 y 9)
32. Mora: se cobra **una sola vez por cuota vencida**, como porcentaje del valor de la cuota o como monto fijo.
33. Límite disponible = límite de crédito − capital pendiente de los préstamos activos del cliente.
34. Saldo fijo: al saldarse la cuota k se genera la k+1 en la misma transacción del recibo (capital = monto / n;
    la última cuota toma el balance restante).
35. Los pagos recibidos del dashboard excluyen los recibos de cancelación por renovación (no entra dinero).
