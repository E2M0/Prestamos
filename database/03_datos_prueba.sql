/* =====================================================================
   SISTEMA DE GESTIÓN DE PRÉSTAMOS  -  03_datos_prueba.sql
   Ruta en el proyecto: database/03_datos_prueba.sql
   Ejecutar DESPUÉS de 01_estructura.sql y 02_datos_base.sql.

   Datos FICTICIOS para pruebas: 14 personas (11 clientes y 3 garantes),
   9 préstamos en distintos estados y sus recibos.
   Los cálculos de cuotas siguen las fórmulas del enunciado
   (tasa mensual nominal, i = tasa * dias_periodo / 30) y el último ajuste
   de redondeo se aplica a la última cuota.
   Fecha de referencia de los datos: 2026-11-16.

   Casos cubiertos:
     PR-000001 Insoluto mensual, una cuota vencida con mora (y mora pagada aparte)
     PR-000002 Absoluto quincenal, una cuota Parcial y otra Vencida
     PR-000003 Saldado
     PR-000004 Renovado -> PR-000005 (renovación con balance general)
     PR-000006 Diario sin sábados ni domingos
     PR-000007 Saldo fijo (solo se han generado las cuotas ya saldadas y la siguiente)
     PR-000008 Trimestral con seguro repartido y gasto de cierre financiado
     PR-000009 Anual con seguro fijo, cuotas todas pendientes
   ===================================================================== */
USE prestamos_db;
GO
SET NOCOUNT ON;
SET QUOTED_IDENTIFIER ON;
SET XACT_ABORT ON;
GO

BEGIN TRANSACTION;

SET IDENTITY_INSERT clientes ON;
INSERT INTO clientes (id, tipo_documento_id, numero_documento, nombres, apellidos, nacionalidad_id, fecha_nacimiento, estado_civil, correo, direccion, referencia_direccion, es_cliente, limite_credito, clasificacion, estado, usuario_creacion_id) VALUES
(1, 1, N'402-1000001-1', N'María Altagracia', N'Pérez Rodríguez', 1, '1985-03-14', N'Casado/a', N'maria.perez1@example.com', N'Av. Estrella Sadhalá #13, Los Jardines, Santiago de los Caballeros', N'Cerca del colmado', 1, 200000, N'A', N'Activo', 1),
(2, 1, N'402-1000002-2', N'José Luis', N'Martínez Gómez', 1, '1979-07-22', N'Casado/a', N'jose.martinez2@example.com', N'Calle del Sol #16, Bella Vista, Santiago de los Caballeros', N'Cerca de la escuela', 1, 50000, N'A', N'Activo', 1),
(3, 1, N'402-1000003-3', N'Ana Karina', N'Santos Núñez', 1, '1990-11-05', N'Soltero/a', N'ana.santos3@example.com', N'Av. Juan Pablo Duarte #19, Cienfuegos, Santiago de los Caballeros', N'Cerca de la farmacia', 1, 120000, N'B', N'Activo', 1),
(4, 1, N'402-1000004-4', N'Carlos Manuel', N'Ramírez Peña', 1, '1988-01-30', N'Unión libre', N'carlos.ramirez4@example.com', N'Calle 27 de Febrero #22, Reparto Peralta, Santiago de los Caballeros', N'Cerca del parque', 1, 60000, N'B', N'Activo', 1),
(5, 1, N'402-1000005-5', N'Luisa Fernanda', N'Díaz Almonte', 1, '1983-09-18', N'Divorciado/a', N'luisa.diaz5@example.com', N'Calle Restauración #25, Pekín, Santiago de los Caballeros', N'Cerca de la estación', 1, 80000, N'A', N'Activo', 1),
(6, 1, N'402-1000006-6', N'Pedro Antonio', N'Castillo Reyes', 1, '1992-05-09', N'Soltero/a', N'pedro.castillo6@example.com', N'Av. Imbert #28, La Trinitaria, Santiago de los Caballeros', N'Cerca de la iglesia', 1, 30000, N'C', N'Activo', 1),
(7, 3, N'VE4821907', N'Yolanda Beatriz', N'Rojas Méndez', 3, '1987-12-02', N'Casado/a', N'yolanda.rojas7@example.com', N'Calle Mella #31, Villa Olga, Santiago de los Caballeros', N'Cerca del colmado', 1, 100000, N'B', N'Activo', 1),
(8, 1, N'402-1000008-8', N'Rafael Enrique', N'Jiménez Cruz', 1, '1975-04-27', N'Casado/a', N'rafael.jimenez8@example.com', N'Calle Duarte #34, Gurabo, Santiago de los Caballeros', N'Cerca de la escuela', 1, 150000, N'A', N'Activo', 1),
(9, 1, N'402-1000009-9', N'Daniela Esther', N'Vargas Polanco', 1, '1995-08-16', N'Soltero/a', N'daniela.vargas9@example.com', N'Av. Estrella Sadhalá #37, Los Salados, Santiago de los Caballeros', N'Cerca de la farmacia', 1, 300000, N'A', N'Activo', 1),
(10, 1, N'402-1000010-0', N'Miguel Ángel', N'Herrera Ortiz', 1, '1981-02-11', N'Casado/a', N'miguel.herrera10@example.com', N'Calle del Sol #40, Baracoa, Santiago de los Caballeros', N'Cerca del parque', 1, 40000, N'C', N'Activo', 1),
(11, 1, N'402-1000011-1', N'Sandra Milagros', N'Fermín Taveras', 1, '1989-10-23', N'Soltero/a', N'sandra.fermin11@example.com', N'Av. Juan Pablo Duarte #43, Cerros de Gurabo, Santiago de los Caballeros', N'Cerca de la estación', 1, 25000, N'C', N'Inactivo', 1),
(12, 1, N'402-1000012-2', N'Juan Carlos', N'Mejía Báez', 1, '1970-06-06', N'Casado/a', N'juan.mejia12@example.com', N'Calle 27 de Febrero #46, El Embrujo, Santiago de los Caballeros', N'Cerca de la iglesia', 0, 0, NULL, N'Activo', 1),
(13, 1, N'402-1000013-3', N'Rosa Elena', N'Guzmán Tejada', 1, '1972-12-19', N'Viudo/a', N'rosa.guzman13@example.com', N'Calle Restauración #49, Ensanche Libertad, Santiago de los Caballeros', N'Cerca del colmado', 0, 0, NULL, N'Activo', 1),
(14, 1, N'402-1000014-4', N'Francisco Javier', N'Lora Abreu', 1, '1968-03-03', N'Casado/a', N'francisco.lora14@example.com', N'Av. Imbert #52, Rafey, Santiago de los Caballeros', N'Cerca de la escuela', 0, 0, NULL, N'Activo', 1);
SET IDENTITY_INSERT clientes OFF;

