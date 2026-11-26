/* =====================================================================
   SISTEMA DE GESTIÓN DE PRÉSTAMOS  -  01_estructura.sql
   Motor: Microsoft SQL Server 2016 o superior
   Ruta en el proyecto: database/01_estructura.sql

   Qué hace:
     1. Crea la base de datos prestamos_db (si no existe).
     2. Borra las tablas del proyecto si ya existían (reinicio limpio).
     3. Crea tablas, llaves, restricciones, índices y secuencias.

   Orden de ejecución:  01_estructura -> 02_datos_base -> 03_datos_prueba
   ADVERTENCIA: este script BORRA y recrea las tablas del proyecto.
   ===================================================================== */
SET NOCOUNT ON;
SET QUOTED_IDENTIFIER ON;
GO

IF DB_ID(N'prestamos_db') IS NULL
    CREATE DATABASE prestamos_db COLLATE Modern_Spanish_CI_AS;
GO

USE prestamos_db;
GO

/* ---------------------------------------------------------------------
   0. Reinicio limpio (orden inverso a las dependencias)
   --------------------------------------------------------------------- */
DROP TABLE IF EXISTS renovaciones_detalle;
DROP TABLE IF EXISTS renovaciones;
DROP TABLE IF EXISTS recibos_ingreso_detalle;
DROP TABLE IF EXISTS recibos_ingreso;
DROP TABLE IF EXISTS prestamos_cuotas;
DROP TABLE IF EXISTS prestamos;
DROP TABLE IF EXISTS tipos_amortizacion;
DROP TABLE IF EXISTS tipos_plazo;
DROP TABLE IF EXISTS tipos_prestamo;
DROP TABLE IF EXISTS clientes_referencias;
DROP TABLE IF EXISTS clientes_laborales;
DROP TABLE IF EXISTS clientes_familiares;
DROP TABLE IF EXISTS clientes_garantes;
DROP TABLE IF EXISTS clientes_telefonos;
DROP TABLE IF EXISTS clientes;
DROP TABLE IF EXISTS nacionalidades;
DROP TABLE IF EXISTS tipos_documento;
DROP TABLE IF EXISTS usuarios;
DROP SEQUENCE IF EXISTS seq_prestamo;
DROP SEQUENCE IF EXISTS seq_recibo;
GO

/* ---------------------------------------------------------------------
   1. Secuencias para numerar préstamos y recibos sin duplicados.
      El backend hace: SELECT NEXT VALUE FOR seq_prestamo
      y le da formato (PR-000001, RC-000001).
   --------------------------------------------------------------------- */
CREATE SEQUENCE seq_prestamo AS INT START WITH 1 INCREMENT BY 1;
CREATE SEQUENCE seq_recibo   AS INT START WITH 1 INCREMENT BY 1;
GO

/* ---------------------------------------------------------------------
   2. Seguridad y auditoría
   --------------------------------------------------------------------- */
CREATE TABLE usuarios (
    id              INT IDENTITY(1,1) NOT NULL,
    usuario         NVARCHAR(50)  NOT NULL,
    nombre          NVARCHAR(100) NOT NULL,
    clave_hash      NVARCHAR(255) NOT NULL,          -- password_hash() de PHP
    rol             NVARCHAR(20)  NOT NULL,
    estado          NVARCHAR(10)  NOT NULL CONSTRAINT df_usuarios_estado DEFAULT N'Activo',
    fecha_creacion  DATETIME2(0)  NOT NULL CONSTRAINT df_usuarios_fecha  DEFAULT SYSDATETIME(),
    CONSTRAINT pk_usuarios         PRIMARY KEY (id),
    CONSTRAINT uq_usuarios_usuario UNIQUE (usuario),
    CONSTRAINT ck_usuarios_rol     CHECK (rol    IN (N'Administrador', N'Cajero')),
    CONSTRAINT ck_usuarios_estado  CHECK (estado IN (N'Activo', N'Inactivo'))
);
GO

/* ---------------------------------------------------------------------
   3. Catálogos del módulo de clientes
   --------------------------------------------------------------------- */
