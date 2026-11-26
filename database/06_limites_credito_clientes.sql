/* Reglas de crédito de los clientes en una base SQL Server que YA existe (sin borrar clientes).
   Ejecútelo en SSMS sobre prestamos_db. Es seguro repetirlo.
   - Límite de clientes: entre 5,000 y 10,000,000 (los menores suben a 5,000 y los mayores bajan a 10,000,000).
   - Clasificación según el límite: C hasta 100,000 | B hasta 1,000,000 | A hasta 10,000,000.
   - Los garantes sin crédito (es_cliente = 0) no se tocan. */
USE prestamos_db;
GO
UPDATE clientes SET limite_credito = 5000 WHERE es_cliente = 1 AND limite_credito < 5000;
UPDATE clientes SET limite_credito = 10000000 WHERE es_cliente = 1 AND limite_credito > 10000000;
UPDATE clientes SET clasificacion = CASE WHEN limite_credito <= 100000 THEN N'C'
                                         WHEN limite_credito <= 1000000 THEN N'B' ELSE N'A' END
 WHERE es_cliente = 1;
GO
IF OBJECT_ID('ck_clientes_limite_rango', 'C') IS NULL
    ALTER TABLE clientes ADD CONSTRAINT ck_clientes_limite_rango
        CHECK (es_cliente = 0 OR (limite_credito >= 5000 AND limite_credito <= 10000000));
IF OBJECT_ID('ck_clientes_clasif_limite', 'C') IS NULL
    ALTER TABLE clientes ADD CONSTRAINT ck_clientes_clasif_limite CHECK (es_cliente = 0 OR (
        (clasificacion = N'C' AND limite_credito <= 100000)
        OR (clasificacion = N'B' AND limite_credito > 100000 AND limite_credito <= 1000000)
        OR (clasificacion = N'A' AND limite_credito > 1000000)));
GO
SELECT clasificacion, COUNT(*) AS clientes, MIN(limite_credito) AS minimo, MAX(limite_credito) AS maximo
  FROM clientes WHERE es_cliente = 1 GROUP BY clasificacion ORDER BY clasificacion;
