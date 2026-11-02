-- Distribuidora Toro - datos iniciales (roles, permisos, parámetros, usuario admin / admin123: cambiar la contraseña al ingresar)
-- Importar DESPUÉS de database/schema.sql, en una base vacía.
-- Distribuidora Toro - copia de seguridad
-- Generada: 02/11/2026 19:26:15
-- Base: distri_toro_generador

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';







INSERT INTO `cajas` (`id`,`nombre`,`activo`) VALUES
(1,'Caja oficina',1),
(2,'Caja mostrador',1);







INSERT INTO `clientes` (`id`,`uuid`,`codigo`,`nombre_comercio`,`titular`,`tipo_documento`,`documento`,`condicion_iva`,`tipo_comercio`,`direccion`,`localidad`,`zona_id`,`latitud`,`longitud`,`telefono`,`whatsapp`,`email`,`dias_visita`,`horario_visita`,`dias_entrega`,`horario_entrega`,`lista_precio_id`,`preventista_id`,`limite_credito`,`condicion_pago`,`dias_credito`,`estado`,`orden_ruta`,`observaciones`,`ultima_visita`,`ultima_compra`,`creado_en`,`actualizado_en`) VALUES
(1,NULL,'C0000','Consumidor Final',NULL,'DNI',NULL,'CF',NULL,NULL,'Salta',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,'0.00','contado',0,'activo',0,NULL,NULL,NULL,'2026-11-02 19:26:15',NULL);




INSERT INTO `configuracion` (`clave`,`valor`) VALUES
('actualizar_precios_al_recibir','1'),
('bloqueo_automatico_dias','0'),
('cliente_consumidor_final','1'),
('comprobante_reparto','factura'),
('costo_metodo','ultimo'),
('credito_modo','advertir'),
('credito_tolerancia_dias','0'),
('deposito_central','1'),
('empresa_condicion_iva','RI'),
('empresa_cuit','30-71234567-1'),
('empresa_domicilio','Av. Tavella 2850, Salta Capital'),
('empresa_email','ventas@distribuidoratoro.com.ar'),
('empresa_iibb','30-71234567-1'),
('empresa_inicio_actividades','2015-03-01'),
('empresa_leyenda','Gracias por su compra'),
('empresa_logo',''),
('empresa_nombre','Distribuidora Toro'),
('empresa_razon_social','Distribuidora Toro S.R.L.'),
('empresa_telefono','(0387) 431-5500'),
('login_bloqueo_min','15'),
('login_max_intentos','5'),
('pedido_movil_auto_confirmar','1'),
('pos_lista_precio','3'),
('punto_venta_defecto','1'),
('redondeo_modo','arriba'),
('stock_alerta_vencimiento_dias','30'),
('stock_permitir_negativo','0'),
('sugerencia_dias_cobertura','15'),
('sugerencia_dias_venta','30');

INSERT INTO `cuentas_bancarias` (`id`,`nombre`,`tipo`,`banco`,`numero`,`cbu_alias`,`saldo_inicial`,`activo`) VALUES
(1,'Banco Macro - Cta. Cte.','banco','Banco Macro',NULL,NULL,'0.00',1),
(2,'Mercado Pago','billetera','Mercado Pago',NULL,NULL,'0.00',1),
(3,'Tarjetas a acreditar','tarjeta','Posnet / Clover',NULL,NULL,'0.00',1);

INSERT INTO `depositos` (`id`,`nombre`,`tipo`,`vehiculo_id`,`activo`) VALUES
(1,'Depósito central','deposito',NULL,1);





INSERT INTO `gastos_categorias` (`id`,`nombre`,`activo`) VALUES
(1,'Combustible',1),
(2,'Sueldos y cargas sociales',1),
(3,'Alquiler',1),
(4,'Servicios (luz, agua, gas, internet)',1),
(5,'Mantenimiento de vehículos',1),
(6,'Impuestos y tasas',1),
(7,'Comisiones bancarias',1),
(8,'Librería y oficina',1),
(9,'Limpieza',1),
(10,'Comisiones de preventistas',1),
(11,'Otros',1);





INSERT INTO `listas_precios` (`id`,`nombre`,`margen_porc`,`redondeo`,`orden`,`activo`) VALUES
(1,'Mayorista','15.00','1.00',1,1),
(2,'Kiosco','25.00','1.00',2,1),
(3,'Minorista','35.00','5.00',3,1),
(4,'Especial','10.00','1.00',4,1);