CREATE TABLE tipos_documento (
    id        INT IDENTITY(1,1) NOT NULL,
    nombre    NVARCHAR(30)  NOT NULL,
    mascara   NVARCHAR(30)  NOT NULL,                -- formato que ve el usuario
    longitud  TINYINT       NOT NULL,                -- longitud máxima del texto
    patron    NVARCHAR(100) NOT NULL,                -- expresión regular (JS y PHP)
    CONSTRAINT pk_tipos_documento PRIMARY KEY (id),
    CONSTRAINT uq_tipos_documento_nombre UNIQUE (nombre)
);

CREATE TABLE nacionalidades (
    id      INT IDENTITY(1,1) NOT NULL,
    nombre  NVARCHAR(60) NOT NULL,
    CONSTRAINT pk_nacionalidades PRIMARY KEY (id),
    CONSTRAINT uq_nacionalidades_nombre UNIQUE (nombre)
);
GO

/* ---------------------------------------------------------------------
   4. clientes = registro único de PERSONAS (clientes y garantes).
      es_cliente = 1  -> la persona puede recibir préstamos.
      es_cliente = 0  -> persona registrada solo como garante.
      El rol de garante se expresa en clientes_garantes.
   --------------------------------------------------------------------- */
CREATE TABLE clientes (
    id                        INT IDENTITY(1,1) NOT NULL,
    tipo_documento_id         INT           NOT NULL,
    numero_documento          NVARCHAR(20)  NOT NULL,
    nombres                   NVARCHAR(100) NOT NULL,
    apellidos                 NVARCHAR(100) NOT NULL,
    nacionalidad_id           INT           NOT NULL,
    fecha_nacimiento          DATE          NOT NULL,
    estado_civil              NVARCHAR(15)  NOT NULL,
    correo                    NVARCHAR(150) NULL,
    direccion                 NVARCHAR(250) NOT NULL,
    referencia_direccion      NVARCHAR(250) NULL,
    es_cliente                BIT           NOT NULL CONSTRAINT df_clientes_es_cliente DEFAULT 1,
    limite_credito            DECIMAL(14,2) NOT NULL CONSTRAINT df_clientes_limite     DEFAULT 0,
    clasificacion             NVARCHAR(1)   NULL,
    estado                    NVARCHAR(10)  NOT NULL CONSTRAINT df_clientes_estado     DEFAULT N'Activo',
    usuario_creacion_id       INT           NOT NULL,
    fecha_creacion            DATETIME2(0)  NOT NULL CONSTRAINT df_clientes_fcrea      DEFAULT SYSDATETIME(),
    usuario_modificacion_id   INT           NULL,
    fecha_modificacion        DATETIME2(0)  NULL,
    CONSTRAINT pk_clientes PRIMARY KEY (id),
    CONSTRAINT uq_clientes_documento UNIQUE (numero_documento),
    CONSTRAINT fk_clientes_tipo_doc  FOREIGN KEY (tipo_documento_id) REFERENCES tipos_documento(id),
    CONSTRAINT fk_clientes_nacion    FOREIGN KEY (nacionalidad_id)   REFERENCES nacionalidades(id),
    CONSTRAINT fk_clientes_usr_crea  FOREIGN KEY (usuario_creacion_id)     REFERENCES usuarios(id),
    CONSTRAINT fk_clientes_usr_mod   FOREIGN KEY (usuario_modificacion_id) REFERENCES usuarios(id),
    CONSTRAINT ck_clientes_estado_civil CHECK (estado_civil IN
        (N'Soltero/a', N'Casado/a', N'Unión libre', N'Divorciado/a', N'Viudo/a')),
    CONSTRAINT ck_clientes_clasif  CHECK (clasificacion IS NULL OR clasificacion IN (N'A', N'B', N'C')),
    CONSTRAINT ck_clientes_estado  CHECK (estado IN (N'Activo', N'Inactivo')),
    CONSTRAINT ck_clientes_limite  CHECK (limite_credito >= 0)
);
CREATE INDEX ix_clientes_nombre ON clientes (apellidos, nombres);
GO

CREATE TABLE clientes_telefonos (
    id          INT IDENTITY(1,1) NOT NULL,
    cliente_id  INT          NOT NULL,
    tipo        NVARCHAR(10) NOT NULL,
    numero      NVARCHAR(20) NOT NULL,
    principal   BIT          NOT NULL CONSTRAINT df_telefonos_principal DEFAULT 0,
    CONSTRAINT pk_clientes_telefonos PRIMARY KEY (id),
    CONSTRAINT fk_telefonos_cliente  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT uq_telefonos_numero   UNIQUE (cliente_id, numero),
    CONSTRAINT ck_telefonos_tipo     CHECK (tipo IN (N'Celular', N'Casa', N'Trabajo'))
);
-- Un solo teléfono principal por persona
CREATE UNIQUE INDEX ux_telefonos_principal ON clientes_telefonos (cliente_id) WHERE principal = 1;
GO