INSERT INTO clientes_telefonos (cliente_id, tipo, numero, principal) VALUES
(1, N'Celular', N'809-555-0101', 1),
(2, N'Celular', N'809-555-0102', 1),
(2, N'Casa', N'809-555-0202', 0),
(3, N'Celular', N'809-555-0103', 1),
(3, N'Trabajo', N'809-555-0303', 0),
(4, N'Celular', N'809-555-0104', 1),
(4, N'Casa', N'809-555-0204', 0),
(5, N'Celular', N'809-555-0105', 1),
(6, N'Celular', N'809-555-0106', 1),
(6, N'Casa', N'809-555-0206', 0),
(6, N'Trabajo', N'809-555-0306', 0),
(7, N'Celular', N'809-555-0107', 1),
(8, N'Celular', N'809-555-0108', 1),
(8, N'Casa', N'809-555-0208', 0),
(9, N'Celular', N'809-555-0109', 1),
(9, N'Trabajo', N'809-555-0309', 0),
(10, N'Celular', N'809-555-0110', 1),
(10, N'Casa', N'809-555-0210', 0),
(11, N'Celular', N'809-555-0111', 1),
(12, N'Celular', N'809-555-0112', 1),
(12, N'Casa', N'809-555-0212', 0),
(12, N'Trabajo', N'809-555-0312', 0),
(13, N'Celular', N'809-555-0113', 1),
(14, N'Celular', N'809-555-0114', 1),
(14, N'Casa', N'809-555-0214', 0);

INSERT INTO clientes_garantes (cliente_id, garante_id, fecha_asignacion, vigente, usuario_creacion_id) VALUES
(1, 2, '2026-04-20', 1, 1),
(2, 12, '2026-01-03', 1, 1),
(3, 12, '2026-08-25', 1, 1),
(4, 14, '2025-06-10', 0, 1),
(4, 13, '2026-02-01', 1, 1),
(5, 14, '2026-09-20', 1, 1),
(6, 1, '2026-10-15', 1, 1),
(7, 3, '2026-07-30', 1, 1),
(8, 4, '2026-05-28', 1, 1),
(9, 13, '2026-02-25', 1, 1),
(10, 5, '2026-03-12', 1, 1),
(11, 6, '2026-06-18', 1, 1);

INSERT INTO clientes_familiares (cliente_id, tipo, nombre, parentesco, telefono, direccion) VALUES
(1, N'Cercano', N'Familiar Cercano 1', N'Madre', N'809-555-0401', N'Misma dirección del cliente'),
(2, N'Cercano', N'Familiar Cercano 2', N'Padre', N'809-555-0402', N'Misma dirección del cliente'),
(3, N'Cercano', N'Familiar Cercano 3', N'Esposo/a', N'809-555-0403', N'Misma dirección del cliente'),
(4, N'Cercano', N'Familiar Cercano 4', N'Tío/a', N'809-555-0404', N'Misma dirección del cliente'),
(5, N'Cercano', N'Familiar Cercano 5', N'Primo/a', N'809-555-0405', N'Misma dirección del cliente'),
(6, N'Cercano', N'Familiar Cercano 6', N'Hermano/a', N'809-555-0406', N'Misma dirección del cliente'),
(7, N'Cercano', N'Familiar Cercano 7', N'Madre', N'809-555-0407', N'Misma dirección del cliente'),
(1, N'No convive', N'Familiar Externo 1', N'Esposo/a', N'809-555-0501', N'Calle Principal #21, Moca'),
(2, N'No convive', N'Familiar Externo 2', N'Tío/a', N'809-555-0502', N'Calle Principal #22, Moca'),
(3, N'No convive', N'Familiar Externo 3', N'Primo/a', N'809-555-0503', N'Calle Principal #23, Moca'),
(4, N'No convive', N'Familiar Externo 4', N'Hermano/a', N'809-555-0504', N'Calle Principal #24, Moca'),
(5, N'No convive', N'Familiar Externo 5', N'Madre', N'809-555-0505', N'Calle Principal #25, Moca');