INSERT INTO `migraciones` (`version`,`nombre`,`aplicada_en`) VALUES
('001','estructura inicial','2026-11-02 19:26:15'),
('002','clientes desde app','2026-11-02 19:26:15');








INSERT INTO `permisos` (`id`,`modulo`,`accion`,`descripcion`) VALUES
(1,'dashboard','ver','Panel principal — Ver'),
(2,'productos','ver','Productos y precios — Ver'),
(3,'productos','crear','Productos y precios — Crear'),
(4,'productos','editar','Productos y precios — Editar'),
(5,'productos','anular','Productos y precios — Anular'),
(6,'productos','exportar','Productos y precios — Exportar'),
(7,'productos','precios','Productos y precios — Modificar precios'),
(8,'stock','ver','Stock e inventario — Ver'),
(9,'stock','crear','Stock e inventario — Crear'),
(10,'stock','anular','Stock e inventario — Anular'),
(11,'stock','exportar','Stock e inventario — Exportar'),
(12,'clientes','ver','Clientes — Ver'),
(13,'clientes','crear','Clientes — Crear'),
(14,'clientes','editar','Clientes — Editar'),
(15,'clientes','anular','Clientes — Anular'),
(16,'clientes','exportar','Clientes — Exportar'),
(17,'proveedores','ver','Proveedores — Ver'),
(18,'proveedores','crear','Proveedores — Crear'),
(19,'proveedores','editar','Proveedores — Editar'),
(20,'proveedores','anular','Proveedores — Anular'),
(21,'proveedores','exportar','Proveedores — Exportar'),
(22,'compras','ver','Compras y recepciones — Ver'),
(23,'compras','crear','Compras y recepciones — Crear'),
(24,'compras','editar','Compras y recepciones — Editar'),
(25,'compras','anular','Compras y recepciones — Anular'),
(26,'compras','exportar','Compras y recepciones — Exportar'),
(27,'pedidos','ver','Pedidos (preventa) — Ver'),
(28,'pedidos','crear','Pedidos (preventa) — Crear'),
(29,'pedidos','editar','Pedidos (preventa) — Editar'),
(30,'pedidos','anular','Pedidos (preventa) — Anular'),
(31,'pedidos','exportar','Pedidos (preventa) — Exportar'),
(32,'ventas','ver','Ventas y facturación — Ver'),
(33,'ventas','crear','Ventas y facturación — Crear'),
(34,'ventas','anular','Ventas y facturación — Anular'),
(35,'ventas','exportar','Ventas y facturación — Exportar'),
(36,'reparto','ver','Reparto y logística — Ver'),
(37,'reparto','crear','Reparto y logística — Crear'),
(38,'reparto','editar','Reparto y logística — Editar'),
(39,'reparto','anular','Reparto y logística — Anular'),
(40,'reparto','exportar','Reparto y logística — Exportar'),
(41,'cobranzas','ver','Cobranzas — Ver'),
(42,'cobranzas','crear','Cobranzas — Crear'),
(43,'cobranzas','anular','Cobranzas — Anular'),
(44,'cobranzas','exportar','Cobranzas — Exportar'),
(45,'caja','ver','Caja — Ver'),
(46,'caja','crear','Caja — Crear'),
(47,'caja','anular','Caja — Anular'),
(48,'finanzas','ver','Bancos, cheques y gastos — Ver'),
(49,'finanzas','crear','Bancos, cheques y gastos — Crear'),
(50,'finanzas','anular','Bancos, cheques y gastos — Anular'),
(51,'finanzas','exportar','Bancos, cheques y gastos — Exportar'),
(52,'preventistas','ver','Preventistas y comisiones — Ver'),
(53,'preventistas','crear','Preventistas y comisiones — Crear'),
(54,'preventistas','editar','Preventistas y comisiones — Editar'),
(55,'preventistas','anular','Preventistas y comisiones — Anular'),
(56,'reportes','ver','Reportes — Ver'),
(57,'reportes','exportar','Reportes — Exportar'),
(58,'configuracion','ver','Configuración — Ver'),
(59,'configuracion','editar','Configuración — Editar'),
(60,'usuarios','ver','Usuarios y roles — Ver'),
(61,'usuarios','crear','Usuarios y roles — Crear'),
(62,'usuarios','editar','Usuarios y roles — Editar'),
(63,'usuarios','anular','Usuarios y roles — Anular'),
(64,'auditoria','ver','Auditoría — Ver'),
(65,'auditoria','exportar','Auditoría — Exportar'),
(66,'backups','ver','Copias de seguridad — Ver'),
(67,'backups','crear','Copias de seguridad — Crear'),
(68,'movil_preventa','ver','App preventista (celular) — Ver'),
(69,'movil_reparto','ver','App chofer (celular) — Ver');