CREATE TABLE clientes_garantes (
    id                   INT IDENTITY(1,1) NOT NULL,
    cliente_id           INT          NOT NULL,
    garante_id           INT          NOT NULL,        -- FK a clientes (la misma tabla de personas)
    fecha_asignacion     DATE         NOT NULL CONSTRAINT df_garantes_fecha   DEFAULT CAST(SYSDATETIME() AS DATE),
    vigente              BIT          NOT NULL CONSTRAINT df_garantes_vigente DEFAULT 1,
    usuario_creacion_id  INT          NOT NULL,
    fecha_creacion       DATETIME2(0) NOT NULL CONSTRAINT df_garantes_fcrea   DEFAULT SYSDATETIME(),
    CONSTRAINT pk_clientes_garantes PRIMARY KEY (id),
    CONSTRAINT fk_garantes_cliente  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT fk_garantes_garante  FOREIGN KEY (garante_id) REFERENCES clientes(id),
    CONSTRAINT fk_garantes_usuario  FOREIGN KEY (usuario_creacion_id) REFERENCES usuarios(id),
    CONSTRAINT ck_garantes_distinto CHECK (cliente_id <> garante_id)
);
-- Un solo garante vigente por cliente
CREATE UNIQUE INDEX ux_garantes_vigente ON clientes_garantes (cliente_id) WHERE vigente = 1;
CREATE INDEX ix_garantes_garante ON clientes_garantes (garante_id);
GO

CREATE TABLE clientes_familiares (
    id           INT IDENTITY(1,1) NOT NULL,
    cliente_id   INT           NOT NULL,
    tipo         NVARCHAR(12)  NOT NULL,
    nombre       NVARCHAR(100) NOT NULL,
    parentesco   NVARCHAR(40)  NOT NULL,
    telefono     NVARCHAR(20)  NULL,
    direccion    NVARCHAR(250) NULL,
    CONSTRAINT pk_clientes_familiares PRIMARY KEY (id),
    CONSTRAINT fk_familiares_cliente  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT uq_familiares_tipo     UNIQUE (cliente_id, tipo),
    CONSTRAINT ck_familiares_tipo     CHECK (tipo IN (N'Cercano', N'No convive'))
);

CREATE TABLE clientes_laborales (
    id                 INT IDENTITY(1,1) NOT NULL,
    cliente_id         INT           NOT NULL,
    empresa            NVARCHAR(120) NOT NULL,
    cargo_ocupacion    NVARCHAR(80)  NOT NULL,
    tiempo_laborando   NVARCHAR(40)  NOT NULL,
    sueldo             DECIMAL(14,2) NOT NULL,
    telefono_empresa   NVARCHAR(20)  NULL,
    direccion_empresa  NVARCHAR(250) NULL,
    CONSTRAINT pk_clientes_laborales PRIMARY KEY (id),
    CONSTRAINT fk_laborales_cliente  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT uq_laborales_cliente  UNIQUE (cliente_id),
    CONSTRAINT ck_laborales_sueldo   CHECK (sueldo >= 0)
);

CREATE TABLE clientes_referencias (
    id          INT IDENTITY(1,1) NOT NULL,
    cliente_id  INT           NOT NULL,
    nombre      NVARCHAR(100) NOT NULL,
    telefono    NVARCHAR(20)  NOT NULL,
    CONSTRAINT pk_clientes_referencias PRIMARY KEY (id),
    CONSTRAINT fk_referencias_cliente  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);
GO

/* ---------------------------------------------------------------------
   5. Catálogos de préstamos
   --------------------------------------------------------------------- */
CREATE TABLE tipos_prestamo (
    id           INT IDENTITY(1,1) NOT NULL,
    nombre       NVARCHAR(60)  NOT NULL,
    descripcion  NVARCHAR(250) NULL,
    estado       NVARCHAR(10)  NOT NULL CONSTRAINT df_tipos_prestamo_estado DEFAULT N'Activo',
    CONSTRAINT pk_tipos_prestamo PRIMARY KEY (id),
    CONSTRAINT uq_tipos_prestamo_nombre UNIQUE (nombre),
    CONSTRAINT ck_tipos_prestamo_estado CHECK (estado IN (N'Activo', N'Inactivo'))
);

