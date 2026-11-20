# Sistema Web de Gestión de Préstamos

Proyecto académico de **Desarrollo Web**. Permite registrar clientes y garantes, aperturar préstamos con su tabla de
amortización, cobrar con recibos de ingreso (imprimibles, compartibles y por correo) y renovar préstamos.


## Tecnología

| Capa | Herramientas |
|---|---|
| Frontend | React + Vite (JavaScript), React Router DOM, Axios, Lucide React |
| Backend | PHP 8.3 puro (sin framework ni Composer), API REST, PDO_SQLSRV |
| Base de datos | Microsoft SQL Server |
| Herramientas | Git / GitHub, VS Code |

Reglas de diseño: los cálculos financieros se hacen **solo en PHP** (React únicamente los muestra); toda operación que
toca varias tablas es una **transacción**; todas las consultas son **preparadas**; las validaciones existen en React y en PHP;
los maestros (clientes, préstamos, tipos) **no se eliminan**, cambian de estado.

## Requisitos

- Windows con **PHP 8.3** (extensiones `sqlsrv` y `pdo_sqlsrv` activas) y **ODBC Driver 18 for SQL Server**
- **SQL Server** (por ejemplo, SQL Server Developer/Express)
- **Node.js** 20 o superior y **Git**

## Instalación

1. **Base de datos.** En SQL Server Management Studio ejecute, en este orden, los scripts de la carpeta `database/`:
   1. `01_estructura.sql` (crea la base `prestamos_db`, tablas y restricciones)
   2. `02_datos_base.sql` (catálogos y los dos usuarios)
   3. `03_datos_prueba.sql` (opcional: clientes, préstamos y recibos de demostración)
2. **Configuración del backend.** Copie `backend/src/config/config.example.php` como `backend/src/config/config.php` y ajuste
   el nombre del servidor (`db.servidor`). `config.php` no se sube a Git.
3. **Backend.** Desde la carpeta `backend`:
   ```powershell
   php -S localhost:8000 -t public public/index.php
   ```
4. **Frontend.** Desde la carpeta `frontend`:
   ```powershell
   npm install
   npm run dev
   ```
   Abra http://localhost:5173. Vite envía todo lo que empiece por `/api` al backend (puerto 8000).

### Usuarios de prueba

| Usuario | Contraseña | Rol |
|---|---|---|
| `administrador` | `Admin123*` | Administrador |
| `cajero` | `Cajero123*` | Cajero |

Cámbielas antes de usar el sistema fuera de un entorno de prueba.

### Correo (opcional)

El envío de recibos por correo usa PHPMailer, que se descarga manualmente (no se usa Composer): copie los archivos de
`src` de PHPMailer en `backend/lib/PHPMailer/src` y complete el bloque `correo` de `config.php` (servidor SMTP, usuario y
contraseña de aplicación). Sin esa configuración, el botón de correo responde que no está disponible.

## Pruebas automatizadas

Cálculo de amortizaciones, pagos y cancelaciones (no necesitan SQL Server ni navegador):

```powershell
php tests/ejecutar.php
```

Termina con código 0 si todo pasa. Ver `docs/PLAN_DE_PRUEBAS.md`.

## Estructura

```
backend/
  public/index.php        punto de entrada (front controller)
  src/config/             configuración, conexión PDO, CORS
  src/helpers/            router, respuestas JSON, errores, sesión
  src/routes/api.php      tabla de rutas
  src/controllers/        reciben la petición y responden
  src/services/           reglas de negocio y cálculos
  src/models/             consultas SQL preparadas
database/                 scripts SQL (estructura, datos base, datos de prueba)
frontend/src/
  pages/ components/      pantallas y piezas de interfaz
  services/               llamadas a la API (Axios)
  utils/ hooks/ context/  validaciones, formato, sesión
tests/                    pruebas automatizadas del cálculo financiero
docs/                     documentación (ver abajo)
```

## Documentación

| Documento | Contenido |
|---|---|
| [`docs/MANUAL_USUARIO.md`](docs/MANUAL_USUARIO.md) | Cómo usar cada módulo |
| [`docs/MANUAL_TECNICO.md`](docs/MANUAL_TECNICO.md) | Arquitectura, modelo de datos, API, transacciones, seguridad |
| [`docs/SUPUESTOS.md`](docs/SUPUESTOS.md) | Reglas no especificadas en el enunciado y decisiones tomadas |
| [`docs/PLAN_DE_PRUEBAS.md`](docs/PLAN_DE_PRUEBAS.md) | Pruebas automatizadas y lista de pruebas manuales |
| [`docs/GUIA_DEFENSA.md`](docs/GUIA_DEFENSA.md) | Preguntas probables y cómo responderlas |
| [`docs/REGISTRO.md`](docs/REGISTRO.md) | Estado de cada etapa y funcionalidad |

## Limitaciones conocidas

- El envío de correo requiere configurar SMTP y no está probado con un servidor real.
- No hay pantalla para anular recibos (el estado «Anulado» existe en la base de datos).
- La cookie de sesión no está marcada como `secure`: al publicar con HTTPS debe activarse (`Sesion.php`).
- En saldo fijo solo existen las cuotas ya generadas; los intereses futuros no generados no entran en el «balance general».