INSERT INTO clientes_laborales (cliente_id, empresa, cargo_ocupacion, tiempo_laborando, sueldo, telefono_empresa, direccion_empresa) VALUES
(1, N'Supermercado Los Hidalgos', N'Cajera', N'2 años', 28500, N'809-555-0601', N'Zona industrial #1, Santiago'),
(2, N'Zona Franca Santiago', N'Supervisor de planta', N'3 años', 32000, N'809-555-0602', N'Zona industrial #2, Santiago'),
(3, N'Colegio San Ignacio', N'Docente', N'4 años', 35500, N'809-555-0603', N'Zona industrial #3, Santiago'),
(4, N'Clínica Corominas', N'Enfermera', N'5 años', 39000, N'809-555-0604', N'Zona industrial #4, Santiago'),
(5, N'Ferretería El Progreso', N'Encargado de ventas', N'6 años', 42500, N'809-555-0605', N'Zona industrial #5, Santiago'),
(6, N'Cooperativa Cibao', N'Contador', N'7 años', 46000, N'809-555-0606', N'Zona industrial #6, Santiago'),
(7, N'Banco Local Santiago', N'Oficial de negocios', N'8 años', 49500, N'809-555-0607', N'Zona industrial #7, Santiago'),
(8, N'Taller Mecánico Díaz', N'Mecánico', N'9 años', 53000, N'809-555-0608', N'Zona industrial #8, Santiago'),
(9, N'Farmacia Popular', N'Farmacéutica', N'1 años', 56500, N'809-555-0609', N'Zona industrial #9, Santiago'),
(10, N'Constructora Yaque', N'Ingeniero civil', N'2 años', 60000, N'809-555-0610', N'Zona industrial #10, Santiago'),
(11, N'Hotel Matum', N'Recepcionista', N'3 años', 63500, N'809-555-0611', N'Zona industrial #11, Santiago'),
(12, N'Distribuidora Cibao Norte', N'Vendedor', N'4 años', 67000, N'809-555-0612', N'Zona industrial #12, Santiago'),
(13, N'Transporte Rápido SRL', N'Chofer', N'5 años', 70500, N'809-555-0613', N'Zona industrial #13, Santiago'),
(14, N'Panadería La Espiga', N'Panadero', N'6 años', 74000, N'809-555-0614', N'Zona industrial #14, Santiago');

INSERT INTO clientes_referencias (cliente_id, nombre, telefono) VALUES
(1, N'Referencia 1 de cliente 1', N'809-555-0704'),
(1, N'Referencia 2 de cliente 1', N'809-555-0705'),
(1, N'Referencia 3 de cliente 1', N'809-555-0706'),
(2, N'Referencia 1 de cliente 2', N'809-555-0707'),
(2, N'Referencia 2 de cliente 2', N'809-555-0708'),
(2, N'Referencia 3 de cliente 2', N'809-555-0709'),
(3, N'Referencia 1 de cliente 3', N'809-555-0710'),
(3, N'Referencia 2 de cliente 3', N'809-555-0711'),
(3, N'Referencia 3 de cliente 3', N'809-555-0712'),
(4, N'Referencia 1 de cliente 4', N'809-555-0713'),
(4, N'Referencia 2 de cliente 4', N'809-555-0714'),
(4, N'Referencia 3 de cliente 4', N'809-555-0715'),
(5, N'Referencia 1 de cliente 5', N'809-555-0716'),
(5, N'Referencia 2 de cliente 5', N'809-555-0717'),
(5, N'Referencia 3 de cliente 5', N'809-555-0718'),
(6, N'Referencia 1 de cliente 6', N'809-555-0719'),
(6, N'Referencia 2 de cliente 6', N'809-555-0720'),
(6, N'Referencia 3 de cliente 6', N'809-555-0721'),
(7, N'Referencia 1 de cliente 7', N'809-555-0722'),
(7, N'Referencia 2 de cliente 7', N'809-555-0723'),
(7, N'Referencia 3 de cliente 7', N'809-555-0724'),
(8, N'Referencia 1 de cliente 8', N'809-555-0725'),
(8, N'Referencia 2 de cliente 8', N'809-555-0726'),
(8, N'Referencia 3 de cliente 8', N'809-555-0727'),
(9, N'Referencia 1 de cliente 9', N'809-555-0728'),
(9, N'Referencia 2 de cliente 9', N'809-555-0729'),
(9, N'Referencia 3 de cliente 9', N'809-555-0730'),
(10, N'Referencia 1 de cliente 10', N'809-555-0731'),
(10, N'Referencia 2 de cliente 10', N'809-555-0732'),
(10, N'Referencia 3 de cliente 10', N'809-555-0733'),
(11, N'Referencia 1 de cliente 11', N'809-555-0734'),
(11, N'Referencia 2 de cliente 11', N'809-555-0735'),
(11, N'Referencia 3 de cliente 11', N'809-555-0736');

SET IDENTITY_INSERT prestamos ON;
INSERT INTO prestamos (id, numero, cliente_id, tipo_prestamo_id, monto, plazo, tasa, tipo_plazo_id, tipo_amortizacion_id, incluye_sabado, incluye_domingo, tipo_mora, valor_mora, seguro_tipo, seguro_monto, gasto_cierre, gasto_cierre_financiado, desembolso, fecha_apertura, fecha_primer_pago, capital_pendiente, estado, usuario_creacion_id, fecha_creacion) VALUES
(1, N'PR-000001', 1, 1, 100000, 12, 3.0, 6, 2, 1, 1, N'Porcentaje', 5, N'Fijo', 0, 2000, 0, 98000, '2026-05-10', '2026-06-10', 62590.72, N'Activo', 1, '2026-05-10T09:00:00'),
(2, N'PR-000002', 3, 3, 50000, 10, 4.0, 5, 1, 1, 1, N'Monto', 150, N'Fijo', 0, 0, 0, 50000, '2026-09-01', '2026-09-16', 33150.00, N'Activo', 1, '2026-09-01T09:00:00'),
(3, N'PR-000003', 2, 1, 30000, 6, 2.5, 6, 1, 1, 1, N'Porcentaje', 5, N'Fijo', 0, 0, 0, 30000, '2026-01-05', '2026-02-05', 0.00, N'Saldado', 1, '2026-01-05T09:00:00'),
(4, N'PR-000004', 5, 2, 20000, 8, 4.0, 3, 2, 1, 1, N'Porcentaje', 5, N'Fijo', 0, 0, 0, 20000, '2026-09-28', '2026-10-05', 0.00, N'Renovado', 1, '2026-09-28T09:00:00'),
(5, N'PR-000005', 5, 2, 25000, 6, 3.5, 6, 2, 1, 1, N'Porcentaje', 5, N'Fijo', 0, 1500, 0, 23500, '2026-10-20', '2026-11-20', 25000, N'Activo', 1, '2026-10-20T09:00:00'),
(6, N'PR-000006', 6, 1, 10000, 20, 5.0, 1, 1, 0, 0, N'Monto', 25, N'Fijo', 0, 0, 0, 10000, '2026-10-26', '2026-10-27', 4000.00, N'Activo', 1, '2026-10-26T09:00:00'),
(7, N'PR-000007', 7, 3, 60000, 12, 3.0, 6, 3, 1, 1, N'Porcentaje', 3, N'Fijo', 0, 0, 0, 60000, '2026-08-05', '2026-09-05', 50000.00, N'Activo', 1, '2026-08-05T09:00:00'),
(8, N'PR-000008', 8, 3, 80000, 4, 2.0, 7, 1, 1, 1, N'Porcentaje', 5, N'Repartido', 2000, 1600, 1, 80000, '2026-06-01', '2026-09-01', 60000.00, N'Activo', 1, '2026-06-01T09:00:00'),
(9, N'PR-000009', 9, 3, 150000, 3, 1.5, 9, 1, 1, 1, N'Porcentaje', 5, N'Fijo', 500, 3000, 0, 147000, '2026-03-01', '2027-03-01', 150000, N'Activo', 1, '2026-03-01T09:00:00');
SET IDENTITY_INSERT prestamos OFF;