CREATE TABLE tipos_plazo (
    id                            INT IDENTITY(1,1) NOT NULL,
    nombre                        NVARCHAR(30) NOT NULL,
    dias_periodo                  SMALLINT     NOT NULL,   -- para convertir la tasa: i = tasa * dias/30
    meses_periodo                 TINYINT      NOT NULL,   -- >0: las fechas avanzan por meses calendario
    plazo_minimo                  SMALLINT     NOT NULL CONSTRAINT df_tipos_plazo_min DEFAULT 1,
    plazo_maximo                  SMALLINT     NOT NULL,
    permite_excluir_fines_semana  BIT          NOT NULL CONSTRAINT df_tipos_plazo_excluir DEFAULT 0,
    CONSTRAINT pk_tipos_plazo PRIMARY KEY (id),
    CONSTRAINT uq_tipos_plazo_nombre UNIQUE (nombre),
    CONSTRAINT ck_tipos_plazo_dias CHECK (dias_periodo > 0),
    CONSTRAINT ck_tipos_plazo_rango CHECK (plazo_minimo >= 1 AND plazo_maximo >= plazo_minimo)
);

CREATE TABLE tipos_amortizacion (
    id      INT IDENTITY(1,1) NOT NULL,
    codigo  NVARCHAR(10) NOT NULL,
    nombre  NVARCHAR(30) NOT NULL,
    CONSTRAINT pk_tipos_amortizacion PRIMARY KEY (id),
    CONSTRAINT uq_tipos_amortizacion_codigo UNIQUE (codigo),
    CONSTRAINT ck_tipos_amortizacion_codigo CHECK (codigo IN (N'ABSOLUTO', N'INSOLUTO', N'FIJO'))
);
GO

/* ---------------------------------------------------------------------
   6. Préstamos (cabecera y cuotas)
      seguro_monto: si seguro_tipo = 'Fijo'      -> monto por cuota
                    si seguro_tipo = 'Repartido' -> monto total a repartir
   --------------------------------------------------------------------- */
