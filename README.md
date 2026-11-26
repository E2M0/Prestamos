# Sistema Web de Gestión de Préstamos

Proyecto académico de **Desarrollo Web**. Permite registrar clientes y garantes, aperturar préstamos con su tabla de
amortización, cobrar con recibos de ingreso (imprimibles, compartibles y por correo) y renovar préstamos.

Marca del sistema: **ALTÉZZA — Créditos & Préstamos** (logo, nombre y lema configurables; ver «Marca y empresa»).

**Integrantes:** _(completar)_

## Ramas del repositorio

| Rama | Base de datos | Uso |
|---|---|---|
| `main` | Microsoft SQL Server | Desarrollo local |
| `Host` | MySQL / MariaDB | Versión publicada en un hosting gratuito (InfinityFree) |

Las dos comparten el mismo frontend y casi todo el backend; cambian los modelos, la conexión (`Database.php`) y los scripts de
`database/mysql/`. Los cambios nuevos se hacen en `main` y se pasan a `Host` con `git merge main`.

## Tecnología

| Capa | Herramientas |
|---|---|
| Frontend | React + Vite (JavaScript), React Router DOM, Axios, Lucide React |
| Backend | PHP 8.3 puro (sin framework ni Composer), API REST, PDO (SQL Server en `main`, MySQL en `Host`) |
| Base de datos | Microsoft SQL Server (local) y MySQL / MariaDB (publicado) |
| Correo | PHPMailer (copiado a mano, sin Composer) con SMTP de Gmail |
| Herramientas | Git / GitHub, VS Code |

Reglas de diseño: los cálculos financieros se hacen **solo en PHP** (React únicamente los muestra); toda operación que
toca varias tablas es una **transacción**; todas las consultas son **preparadas**; las validaciones existen en React y en PHP;
los maestros (clientes, préstamos, tipos) **no se eliminan**, cambian de estado.

## Funciones principales

- **Clientes y garantes:** formulario por pestañas (general, contacto, laboral, referencias y garante), búsqueda de personas y garante vigente.
  Las referencias personales son **opcionales** (de 0 a 3).
- **Crédito del cliente:** el límite va de RD$ 5,000 a RD$ 10,000,000 y la clasificación debe corresponder al límite
  (C: hasta 100,000 · B: hasta 1,000,000 · A: hasta 10,000,000). Se valida en React, en PHP y con restricciones en la base.
- **Préstamos:** tipos de plazo con rango de cuotas, amortización absoluta, insoluta y de saldo fijo, mora, seguro y gasto de cierre.
  Se verifica que el capital pendiente más el préstamo nuevo no supere el límite del cliente.
- **Reglas de negocio** (tabla `reglas_negocio`, editables con `UPDATE`): tasa mensual, monto mínimo, mora, seguro, gasto de cierre e
  interés total máximos. Si una regla no existe, no hay límite.
- **Recibos de ingreso:** pago normal, abono a capital (reducir cuota o plazo) y solo mora; impresión en **A4 o POS 80 mm** con el
  diseño de la empresa, y envío **por correo** con el recibo en HTML.
- **Renovaciones** de préstamos (solo Administrador).
- **Roles:** el *Cajero* opera el día a día; el *Administrador* además define tipos de préstamo y autoriza renovaciones.
  Se exige en la API (403) y se oculta en el menú.

## Requisitos

- Windows con **PHP 8.3** (extensiones `sqlsrv` y `pdo_sqlsrv` activas) y **ODBC Driver 18 for SQL Server**
  (en la rama `Host`: `pdo_mysql` y un servidor MySQL / MariaDB)
- **SQL Server** (por ejemplo, SQL Server Developer/Express)
- **Node.js** 20 o superior y **Git**

## Instalación

1. **Base de datos.** En SQL Server Management Studio ejecute, en este orden, los scripts de la carpeta `database/`:
   1. `01_estructura.sql` (crea la base `prestamos_db`, tablas y restricciones)
   2. `02_datos_base.sql` (catálogos y los dos usuarios)
   3. `03_datos_prueba.sql` (opcional: clientes, préstamos y recibos de demostración)

   Si ya tiene la base creada, no vuelva a ejecutar `01_estructura.sql` (borra las tablas). Aplique solo las migraciones que falten,
   en orden: `04_rango_cuotas_por_plazo.sql`, `05_reglas_negocio.sql` y `06_limites_credito_clientes.sql`.
   Para MySQL / MariaDB use los mismos scripts de `database/mysql/` (en phpMyAdmin).
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
contraseña de aplicación de Gmail). Sin esa configuración, el botón de correo responde que no está disponible.
El correo lleva el diseño de la empresa con el logo incrustado; Gmail puede ocultar las imágenes si el mensaje cae en spam
hasta que se marque como «no es spam».

### Marca y empresa

El logo, el nombre y el lema se muestran en el inicio de sesión, el menú y los recibos. El nombre y el lema se leen de
`GET /api/empresa` (configuración del backend). Las imágenes están en `frontend/public/`; si no se encuentran, se muestra el texto.

## Pruebas automatizadas

Cálculo de amortizaciones, pagos y cancelaciones, límites de cuotas, reglas de negocio, referencias opcionales y reglas de crédito
(no necesitan base de datos ni navegador):

```powershell
php tests/ejecutar.php
```

Termina con código 0 si todo pasa. Ver `docs/PLAN_DE_PRUEBAS.md`.

## Publicación en hosting (rama `Host`)

Se publica con `publicar.ps1`, que arma la carpeta `publicar\htdocs` (frontend compilado, backend en `app/` y la configuración
generada desde `despliegue/config.hosting.php`, que no se sube a Git). Después se copia el contenido de `htdocs` al servidor por FTP.
Las migraciones SQL se ejecutan antes en phpMyAdmin. `GET /api/salud` debe responder `"baseDatos": "conectada"`.
Pasos detallados en [`docs/PUBLICAR_EN_HOSTING.md`](docs/PUBLICAR_EN_HOSTING.md).

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
database/                 scripts SQL: estructura, datos base, datos de prueba y migraciones (mysql/ para MySQL)
despliegue/               plantillas de configuración y .htaccess del hosting
publicar.ps1              arma la carpeta para subir al hosting
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

- No hay pantalla para gestionar usuarios (crear, cambiar clave o desactivar): los dos usuarios vienen del script de datos base.
- No hay pantalla para anular recibos (el estado «Anulado» existe en la base de datos).
- La cookie de sesión no está marcada como `secure`: al publicar con HTTPS debe activarse (`Sesion.php`).
- Los CHECK de la base se probaron en SQL Server y se verificó que MariaDB los guarda; en MySQL anterior a 8.0.16 se ignoran y queda
  la validación de PHP.
- Si se cargan datos de prueba en MySQL, hay que dejar el contador de `secuencias` en el mayor número usado; si no, al crear un
  préstamo o recibo falla con «Duplicate entry».
- En saldo fijo solo existen las cuotas ya generadas; los intereses futuros no generados no entran en el «balance general».
