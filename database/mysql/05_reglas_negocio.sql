/* Crea la tabla de reglas de negocio en una base MySQL/MariaDB que YA existe (sin borrar datos).
   Ejecútelo UNA vez en phpMyAdmin (pestaña SQL, con la base seleccionada). */
CREATE TABLE IF NOT EXISTS reglas_negocio (
    clave        VARCHAR(40)   NOT NULL,
    valor        DECIMAL(12,4) NOT NULL,
    descripcion  VARCHAR(200)  NOT NULL,
    CONSTRAINT pk_reglas_negocio PRIMARY KEY (clave),
    CONSTRAINT ck_reglas_valor CHECK (valor >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO reglas_negocio (clave, valor, descripcion) VALUES
 ('tasa_minima', 0.5, 'Tasa de interés mensual mínima (%)'),
 ('tasa_maxima', 10, 'Tasa de interés mensual máxima (%)'),
 ('monto_minimo', 1000, 'Monto mínimo de un préstamo (RD$)'),
 ('mora_porcentaje_maxima', 10, 'Mora en porcentaje: máximo (% de la cuota)'),
 ('mora_fija_maxima_pct', 5, 'Mora en monto fijo: máximo (% del monto del préstamo)'),
 ('seguro_total_maximo_pct', 10, 'Seguro total del préstamo: máximo (% del monto)'),
 ('gasto_cierre_maximo_pct', 10, 'Gasto de cierre: máximo (% del monto)'),
 ('interes_total_maximo_pct', 100, 'Interés total del préstamo: máximo (% del capital)');