CREATE TABLE prestamos (
    id                        INT IDENTITY(1,1) NOT NULL,
    numero                    NVARCHAR(20)  NOT NULL,
    cliente_id                INT           NOT NULL,
    tipo_prestamo_id          INT           NOT NULL,
    monto                     DECIMAL(14,2) NOT NULL,
    plazo                     SMALLINT      NOT NULL,
    tasa                      DECIMAL(8,4)  NOT NULL,      -- % mensual nominal
    tipo_plazo_id             INT           NOT NULL,
    tipo_amortizacion_id      INT           NOT NULL,
    incluye_sabado            BIT           NOT NULL CONSTRAINT df_prestamos_sab DEFAULT 1,
    incluye_domingo           BIT           NOT NULL CONSTRAINT df_prestamos_dom DEFAULT 1,
    tipo_mora                 NVARCHAR(10)  NOT NULL,
    valor_mora                DECIMAL(14,2) NOT NULL CONSTRAINT df_prestamos_mora DEFAULT 0,
    seguro_tipo               NVARCHAR(10)  NOT NULL,
    seguro_monto              DECIMAL(14,2) NOT NULL CONSTRAINT df_prestamos_seguro DEFAULT 0,
    gasto_cierre              DECIMAL(14,2) NOT NULL CONSTRAINT df_prestamos_gasto  DEFAULT 0,
    gasto_cierre_financiado   BIT           NOT NULL CONSTRAINT df_prestamos_gfin   DEFAULT 0,
    desembolso                DECIMAL(14,2) NOT NULL,
    fecha_apertura            DATE          NOT NULL,
    fecha_primer_pago         DATE          NOT NULL,
    capital_pendiente         DECIMAL(14,2) NOT NULL,
    estado                    NVARCHAR(10)  NOT NULL CONSTRAINT df_prestamos_estado DEFAULT N'Activo',
    usuario_creacion_id       INT           NOT NULL,
    fecha_creacion            DATETIME2(0)  NOT NULL CONSTRAINT df_prestamos_fcrea DEFAULT SYSDATETIME(),
    CONSTRAINT pk_prestamos PRIMARY KEY (id),
    CONSTRAINT uq_prestamos_numero UNIQUE (numero),
    CONSTRAINT fk_prestamos_cliente  FOREIGN KEY (cliente_id)           REFERENCES clientes(id),
    CONSTRAINT fk_prestamos_tipo     FOREIGN KEY (tipo_prestamo_id)     REFERENCES tipos_prestamo(id),
    CONSTRAINT fk_prestamos_plazo    FOREIGN KEY (tipo_plazo_id)        REFERENCES tipos_plazo(id),
    CONSTRAINT fk_prestamos_amort    FOREIGN KEY (tipo_amortizacion_id) REFERENCES tipos_amortizacion(id),
    CONSTRAINT fk_prestamos_usuario  FOREIGN KEY (usuario_creacion_id)  REFERENCES usuarios(id),
    CONSTRAINT ck_prestamos_monto    CHECK (monto > 0),
    CONSTRAINT ck_prestamos_plazo    CHECK (plazo > 0),
    CONSTRAINT ck_prestamos_tasa     CHECK (tasa >= 0),
    CONSTRAINT ck_prestamos_mora     CHECK (tipo_mora IN (N'Porcentaje', N'Monto') AND valor_mora >= 0),
    CONSTRAINT ck_prestamos_seguro   CHECK (seguro_tipo IN (N'Fijo', N'Repartido') AND seguro_monto >= 0),
    CONSTRAINT ck_prestamos_gasto    CHECK (gasto_cierre >= 0),
    CONSTRAINT ck_prestamos_desemb   CHECK (desembolso > 0),
    CONSTRAINT ck_prestamos_capital  CHECK (capital_pendiente >= 0 AND capital_pendiente <= monto),
    CONSTRAINT ck_prestamos_fechas   CHECK (fecha_primer_pago >= fecha_apertura),
    CONSTRAINT ck_prestamos_estado   CHECK (estado IN (N'Activo', N'Saldado', N'Renovado'))
);
CREATE INDEX ix_prestamos_cliente ON prestamos (cliente_id, estado);
GO

CREATE TABLE prestamos_cuotas (
    id                   INT IDENTITY(1,1) NOT NULL,
    prestamo_id          INT           NOT NULL,
    numero_cuota         SMALLINT      NOT NULL,
    fecha_pago           DATE          NOT NULL,
    capital              DECIMAL(14,2) NOT NULL,
    interes              DECIMAL(14,2) NOT NULL,
    seguro               DECIMAL(14,2) NOT NULL CONSTRAINT df_cuotas_seguro DEFAULT 0,
    gasto_cierre         DECIMAL(14,2) NOT NULL CONSTRAINT df_cuotas_gasto  DEFAULT 0,  -- solo si fue financiado
    mora_acumulada       DECIMAL(14,2) NOT NULL CONSTRAINT df_cuotas_mora   DEFAULT 0,
    valor_cuota          DECIMAL(14,2) NOT NULL,                                          -- capital+interés+seguro+gasto
    capital_pagado       DECIMAL(14,2) NOT NULL CONSTRAINT df_cuotas_cap_pag DEFAULT 0,
    interes_pagado       DECIMAL(14,2) NOT NULL CONSTRAINT df_cuotas_int_pag DEFAULT 0,
    seguro_pagado        DECIMAL(14,2) NOT NULL CONSTRAINT df_cuotas_seg_pag DEFAULT 0,
    gasto_cierre_pagado  DECIMAL(14,2) NOT NULL CONSTRAINT df_cuotas_gas_pag DEFAULT 0,
    mora_pagada          DECIMAL(14,2) NOT NULL CONSTRAINT df_cuotas_mor_pag DEFAULT 0,
    balance              DECIMAL(14,2) NOT NULL,                                          -- capital pendiente tras esta cuota
    estado               NVARCHAR(10)  NOT NULL CONSTRAINT df_cuotas_estado DEFAULT N'Pendiente',
    CONSTRAINT pk_prestamos_cuotas PRIMARY KEY (id),
    CONSTRAINT fk_cuotas_prestamo  FOREIGN KEY (prestamo_id) REFERENCES prestamos(id),
    CONSTRAINT uq_cuotas_numero    UNIQUE (prestamo_id, numero_cuota),
    CONSTRAINT ck_cuotas_estado    CHECK (estado IN (N'Pendiente', N'Parcial', N'Pagada', N'Vencida')),
    CONSTRAINT ck_cuotas_valor     CHECK (valor_cuota = capital + interes + seguro + gasto_cierre),
    CONSTRAINT ck_cuotas_pagado    CHECK (capital_pagado <= capital AND interes_pagado <= interes
                                          AND seguro_pagado <= seguro AND gasto_cierre_pagado <= gasto_cierre
                                          AND mora_pagada <= mora_acumulada),
    CONSTRAINT ck_cuotas_balance   CHECK (balance >= 0)
);
CREATE INDEX ix_cuotas_prestamo ON prestamos_cuotas (prestamo_id, estado, fecha_pago);
GO

