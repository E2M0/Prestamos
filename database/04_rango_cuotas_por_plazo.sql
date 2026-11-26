/* Agrega el rango de cuotas por tipo de plazo a una base SQL Server que YA existe (sin borrar datos).
   Ejecútelo en SSMS sobre prestamos_db. Si la base ya tiene las columnas plazo_minimo y plazo_maximo
   (porque se creó con los scripts 01-03 actualizados), no hace nada. */
USE prestamos_db;
GO
IF COL_LENGTH('tipos_plazo', 'plazo_maximo') IS NULL
BEGIN
    ALTER TABLE tipos_plazo ADD plazo_minimo SMALLINT NOT NULL CONSTRAINT df_tipos_plazo_min DEFAULT 1;
    ALTER TABLE tipos_plazo ADD plazo_maximo SMALLINT NOT NULL CONSTRAINT df_tipos_plazo_max DEFAULT 600;
END
GO
IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'ck_tipos_plazo_rango')
    ALTER TABLE tipos_plazo ADD CONSTRAINT ck_tipos_plazo_rango CHECK (plazo_minimo >= 1 AND plazo_maximo >= plazo_minimo);
GO
UPDATE tipos_plazo SET plazo_minimo = 15, plazo_maximo = 365 WHERE nombre = N'Diario';
UPDATE tipos_plazo SET plazo_minimo = 10, plazo_maximo = 180 WHERE nombre = N'Interdiario';
UPDATE tipos_plazo SET plazo_minimo = 4, plazo_maximo = 156 WHERE nombre = N'Semanal';
UPDATE tipos_plazo SET plazo_minimo = 2, plazo_maximo = 130 WHERE nombre = N'Intersemanal';
UPDATE tipos_plazo SET plazo_minimo = 2, plazo_maximo = 120 WHERE nombre = N'Quincenal';
UPDATE tipos_plazo SET plazo_minimo = 1, plazo_maximo = 120 WHERE nombre = N'Mensual';
UPDATE tipos_plazo SET plazo_minimo = 1, plazo_maximo = 40 WHERE nombre = N'Trimestral';
UPDATE tipos_plazo SET plazo_minimo = 1, plazo_maximo = 20 WHERE nombre = N'Semestral';
UPDATE tipos_plazo SET plazo_minimo = 1, plazo_maximo = 15 WHERE nombre = N'Anual';
GO