SET IDENTITY_INSERT prestamos_cuotas ON;
INSERT INTO prestamos_cuotas (id, prestamo_id, numero_cuota, fecha_pago, capital, interes, seguro, gasto_cierre, mora_acumulada, valor_cuota, capital_pagado, interes_pagado, seguro_pagado, gasto_cierre_pagado, mora_pagada, balance, estado) VALUES
(1, 1, 1, '2026-06-10', 7046.21, 3000.00, 0.00, 0.00, 502.31, 10046.21, 7046.21, 3000.00, 0.00, 0.00, 502.31, 92953.79, N'Pagada'),
(2, 1, 2, '2026-07-10', 7257.60, 2788.61, 0.00, 0.00, 502.31, 10046.21, 7257.60, 2788.61, 0.00, 0.00, 502.31, 85696.19, N'Pagada'),
(3, 1, 3, '2026-08-10', 7475.32, 2570.89, 0.00, 0.00, 502.31, 10046.21, 7475.32, 2570.89, 0.00, 0.00, 502.31, 78220.87, N'Pagada'),
(4, 1, 4, '2026-09-10', 7699.58, 2346.63, 0.00, 0.00, 502.31, 10046.21, 7699.58, 2346.63, 0.00, 0.00, 502.31, 70521.29, N'Pagada'),
(5, 1, 5, '2026-10-10', 7930.57, 2115.64, 0.00, 0.00, 502.31, 10046.21, 7930.57, 2115.64, 0.00, 0.00, 502.31, 62590.72, N'Pagada'),
(6, 1, 6, '2026-11-10', 8168.49, 1877.72, 0.00, 0.00, 502.31, 10046.21, 0, 0, 0, 0, 502.31, 54422.23, N'Vencida'),
(7, 1, 7, '2026-12-10', 8413.54, 1632.67, 0.00, 0.00, 0, 10046.21, 0, 0, 0, 0, 0, 46008.69, N'Pendiente'),
(8, 1, 8, '2027-01-10', 8665.95, 1380.26, 0.00, 0.00, 0, 10046.21, 0, 0, 0, 0, 0, 37342.74, N'Pendiente'),
(9, 1, 9, '2027-02-10', 8925.93, 1120.28, 0.00, 0.00, 0, 10046.21, 0, 0, 0, 0, 0, 28416.81, N'Pendiente'),
(10, 1, 10, '2027-03-10', 9193.71, 852.50, 0.00, 0.00, 0, 10046.21, 0, 0, 0, 0, 0, 19223.10, N'Pendiente'),
(11, 1, 11, '2027-04-10', 9469.52, 576.69, 0.00, 0.00, 0, 10046.21, 0, 0, 0, 0, 0, 9753.58, N'Pendiente'),
(12, 1, 12, '2027-05-10', 9753.58, 292.61, 0.00, 0.00, 0, 10046.19, 0, 0, 0, 0, 0, 0.00, N'Pendiente'),
(13, 2, 1, '2026-09-16', 5000.00, 1000.00, 0.00, 0.00, 150, 6000.00, 5000.00, 1000.00, 0.00, 0.00, 150, 45000.00, N'Pagada'),
(14, 2, 2, '2026-10-01', 5000.00, 1000.00, 0.00, 0.00, 150, 6000.00, 5000.00, 1000.00, 0.00, 0.00, 150, 40000.00, N'Pagada'),
(15, 2, 3, '2026-10-16', 5000.00, 1000.00, 0.00, 0.00, 150, 6000.00, 5000.00, 1000.00, 0.00, 0.00, 150, 35000.00, N'Pagada'),
(16, 2, 4, '2026-10-31', 5000.00, 1000.00, 0.00, 0.00, 150, 6000.00, 1850.00, 1000.00, 0.00, 0.00, 150, 30000.00, N'Parcial'),
(17, 2, 5, '2026-11-15', 5000.00, 1000.00, 0.00, 0.00, 150, 6000.00, 0, 0, 0, 0, 0, 25000.00, N'Vencida'),
(18, 2, 6, '2026-11-30', 5000.00, 1000.00, 0.00, 0.00, 0, 6000.00, 0, 0, 0, 0, 0, 20000.00, N'Pendiente'),
(19, 2, 7, '2026-12-15', 5000.00, 1000.00, 0.00, 0.00, 0, 6000.00, 0, 0, 0, 0, 0, 15000.00, N'Pendiente'),
(20, 2, 8, '2026-12-30', 5000.00, 1000.00, 0.00, 0.00, 0, 6000.00, 0, 0, 0, 0, 0, 10000.00, N'Pendiente'),
(21, 2, 9, '2027-01-14', 5000.00, 1000.00, 0.00, 0.00, 0, 6000.00, 0, 0, 0, 0, 0, 5000.00, N'Pendiente'),
(22, 2, 10, '2027-01-29', 5000.00, 1000.00, 0.00, 0.00, 0, 6000.00, 0, 0, 0, 0, 0, 0.00, N'Pendiente'),
(23, 3, 1, '2026-02-05', 5000.00, 750.00, 0.00, 0.00, 287.50, 5750.00, 5000.00, 750.00, 0.00, 0.00, 287.50, 25000.00, N'Pagada'),
(24, 3, 2, '2026-03-05', 5000.00, 750.00, 0.00, 0.00, 287.50, 5750.00, 5000.00, 750.00, 0.00, 0.00, 287.50, 20000.00, N'Pagada'),
(25, 3, 3, '2026-04-05', 5000.00, 750.00, 0.00, 0.00, 287.50, 5750.00, 5000.00, 750.00, 0.00, 0.00, 287.50, 15000.00, N'Pagada'),
(26, 3, 4, '2026-05-05', 5000.00, 750.00, 0.00, 0.00, 287.50, 5750.00, 5000.00, 750.00, 0.00, 0.00, 287.50, 10000.00, N'Pagada'),
(27, 3, 5, '2026-06-05', 5000.00, 750.00, 0.00, 0.00, 287.50, 5750.00, 5000.00, 750.00, 0.00, 0.00, 287.50, 5000.00, N'Pagada'),
(28, 3, 6, '2026-07-05', 5000.00, 750.00, 0.00, 0.00, 287.50, 5750.00, 5000.00, 750.00, 0.00, 0.00, 287.50, 0.00, N'Pagada'),
(29, 4, 1, '2026-10-05', 2419.47, 186.67, 0.00, 0.00, 130.31, 2606.14, 2419.47, 186.67, 0.00, 0.00, 130.31, 17580.53, N'Pagada'),
(30, 4, 2, '2026-10-12', 2442.06, 164.08, 0.00, 0.00, 130.31, 2606.14, 2442.06, 164.08, 0.00, 0.00, 130.31, 15138.47, N'Pagada'),
(31, 4, 3, '2026-10-19', 2464.85, 141.29, 0.00, 0.00, 130.31, 2606.14, 2464.85, 141.29, 0.00, 0.00, 130.31, 12673.62, N'Pagada'),
(32, 4, 4, '2026-10-26', 2487.85, 118.29, 0.00, 0.00, 130.31, 2606.14, 2487.85, 118.29, 0.00, 0.00, 130.31, 10185.77, N'Pagada'),
(33, 4, 5, '2026-11-02', 2511.07, 95.07, 0.00, 0.00, 130.31, 2606.14, 2511.07, 95.07, 0.00, 0.00, 130.31, 7674.70, N'Pagada'),
(34, 4, 6, '2026-11-09', 2534.51, 71.63, 0.00, 0.00, 130.31, 2606.14, 2534.51, 71.63, 0.00, 0.00, 130.31, 5140.19, N'Pagada'),
(35, 4, 7, '2026-11-16', 2558.16, 47.98, 0.00, 0.00, 0, 2606.14, 2558.16, 47.98, 0.00, 0.00, 0, 2582.03, N'Pagada'),
(36, 4, 8, '2026-11-23', 2582.03, 24.10, 0.00, 0.00, 0, 2606.13, 2582.03, 24.10, 0.00, 0.00, 0, 0.00, N'Pagada'),
(37, 5, 1, '2026-11-20', 3816.71, 875.00, 0.00, 0.00, 0, 4691.71, 0, 0, 0, 0, 0, 21183.29, N'Pendiente'),
(38, 5, 2, '2026-12-20', 3950.29, 741.42, 0.00, 0.00, 0, 4691.71, 0, 0, 0, 0, 0, 17233.00, N'Pendiente'),
(39, 5, 3, '2027-01-20', 4088.55, 603.16, 0.00, 0.00, 0, 4691.71, 0, 0, 0, 0, 0, 13144.45, N'Pendiente'),
(40, 5, 4, '2027-02-20', 4231.65, 460.06, 0.00, 0.00, 0, 4691.71, 0, 0, 0, 0, 0, 8912.80, N'Pendiente'),
(41, 5, 5, '2027-03-20', 4379.76, 311.95, 0.00, 0.00, 0, 4691.71, 0, 0, 0, 0, 0, 4533.04, N'Pendiente'),
(42, 5, 6, '2027-04-20', 4533.04, 158.66, 0.00, 0.00, 0, 4691.70, 0, 0, 0, 0, 0, 0.00, N'Pendiente'),
(43, 6, 1, '2026-10-27', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 9500.00, N'Pagada'),
(44, 6, 2, '2026-10-28', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 9000.00, N'Pagada'),
(45, 6, 3, '2026-10-29', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 8500.00, N'Pagada'),
(46, 6, 4, '2026-10-30', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 8000.00, N'Pagada'),
(47, 6, 5, '2026-11-02', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 7500.00, N'Pagada'),
(48, 6, 6, '2026-11-03', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 7000.00, N'Pagada'),
(49, 6, 7, '2026-11-04', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 6500.00, N'Pagada'),
(50, 6, 8, '2026-11-05', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 6000.00, N'Pagada'),
(51, 6, 9, '2026-11-06', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 5500.00, N'Pagada'),
(52, 6, 10, '2026-11-09', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 5000.00, N'Pagada'),
(53, 6, 11, '2026-11-10', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 4500.00, N'Pagada'),
(54, 6, 12, '2026-11-11', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 500.00, 16.67, 0.00, 0.00, 25, 4000.00, N'Pagada'),
(55, 6, 13, '2026-11-12', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 0, 0, 0, 0, 0, 3500.00, N'Vencida'),
(56, 6, 14, '2026-11-13', 500.00, 16.67, 0.00, 0.00, 25, 516.67, 0, 0, 0, 0, 0, 3000.00, N'Vencida'),
(57, 6, 15, '2026-11-16', 500.00, 16.67, 0.00, 0.00, 0, 516.67, 0, 0, 0, 0, 0, 2500.00, N'Pendiente'),
(58, 6, 16, '2026-11-17', 500.00, 16.67, 0.00, 0.00, 0, 516.67, 0, 0, 0, 0, 0, 2000.00, N'Pendiente'),
(59, 6, 17, '2026-11-18', 500.00, 16.67, 0.00, 0.00, 0, 516.67, 0, 0, 0, 0, 0, 1500.00, N'Pendiente'),
(60, 6, 18, '2026-11-19', 500.00, 16.67, 0.00, 0.00, 0, 516.67, 0, 0, 0, 0, 0, 1000.00, N'Pendiente'),
(61, 6, 19, '2026-11-20', 500.00, 16.67, 0.00, 0.00, 0, 516.67, 0, 0, 0, 0, 0, 500.00, N'Pendiente'),
(62, 6, 20, '2026-11-23', 500.00, 16.67, 0.00, 0.00, 0, 516.67, 0, 0, 0, 0, 0, 0.00, N'Pendiente'),
(63, 7, 1, '2026-09-05', 5000.00, 1800.00, 0.00, 0.00, 204.00, 6800.00, 5000.00, 1800.00, 0.00, 0.00, 204.00, 55000.00, N'Pagada'),
(64, 7, 2, '2026-10-05', 5000.00, 1650.00, 0.00, 0.00, 199.50, 6650.00, 5000.00, 1650.00, 0.00, 0.00, 199.50, 50000.00, N'Pagada'),
(65, 7, 3, '2026-11-05', 5000.00, 1500.00, 0.00, 0.00, 195.00, 6500.00, 0, 0, 0, 0, 0, 45000.00, N'Vencida'),
(66, 8, 1, '2026-09-01', 20000.00, 4800.00, 500.00, 400.00, 1285.00, 25700.00, 20000.00, 4800.00, 500.00, 400.00, 1285.00, 60000.00, N'Pagada'),
(67, 8, 2, '2026-12-01', 20000.00, 4800.00, 500.00, 400.00, 0, 25700.00, 0, 0, 0, 0, 0, 40000.00, N'Pendiente'),
(68, 8, 3, '2027-03-01', 20000.00, 4800.00, 500.00, 400.00, 0, 25700.00, 0, 0, 0, 0, 0, 20000.00, N'Pendiente'),
(69, 8, 4, '2027-06-01', 20000.00, 4800.00, 500.00, 400.00, 0, 25700.00, 0, 0, 0, 0, 0, 0.00, N'Pendiente'),
(70, 9, 1, '2027-03-01', 50000.00, 27000.00, 500.00, 0.00, 0, 77500.00, 0, 0, 0, 0, 0, 100000.00, N'Pendiente'),
(71, 9, 2, '2028-03-01', 50000.00, 27000.00, 500.00, 0.00, 0, 77500.00, 0, 0, 0, 0, 0, 50000.00, N'Pendiente'),
(72, 9, 3, '2029-03-01', 50000.00, 27000.00, 500.00, 0.00, 0, 77500.00, 0, 0, 0, 0, 0, 0.00, N'Pendiente');
SET IDENTITY_INSERT prestamos_cuotas OFF;