/* ---------------------------------------------------------------------
   7. Recibos de ingreso
      balance_posterior = capital pendiente del préstamo después del recibo.
      cuota_id puede ser NULL en el detalle de un abono a capital
      (el abono reduce el capital global, no paga una cuota concreta).
   --------------------------------------------------------------------- */
CREATE TABLE recibos_ingreso (
    id                   INT IDENTITY(1,1) NOT NULL,
    numero               NVARCHAR(20)  NOT NULL,
    prestamo_id          INT           NOT NULL,
    fecha                DATETIME2(0)  NOT NULL CONSTRAINT df_recibos_fecha DEFAULT SYSDATETIME(),
    tipo_pago            NVARCHAR(15)  NOT NULL,
    monto_recibido       DECIMAL(14,2) NOT NULL,
    total_mora           DECIMAL(14,2) NOT NULL CONSTRAINT df_recibos_mora DEFAULT 0,
    total_interes        DECIMAL(14,2) NOT NULL CONSTRAINT df_recibos_int  DEFAULT 0,
    total_seguro         DECIMAL(14,2) NOT NULL CONSTRAINT df_recibos_seg  DEFAULT 0,
    total_gasto_cierre   DECIMAL(14,2) NOT NULL CONSTRAINT df_recibos_gas  DEFAULT 0,
    total_capital        DECIMAL(14,2) NOT NULL CONSTRAINT df_recibos_cap  DEFAULT 0,
    balance_posterior    DECIMAL(14,2) NOT NULL,
    modalidad_abono      NVARCHAR(15)  NULL,
    formato_impreso      NVARCHAR(5)   NOT NULL CONSTRAINT df_recibos_formato DEFAULT N'A4',
    estado               NVARCHAR(10)  NOT NULL CONSTRAINT df_recibos_estado  DEFAULT N'Activo',
    observacion          NVARCHAR(250) NULL,
    usuario_creacion_id  INT           NOT NULL,
    fecha_creacion       DATETIME2(0)  NOT NULL CONSTRAINT df_recibos_fcrea DEFAULT SYSDATETIME(),
    CONSTRAINT pk_recibos_ingreso PRIMARY KEY (id),
    CONSTRAINT uq_recibos_numero  UNIQUE (numero),
    CONSTRAINT fk_recibos_prestamo FOREIGN KEY (prestamo_id) REFERENCES prestamos(id),
    CONSTRAINT fk_recibos_usuario  FOREIGN KEY (usuario_creacion_id) REFERENCES usuarios(id),
    CONSTRAINT ck_recibos_tipo     CHECK (tipo_pago IN (N'normal', N'abono_capital', N'solo_mora')),
    CONSTRAINT ck_recibos_monto    CHECK (monto_recibido > 0),
    CONSTRAINT ck_recibos_suma     CHECK (monto_recibido = total_mora + total_interes + total_seguro
                                                         + total_gasto_cierre + total_capital),
    CONSTRAINT ck_recibos_modalidad CHECK (modalidad_abono IS NULL OR modalidad_abono IN (N'Reducir cuota', N'Reducir plazo')),
    CONSTRAINT ck_recibos_formato  CHECK (formato_impreso IN (N'POS80', N'A4')),
    CONSTRAINT ck_recibos_estado   CHECK (estado IN (N'Activo', N'Anulado'))
);
CREATE INDEX ix_recibos_prestamo ON recibos_ingreso (prestamo_id, fecha);
GO

