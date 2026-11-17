# Registro de avance del proyecto

Estados: **Probado** = verificado por ejecución · **Entregado** = código entregado, falta tu prueba · **Pendiente**.

## Etapas

| # | Etapa | Estado |
|---|---|---|
| 1 | Análisis, arquitectura y diseño de BD | Terminada |
| 2 | Scripts SQL (estructura, datos base, datos de prueba) | Probado (14 personas, 11 clientes, 9 préstamos, 72 cuotas, 35 recibos) |
| 3 | Entorno y comunicación React ↔ PHP ↔ SQL Server | Probado |
| 4 | Layout, login, rutas, dashboard, componentes | Probado (dashboard cuadra con los datos de prueba) |
| 5 | Clientes y garantes | Entregado |
| 6 | Tipos de préstamo y apertura | Pendiente |
| 7 | Cálculos de amortización | Pendiente |
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
| Modal, ModalConsulta (buscador + lista), Tabla, ConfirmDialog, CampoSelect, SeccionForm | Entregado |
| Clientes: registrar, modificar (modal de consulta), activar / inactivar | Entregado |
| Teléfonos, datos laborales, familiares y 3 referencias | Entregado |
| Garante: búsqueda por documento, carga automática, alta nueva, edición | Entregado |
| Búsqueda inversa (cliente que ya es garante) sin duplicar la persona | Entregado |
| Validación de documentos con expresiones regulares (React y PHP) | Entregado |
| Tipos de préstamo, apertura, recibos y renovaciones | Pendiente |
