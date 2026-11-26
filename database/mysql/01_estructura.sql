/* =====================================================================
   SISTEMA DE GESTIÓN DE PRÉSTAMOS  -  mysql/01_estructura.sql
   Motor: MySQL 5.7+ / MariaDB 10.3+   (versión para publicar en un hosting compartido)
   Equivale a database/01_estructura.sql (SQL Server).

   Qué hace: borra las tablas del proyecto si existían y las crea de nuevo.
   NO crea la base de datos: en un hosting se crea desde el panel y se selecciona antes
   de importar este archivo (phpMyAdmin -> elegir la base -> Importar).

   Orden de ejecución:  01_estructura -> 02_datos_base -> 03_datos_prueba (opcional)
   ADVERTENCIA: este script BORRA y recrea las tablas del proyecto.
   ===================================================================== */
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


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
DROP TABLE IF EXISTS reglas_negocio;
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
DROP TABLE IF EXISTS secuencias;

SET FOREIGN_KEY_CHECKS = 1;

/* ---------------------------------------------------------------------
   1. Contadores para numerar préstamos y recibos sin duplicados.
      MySQL no tiene SEQUENCE: se usa una tabla. El backend hace
      UPDATE secuencias SET valor = LAST_INSERT_ID(valor + 1) WHERE nombre = ?
      (atómico: dos usuarios a la vez nunca obtienen el mismo número) y le da formato
      (PR-000001, RC-000001). 'valor' guarda el último número usado.
   --------------------------------------------------------------------- */