CREATE TABLE recibos_ingreso_detalle (
    id                    INT IDENTITY(1,1) NOT NULL,
    recibo_id             INT           NOT NULL,
    cuota_id              INT           NULL,
    mora_aplicada         DECIMAL(14,2) NOT NULL CONSTRAINT df_recdet_mora DEFAULT 0,
    interes_aplicado      DECIMAL(14,2) NOT NULL CONSTRAINT df_recdet_int  DEFAULT 0,
    seguro_aplicado       DECIMAL(14,2) NOT NULL CONSTRAINT df_recdet_seg  DEFAULT 0,
    gasto_cierre_aplicado DECIMAL(14,2) NOT NULL CONSTRAINT df_recdet_gas  DEFAULT 0,
    capital_aplicado      DECIMAL(14,2) NOT NULL CONSTRAINT df_recdet_cap  DEFAULT 0,
    CONSTRAINT pk_recibos_ingreso_detalle PRIMARY KEY (id),
    CONSTRAINT fk_recdet_recibo FOREIGN KEY (recibo_id) REFERENCES recibos_ingreso(id),
    CONSTRAINT fk_recdet_cuota  FOREIGN KEY (cuota_id)  REFERENCES prestamos_cuotas(id)
);
CREATE INDEX ix_recdet_recibo ON recibos_ingreso_detalle (recibo_id);
CREATE INDEX ix_recdet_cuota  ON recibos_ingreso_detalle (cuota_id);
GO

/* ---------------------------------------------------------------------
   8. Renovaciones
   --------------------------------------------------------------------- */
CREATE TABLE renovaciones (
    id                     INT IDENTITY(1,1) NOT NULL,
    cliente_id             INT           NOT NULL,
    prestamo_nuevo_id      INT           NOT NULL,
    fecha                  DATETIME2(0)  NOT NULL CONSTRAINT df_renov_fecha DEFAULT SYSDATETIME(),
    total_saldo_cancelado  DECIMAL(14,2) NOT NULL,
    desembolso_final       DECIMAL(14,2) NOT NULL,
    usuario_creacion_id    INT           NOT NULL,
    fecha_creacion         DATETIME2(0)  NOT NULL CONSTRAINT df_renov_fcrea DEFAULT SYSDATETIME(),
    CONSTRAINT pk_renovaciones PRIMARY KEY (id),
    CONSTRAINT fk_renov_cliente  FOREIGN KEY (cliente_id)          REFERENCES clientes(id),
    CONSTRAINT fk_renov_nuevo    FOREIGN KEY (prestamo_nuevo_id)   REFERENCES prestamos(id),
    CONSTRAINT fk_renov_usuario  FOREIGN KEY (usuario_creacion_id) REFERENCES usuarios(id),
    CONSTRAINT uq_renov_nuevo    UNIQUE (prestamo_nuevo_id)
);

CREATE TABLE renovaciones_detalle (
    id                    INT IDENTITY(1,1) NOT NULL,
    renovacion_id         INT           NOT NULL,
    prestamo_anterior_id  INT           NOT NULL,
    base_saldo            NVARCHAR(20)  NOT NULL,
    monto_cancelado       DECIMAL(14,2) NOT NULL,
    descuento             DECIMAL(14,2) NOT NULL CONSTRAINT df_renovdet_desc DEFAULT 0,
    recibo_id             INT           NOT NULL,
    CONSTRAINT pk_renovaciones_detalle PRIMARY KEY (id),
    CONSTRAINT fk_renovdet_renov    FOREIGN KEY (renovacion_id)        REFERENCES renovaciones(id),
    CONSTRAINT fk_renovdet_anterior FOREIGN KEY (prestamo_anterior_id) REFERENCES prestamos(id),
    CONSTRAINT fk_renovdet_recibo   FOREIGN KEY (recibo_id)            REFERENCES recibos_ingreso(id),
    CONSTRAINT uq_renovdet_anterior UNIQUE (prestamo_anterior_id),     -- un préstamo solo se renueva una vez
    CONSTRAINT ck_renovdet_base     CHECK (base_saldo IN (N'Capital pendiente', N'Balance a la fecha', N'Balance general')),
    CONSTRAINT ck_renovdet_montos   CHECK (monto_cancelado >= 0 AND descuento >= 0)
);
GO

PRINT 'Estructura creada correctamente en prestamos_db.';
GO
