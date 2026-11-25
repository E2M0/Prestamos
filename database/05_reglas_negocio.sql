/* Crea la tabla de reglas de negocio en una base SQL Server que YA existe (sin borrar datos).
   Ejecútelo en SSMS sobre prestamos_db. Es seguro repetirlo. */
USE prestamos_db;
GO
IF OBJECT_ID('reglas_negocio', 'U') IS NULL
BEGIN
    CREATE TABLE reglas_negocio (
        clave        NVARCHAR(40)  NOT NULL,
        valor        DECIMAL(12,4) NOT NULL,
        descripcion  NVARCHAR(200) NOT NULL,
        CONSTRAINT pk_reglas_negocio PRIMARY KEY (clave),
        CONSTRAINT ck_reglas_valor CHECK (valor >= 0)
    );
    INSERT INTO reglas_negocio (clave, valor, descripcion) VALUES
 (N'tasa_minima', 0.5, N'Tasa de interés mensual mínima (%)'),
 (N'tasa_maxima', 10, N'Tasa de interés mensual máxima (%)'),
 (N'monto_minimo', 1000, N'Monto mínimo de un préstamo (RD$)'),
 (N'mora_porcentaje_maxima', 10, N'Mora en porcentaje: máximo (% de la cuota)'),
 (N'mora_fija_maxima_pct', 5, N'Mora en monto fijo: máximo (% del monto del préstamo)'),
 (N'seguro_total_maximo_pct', 10, N'Seguro total del préstamo: máximo (% del monto)'),
 (N'gasto_cierre_maximo_pct', 10, N'Gasto de cierre: máximo (% del monto)'),
 (N'interes_total_maximo_pct', 100, N'Interés total del préstamo: máximo (% del capital)');
END
GO
SELECT clave, valor, descripcion FROM reglas_negocio ORDER BY clave;
