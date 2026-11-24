/* Agrega el rango de cuotas por tipo de plazo a una base MySQL/MariaDB que YA existe (sin borrar datos).
   Ejecútelo UNA sola vez (phpMyAdmin -> pestaña SQL, con la base seleccionada).
   Si la base se creó con los scripts 01-03 actualizados, NO lo necesita: dará error "columna duplicada". */
ALTER TABLE tipos_plazo ADD COLUMN plazo_minimo SMALLINT NOT NULL DEFAULT 1;
ALTER TABLE tipos_plazo ADD COLUMN plazo_maximo SMALLINT NOT NULL DEFAULT 600;
ALTER TABLE tipos_plazo ADD CONSTRAINT ck_tipos_plazo_rango CHECK (plazo_minimo >= 1 AND plazo_maximo >= plazo_minimo);
UPDATE tipos_plazo SET plazo_minimo = 15, plazo_maximo = 365 WHERE nombre = 'Diario';
UPDATE tipos_plazo SET plazo_minimo = 10, plazo_maximo = 180 WHERE nombre = 'Interdiario';
UPDATE tipos_plazo SET plazo_minimo = 4, plazo_maximo = 156 WHERE nombre = 'Semanal';
UPDATE tipos_plazo SET plazo_minimo = 2, plazo_maximo = 130 WHERE nombre = 'Intersemanal';
UPDATE tipos_plazo SET plazo_minimo = 2, plazo_maximo = 120 WHERE nombre = 'Quincenal';
UPDATE tipos_plazo SET plazo_minimo = 1, plazo_maximo = 120 WHERE nombre = 'Mensual';
UPDATE tipos_plazo SET plazo_minimo = 1, plazo_maximo = 40 WHERE nombre = 'Trimestral';
UPDATE tipos_plazo SET plazo_minimo = 1, plazo_maximo = 20 WHERE nombre = 'Semestral';
UPDATE tipos_plazo SET plazo_minimo = 1, plazo_maximo = 15 WHERE nombre = 'Anual';
