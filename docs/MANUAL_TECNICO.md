# Manual técnico

## 1. Arquitectura

```
Navegador (React) ──/api──► Vite (proxy) ──► PHP (router) ──► Controlador ──► Servicio ──► Modelo ──► SQL Server
```

- **Front controller:** `backend/public/index.php` carga la configuración, el autoload simple (clases por carpeta), el
  manejador de errores y las rutas, y despacha la petición.
- **Router propio** (`helpers/Router.php`): rutas con parámetros `{id}`; método + ruta → `[Controlador, método]`.
- **Controlador:** valida sesión (`Sesion::requerir()`), lee la petición (`Peticion`) y responde (`Respuesta::ok/error`). No tiene lógica de negocio.
- **Servicio:** reglas de negocio, validaciones y cálculos. Es el único lugar donde se calcula dinero.
- **Modelo:** SQL con consultas preparadas (PDO). No decide reglas.
- **Errores:** `ErrorApi(mensaje, estado, errores)` produce una respuesta JSON uniforme; cualquier otra excepción se
  registra en `backend/logs` y el usuario recibe un mensaje genérico (no se filtran detalles internos).
- **Sesión:** cookie `HttpOnly` y `SameSite=Lax`; los datos del usuario viven en el servidor; `session_regenerate_id` al iniciar sesión.
- **CORS:** orígenes permitidos en `config.php`. En desarrollo el proxy de Vite evita depender de CORS.

## 2. Modelo de datos (SQL Server)

| Módulo | Tablas |
|---|---|
| Seguridad | `usuarios` |
| Catálogos | `tipos_documento`, `nacionalidades`, `tipos_prestamo`, `tipos_plazo`, `tipos_amortizacion` |
| Clientes | `clientes` (toda persona registrada: `es_cliente = 1` puede recibir préstamos; `0` es solo garante), `clientes_telefonos`, `clientes_familiares`, `clientes_laborales`, `clientes_referencias`, `clientes_garantes` |
| Préstamos | `prestamos`, `prestamos_cuotas` |
| Recibos | `recibos_ingreso`, `recibos_ingreso_detalle` |
| Renovación | `renovaciones`, `renovaciones_detalle` |

Integridad: llaves primarias y foráneas, `UNIQUE` en documentos y números (`PR-000001`, `RC-000001` salen de las secuencias
`seq_prestamo` y `seq_recibo`), y restricciones `CHECK` en estados, importes no negativos y coherencia (por ejemplo,
`valor_cuota = capital + interés + seguro + gasto`, `monto_recibido = suma de sus componentes`, lo pagado de cada
componente ≤ su total). Las tablas de movimiento guardan usuario y fecha de creación (auditoría básica).

## 3. Reglas financieras (todas en PHP)

| Servicio | Responsabilidad |
|---|---|
| `AmortizacionService` | Cuotas de los tres tipos (absoluto, insoluto, saldo fijo), seguro y gasto de cierre financiado |
| `CalendarioService` | Fechas de las cuotas: meses de calendario, días, y exclusión de fines de semana (plazo diario) |
| `MoraService` | Mora una sola vez por cuota vencida, estado de cada cuota, sincronización con la base de datos |
| `PagoService` | Planificador de pagos (normal, solo mora, abono a capital); vista previa y cobro usan el mismo cálculo |
| `CancelacionService` | Saldos de cancelación por renovación (tres bases) y descuento |
| `RenovacionService` | Orquesta cancelaciones + préstamo nuevo |

Fórmulas (M = monto, n = cuotas, i = tasa mensual/100 × días del período/30):
- **Absoluto:** capital = M/n; interés = M·i.
- **Insoluto:** cuota = M·i / (1 − (1+i)^−n); interés = balance·i; capital = cuota − interés.
- **Saldo fijo:** capital = balance / cuotas restantes; interés = balance·i; solo se genera la primera cuota y cada siguiente nace al saldarse la anterior.

Todo importe se redondea a 2 decimales y el último ajuste cae en la última cuota. Los pagos se calculan en **centavos enteros**
para que el redondeo de la coma flotante nunca altere un total.