INSERT INTO `puntos_venta` (`id`,`numero`,`nombre`,`proveedor_facturacion`,`activo`) VALUES
(1,1,'Casa central','interno',1);








INSERT INTO `rol_permiso` (`rol_id`,`permiso_id`) VALUES
(1,1),
(1,2),
(1,3),
(1,4),
(1,5),
(1,6),
(1,7),
(1,8),
(1,9),
(1,10),
(1,11),
(1,12),
(1,13),
(1,14),
(1,15),
(1,16),
(1,17),
(1,18),
(1,19),
(1,20),
(1,21),
(1,22),
(1,23),
(1,24),
(1,25),
(1,26),
(1,27),
(1,28),
(1,29),
(1,30),
(1,31),
(1,32),
(1,33),
(1,34),
(1,35),
(1,36),
(1,37),
(1,38),
(1,39),
(1,40),
(1,41),
(1,42),
(1,43),
(1,44),
(1,45),
(1,46),
(1,47),
(1,48),
(1,49),
(1,50),
(1,51),
(1,52),
(1,53),
(1,54),
(1,55),
(1,56),
(1,57),
(1,58),
(1,59),
(1,60),
(1,61),
(1,62),
(1,63),
(1,64),
(1,65),
(1,66),
(1,67),
(1,68),
(1,69),
(2,1),
(2,2),
(2,3),
(2,4),
(2,5),
(2,6),
(2,7),
(2,8),
(2,9),
(2,10),
(2,11),
(2,12),
(2,13),
(2,14),
(2,15),
(2,16),
(2,17),
(2,18),
(2,19),
(2,20),
(2,21),
(2,22),
(2,23),
(2,24),
(2,25),
(2,26),
(2,27),
(2,28),
(2,29),
(2,30),
(2,31),
(2,32),
(2,33),
(2,34),
(2,35),
(2,36),
(2,37),
(2,38),
(2,39),
(2,40),
(2,41),
(2,42),
(2,43),
(2,44),
(2,45),
(2,46),
(2,47),
(2,48),
(2,49),
(2,50),
(2,51),
(2,52),
(2,53),
(2,54),
(2,55),
(2,56),
(2,57),
(2,58),
(2,60),
(2,64),
(2,65),
(2,66),
(2,68),
(2,69),
(3,1),
(3,2),
(3,8),
(3,12),
(3,13),
(3,14),
(3,27),
(3,28),
(3,32),
(3,33),
(3,41),
(3,42),
(3,45),
(3,46),
(4,2),
(4,8),
(4,12),
(4,13),
(4,27),
(4,28),
(4,68),
(5,1),
(5,2),
(5,8),
(5,9),
(5,11),
(5,17),
(5,22),
(5,23),
(5,27),
(5,29),
(5,36),
(6,69),
(7,1),
(7,56),
(7,57);

INSERT INTO `roles` (`id`,`nombre`,`slug`,`descripcion`,`pantalla_inicio`,`es_sistema`) VALUES
(1,'Administrador','admin','Acceso total al sistema','/',1),
(2,'Encargado / Oficina','encargado','Gestión comercial y administrativa completa','/',1),
(3,'Vendedor de mostrador','mostrador','Ventas en mostrador, cobranzas y caja','/ventas/pos',1),
(4,'Preventista','preventista','Toma de pedidos en la calle desde el celular','/movil',1),
(5,'Depósito','deposito','Stock, recepción de mercadería y preparación de pedidos','/stock',1),
(6,'Chofer / Repartidor','chofer','Entregas y cobros en el reparto desde el celular','/chofer',1),
(7,'Contador','contador','Sólo lectura de reportes','/reportes',1);







INSERT INTO `usuarios` (`id`,`nombre`,`usuario`,`email`,`password_hash`,`rol_id`,`caja_id`,`activo`,`ultimo_login`,`creado_en`,`actualizado_en`) VALUES
(1,'Administrador','admin',NULL,'$2y$10$aD5ZLkFvMYZeAIYf.A0mL.tM4pFFzMmt/z/jp4ppvtaOsJQPGOPfy',1,1,1,NULL,'2026-11-02 19:26:15',NULL);




SET FOREIGN_KEY_CHECKS = 1;
