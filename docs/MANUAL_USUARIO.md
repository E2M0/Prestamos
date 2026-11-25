# Manual de usuario

Ingrese con un usuario del sistema (ver `README.md`). El menú lateral da acceso a cada módulo; el encabezado muestra su perfil y
permite cerrar sesión.

## Convenciones de la interfaz

- **Modificar** siempre se hace así: botón *Buscar / Seleccionar* → se abre una ventana con buscador y lista → elija con
  **doble clic** sobre la fila (o un clic y el botón *Seleccionar*) → los datos se cargan en el formulario.
- Los campos con error se marcan en rojo con el motivo. Los mensajes verdes confirman una acción.
- Nada importante se borra: los clientes y los tipos de préstamo se **inactivan**; los préstamos cambian de estado.

## Dashboard
Seis indicadores (clientes activos, préstamos activos, capital colocado, pagos recibidos, cuotas vencidas y mora pendiente), tres
gráficos y la actividad reciente. Al abrirlo, la mora de las cuotas vencidas se actualiza a la fecha de hoy.

## Clientes y garantes
1. **Nuevo cliente:** complete datos personales, teléfonos, datos laborales, y, si desea, familiares y hasta **tres referencias** (ambos opcionales).
   El número de documento se valida según su tipo (cédula, pasaporte, RNC…).
2. **Garante:** escriba su documento. Si ya existe, sus datos se cargan solos; si no, se registra una persona nueva. Una persona
   que ya es cliente puede ser garante sin duplicarse.
3. **Modificar:** *Buscar cliente* → elija → edite → guarde. Para **inactivar** o **activar** use el botón de estado.
4. Un cliente inactivo no puede recibir préstamos nuevos.

## Tipos de préstamo
Cree y modifique los tipos (nombre, descripción). Puede inactivarlos; un tipo inactivo no aparece al aperturar.

## Apertura de préstamos
1. **Seleccione el cliente.** Verá su límite de crédito, el capital pendiente y el **disponible** (límite − capital pendiente de sus préstamos activos).
2. Elija el **tipo de préstamo** (o cree uno con *Nuevo tipo* sin salir de la pantalla), el **monto**, el **plazo** (cantidad de cuotas),
   la **tasa mensual**, el **tipo de plazo** (diario, semanal, quincenal, mensual…) y el **tipo de amortización**.
3. Defina la **mora** (porcentaje de la cuota o monto fijo) y el **seguro** (fijo en cada cuota o repartido).
4. Indique el **gasto de cierre** y si se financia (se suma a las cuotas) o se descuenta del desembolso.
5. Revise la **fecha de apertura** y la del **primer pago** (el sistema sugiere una; puede cambiarla).
6. Presione **Calcular**: aparece la tabla de amortización, el desembolso y los totales. No se guarda nada todavía.
7. Si todo está bien, **Guardar** y confirme. Si cambia cualquier dato, debe calcular de nuevo.

> En **saldo fijo** solo se muestra la primera cuota: las demás se generan al saldar la anterior.

## Recibos de ingreso (cobros)
1. **Seleccionar préstamo** (por número, cliente o documento). Verá el resumen, la mora pendiente, las cuotas y los recibos anteriores.
2. Elija el **tipo de pago**:
   - **Pago normal:** se aplica a las cuotas más antiguas (seguro → mora → interés → gasto → capital).
   - **Solo mora:** paga únicamente la mora acumulada.
   - **Abono a capital:** reduce el capital de las cuotas aún no vencidas; elija **reducir cuota** (mismo plazo) o **reducir plazo** (menos cuotas). No se permite con mora pendiente.
3. Escriba el **monto** o use un valor sugerido (próxima cuota, total vencido, mora, abono máximo).
4. **Calcular pago** muestra cómo se repartiría. Si está bien, **Cobrar** y confirme.
5. Aparece el **recibo**: elija formato **A4** o **POS 80 mm** e **Imprimir**; **Compartir** (menú del dispositivo o WhatsApp), **Copiar resumen**
   o enviarlo por **correo** (requiere configurar SMTP).
6. Doble clic en un recibo del historial para verlo o reimprimirlo.

## Renovación de préstamos
1. **Seleccione el cliente**; se listan sus préstamos activos.
2. Marque de **1 a 3** y elija la **base de saldo** de cada uno:
   - **Capital pendiente:** solo el capital que falta.
   - **Balance a la fecha:** capital + mora, interés, seguro y gasto de las cuotas ya exigibles.
   - **Balance general:** todo lo pendiente, incluidos intereses futuros.
3. Complete las condiciones del **préstamo nuevo** igual que en una apertura. Su monto debe cubrir los saldos a cancelar.
4. **Calcular** muestra los saldos, el descuento (lo que no se cobra) y el **desembolso final** (desembolso del nuevo − saldos).
5. **Renovar** y confirme: se generan los recibos de cancelación, los préstamos anteriores pasan a *Renovado* y se crea el nuevo, todo junto o nada.

## Estados
- **Cuota:** Pendiente · Parcial (con pagos) · Vencida (fecha pasada sin pagos) · Pagada (todo cubierto, incluida la mora).
- **Préstamo:** Activo · Saldado (pagado por completo) · Renovado (cancelado por una renovación).