Las decisiones que el enunciado no fija están en `docs/SUPUESTOS.md`.

## 4. Transacciones

`Database::transaccion(callable)` hace `beginTransaction`, `commit` y `rollback` ante cualquier excepción.
Se usa en las tres operaciones multi-tabla:

| Operación | Qué escribe, todo o nada |
|---|---|
| Guardar préstamo | número por secuencia + cabecera + cuotas (con el cliente bloqueado para validar el límite de crédito) |
| Aplicar recibo | préstamo bloqueado → cuotas modificadas/eliminadas/generadas → recibo + detalle → capital pendiente y estado |
| Renovación | clientes y préstamos bloqueados → por cada préstamo anterior: cuotas, recibo y estado «Renovado» → préstamo nuevo con sus cuotas → renovación y detalle |

Los bloqueos (`UPDLOCK, HOLDLOCK`) evitan que dos usuarios cobren el mismo préstamo a la vez; los préstamos se bloquean siempre
en orden de id para evitar bloqueos cruzados.

## 5. API

Todas las rutas (salvo `ping`, `salud` y `login`) requieren sesión. Respuesta: `{ "ok": true, "datos": ... }` o `{ "ok": false, "mensaje": ..., "errores": { campo: texto } }`.

| Método y ruta | Función |
|---|---|
| `GET /api/ping`, `/api/salud` | API viva / conexión a SQL Server |
| `POST /api/auth/login`, `/logout`; `GET /api/auth/yo` | Sesión |
| `GET /api/dashboard` | Indicadores, gráficos y actividad reciente |
| `GET /api/clientes`, `/clientes/{id}`; `POST`, `PUT /api/clientes/{id}`; `PATCH …/estado` | Clientes y garantes |
| `GET /api/personas/buscar` | Búsqueda de persona por documento (garante) |
| `GET/POST/PUT/PATCH /api/tipos-prestamo…` | Tipos de préstamo |
| `GET /api/catalogos/clientes`, `/catalogos/prestamos` | Catálogos de formularios |
| `GET /api/clientes/{id}/credito` | Límite, capital pendiente, disponible y garante |
| `GET /api/prestamos/fecha-primer-pago` | Fecha sugerida del primer pago |
| `POST /api/prestamos/calcular`, `POST /api/prestamos` | Tabla virtual / guardar préstamo |
| `GET /api/prestamos`, `/prestamos/{id}/cuenta` | Consulta para cobrar |
| `POST /api/recibos/calcular`, `POST /api/recibos` | Vista previa / cobrar |
| `GET /api/recibos/{id}`; `PATCH …/formato`; `POST …/correo` | Recibo, formato de impresión, envío por correo |
| `GET /api/clientes/{id}/prestamos-renovables` | Préstamos activos con sus tres saldos |
| `POST /api/renovaciones/calcular`, `POST /api/renovaciones` | Renovación |

## 6. Frontend

- `pages/` una pantalla por módulo; `components/` piezas reutilizables (`Modal`, `ModalConsulta`, `Tabla`, `Campo`, `ConfirmDialog`, `Alerta`…).
- `ModalConsulta` implementa la convención de «modificar»: buscador + lista; doble clic o botón Seleccionar.
- `services/` encapsula las llamadas Axios; `utils/validaciones*.js` repiten las validaciones del servidor con las mismas claves de error.
- La renovación reutiliza el formulario de apertura (`Prestamos.jsx` con `modo="renovacion"`).
- Impresión: el recibo se monta en una zona aparte y el CSS de impresión oculta el resto; `@page` cambia entre A4 y 80 mm.
- No se escribe nada sensible en la consola; no hay credenciales en el frontend.

## 7. Seguridad

Consultas preparadas en todo el backend; contraseñas con `password_hash`; cookie `HttpOnly`; `config.php` fuera de Git; errores
internos solo en el log; validación del lado del servidor aunque ya exista en React. Pendiente al publicar: HTTPS y cookie
`secure`, contraseñas nuevas y orígenes CORS reales.

## 8. Pruebas

Ver `docs/PLAN_DE_PRUEBAS.md`. Las pruebas automatizadas están en `tests/` (`php tests/ejecutar.php`).
