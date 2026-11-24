/* =====================================================================
   SISTEMA DE GESTIÓN DE PRÉSTAMOS  -  02_datos_base.sql
   Ruta en el proyecto: database/mysql/02_datos_base.sql (MySQL / MariaDB)
   Ejecutar DESPUÉS de 01_estructura.sql.

   Carga los catálogos que el sistema necesita para funcionar:
   usuarios iniciales, tipos de documento, nacionalidades, tipos de plazo,
   tipos de amortización y tipos de préstamo.

   Usuarios de desarrollo (cambiar antes de cualquier uso real):
       administrador  /  Admin123*
       cajero         /  Cajero123*
   ===================================================================== */
SET NAMES utf8mb4;
START TRANSACTION;

/* --- Usuarios (clave_hash generado con password_hash de PHP, bcrypt) --- */
INSERT INTO usuarios (usuario, nombre, clave_hash, rol) VALUES
 ('administrador', 'Administrador del Sistema', '$2y$10$FJhGfEl1vEEyJp2uxOuo4eQpZVEfUMw.Wg63.1CDkg6zSLp2/.QnS', 'Administrador'),
 ('cajero',        'Cajero Principal',          '$2y$10$xT6T3NHdKuzxJK.robmmnO267.RzwL56TZ24hmZcmI.uplH2vXqOW', 'Cajero');

/* --- Tipos de documento: formato único de ingreso y expresión de validación --- */
INSERT INTO tipos_documento (nombre, mascara, longitud, patron) VALUES
 ('Cédula',    '000-0000000-0', 13, '^[0-9]{3}-[0-9]{7}-[0-9]$'),
 ('RNC',       '0-00-00000-0',  12, '^[0-9]-[0-9]{2}-[0-9]{5}-[0-9]$'),
 ('Pasaporte', 'Letras y números (6 a 12)', 12, '^[A-Z0-9]{6,12}$');

/* --- Nacionalidades --- */
INSERT INTO nacionalidades (nombre) VALUES
 ('Dominicana'), ('Haitiana'), ('Venezolana'), ('Colombiana'), ('Estadounidense'),
 ('Española'), ('Italiana'), ('Cubana'), ('Puertorriqueña'), ('Mexicana');

/* --- Tipos de plazo ---
   dias_periodo : se usa en i = tasa * dias_periodo / 30
   meses_periodo: si es mayor que 0 las fechas avanzan por meses de calendario
   permite_excluir_fines_semana: solo el plazo Diario
   plazo_minimo / plazo_maximo: rango de cuotas permitido con ese plazo (se cambia con UPDATE)  */
INSERT INTO tipos_plazo (nombre, dias_periodo, meses_periodo, plazo_minimo, plazo_maximo, permite_excluir_fines_semana) VALUES
 ('Diario',       1,   0,  15, 365, 1),
 ('Interdiario',  2,   0,  10, 180, 0),
 ('Semanal',      7,   0,  4, 156, 0),
 ('Intersemanal', 14,  0,  2, 130, 0),
 ('Quincenal',    15,  0,  2, 120, 0),
 ('Mensual',      30,  1,  1, 120, 0),
 ('Trimestral',   90,  3,  1, 40, 0),
 ('Semestral',    180, 6,  1, 20, 0),
 ('Anual',        360, 12, 1, 15, 0);

/* --- Tipos de amortización --- */
INSERT INTO tipos_amortizacion (codigo, nombre) VALUES
 ('ABSOLUTO', 'Saldo absoluto'),
 ('INSOLUTO', 'Saldo insoluto'),
 ('FIJO',     'Saldo fijo');

/* --- Tipos de préstamo --- */
INSERT INTO tipos_prestamo (nombre, descripcion, estado) VALUES
 ('Personal',    'Préstamo para gastos personales',        'Activo'),
 ('Vehículo',    'Compra o reparación de vehículos',       'Activo'),
 ('Comercial',   'Capital de trabajo para negocios',       'Activo'),
 ('Educativo',   'Estudios y capacitación',                'Inactivo');

COMMIT;
