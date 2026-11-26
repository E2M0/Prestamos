/* =====================================================================
   SISTEMA DE GESTIÓN DE PRÉSTAMOS  -  02_datos_base.sql
   Ruta en el proyecto: database/02_datos_base.sql
   Ejecutar DESPUÉS de 01_estructura.sql.

   Carga los catálogos que el sistema necesita para funcionar:
   usuarios iniciales, tipos de documento, nacionalidades, tipos de plazo,
   tipos de amortización y tipos de préstamo.

   Usuarios de desarrollo (cambiar antes de cualquier uso real):
       administrador  /  Admin123*
       cajero         /  Cajero123*
   ===================================================================== */
USE prestamos_db;
GO
SET NOCOUNT ON;
SET QUOTED_IDENTIFIER ON;
SET XACT_ABORT ON;
GO

BEGIN TRANSACTION;

/* --- Usuarios (clave_hash generado con password_hash de PHP, bcrypt) --- */
INSERT INTO usuarios (usuario, nombre, clave_hash, rol) VALUES
 (N'administrador', N'Administrador del Sistema', N'$2y$10$FJhGfEl1vEEyJp2uxOuo4eQpZVEfUMw.Wg63.1CDkg6zSLp2/.QnS', N'Administrador'),
 (N'cajero',        N'Cajero Principal',          N'$2y$10$xT6T3NHdKuzxJK.robmmnO267.RzwL56TZ24hmZcmI.uplH2vXqOW', N'Cajero');

/* --- Tipos de documento: formato único de ingreso y expresión de validación --- */
INSERT INTO tipos_documento (nombre, mascara, longitud, patron) VALUES
 (N'Cédula',    N'000-0000000-0', 13, N'^[0-9]{3}-[0-9]{7}-[0-9]$'),
 (N'RNC',       N'0-00-00000-0',  12, N'^[0-9]-[0-9]{2}-[0-9]{5}-[0-9]$'),
 (N'Pasaporte', N'Letras y números (6 a 12)', 12, N'^[A-Z0-9]{6,12}$');

/* --- Nacionalidades --- */
INSERT INTO nacionalidades (nombre) VALUES
 (N'Dominicana'), (N'Haitiana'), (N'Venezolana'), (N'Colombiana'), (N'Estadounidense'),
 (N'Española'), (N'Italiana'), (N'Cubana'), (N'Puertorriqueña'), (N'Mexicana');

/* --- Tipos de plazo ---
   dias_periodo : se usa en i = tasa * dias_periodo / 30
   meses_periodo: si es mayor que 0 las fechas avanzan por meses de calendario
   permite_excluir_fines_semana: solo el plazo Diario
   plazo_minimo / plazo_maximo: rango de cuotas permitido con ese plazo (se cambia con UPDATE)  */
INSERT INTO tipos_plazo (nombre, dias_periodo, meses_periodo, plazo_minimo, plazo_maximo, permite_excluir_fines_semana) VALUES
 (N'Diario',       1,   0,  15, 365, 1),
 (N'Interdiario',  2,   0,  10, 180, 0),
 (N'Semanal',      7,   0,  4, 156, 0),
 (N'Intersemanal', 14,  0,  2, 130, 0),
 (N'Quincenal',    15,  0,  2, 120, 0),
 (N'Mensual',      30,  1,  1, 120, 0),
 (N'Trimestral',   90,  3,  1, 40, 0),
 (N'Semestral',    180, 6,  1, 20, 0),
 (N'Anual',        360, 12, 1, 15, 0);

/* --- Tipos de amortización --- */
INSERT INTO tipos_amortizacion (codigo, nombre) VALUES
 (N'ABSOLUTO', N'Saldo absoluto'),
 (N'INSOLUTO', N'Saldo insoluto'),
 (N'FIJO',     N'Saldo fijo');

/* --- Tipos de préstamo --- */
INSERT INTO tipos_prestamo (nombre, descripcion, estado) VALUES
 (N'Personal',    N'Préstamo para gastos personales',        N'Activo'),
 (N'Vehículo',    N'Compra o reparación de vehículos',       N'Activo'),
 (N'Comercial',   N'Capital de trabajo para negocios',       N'Activo'),
 (N'Educativo',   N'Estudios y capacitación',                N'Inactivo');

COMMIT TRANSACTION;

PRINT 'Datos base cargados correctamente.';
GO