SET IDENTITY_INSERT recibos_ingreso ON;
INSERT INTO recibos_ingreso (id, numero, prestamo_id, fecha, tipo_pago, monto_recibido, total_mora, total_interes, total_seguro, total_gasto_cierre, total_capital, balance_posterior, formato_impreso, estado, observacion, usuario_creacion_id, fecha_creacion) VALUES
(1, N'RC-000001', 3, '2026-02-05T10:16:00', N'normal', 6037.50, 287.50, 750.00, 0.00, 0.00, 5000.00, 25000.00, N'POS80', N'Activo', NULL, 2, '2026-02-05T10:16:00'),
(2, N'RC-000002', 3, '2026-03-05T10:17:00', N'normal', 6037.50, 287.50, 750.00, 0.00, 0.00, 5000.00, 20000.00, N'A4', N'Activo', NULL, 2, '2026-03-05T10:17:00'),
(3, N'RC-000003', 3, '2026-04-05T10:18:00', N'normal', 6037.50, 287.50, 750.00, 0.00, 0.00, 5000.00, 15000.00, N'POS80', N'Activo', NULL, 2, '2026-04-05T10:18:00'),
(4, N'RC-000004', 3, '2026-05-05T10:19:00', N'normal', 6037.50, 287.50, 750.00, 0.00, 0.00, 5000.00, 10000.00, N'A4', N'Activo', NULL, 2, '2026-05-05T10:19:00'),
(5, N'RC-000005', 3, '2026-06-05T10:20:00', N'normal', 6037.50, 287.50, 750.00, 0.00, 0.00, 5000.00, 5000.00, N'POS80', N'Activo', NULL, 2, '2026-06-05T10:20:00'),
(6, N'RC-000006', 1, '2026-06-10T10:16:00', N'normal', 10548.52, 502.31, 3000.00, 0.00, 0.00, 7046.21, 92953.79, N'A4', N'Activo', NULL, 2, '2026-06-10T10:16:00'),
(7, N'RC-000007', 3, '2026-07-05T10:21:00', N'normal', 6037.50, 287.50, 750.00, 0.00, 0.00, 5000.00, 0.00, N'POS80', N'Activo', NULL, 2, '2026-07-05T10:21:00'),
(8, N'RC-000008', 1, '2026-07-10T10:17:00', N'normal', 10548.52, 502.31, 2788.61, 0.00, 0.00, 7257.60, 85696.19, N'A4', N'Activo', NULL, 2, '2026-07-10T10:17:00'),
(9, N'RC-000009', 1, '2026-08-10T10:18:00', N'normal', 10548.52, 502.31, 2570.89, 0.00, 0.00, 7475.32, 78220.87, N'POS80', N'Activo', NULL, 2, '2026-08-10T10:18:00'),
(10, N'RC-000010', 8, '2026-09-01T10:16:00', N'normal', 26985.00, 1285.00, 4800.00, 500.00, 400.00, 20000.00, 60000.00, N'A4', N'Activo', NULL, 2, '2026-09-01T10:16:00'),
(11, N'RC-000011', 7, '2026-09-05T10:16:00', N'normal', 7004.00, 204.00, 1800.00, 0.00, 0.00, 5000.00, 55000.00, N'POS80', N'Activo', NULL, 2, '2026-09-05T10:16:00'),
(12, N'RC-000012', 1, '2026-09-10T10:19:00', N'normal', 10548.52, 502.31, 2346.63, 0.00, 0.00, 7699.58, 70521.29, N'A4', N'Activo', NULL, 2, '2026-09-10T10:19:00'),
(13, N'RC-000013', 2, '2026-09-16T10:16:00', N'normal', 6150.00, 150, 1000.00, 0.00, 0.00, 5000.00, 45000.00, N'POS80', N'Activo', NULL, 2, '2026-09-16T10:16:00'),
(14, N'RC-000014', 2, '2026-10-01T10:17:00', N'normal', 6150.00, 150, 1000.00, 0.00, 0.00, 5000.00, 40000.00, N'A4', N'Activo', NULL, 2, '2026-10-01T10:17:00'),
(15, N'RC-000015', 4, '2026-10-05T10:16:00', N'normal', 2736.45, 130.31, 186.67, 0.00, 0.00, 2419.47, 17580.53, N'POS80', N'Activo', NULL, 2, '2026-10-05T10:16:00'),
(16, N'RC-000016', 7, '2026-10-05T10:17:00', N'normal', 6849.50, 199.50, 1650.00, 0.00, 0.00, 5000.00, 50000.00, N'A4', N'Activo', NULL, 2, '2026-10-05T10:17:00'),
(17, N'RC-000017', 1, '2026-10-10T10:20:00', N'normal', 10548.52, 502.31, 2115.64, 0.00, 0.00, 7930.57, 62590.72, N'POS80', N'Activo', NULL, 2, '2026-10-10T10:20:00'),
(18, N'RC-000018', 4, '2026-10-12T10:17:00', N'normal', 2736.45, 130.31, 164.08, 0.00, 0.00, 2442.06, 15138.47, N'A4', N'Activo', NULL, 2, '2026-10-12T10:17:00'),
(19, N'RC-000019', 2, '2026-10-16T10:18:00', N'normal', 6150.00, 150, 1000.00, 0.00, 0.00, 5000.00, 35000.00, N'POS80', N'Activo', NULL, 2, '2026-10-16T10:18:00'),
(20, N'RC-000020', 4, '2026-10-19T10:18:00', N'normal', 2736.45, 130.31, 141.29, 0.00, 0.00, 2464.85, 12673.62, N'A4', N'Activo', NULL, 2, '2026-10-19T10:18:00'),
(21, N'RC-000021', 4, '2026-10-20T10:00:00', N'normal', 13421.62, 390.93, 357.07, 0.00, 0.00, 12673.62, 0.00, N'POS80', N'Activo', N'Cancelación por renovación', 2, '2026-10-20T10:00:00'),
(22, N'RC-000022', 6, '2026-10-27T09:16:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 9500.00, N'A4', N'Activo', NULL, 2, '2026-10-27T09:16:00'),
(23, N'RC-000023', 6, '2026-10-28T09:17:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 9000.00, N'POS80', N'Activo', NULL, 2, '2026-10-28T09:17:00'),
(24, N'RC-000024', 6, '2026-10-29T09:18:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 8500.00, N'A4', N'Activo', NULL, 2, '2026-10-29T09:18:00'),
(25, N'RC-000025', 6, '2026-10-30T09:19:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 8000.00, N'POS80', N'Activo', NULL, 2, '2026-10-30T09:19:00'),
(26, N'RC-000026', 6, '2026-11-02T09:20:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 7500.00, N'A4', N'Activo', NULL, 2, '2026-11-02T09:20:00'),
(27, N'RC-000027', 2, '2026-11-02T11:05:00', N'normal', 3000.00, 150, 1000.00, 0.00, 0.00, 1850.00, 33150.00, N'POS80', N'Activo', N'Pago parcial de cuota', 2, '2026-11-02T11:05:00'),
(28, N'RC-000028', 6, '2026-11-03T09:21:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 7000.00, N'A4', N'Activo', NULL, 2, '2026-11-03T09:21:00'),
(29, N'RC-000029', 6, '2026-11-04T09:22:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 6500.00, N'POS80', N'Activo', NULL, 2, '2026-11-04T09:22:00'),
(30, N'RC-000030', 6, '2026-11-05T09:23:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 6000.00, N'A4', N'Activo', NULL, 2, '2026-11-05T09:23:00'),
(31, N'RC-000031', 6, '2026-11-06T09:24:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 5500.00, N'POS80', N'Activo', NULL, 2, '2026-11-06T09:24:00'),
(32, N'RC-000032', 6, '2026-11-09T09:25:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 5000.00, N'A4', N'Activo', NULL, 2, '2026-11-09T09:25:00'),
(33, N'RC-000033', 6, '2026-11-10T09:26:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 4500.00, N'POS80', N'Activo', NULL, 2, '2026-11-10T09:26:00'),
(34, N'RC-000034', 6, '2026-11-11T09:27:00', N'normal', 541.67, 25, 16.67, 0.00, 0.00, 500.00, 4000.00, N'A4', N'Activo', NULL, 2, '2026-11-11T09:27:00'),
(35, N'RC-000035', 1, '2026-11-15T16:40:00', N'solo_mora', 502.31, 502.31, 0, 0, 0, 0, 62590.72, N'POS80', N'Activo', N'Pago de mora acumulada', 2, '2026-11-15T16:40:00');
SET IDENTITY_INSERT recibos_ingreso OFF;

INSERT INTO recibos_ingreso_detalle (recibo_id, cuota_id, mora_aplicada, interes_aplicado, seguro_aplicado, gasto_cierre_aplicado, capital_aplicado) VALUES
(1, 23, 287.50, 750.00, 0.00, 0.00, 5000.00),
(2, 24, 287.50, 750.00, 0.00, 0.00, 5000.00),
(3, 25, 287.50, 750.00, 0.00, 0.00, 5000.00),
(4, 26, 287.50, 750.00, 0.00, 0.00, 5000.00),
(5, 27, 287.50, 750.00, 0.00, 0.00, 5000.00),
(6, 1, 502.31, 3000.00, 0.00, 0.00, 7046.21),
(7, 28, 287.50, 750.00, 0.00, 0.00, 5000.00),
(8, 2, 502.31, 2788.61, 0.00, 0.00, 7257.60),
(9, 3, 502.31, 2570.89, 0.00, 0.00, 7475.32),
(10, 66, 1285.00, 4800.00, 500.00, 400.00, 20000.00),
(11, 63, 204.00, 1800.00, 0.00, 0.00, 5000.00),
(12, 4, 502.31, 2346.63, 0.00, 0.00, 7699.58),
(13, 13, 150, 1000.00, 0.00, 0.00, 5000.00),
(14, 14, 150, 1000.00, 0.00, 0.00, 5000.00),
(15, 29, 130.31, 186.67, 0.00, 0.00, 2419.47),
(16, 64, 199.50, 1650.00, 0.00, 0.00, 5000.00),
(17, 5, 502.31, 2115.64, 0.00, 0.00, 7930.57),
(18, 30, 130.31, 164.08, 0.00, 0.00, 2442.06),
(19, 15, 150, 1000.00, 0.00, 0.00, 5000.00),
(20, 31, 130.31, 141.29, 0.00, 0.00, 2464.85),
(21, 32, 130.31, 118.29, 0.00, 0.00, 2487.85),
(21, 33, 130.31, 95.07, 0.00, 0.00, 2511.07),
(21, 34, 130.31, 71.63, 0.00, 0.00, 2534.51),
(21, 35, 0, 47.98, 0.00, 0.00, 2558.16),
(21, 36, 0, 24.10, 0.00, 0.00, 2582.03),
(22, 43, 25, 16.67, 0.00, 0.00, 500.00),
(23, 44, 25, 16.67, 0.00, 0.00, 500.00),
(24, 45, 25, 16.67, 0.00, 0.00, 500.00),
(25, 46, 25, 16.67, 0.00, 0.00, 500.00),
(26, 47, 25, 16.67, 0.00, 0.00, 500.00),
(27, 16, 150, 1000.00, 0.00, 0.00, 1850.00),
(28, 48, 25, 16.67, 0.00, 0.00, 500.00),
(29, 49, 25, 16.67, 0.00, 0.00, 500.00),
(30, 50, 25, 16.67, 0.00, 0.00, 500.00),
(31, 51, 25, 16.67, 0.00, 0.00, 500.00),
(32, 52, 25, 16.67, 0.00, 0.00, 500.00),
(33, 53, 25, 16.67, 0.00, 0.00, 500.00),
(34, 54, 25, 16.67, 0.00, 0.00, 500.00),
(35, 6, 502.31, 0, 0, 0, 0);

SET IDENTITY_INSERT renovaciones ON;
INSERT INTO renovaciones (id, cliente_id, prestamo_nuevo_id, fecha, total_saldo_cancelado, desembolso_final, usuario_creacion_id) VALUES
(1, 5, 5, '2026-10-20T10:00:00', 13421.62, 10078.38, 2);
SET IDENTITY_INSERT renovaciones OFF;

INSERT INTO renovaciones_detalle (renovacion_id, prestamo_anterior_id, base_saldo, monto_cancelado, descuento, recibo_id) VALUES
(1, 4, N'Balance general', 13421.62, 0, 21);

/* Las secuencias continúan después de los números ya usados */
ALTER SEQUENCE seq_prestamo RESTART WITH 10;
ALTER SEQUENCE seq_recibo   RESTART WITH 36;

COMMIT TRANSACTION;

PRINT 'Datos de prueba cargados correctamente.';
GO

/* ---------------------------------------------------------------------
   Consultas de control (opcionales). Todas deben dar 0 filas en las dos
   últimas y los conteos esperados en las primeras.
   --------------------------------------------------------------------- */
SELECT 'clientes' AS tabla, COUNT(*) AS filas FROM clientes WHERE es_cliente = 1
UNION ALL SELECT 'personas', COUNT(*) FROM clientes
UNION ALL SELECT 'prestamos', COUNT(*) FROM prestamos
UNION ALL SELECT 'cuotas', COUNT(*) FROM prestamos_cuotas
UNION ALL SELECT 'recibos', COUNT(*) FROM recibos_ingreso;

SELECT estado, COUNT(*) AS prestamos FROM prestamos GROUP BY estado;

-- capital_pendiente de la cabecera debe coincidir con monto - capital pagado en cuotas
SELECT p.numero, p.capital_pendiente, p.monto - SUM(c.capital_pagado) AS esperado
FROM prestamos p JOIN prestamos_cuotas c ON c.prestamo_id = p.id
GROUP BY p.numero, p.capital_pendiente, p.monto
HAVING p.capital_pendiente <> p.monto - SUM(c.capital_pagado);

-- el detalle de cada recibo debe sumar lo mismo que su cabecera
SELECT r.numero
FROM recibos_ingreso r JOIN recibos_ingreso_detalle d ON d.recibo_id = r.id
GROUP BY r.numero, r.total_mora, r.total_interes, r.total_seguro, r.total_gasto_cierre, r.total_capital
HAVING SUM(d.mora_aplicada) <> r.total_mora OR SUM(d.interes_aplicado) <> r.total_interes
    OR SUM(d.seguro_aplicado) <> r.total_seguro OR SUM(d.gasto_cierre_aplicado) <> r.total_gasto_cierre
    OR SUM(d.capital_aplicado) <> r.total_capital;
GO