CREATE TABLE secuencias (
    nombre  VARCHAR(30) NOT NULL,
    valor   INT         NOT NULL DEFAULT 0,
    PRIMARY KEY (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO secuencias (nombre, valor) VALUES ('prestamo', 0), ('recibo', 0);


/* ---------------------------------------------------------------------
   2. Seguridad y auditoría
   --------------------------------------------------------------------- */
CREATE TABLE usuarios (
    id              INT NOT NULL AUTO_INCREMENT,
    usuario         VARCHAR(50)  NOT NULL,
    nombre          VARCHAR(100) NOT NULL,
    clave_hash      VARCHAR(255) NOT NULL,          -- password_hash() de PHP
    rol             VARCHAR(20)  NOT NULL,
    estado          VARCHAR(10)  NOT NULL DEFAULT 'Activo',
    fecha_creacion  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_usuarios         PRIMARY KEY (id),
    CONSTRAINT uq_usuarios_usuario UNIQUE (usuario),
    CONSTRAINT ck_usuarios_rol     CHECK (rol    IN ('Administrador', 'Cajero')),
    CONSTRAINT ck_usuarios_estado  CHECK (estado IN ('Activo', 'Inactivo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------
   3. Catálogos del módulo de clientes
   --------------------------------------------------------------------- */
CREATE TABLE tipos_documento (
    id        INT NOT NULL AUTO_INCREMENT,
    nombre    VARCHAR(30)  NOT NULL,
    mascara   VARCHAR(30)  NOT NULL,                -- formato que ve el usuario
    longitud  TINYINT       NOT NULL,                -- longitud máxima del texto
    patron    VARCHAR(100) NOT NULL,                -- expresión regular (JS y PHP)
    CONSTRAINT pk_tipos_documento PRIMARY KEY (id),
    CONSTRAINT uq_tipos_documento_nombre UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE nacionalidades (
    id      INT NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(60) NOT NULL,
    CONSTRAINT pk_nacionalidades PRIMARY KEY (id),
    CONSTRAINT uq_nacionalidades_nombre UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------
   4. clientes = registro único de PERSONAS (clientes y garantes).
      es_cliente = 1  -> la persona puede recibir préstamos.
      es_cliente = 0  -> persona registrada solo como garante.
      El rol de garante se expresa en clientes_garantes.
   --------------------------------------------------------------------- */
CREATE TABLE clientes (
    id                        INT NOT NULL AUTO_INCREMENT,
    tipo_documento_id         INT           NOT NULL,
    numero_documento          VARCHAR(20)  NOT NULL,
    nombres                   VARCHAR(100) NOT NULL,
    apellidos                 VARCHAR(100) NOT NULL,
    nacionalidad_id           INT           NOT NULL,
    fecha_nacimiento          DATE          NOT NULL,
    estado_civil              VARCHAR(15)  NOT NULL,
    correo                    VARCHAR(150) NULL,
    direccion                 VARCHAR(250) NOT NULL,
    referencia_direccion      VARCHAR(250) NULL,
    es_cliente                TINYINT(1)           NOT NULL DEFAULT 1,
    limite_credito            DECIMAL(14,2) NOT NULL DEFAULT 0,
    clasificacion             VARCHAR(1)   NULL,
    estado                    VARCHAR(10)  NOT NULL DEFAULT 'Activo',
    usuario_creacion_id       INT           NOT NULL,
    fecha_creacion            DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_modificacion_id   INT           NULL,
    fecha_modificacion        DATETIME  NULL,
    CONSTRAINT pk_clientes PRIMARY KEY (id),
    CONSTRAINT uq_clientes_documento UNIQUE (numero_documento),
    CONSTRAINT fk_clientes_tipo_doc  FOREIGN KEY (tipo_documento_id) REFERENCES tipos_documento(id),
    CONSTRAINT fk_clientes_nacion    FOREIGN KEY (nacionalidad_id)   REFERENCES nacionalidades(id),
    CONSTRAINT fk_clientes_usr_crea  FOREIGN KEY (usuario_creacion_id)     REFERENCES usuarios(id),
    CONSTRAINT fk_clientes_usr_mod   FOREIGN KEY (usuario_modificacion_id) REFERENCES usuarios(id),
    CONSTRAINT ck_clientes_estado_civil CHECK (estado_civil IN
        ('Soltero/a', 'Casado/a', 'Unión libre', 'Divorciado/a', 'Viudo/a')),
    CONSTRAINT ck_clientes_clasif  CHECK (clasificacion IS NULL OR clasificacion IN ('A', 'B', 'C')),
    CONSTRAINT ck_clientes_estado  CHECK (estado IN ('Activo', 'Inactivo')),
    CONSTRAINT ck_clientes_limite  CHECK (limite_credito >= 0),
    -- Crédito de los clientes (es_cliente = 1): límite entre 5,000 y 10,000,000 y clasificación acorde al límite.
    -- C: hasta 100,000 | B: hasta 1,000,000 | A: hasta 10,000,000. Los garantes sin crédito (es_cliente = 0) no aplican.
    CONSTRAINT ck_clientes_limite_rango CHECK (es_cliente = 0 OR (limite_credito >= 5000 AND limite_credito <= 10000000)),
    CONSTRAINT ck_clientes_clasif_limite CHECK (es_cliente = 0 OR (
        (clasificacion = 'C' AND limite_credito <= 100000)
        OR (clasificacion = 'B' AND limite_credito > 100000 AND limite_credito <= 1000000)
        OR (clasificacion = 'A' AND limite_credito > 1000000)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_clientes_nombre ON clientes (apellidos, nombres);

CREATE TABLE clientes_telefonos (
    id          INT NOT NULL AUTO_INCREMENT,
    cliente_id  INT          NOT NULL,
    tipo        VARCHAR(10) NOT NULL,
    numero      VARCHAR(20) NOT NULL,
    principal   TINYINT(1)          NOT NULL DEFAULT 0,
    principal_unico INT AS (IF(principal = 1, cliente_id, NULL)) STORED,   -- apoyo del índice único parcial
    CONSTRAINT pk_clientes_telefonos PRIMARY KEY (id),
    CONSTRAINT fk_telefonos_cliente  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT uq_telefonos_numero   UNIQUE (cliente_id, numero),
    CONSTRAINT ck_telefonos_tipo     CHECK (tipo IN ('Celular', 'Casa', 'Trabajo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Un solo teléfono principal por persona
CREATE UNIQUE INDEX ux_telefonos_principal ON clientes_telefonos (principal_unico);

CREATE TABLE clientes_garantes (
    id                   INT NOT NULL AUTO_INCREMENT,
    cliente_id           INT          NOT NULL,
    garante_id           INT          NOT NULL,        -- FK a clientes (la misma tabla de personas)
    fecha_asignacion     DATE         NOT NULL,
    vigente              TINYINT(1)          NOT NULL DEFAULT 1,
    vigente_unico        INT AS (IF(vigente = 1, cliente_id, NULL)) STORED,   -- apoyo del índice único parcial
    usuario_creacion_id  INT          NOT NULL,
    fecha_creacion       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_clientes_garantes PRIMARY KEY (id),
    CONSTRAINT fk_garantes_cliente  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT fk_garantes_garante  FOREIGN KEY (garante_id) REFERENCES clientes(id),
    CONSTRAINT fk_garantes_usuario  FOREIGN KEY (usuario_creacion_id) REFERENCES usuarios(id),
    CONSTRAINT ck_garantes_distinto CHECK (cliente_id <> garante_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Un solo garante vigente por cliente
CREATE UNIQUE INDEX ux_garantes_vigente ON clientes_garantes (vigente_unico);
CREATE INDEX ix_garantes_garante ON clientes_garantes (garante_id);

CREATE TABLE clientes_familiares (
    id           INT NOT NULL AUTO_INCREMENT,
    cliente_id   INT           NOT NULL,
    tipo         VARCHAR(12)  NOT NULL,
    nombre       VARCHAR(100) NOT NULL,
    parentesco   VARCHAR(40)  NOT NULL,
    telefono     VARCHAR(20)  NULL,
    direccion    VARCHAR(250) NULL,
    CONSTRAINT pk_clientes_familiares PRIMARY KEY (id),
    CONSTRAINT fk_familiares_cliente  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT uq_familiares_tipo     UNIQUE (cliente_id, tipo),
    CONSTRAINT ck_familiares_tipo     CHECK (tipo IN ('Cercano', 'No convive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clientes_laborales (
    id                 INT NOT NULL AUTO_INCREMENT,
    cliente_id         INT           NOT NULL,
    empresa            VARCHAR(120) NOT NULL,
    cargo_ocupacion    VARCHAR(80)  NOT NULL,
    tiempo_laborando   VARCHAR(40)  NOT NULL,
    sueldo             DECIMAL(14,2) NOT NULL,
    telefono_empresa   VARCHAR(20)  NULL,
    direccion_empresa  VARCHAR(250) NULL,
    CONSTRAINT pk_clientes_laborales PRIMARY KEY (id),
    CONSTRAINT fk_laborales_cliente  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    CONSTRAINT uq_laborales_cliente  UNIQUE (cliente_id),
    CONSTRAINT ck_laborales_sueldo   CHECK (sueldo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clientes_referencias (
    id          INT NOT NULL AUTO_INCREMENT,
    cliente_id  INT           NOT NULL,
    nombre      VARCHAR(100) NOT NULL,
    telefono    VARCHAR(20)  NOT NULL,
    CONSTRAINT pk_clientes_referencias PRIMARY KEY (id),
    CONSTRAINT fk_referencias_cliente  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------
   5. Catálogos de préstamos
   --------------------------------------------------------------------- */
CREATE TABLE tipos_prestamo (
    id           INT NOT NULL AUTO_INCREMENT,
    nombre       VARCHAR(60)  NOT NULL,
    descripcion  VARCHAR(250) NULL,
    estado       VARCHAR(10)  NOT NULL DEFAULT 'Activo',
    CONSTRAINT pk_tipos_prestamo PRIMARY KEY (id),
    CONSTRAINT uq_tipos_prestamo_nombre UNIQUE (nombre),
    CONSTRAINT ck_tipos_prestamo_estado CHECK (estado IN ('Activo', 'Inactivo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tipos_plazo (
    id                            INT NOT NULL AUTO_INCREMENT,
    nombre                        VARCHAR(30) NOT NULL,
    dias_periodo                  SMALLINT     NOT NULL,   -- para convertir la tasa: i = tasa * dias/30
    meses_periodo                 TINYINT      NOT NULL,   -- >0: las fechas avanzan por meses calendario
    plazo_minimo                  SMALLINT     NOT NULL DEFAULT 1,
    plazo_maximo                  SMALLINT     NOT NULL,
    permite_excluir_fines_semana  TINYINT(1)          NOT NULL DEFAULT 0,
    CONSTRAINT pk_tipos_plazo PRIMARY KEY (id),
    CONSTRAINT uq_tipos_plazo_nombre UNIQUE (nombre),
    CONSTRAINT ck_tipos_plazo_dias CHECK (dias_periodo > 0),
    CONSTRAINT ck_tipos_plazo_rango CHECK (plazo_minimo >= 1 AND plazo_maximo >= plazo_minimo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Reglas de negocio: límites que se validan al abrir un préstamo (se cambian con UPDATE, sin tocar el código) */
CREATE TABLE reglas_negocio (
    clave        VARCHAR(40)  NOT NULL,
    valor        DECIMAL(12,4) NOT NULL,
    descripcion  VARCHAR(200) NOT NULL,
    CONSTRAINT pk_reglas_negocio PRIMARY KEY (clave),
    CONSTRAINT ck_reglas_valor CHECK (valor >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tipos_amortizacion (
    id      INT NOT NULL AUTO_INCREMENT,
    codigo  VARCHAR(10) NOT NULL,
    nombre  VARCHAR(30) NOT NULL,
    CONSTRAINT pk_tipos_amortizacion PRIMARY KEY (id),
    CONSTRAINT uq_tipos_amortizacion_codigo UNIQUE (codigo),
    CONSTRAINT ck_tipos_amortizacion_codigo CHECK (codigo IN ('ABSOLUTO', 'INSOLUTO', 'FIJO'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------------------------------------------------
   6. Préstamos (cabecera y cuotas)
      seguro_monto: si seguro_tipo = 'Fijo'      -> monto por cuota
                    si seguro_tipo = 'Repartido' -> monto total a repartir
   --------------------------------------------------------------------- */
CREATE TABLE prestamos (
    id                        INT NOT NULL AUTO_INCREMENT,
    numero                    VARCHAR(20)  NOT NULL,
    cliente_id                INT           NOT NULL,
    tipo_prestamo_id          INT           NOT NULL,
    monto                     DECIMAL(14,2) NOT NULL,
    plazo                     SMALLINT      NOT NULL,
    tasa                      DECIMAL(8,4)  NOT NULL,      -- % mensual nominal
    tipo_plazo_id             INT           NOT NULL,
    tipo_amortizacion_id      INT           NOT NULL,
    incluye_sabado            TINYINT(1)           NOT NULL DEFAULT 1,
    incluye_domingo           TINYINT(1)           NOT NULL DEFAULT 1,
    tipo_mora                 VARCHAR(10)  NOT NULL,
    valor_mora                DECIMAL(14,2) NOT NULL DEFAULT 0,
    seguro_tipo               VARCHAR(10)  NOT NULL,
    seguro_monto              DECIMAL(14,2) NOT NULL DEFAULT 0,
    gasto_cierre              DECIMAL(14,2) NOT NULL DEFAULT 0,
    gasto_cierre_financiado   TINYINT(1)           NOT NULL DEFAULT 0,
    desembolso                DECIMAL(14,2) NOT NULL,
    fecha_apertura            DATE          NOT NULL,
    fecha_primer_pago         DATE          NOT NULL,
    capital_pendiente         DECIMAL(14,2) NOT NULL,
    estado                    VARCHAR(10)  NOT NULL DEFAULT 'Activo',
    usuario_creacion_id       INT           NOT NULL,
    fecha_creacion            DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
    CONSTRAINT ck_prestamos_mora     CHECK (tipo_mora IN ('Porcentaje', 'Monto') AND valor_mora >= 0),
    CONSTRAINT ck_prestamos_seguro   CHECK (seguro_tipo IN ('Fijo', 'Repartido') AND seguro_monto >= 0),
    CONSTRAINT ck_prestamos_gasto    CHECK (gasto_cierre >= 0),
    CONSTRAINT ck_prestamos_desemb   CHECK (desembolso > 0),
    CONSTRAINT ck_prestamos_capital  CHECK (capital_pendiente >= 0 AND capital_pendiente <= monto),
    CONSTRAINT ck_prestamos_fechas   CHECK (fecha_primer_pago >= fecha_apertura),
    CONSTRAINT ck_prestamos_estado   CHECK (estado IN ('Activo', 'Saldado', 'Renovado'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_prestamos_cliente ON prestamos (cliente_id, estado);

CREATE TABLE prestamos_cuotas (
    id                   INT NOT NULL AUTO_INCREMENT,
    prestamo_id          INT           NOT NULL,
    numero_cuota         SMALLINT      NOT NULL,
    fecha_pago           DATE          NOT NULL,
    capital              DECIMAL(14,2) NOT NULL,
    interes              DECIMAL(14,2) NOT NULL,
    seguro               DECIMAL(14,2) NOT NULL DEFAULT 0,
    gasto_cierre         DECIMAL(14,2) NOT NULL DEFAULT 0,  -- solo si fue financiado
    mora_acumulada       DECIMAL(14,2) NOT NULL DEFAULT 0,
    valor_cuota          DECIMAL(14,2) NOT NULL,                                          -- capital+interés+seguro+gasto
    capital_pagado       DECIMAL(14,2) NOT NULL DEFAULT 0,
    interes_pagado       DECIMAL(14,2) NOT NULL DEFAULT 0,
    seguro_pagado        DECIMAL(14,2) NOT NULL DEFAULT 0,
    gasto_cierre_pagado  DECIMAL(14,2) NOT NULL DEFAULT 0,
    mora_pagada          DECIMAL(14,2) NOT NULL DEFAULT 0,
    balance              DECIMAL(14,2) NOT NULL,                                          -- capital pendiente tras esta cuota
    estado               VARCHAR(10)  NOT NULL DEFAULT 'Pendiente',
    CONSTRAINT pk_prestamos_cuotas PRIMARY KEY (id),
    CONSTRAINT fk_cuotas_prestamo  FOREIGN KEY (prestamo_id) REFERENCES prestamos(id),
    CONSTRAINT uq_cuotas_numero    UNIQUE (prestamo_id, numero_cuota),
    CONSTRAINT ck_cuotas_estado    CHECK (estado IN ('Pendiente', 'Parcial', 'Pagada', 'Vencida')),
    CONSTRAINT ck_cuotas_valor     CHECK (valor_cuota = capital + interes + seguro + gasto_cierre),
    CONSTRAINT ck_cuotas_pagado    CHECK (capital_pagado <= capital AND interes_pagado <= interes
                                          AND seguro_pagado <= seguro AND gasto_cierre_pagado <= gasto_cierre
                                          AND mora_pagada <= mora_acumulada),
    CONSTRAINT ck_cuotas_balance   CHECK (balance >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_cuotas_prestamo ON prestamos_cuotas (prestamo_id, estado, fecha_pago);

/* ---------------------------------------------------------------------
   7. Recibos de ingreso
      balance_posterior = capital pendiente del préstamo después del recibo.
      cuota_id puede ser NULL en el detalle de un abono a capital
      (el abono reduce el capital global, no paga una cuota concreta).
   --------------------------------------------------------------------- */
CREATE TABLE recibos_ingreso (
    id                   INT NOT NULL AUTO_INCREMENT,
    numero               VARCHAR(20)  NOT NULL,
    prestamo_id          INT           NOT NULL,
    fecha                DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo_pago            VARCHAR(15)  NOT NULL,
    monto_recibido       DECIMAL(14,2) NOT NULL,
    total_mora           DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_interes        DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_seguro         DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_gasto_cierre   DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_capital        DECIMAL(14,2) NOT NULL DEFAULT 0,
    balance_posterior    DECIMAL(14,2) NOT NULL,
    modalidad_abono      VARCHAR(15)  NULL,
    formato_impreso      VARCHAR(5)   NOT NULL DEFAULT 'A4',
    estado               VARCHAR(10)  NOT NULL DEFAULT 'Activo',
    observacion          VARCHAR(250) NULL,
    usuario_creacion_id  INT           NOT NULL,
    fecha_creacion       DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_recibos_ingreso PRIMARY KEY (id),
    CONSTRAINT uq_recibos_numero  UNIQUE (numero),
    CONSTRAINT fk_recibos_prestamo FOREIGN KEY (prestamo_id) REFERENCES prestamos(id),
    CONSTRAINT fk_recibos_usuario  FOREIGN KEY (usuario_creacion_id) REFERENCES usuarios(id),
    CONSTRAINT ck_recibos_tipo     CHECK (tipo_pago IN ('normal', 'abono_capital', 'solo_mora')),
    CONSTRAINT ck_recibos_monto    CHECK (monto_recibido > 0),
    CONSTRAINT ck_recibos_suma     CHECK (monto_recibido = total_mora + total_interes + total_seguro
                                                         + total_gasto_cierre + total_capital),
    CONSTRAINT ck_recibos_modalidad CHECK (modalidad_abono IS NULL OR modalidad_abono IN ('Reducir cuota', 'Reducir plazo')),
    CONSTRAINT ck_recibos_formato  CHECK (formato_impreso IN ('POS80', 'A4')),
    CONSTRAINT ck_recibos_estado   CHECK (estado IN ('Activo', 'Anulado'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_recibos_prestamo ON recibos_ingreso (prestamo_id, fecha);

CREATE TABLE recibos_ingreso_detalle (
    id                    INT NOT NULL AUTO_INCREMENT,
    recibo_id             INT           NOT NULL,
    cuota_id              INT           NULL,
    mora_aplicada         DECIMAL(14,2) NOT NULL DEFAULT 0,
    interes_aplicado      DECIMAL(14,2) NOT NULL DEFAULT 0,
    seguro_aplicado       DECIMAL(14,2) NOT NULL DEFAULT 0,
    gasto_cierre_aplicado DECIMAL(14,2) NOT NULL DEFAULT 0,
    capital_aplicado      DECIMAL(14,2) NOT NULL DEFAULT 0,
    CONSTRAINT pk_recibos_ingreso_detalle PRIMARY KEY (id),
    CONSTRAINT fk_recdet_recibo FOREIGN KEY (recibo_id) REFERENCES recibos_ingreso(id),
    CONSTRAINT fk_recdet_cuota  FOREIGN KEY (cuota_id)  REFERENCES prestamos_cuotas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX ix_recdet_recibo ON recibos_ingreso_detalle (recibo_id);
CREATE INDEX ix_recdet_cuota  ON recibos_ingreso_detalle (cuota_id);

/* ---------------------------------------------------------------------
   8. Renovaciones
   --------------------------------------------------------------------- */
CREATE TABLE renovaciones (
    id                     INT NOT NULL AUTO_INCREMENT,
    cliente_id             INT           NOT NULL,
    prestamo_nuevo_id      INT           NOT NULL,
    fecha                  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_saldo_cancelado  DECIMAL(14,2) NOT NULL,
    desembolso_final       DECIMAL(14,2) NOT NULL,
    usuario_creacion_id    INT           NOT NULL,
    fecha_creacion         DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_renovaciones PRIMARY KEY (id),
    CONSTRAINT fk_renov_cliente  FOREIGN KEY (cliente_id)          REFERENCES clientes(id),
    CONSTRAINT fk_renov_nuevo    FOREIGN KEY (prestamo_nuevo_id)   REFERENCES prestamos(id),
    CONSTRAINT fk_renov_usuario  FOREIGN KEY (usuario_creacion_id) REFERENCES usuarios(id),
    CONSTRAINT uq_renov_nuevo    UNIQUE (prestamo_nuevo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE renovaciones_detalle (
    id                    INT NOT NULL AUTO_INCREMENT,
    renovacion_id         INT           NOT NULL,
    prestamo_anterior_id  INT           NOT NULL,
    base_saldo            VARCHAR(20)  NOT NULL,
    monto_cancelado       DECIMAL(14,2) NOT NULL,
    descuento             DECIMAL(14,2) NOT NULL DEFAULT 0,
    recibo_id             INT           NOT NULL,
    CONSTRAINT pk_renovaciones_detalle PRIMARY KEY (id),
    CONSTRAINT fk_renovdet_renov    FOREIGN KEY (renovacion_id)        REFERENCES renovaciones(id),
    CONSTRAINT fk_renovdet_anterior FOREIGN KEY (prestamo_anterior_id) REFERENCES prestamos(id),
    CONSTRAINT fk_renovdet_recibo   FOREIGN KEY (recibo_id)            REFERENCES recibos_ingreso(id),
    CONSTRAINT uq_renovdet_anterior UNIQUE (prestamo_anterior_id),     -- un préstamo solo se renueva una vez
    CONSTRAINT ck_renovdet_base     CHECK (base_saldo IN ('Capital pendiente', 'Balance a la fecha', 'Balance general')),
    CONSTRAINT ck_renovdet_montos   CHECK (monto_cancelado >= 0 AND descuento >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

