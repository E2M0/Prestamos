# Registro de avance del proyecto

Estados: **Probado** = verificado por ejecución · **Entregado** = código entregado, falta tu prueba · **Pendiente**.

## Etapas

| # | Etapa | Estado |
|---|---|---|
| 1 | Análisis, arquitectura y diseño de BD | Terminada |
| 2 | Scripts SQL (estructura, datos base, datos de prueba) | Probado (14 personas, 11 clientes, 9 préstamos, 72 cuotas, 35 recibos) |
| 3 | Entorno y comunicación React ↔ PHP ↔ SQL Server | Probado |
| 4 | Layout, login, rutas, dashboard, componentes | Probado (dashboard cuadra con los datos de prueba) |
| 5 | Clientes y garantes | Probado |
| 6 | Tipos de préstamo y formulario de apertura | Entregado (probada solo en parte por el usuario) |
| 7 | Cálculos de amortización, tabla virtual y guardado del préstamo | Probado |
| 8 | Recibos de ingreso | Probado (correo sin probar: falta configurar SMTP) |
| 9 | Renovación de préstamos | Entregado (falta tu prueba) |
| 10 | Pruebas, documentación y presentación | Entregado (falta tu revisión) |

## Funcionalidades

| Funcionalidad | Estado |
|---|---|
| Conexión a SQL Server con PDO_SQLSRV y Autenticación de Windows | Probado |
| API REST con router, controladores, modelos, servicios, CORS y errores controlados | Probado |
| Login / logout con sesión (cookie HttpOnly), rutas protegidas | Probado |
| Sidebar, encabezado con perfil, diseño responsive | Probado |
| Dashboard con 6 indicadores, 3 gráficos y actividad reciente (consultas reales) | Probado |
| Componentes base (Boton, Etiqueta, Alerta, Campo, Cargando, EstadoVacio...) | Probado |
| Modal, ModalConsulta (buscador + lista), Tabla, ConfirmDialog, CampoSelect, SeccionForm | Probado |
| Clientes: registrar, modificar (modal de consulta), activar / inactivar | Probado |
| Teléfonos, datos laborales, familiares y referencias (estas dos últimas opcionales) | Probado |
| Garante: búsqueda por documento, carga automática, alta nueva, edición | Probado |
| Búsqueda inversa (cliente que ya es garante) sin duplicar la persona | Probado |
| Validación de documentos con expresiones regulares (React y PHP) | Probado |
| Tipos de préstamo: registrar, modificar (modal de consulta), activar / inactivar | Entregado |
| Apertura: selección de cliente, límite disponible, garante, condiciones, mora, seguro, gasto de cierre | Entregado |
| Apertura: fecha sugerida del primer pago calculada en PHP (CalendarioService) | Entregado |
| Apertura: crear tipo de préstamo desde un modal sin salir de la pantalla | Entregado |
| Motor de amortización en PHP (absoluto, insoluto, fijo; seguro; gasto de cierre financiado) | Entregado (probado contra una referencia exacta: 4.009 casos sin diferencias) |
| Apertura: Calcular (servidor), tabla virtual, desembolso y fecha del primer pago | Entregado |
| Apertura: Guardar en transacción (cabecera + cuotas, número por secuencia, límite bloqueado) | Entregado (falta tu prueba con SQL Server) |
| Motor de pagos en PHP (normal, solo mora, abono a capital; FIJO genera cuotas) | Entregado (probado con ≈57.000 pagos simulados: invariantes sin errores; 300 préstamos saldados) |
| Mora una vez por cuota, guardada y sincronizada (consulta, cobro y dashboard) | Entregado (lógica probada; SQL Server sin probar) |
| Recibos: consulta de préstamo, vista previa, cobro en transacción, historial | Entregado (falta tu prueba) |
| Recibo imprimible A4 / POS 80 mm, compartir, copiar resumen | Entregado (falta tu prueba en navegador) |
| Envío del recibo por correo (PHPMailer + SMTP) | Entregado, **no probado** (requiere configurar SMTP) |
| Renovación: selección de 1 a 3 préstamos del cliente y base de saldo (capital / a la fecha / general) | Entregado (falta tu prueba) |
| Renovación: saldos, descuento y desembolso final calculados en PHP | Entregado (cálculo probado con 7.200 planes simulados: sin errores; SQL Server sin probar) |
| Renovación: recibos de cancelación, préstamos anteriores «Renovado» y préstamo nuevo en una transacción | Entregado (falta tu prueba con SQL Server) |
| Pruebas automatizadas del cálculo (`php tests/ejecutar.php`: 301.906 verificaciones) | Probado (pasan; detectan un error introducido a propósito) |
| Auditoría de código (consola, credenciales, SQL preparado, funciones no disponibles) | Probado |
| Documentación: README, manual técnico, manual de usuario, plan de pruebas, guía de defensa | Entregado (falta tu revisión) |
| Presentación para la defensa | Entregado (falta tu revisión) |
| Versión MySQL para publicar (scripts, backend adaptado, `publicar.ps1`, guía de hosting) | Entregado, **sin probar contra un MySQL real** |
| Permisos por rol (Administrador / Cajero) en API y menú | Entregado (falta tu prueba con ambos usuarios) |
| Rango de cuotas por tipo de plazo (`plazo_minimo` / `plazo_maximo`) | Entregado (probado en validación automática; falta tu prueba en pantalla) |
| Reglas de negocio (`reglas_negocio`): tasa, monto mínimo, mora, seguro, gasto de cierre e interés total | Entregado (probado en validación automática; falta tu prueba en pantalla y ejecutar `05_reglas_negocio.sql`) |
