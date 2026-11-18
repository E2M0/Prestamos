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
| 7 | Cálculos de amortización, tabla virtual y guardado del préstamo | Entregado |
| 8 | Recibos de ingreso | Pendiente |
| 9 | Renovación de préstamos | Pendiente |
| 10 | Pruebas, documentación y presentación | Pendiente |

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
| Teléfonos, datos laborales, familiares y 3 referencias | Probado |
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
| Recibos y renovaciones | Pendiente |
