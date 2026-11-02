-- =====================================================================
-- Distribuidora Toro - Sistema de gestión
-- Estructura completa de la base de datos (MySQL 5.7+ / MariaDB 10.4+)
-- Motor InnoDB, charset utf8mb4.
-- Importar ANTES que datos_iniciales.sql o seed_demo.sql.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- NÚCLEO: configuración, usuarios, roles, permisos, auditoría
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS migraciones (
  version VARCHAR(20) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  aplicada_en DATETIME NOT NULL,
  PRIMARY KEY (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracion (
  clave VARCHAR(80) NOT NULL,
  valor TEXT NULL,
  PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  slug VARCHAR(40) NOT NULL,
  descripcion VARCHAR(255) NULL,
  pantalla_inicio VARCHAR(80) NOT NULL DEFAULT '/',
  es_sistema TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permisos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  modulo VARCHAR(40) NOT NULL,
  accion VARCHAR(30) NOT NULL,
  descripcion VARCHAR(150) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_permisos (modulo, accion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rol_permiso (
  rol_id INT UNSIGNED NOT NULL,
  permiso_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  KEY idx_rp_permiso (permiso_id),
  CONSTRAINT fk_rp_rol FOREIGN KEY (rol_id) REFERENCES roles (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cajas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  usuario VARCHAR(50) NOT NULL,
  email VARCHAR(120) NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol_id INT UNSIGNED NOT NULL,
  caja_id INT UNSIGNED NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_login DATETIME NULL,
  creado_en DATETIME NOT NULL,
  actualizado_en DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_usuario (usuario),
  KEY idx_usuarios_rol (rol_id),
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles (id),
  CONSTRAINT fk_usuarios_caja FOREIGN KEY (caja_id) REFERENCES cajas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_intentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario VARCHAR(50) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  exito TINYINT(1) NOT NULL DEFAULT 0,
  fecha DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_li_usuario_fecha (usuario, fecha),
  KEY idx_li_ip_fecha (ip, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha DATETIME NOT NULL,
  usuario_id INT UNSIGNED NULL,
  modulo VARCHAR(40) NOT NULL,
  accion VARCHAR(40) NOT NULL,
  entidad VARCHAR(50) NULL,
  entidad_id BIGINT UNSIGNED NULL,
  descripcion VARCHAR(255) NULL,
  datos_anteriores MEDIUMTEXT NULL,
  datos_nuevos MEDIUMTEXT NULL,
  ip VARCHAR(45) NULL,
  PRIMARY KEY (id),
  KEY idx_aud_fecha (fecha),
  KEY idx_aud_modulo (modulo, fecha),
  KEY idx_aud_entidad (entidad, entidad_id),
  KEY idx_aud_usuario (usuario_id, fecha),
  CONSTRAINT fk_aud_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CATÁLOGO
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS rubros (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_rubros_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS marcas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_marcas_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proveedores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  razon_social VARCHAR(150) NOT NULL,
  nombre_fantasia VARCHAR(150) NULL,
  cuit VARCHAR(13) NULL,
  condicion_iva VARCHAR(4) NOT NULL DEFAULT 'RI',
  direccion VARCHAR(200) NULL,
  localidad VARCHAR(100) NULL,
  telefono VARCHAR(50) NULL,
  email VARCHAR(120) NULL,
  contacto VARCHAR(100) NULL,
  plazo_pago_dias INT NOT NULL DEFAULT 0,
  observaciones TEXT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_prov_razon (razon_social),
  KEY idx_prov_cuit (cuit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS listas_precios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  margen_porc DECIMAL(8,2) NOT NULL DEFAULT 0,
  redondeo DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '0 = sin redondeo; 0.10, 0.50, 1, 5, 10...',
  orden INT NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(30) NOT NULL,
  codigo_barras VARCHAR(30) NULL,
  descripcion VARCHAR(200) NOT NULL,
  rubro_id INT UNSIGNED NULL,
  marca_id INT UNSIGNED NULL,
  proveedor_id INT UNSIGNED NULL,
  unidad_base VARCHAR(20) NOT NULL DEFAULT 'Unidad',
  costo DECIMAL(14,4) NOT NULL DEFAULT 0 COMMENT 'Último costo neto (sin IVA) por unidad base',
  costo_promedio DECIMAL(14,4) NOT NULL DEFAULT 0 COMMENT 'Costo promedio ponderado por unidad base',
  iva_alicuota DECIMAL(5,2) NOT NULL DEFAULT 21.00,
  impuesto_interno DECIMAL(14,4) NOT NULL DEFAULT 0 COMMENT 'Monto fijo de impuestos internos por unidad base',
  stock_minimo DECIMAL(14,3) NOT NULL DEFAULT 0,
  stock_maximo DECIMAL(14,3) NOT NULL DEFAULT 0,
  maneja_lotes TINYINT(1) NOT NULL DEFAULT 0,
  ubicacion VARCHAR(30) NULL COMMENT 'Ubicación en depósito para el picking',
  foto VARCHAR(150) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL,
  actualizado_en DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_productos_codigo (codigo),
  KEY idx_productos_barras (codigo_barras),
  KEY idx_productos_desc (descripcion),
  KEY idx_productos_rubro (rubro_id),
  KEY idx_productos_marca (marca_id),
  KEY idx_productos_proveedor (proveedor_id),
  CONSTRAINT fk_prod_rubro FOREIGN KEY (rubro_id) REFERENCES rubros (id),
  CONSTRAINT fk_prod_marca FOREIGN KEY (marca_id) REFERENCES marcas (id),
  CONSTRAINT fk_prod_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS producto_presentaciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  producto_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(40) NOT NULL,
  factor DECIMAL(12,3) NOT NULL DEFAULT 1 COMMENT 'Cantidad de unidades base que contiene',
  codigo_barras VARCHAR(30) NULL,
  es_venta TINYINT(1) NOT NULL DEFAULT 1,
  es_compra TINYINT(1) NOT NULL DEFAULT 1,
  predeterminada_venta TINYINT(1) NOT NULL DEFAULT 0,
  predeterminada_compra TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_pres_producto (producto_id),
  KEY idx_pres_barras (codigo_barras),
  CONSTRAINT fk_pres_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS producto_precios (
  producto_id INT UNSIGNED NOT NULL,
  lista_id INT UNSIGNED NOT NULL,
  modo VARCHAR(10) NOT NULL DEFAULT 'margen' COMMENT 'margen | fijo',
  margen_porc DECIMAL(8,2) NULL COMMENT 'NULL = usa el margen de la lista',
  precio DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Precio final (IVA incluido) por unidad base',
  actualizado_en DATETIME NULL,
  PRIMARY KEY (producto_id, lista_id),
  KEY idx_pp_lista (lista_id),
  CONSTRAINT fk_pp_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE,
  CONSTRAINT fk_pp_lista FOREIGN KEY (lista_id) REFERENCES listas_precios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS precios_historial (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha DATETIME NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  lista_id INT UNSIGNED NULL COMMENT 'NULL = cambio de costo',
  valor_anterior DECIMAL(14,4) NOT NULL,
  valor_nuevo DECIMAL(14,4) NOT NULL,
  motivo VARCHAR(150) NULL,
  usuario_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_ph_producto (producto_id, fecha),
  CONSTRAINT fk_ph_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE,
  CONSTRAINT fk_ph_lista FOREIGN KEY (lista_id) REFERENCES listas_precios (id) ON DELETE SET NULL,
  CONSTRAINT fk_ph_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promociones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  producto_id INT UNSIGNED NOT NULL,
  descripcion VARCHAR(100) NULL,
  cantidad_compra DECIMAL(12,3) NOT NULL COMMENT 'Unidades base que debe llevar (ej. 12)',
  cantidad_bonificada DECIMAL(12,3) NOT NULL COMMENT 'Unidades base sin cargo (ej. 1)',
  desde DATE NULL,
  hasta DATE NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_promo_producto (producto_id),
  CONSTRAINT fk_promo_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- LOGÍSTICA BASE (vehículos se necesitan para depósitos tipo camión)
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS vehiculos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  patente VARCHAR(10) NOT NULL,
  descripcion VARCHAR(100) NULL,
  capacidad_kg DECIMAL(10,2) NULL,
  capacidad_bultos INT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_vehiculos_patente (patente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS choferes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NULL,
  nombre VARCHAR(100) NOT NULL,
  dni VARCHAR(12) NULL,
  telefono VARCHAR(50) NULL,
  licencia_vencimiento DATE NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_choferes_usuario (usuario_id),
  CONSTRAINT fk_choferes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- STOCK
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS depositos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  tipo VARCHAR(10) NOT NULL DEFAULT 'deposito' COMMENT 'deposito | vehiculo',
  vehiculo_id INT UNSIGNED NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_depositos_vehiculo (vehiculo_id),
  CONSTRAINT fk_depositos_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock (
  producto_id INT UNSIGNED NOT NULL,
  deposito_id INT UNSIGNED NOT NULL,
  cantidad DECIMAL(14,3) NOT NULL DEFAULT 0 COMMENT 'Stock físico en unidad base',
  reservado DECIMAL(14,3) NOT NULL DEFAULT 0 COMMENT 'Reservado por pedidos confirmados',
  PRIMARY KEY (producto_id, deposito_id),
  KEY idx_stock_deposito (deposito_id),
  CONSTRAINT fk_stock_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_deposito FOREIGN KEY (deposito_id) REFERENCES depositos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_lotes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  producto_id INT UNSIGNED NOT NULL,
  deposito_id INT UNSIGNED NOT NULL,
  lote VARCHAR(40) NOT NULL,
  vencimiento DATE NULL,
  cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_lote (producto_id, deposito_id, lote),
  KEY idx_lote_venc (vencimiento),
  CONSTRAINT fk_lote_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE CASCADE,
  CONSTRAINT fk_lote_deposito FOREIGN KEY (deposito_id) REFERENCES depositos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movimientos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha DATETIME NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  deposito_id INT UNSIGNED NOT NULL,
  lote_id INT UNSIGNED NULL,
  tipo VARCHAR(30) NOT NULL,
  cantidad DECIMAL(14,3) NOT NULL COMMENT 'Positivo ingresa, negativo egresa (unidad base)',
  saldo DECIMAL(14,3) NOT NULL COMMENT 'Stock físico resultante en el depósito',
  costo_unitario DECIMAL(14,4) NOT NULL DEFAULT 0,
  documento_tipo VARCHAR(30) NULL,
  documento_id BIGINT UNSIGNED NULL,
  motivo VARCHAR(150) NULL,
  usuario_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_sm_producto (producto_id, fecha),
  KEY idx_sm_deposito (deposito_id, fecha),
  KEY idx_sm_documento (documento_tipo, documento_id),
  KEY idx_sm_fecha (fecha),
  CONSTRAINT fk_sm_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
  CONSTRAINT fk_sm_deposito FOREIGN KEY (deposito_id) REFERENCES depositos (id),
  CONSTRAINT fk_sm_lote FOREIGN KEY (lote_id) REFERENCES stock_lotes (id) ON DELETE SET NULL,
  CONSTRAINT fk_sm_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ajustes_stock (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha DATETIME NOT NULL,
  deposito_id INT UNSIGNED NOT NULL,
  motivo VARCHAR(30) NOT NULL COMMENT 'rotura | vencimiento | faltante | sobrante | consumo_interno | inventario | otro',
  observaciones VARCHAR(255) NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'aplicado',
  usuario_id INT UNSIGNED NULL,
  motivo_anulacion VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_aj_fecha (fecha),
  CONSTRAINT fk_aj_deposito FOREIGN KEY (deposito_id) REFERENCES depositos (id),
  CONSTRAINT fk_aj_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ajustes_stock_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ajuste_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad DECIMAL(14,3) NOT NULL COMMENT 'Unidad base, con signo',
  lote VARCHAR(40) NULL,
  vencimiento DATE NULL,
  costo_unitario DECIMAL(14,4) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_aji_ajuste (ajuste_id),
  CONSTRAINT fk_aji_ajuste FOREIGN KEY (ajuste_id) REFERENCES ajustes_stock (id) ON DELETE CASCADE,
  CONSTRAINT fk_aji_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transferencias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha DATETIME NOT NULL,
  deposito_origen_id INT UNSIGNED NOT NULL,
  deposito_destino_id INT UNSIGNED NOT NULL,
  observaciones VARCHAR(255) NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'aplicada',
  hoja_ruta_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  motivo_anulacion VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_tr_fecha (fecha),
  CONSTRAINT fk_tr_origen FOREIGN KEY (deposito_origen_id) REFERENCES depositos (id),
  CONSTRAINT fk_tr_destino FOREIGN KEY (deposito_destino_id) REFERENCES depositos (id),
  CONSTRAINT fk_tr_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transferencias_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  transferencia_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad DECIMAL(14,3) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_tri_tr (transferencia_id),
  CONSTRAINT fk_tri_tr FOREIGN KEY (transferencia_id) REFERENCES transferencias (id) ON DELETE CASCADE,
  CONSTRAINT fk_tri_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha DATETIME NOT NULL,
  deposito_id INT UNSIGNED NOT NULL,
  rubro_id INT UNSIGNED NULL,
  descripcion VARCHAR(150) NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'abierto' COMMENT 'abierto | aplicado | anulado',
  ajuste_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  aplicado_en DATETIME NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_inv_deposito FOREIGN KEY (deposito_id) REFERENCES depositos (id),
  CONSTRAINT fk_inv_rubro FOREIGN KEY (rubro_id) REFERENCES rubros (id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_ajuste FOREIGN KEY (ajuste_id) REFERENCES ajustes_stock (id) ON DELETE SET NULL,
  CONSTRAINT fk_inv_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventario_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  inventario_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  stock_sistema DECIMAL(14,3) NOT NULL DEFAULT 0,
  cantidad_contada DECIMAL(14,3) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_invi (inventario_id, producto_id),
  CONSTRAINT fk_invi_inv FOREIGN KEY (inventario_id) REFERENCES inventarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_invi_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CLIENTES, ZONAS Y PREVENTISTAS
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS preventistas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NULL,
  nombre VARCHAR(100) NOT NULL,
  telefono VARCHAR(50) NULL,
  comision_porc DECIMAL(6,2) NOT NULL DEFAULT 0,
  comision_base VARCHAR(10) NOT NULL DEFAULT 'ventas' COMMENT 'ventas | cobranzas',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_prev_usuario (usuario_id),
  CONSTRAINT fk_prev_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS zonas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NULL,
  dias_visita VARCHAR(20) NULL COMMENT 'Días ISO separados por coma: 1=lunes ... 7=domingo',
  dias_entrega VARCHAR(20) NULL,
  preventista_id INT UNSIGNED NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  CONSTRAINT fk_zonas_prev FOREIGN KEY (preventista_id) REFERENCES preventistas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(20) NOT NULL,
  nombre_comercio VARCHAR(150) NOT NULL,
  titular VARCHAR(150) NULL,
  tipo_documento VARCHAR(5) NOT NULL DEFAULT 'DNI' COMMENT 'CUIT | CUIL | DNI',
  documento VARCHAR(13) NULL,
  condicion_iva VARCHAR(4) NOT NULL DEFAULT 'CF' COMMENT 'RI | MONO | EX | CF',
  tipo_comercio VARCHAR(30) NULL,
  direccion VARCHAR(200) NULL,
  localidad VARCHAR(100) NULL,
  zona_id INT UNSIGNED NULL,
  latitud DECIMAL(10,7) NULL,
  longitud DECIMAL(10,7) NULL,
  telefono VARCHAR(50) NULL,
  whatsapp VARCHAR(30) NULL,
  email VARCHAR(120) NULL,
  dias_visita VARCHAR(20) NULL,
  horario_visita VARCHAR(60) NULL,
  dias_entrega VARCHAR(20) NULL,
  horario_entrega VARCHAR(60) NULL,
  lista_precio_id INT UNSIGNED NULL,
  preventista_id INT UNSIGNED NULL,
  limite_credito DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT '0 = sin límite',
  condicion_pago VARCHAR(10) NOT NULL DEFAULT 'contado' COMMENT 'contado | cta_cte',
  dias_credito INT NOT NULL DEFAULT 0,
  estado VARCHAR(10) NOT NULL DEFAULT 'activo' COMMENT 'activo | inactivo | bloqueado',
  orden_ruta INT NOT NULL DEFAULT 0,
  observaciones TEXT NULL,
  ultima_visita DATETIME NULL,
  ultima_compra DATETIME NULL,
  creado_en DATETIME NOT NULL,
  actualizado_en DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_clientes_codigo (codigo),
  KEY idx_clientes_nombre (nombre_comercio),
  KEY idx_clientes_doc (documento),
  KEY idx_clientes_zona (zona_id),
  KEY idx_clientes_prev (preventista_id),
  CONSTRAINT fk_cli_zona FOREIGN KEY (zona_id) REFERENCES zonas (id) ON DELETE SET NULL,
  CONSTRAINT fk_cli_lista FOREIGN KEY (lista_precio_id) REFERENCES listas_precios (id) ON DELETE SET NULL,
  CONSTRAINT fk_cli_prev FOREIGN KEY (preventista_id) REFERENCES preventistas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuenta corriente de clientes: importe > 0 aumenta la deuda, < 0 la disminuye.
-- "pendiente" es el importe aún no imputado (de deuda o de crédito a favor).
CREATE TABLE IF NOT EXISTS cc_clientes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  vencimiento DATE NULL,
  tipo VARCHAR(20) NOT NULL COMMENT 'comprobante | recibo | anulacion | saldo_inicial | cheque_rechazado | ajuste',
  documento_tipo VARCHAR(20) NULL,
  documento_id BIGINT UNSIGNED NULL,
  descripcion VARCHAR(150) NOT NULL,
  importe DECIMAL(14,2) NOT NULL,
  pendiente DECIMAL(14,2) NOT NULL DEFAULT 0,
  usuario_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_ccc_cliente (cliente_id, fecha),
  KEY idx_ccc_pend (cliente_id, pendiente),
  KEY idx_ccc_doc (documento_tipo, documento_id),
  CONSTRAINT fk_ccc_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id),
  CONSTRAINT fk_ccc_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cc_clientes_imputaciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  debito_id BIGINT UNSIGNED NOT NULL,
  credito_id BIGINT UNSIGNED NOT NULL,
  importe DECIMAL(14,2) NOT NULL,
  fecha DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ccci_deb (debito_id),
  KEY idx_ccci_cred (credito_id),
  CONSTRAINT fk_ccci_deb FOREIGN KEY (debito_id) REFERENCES cc_clientes (id) ON DELETE CASCADE,
  CONSTRAINT fk_ccci_cred FOREIGN KEY (credito_id) REFERENCES cc_clientes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NULL,
  cliente_id INT UNSIGNED NOT NULL,
  preventista_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  fecha DATETIME NOT NULL,
  resultado VARCHAR(12) NOT NULL COMMENT 'pedido | sin_pedido',
  motivo VARCHAR(30) NULL,
  observaciones VARCHAR(255) NULL,
  latitud DECIMAL(10,7) NULL,
  longitud DECIMAL(10,7) NULL,
  pedido_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_visitas_uuid (uuid),
  KEY idx_visitas_cliente (cliente_id, fecha),
  KEY idx_visitas_prev (preventista_id, fecha),
  CONSTRAINT fk_vis_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id),
  CONSTRAINT fk_vis_prev FOREIGN KEY (preventista_id) REFERENCES preventistas (id) ON DELETE SET NULL,
  CONSTRAINT fk_vis_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- COMPRAS Y PROVEEDORES
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ordenes_compra (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero INT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  proveedor_id INT UNSIGNED NOT NULL,
  deposito_id INT UNSIGNED NOT NULL,
  estado VARCHAR(16) NOT NULL DEFAULT 'borrador' COMMENT 'borrador | enviada | recibida_parcial | recibida | anulada',
  fecha_entrega DATE NULL,
  observaciones VARCHAR(255) NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  usuario_id INT UNSIGNED NULL,
  motivo_anulacion VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_oc_numero (numero),
  KEY idx_oc_proveedor (proveedor_id, fecha),
  KEY idx_oc_estado (estado),
  CONSTRAINT fk_oc_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores (id),
  CONSTRAINT fk_oc_deposito FOREIGN KEY (deposito_id) REFERENCES depositos (id),
  CONSTRAINT fk_oc_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orden_compra_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  orden_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  presentacion_id INT UNSIGNED NULL,
  presentacion_nombre VARCHAR(40) NOT NULL DEFAULT 'Unidad',
  cantidad DECIMAL(14,3) NOT NULL,
  factor DECIMAL(12,3) NOT NULL DEFAULT 1,
  cantidad_base DECIMAL(14,3) NOT NULL,
  costo_unitario DECIMAL(14,4) NOT NULL DEFAULT 0 COMMENT 'Costo neto por presentación',
  cantidad_recibida_base DECIMAL(14,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_oci_orden (orden_id),
  CONSTRAINT fk_oci_orden FOREIGN KEY (orden_id) REFERENCES ordenes_compra (id) ON DELETE CASCADE,
  CONSTRAINT fk_oci_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
  CONSTRAINT fk_oci_pres FOREIGN KEY (presentacion_id) REFERENCES producto_presentaciones (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS facturas_proveedor (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  proveedor_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(4) NOT NULL DEFAULT 'FA' COMMENT 'FA FB FC NCA NCB NCC NDA NDB NDC',
  punto_venta INT UNSIGNED NOT NULL DEFAULT 1,
  numero INT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  vencimiento DATE NULL,
  neto_gravado DECIMAL(14,2) NOT NULL DEFAULT 0,
  no_gravado DECIMAL(14,2) NOT NULL DEFAULT 0,
  iva_21 DECIMAL(14,2) NOT NULL DEFAULT 0,
  iva_105 DECIMAL(14,2) NOT NULL DEFAULT 0,
  iva_27 DECIMAL(14,2) NOT NULL DEFAULT 0,
  percepcion_iva DECIMAL(14,2) NOT NULL DEFAULT 0,
  percepcion_iibb DECIMAL(14,2) NOT NULL DEFAULT 0,
  impuestos_internos DECIMAL(14,2) NOT NULL DEFAULT 0,
  otros DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  orden_id INT UNSIGNED NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'registrada' COMMENT 'registrada | anulada',
  observaciones VARCHAR(255) NULL,
  usuario_id INT UNSIGNED NULL,
  motivo_anulacion VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_fp_numero (proveedor_id, tipo, punto_venta, numero),
  KEY idx_fp_fecha (fecha),
  CONSTRAINT fk_fp_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores (id),
  CONSTRAINT fk_fp_orden FOREIGN KEY (orden_id) REFERENCES ordenes_compra (id) ON DELETE SET NULL,
  CONSTRAINT fk_fp_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recepciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  orden_id INT UNSIGNED NULL,
  proveedor_id INT UNSIGNED NOT NULL,
  deposito_id INT UNSIGNED NOT NULL,
  remito_proveedor VARCHAR(30) NULL,
  factura_proveedor_id INT UNSIGNED NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  estado VARCHAR(10) NOT NULL DEFAULT 'aplicada' COMMENT 'aplicada | anulada',
  observaciones VARCHAR(255) NULL,
  usuario_id INT UNSIGNED NULL,
  motivo_anulacion VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_rec_numero (numero),
  KEY idx_rec_orden (orden_id),
  KEY idx_rec_fecha (fecha),
  CONSTRAINT fk_rec_orden FOREIGN KEY (orden_id) REFERENCES ordenes_compra (id),
  CONSTRAINT fk_rec_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores (id),
  CONSTRAINT fk_rec_deposito FOREIGN KEY (deposito_id) REFERENCES depositos (id),
  CONSTRAINT fk_rec_factura FOREIGN KEY (factura_proveedor_id) REFERENCES facturas_proveedor (id) ON DELETE SET NULL,
  CONSTRAINT fk_rec_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recepcion_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recepcion_id INT UNSIGNED NOT NULL,
  orden_item_id INT UNSIGNED NULL,
  producto_id INT UNSIGNED NOT NULL,
  presentacion_nombre VARCHAR(40) NOT NULL DEFAULT 'Unidad',
  cantidad DECIMAL(14,3) NOT NULL,
  factor DECIMAL(12,3) NOT NULL DEFAULT 1,
  cantidad_base DECIMAL(14,3) NOT NULL,
  costo_unitario_base DECIMAL(14,4) NOT NULL DEFAULT 0,
  lote VARCHAR(40) NULL,
  vencimiento DATE NULL,
  PRIMARY KEY (id),
  KEY idx_reci_rec (recepcion_id),
  CONSTRAINT fk_reci_rec FOREIGN KEY (recepcion_id) REFERENCES recepciones (id) ON DELETE CASCADE,
  CONSTRAINT fk_reci_oci FOREIGN KEY (orden_item_id) REFERENCES orden_compra_items (id) ON DELETE SET NULL,
  CONSTRAINT fk_reci_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS devoluciones_proveedor (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  proveedor_id INT UNSIGNED NOT NULL,
  deposito_id INT UNSIGNED NOT NULL,
  motivo VARCHAR(255) NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  nota_credito_id INT UNSIGNED NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'aplicada',
  usuario_id INT UNSIGNED NULL,
  motivo_anulacion VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_dp_numero (numero),
  CONSTRAINT fk_dp_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores (id),
  CONSTRAINT fk_dp_deposito FOREIGN KEY (deposito_id) REFERENCES depositos (id),
  CONSTRAINT fk_dp_nc FOREIGN KEY (nota_credito_id) REFERENCES facturas_proveedor (id) ON DELETE SET NULL,
  CONSTRAINT fk_dp_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS devoluciones_proveedor_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  devolucion_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad_base DECIMAL(14,3) NOT NULL,
  costo_unitario_base DECIMAL(14,4) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_dpi_dev (devolucion_id),
  CONSTRAINT fk_dpi_dev FOREIGN KEY (devolucion_id) REFERENCES devoluciones_proveedor (id) ON DELETE CASCADE,
  CONSTRAINT fk_dpi_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cuenta corriente de proveedores: importe > 0 = le debemos más; < 0 = pago / NC.
CREATE TABLE IF NOT EXISTS cc_proveedores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  proveedor_id INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  vencimiento DATE NULL,
  tipo VARCHAR(20) NOT NULL,
  documento_tipo VARCHAR(20) NULL,
  documento_id BIGINT UNSIGNED NULL,
  descripcion VARCHAR(150) NOT NULL,
  importe DECIMAL(14,2) NOT NULL,
  pendiente DECIMAL(14,2) NOT NULL DEFAULT 0,
  usuario_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_ccp_prov (proveedor_id, fecha),
  KEY idx_ccp_pend (proveedor_id, pendiente),
  KEY idx_ccp_doc (documento_tipo, documento_id),
  CONSTRAINT fk_ccp_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores (id),
  CONSTRAINT fk_ccp_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cc_proveedores_imputaciones (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  debito_id BIGINT UNSIGNED NOT NULL,
  credito_id BIGINT UNSIGNED NOT NULL,
  importe DECIMAL(14,2) NOT NULL,
  fecha DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ccpi_deb (debito_id),
  KEY idx_ccpi_cred (credito_id),
  CONSTRAINT fk_ccpi_deb FOREIGN KEY (debito_id) REFERENCES cc_proveedores (id) ON DELETE CASCADE,
  CONSTRAINT fk_ccpi_cred FOREIGN KEY (credito_id) REFERENCES cc_proveedores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- FINANZAS: cajas, bancos, cheques, gastos
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS caja_sesiones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  caja_id INT UNSIGNED NOT NULL,
  usuario_apertura_id INT UNSIGNED NULL,
  fecha_apertura DATETIME NOT NULL,
  saldo_inicial DECIMAL(14,2) NOT NULL DEFAULT 0,
  usuario_cierre_id INT UNSIGNED NULL,
  fecha_cierre DATETIME NULL,
  saldo_teorico DECIMAL(14,2) NULL,
  saldo_contado DECIMAL(14,2) NULL,
  diferencia DECIMAL(14,2) NULL,
  arqueo TEXT NULL COMMENT 'Detalle JSON de billetes contados',
  estado VARCHAR(10) NOT NULL DEFAULT 'abierta',
  observaciones VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_cs_caja (caja_id, estado),
  CONSTRAINT fk_cs_caja FOREIGN KEY (caja_id) REFERENCES cajas (id),
  CONSTRAINT fk_cs_uap FOREIGN KEY (usuario_apertura_id) REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT fk_cs_uci FOREIGN KEY (usuario_cierre_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS caja_movimientos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  caja_sesion_id INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  tipo VARCHAR(8) NOT NULL COMMENT 'ingreso | egreso',
  concepto VARCHAR(150) NOT NULL,
  importe DECIMAL(14,2) NOT NULL,
  origen_tipo VARCHAR(20) NULL COMMENT 'recibo | gasto | pago_proveedor | rendicion | manual | anulacion',
  origen_id BIGINT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_cm_sesion (caja_sesion_id),
  KEY idx_cm_fecha (fecha),
  KEY idx_cm_origen (origen_tipo, origen_id),
  CONSTRAINT fk_cm_sesion FOREIGN KEY (caja_sesion_id) REFERENCES caja_sesiones (id),
  CONSTRAINT fk_cm_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cuentas_bancarias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  tipo VARCHAR(10) NOT NULL DEFAULT 'banco' COMMENT 'banco | billetera | tarjeta',
  banco VARCHAR(80) NULL,
  numero VARCHAR(40) NULL,
  cbu_alias VARCHAR(60) NULL,
  saldo_inicial DECIMAL(14,2) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banco_movimientos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cuenta_id INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  tipo VARCHAR(8) NOT NULL COMMENT 'ingreso | egreso',
  concepto VARCHAR(150) NOT NULL,
  importe DECIMAL(14,2) NOT NULL,
  referencia VARCHAR(60) NULL,
  origen_tipo VARCHAR(20) NULL,
  origen_id BIGINT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_bm_cuenta (cuenta_id, fecha),
  KEY idx_bm_origen (origen_tipo, origen_id),
  CONSTRAINT fk_bm_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas_bancarias (id),
  CONSTRAINT fk_bm_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cheques (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero VARCHAR(20) NOT NULL,
  banco VARCHAR(80) NOT NULL,
  librador VARCHAR(150) NULL,
  cuit_librador VARCHAR(13) NULL,
  fecha_emision DATE NULL,
  fecha_pago DATE NOT NULL,
  importe DECIMAL(14,2) NOT NULL,
  estado VARCHAR(12) NOT NULL DEFAULT 'cartera' COMMENT 'cartera | depositado | endosado | rechazado | anulado',
  cliente_id INT UNSIGNED NULL,
  recibo_id INT UNSIGNED NULL,
  proveedor_id INT UNSIGNED NULL,
  pago_proveedor_id INT UNSIGNED NULL,
  cuenta_deposito_id INT UNSIGNED NULL,
  fecha_estado DATETIME NULL,
  observaciones VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ch_estado (estado, fecha_pago),
  KEY idx_ch_cliente (cliente_id),
  CONSTRAINT fk_ch_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id) ON DELETE SET NULL,
  CONSTRAINT fk_ch_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores (id) ON DELETE SET NULL,
  CONSTRAINT fk_ch_cuenta FOREIGN KEY (cuenta_deposito_id) REFERENCES cuentas_bancarias (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gastos_categorias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(80) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_gc_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gastos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  fecha DATE NOT NULL,
  categoria_id INT UNSIGNED NOT NULL,
  descripcion VARCHAR(200) NOT NULL,
  importe DECIMAL(14,2) NOT NULL,
  forma_pago VARCHAR(12) NOT NULL COMMENT 'efectivo | banco',
  caja_sesion_id INT UNSIGNED NULL,
  cuenta_bancaria_id INT UNSIGNED NULL,
  comprobante VARCHAR(40) NULL,
  vehiculo_id INT UNSIGNED NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'registrado' COMMENT 'registrado | anulado',
  usuario_id INT UNSIGNED NULL,
  motivo_anulacion VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_gastos_fecha (fecha),
  KEY idx_gastos_cat (categoria_id),
  CONSTRAINT fk_gastos_cat FOREIGN KEY (categoria_id) REFERENCES gastos_categorias (id),
  CONSTRAINT fk_gastos_sesion FOREIGN KEY (caja_sesion_id) REFERENCES caja_sesiones (id),
  CONSTRAINT fk_gastos_cuenta FOREIGN KEY (cuenta_bancaria_id) REFERENCES cuentas_bancarias (id),
  CONSTRAINT fk_gastos_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos (id) ON DELETE SET NULL,
  CONSTRAINT fk_gastos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos_proveedor (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  proveedor_id INT UNSIGNED NOT NULL,
  total DECIMAL(14,2) NOT NULL,
  observaciones VARCHAR(255) NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'emitido' COMMENT 'emitido | anulado',
  usuario_id INT UNSIGNED NULL,
  motivo_anulacion VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pp_numero (numero),
  KEY idx_pagprov_prov (proveedor_id, fecha),
  CONSTRAINT fk_pagprov_prov FOREIGN KEY (proveedor_id) REFERENCES proveedores (id),
  CONSTRAINT fk_pagprov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos_proveedor_detalle (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pago_id INT UNSIGNED NOT NULL,
  forma_pago VARCHAR(15) NOT NULL COMMENT 'efectivo | transferencia | cheque_propio | cheque_tercero',
  importe DECIMAL(14,2) NOT NULL,
  caja_sesion_id INT UNSIGNED NULL,
  cuenta_bancaria_id INT UNSIGNED NULL,
  cheque_id INT UNSIGNED NULL,
  referencia VARCHAR(60) NULL,
  PRIMARY KEY (id),
  KEY idx_ppd_pago (pago_id),
  CONSTRAINT fk_ppd_pago FOREIGN KEY (pago_id) REFERENCES pagos_proveedor (id) ON DELETE CASCADE,
  CONSTRAINT fk_ppd_sesion FOREIGN KEY (caja_sesion_id) REFERENCES caja_sesiones (id),
  CONSTRAINT fk_ppd_cuenta FOREIGN KEY (cuenta_bancaria_id) REFERENCES cuentas_bancarias (id),
  CONSTRAINT fk_ppd_cheque FOREIGN KEY (cheque_id) REFERENCES cheques (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PEDIDOS, COMPROBANTES DE VENTA Y COBRANZAS
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS puntos_venta (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero INT UNSIGNED NOT NULL,
  nombre VARCHAR(60) NOT NULL,
  proveedor_facturacion VARCHAR(20) NOT NULL DEFAULT 'interno' COMMENT 'interno | arca (futuro)',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pv_numero (numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS numeracion (
  punto_venta_id INT UNSIGNED NOT NULL,
  tipo VARCHAR(4) NOT NULL COMMENT 'FA FB FC NCA NCB NCC NDA NDB NDC RM PR RC PED OC REC DP PP HR',
  ultimo_numero INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (punto_venta_id, tipo),
  CONSTRAINT fk_num_pv FOREIGN KEY (punto_venta_id) REFERENCES puntos_venta (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hojas_ruta (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero INT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  vehiculo_id INT UNSIGNED NOT NULL,
  chofer_id INT UNSIGNED NOT NULL,
  zona_id INT UNSIGNED NULL,
  deposito_origen_id INT UNSIGNED NOT NULL,
  estado VARCHAR(12) NOT NULL DEFAULT 'armado' COMMENT 'armado | en_reparto | rendida | anulada',
  observaciones VARCHAR(255) NULL,
  salida_en DATETIME NULL,
  rendida_en DATETIME NULL,
  usuario_id INT UNSIGNED NULL,
  motivo_anulacion VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_hr_numero (numero),
  KEY idx_hr_fecha (fecha, estado),
  KEY idx_hr_chofer (chofer_id, fecha),
  CONSTRAINT fk_hr_vehiculo FOREIGN KEY (vehiculo_id) REFERENCES vehiculos (id),
  CONSTRAINT fk_hr_chofer FOREIGN KEY (chofer_id) REFERENCES choferes (id),
  CONSTRAINT fk_hr_zona FOREIGN KEY (zona_id) REFERENCES zonas (id) ON DELETE SET NULL,
  CONSTRAINT fk_hr_deposito FOREIGN KEY (deposito_origen_id) REFERENCES depositos (id),
  CONSTRAINT fk_hr_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedidos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NULL COMMENT 'Identificador generado en el dispositivo (evita duplicados offline)',
  numero INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  fecha_entrega DATE NULL,
  cliente_id INT UNSIGNED NOT NULL,
  preventista_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  lista_precio_id INT UNSIGNED NULL,
  deposito_id INT UNSIGNED NOT NULL,
  estado VARCHAR(18) NOT NULL DEFAULT 'borrador' COMMENT 'borrador | confirmado | en_preparacion | preparado | en_reparto | entregado | entregado_parcial | rechazado | anulado',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  descuento_porc DECIMAL(6,2) NOT NULL DEFAULT 0,
  descuento_importe DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  observaciones VARCHAR(255) NULL,
  origen VARCHAR(12) NOT NULL DEFAULT 'oficina' COMMENT 'oficina | movil',
  revision_requerida TINYINT(1) NOT NULL DEFAULT 0,
  revision_detalle TEXT NULL,
  hoja_ruta_id INT UNSIGNED NULL,
  comprobante_id INT UNSIGNED NULL,
  motivo_anulacion VARCHAR(255) NULL,
  motivo_rechazo VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL,
  actualizado_en DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_pedidos_numero (numero),
  UNIQUE KEY uk_pedidos_uuid (uuid),
  KEY idx_pedidos_cliente (cliente_id, fecha),
  KEY idx_pedidos_estado (estado, fecha_entrega),
  KEY idx_pedidos_prev (preventista_id, fecha),
  KEY idx_pedidos_hr (hoja_ruta_id),
  CONSTRAINT fk_ped_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id),
  CONSTRAINT fk_ped_prev FOREIGN KEY (preventista_id) REFERENCES preventistas (id) ON DELETE SET NULL,
  CONSTRAINT fk_ped_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT fk_ped_lista FOREIGN KEY (lista_precio_id) REFERENCES listas_precios (id) ON DELETE SET NULL,
  CONSTRAINT fk_ped_deposito FOREIGN KEY (deposito_id) REFERENCES depositos (id),
  CONSTRAINT fk_ped_hr FOREIGN KEY (hoja_ruta_id) REFERENCES hojas_ruta (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  pedido_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  presentacion_id INT UNSIGNED NULL,
  presentacion_nombre VARCHAR(40) NOT NULL DEFAULT 'Unidad',
  cantidad DECIMAL(14,3) NOT NULL COMMENT 'En la presentación elegida',
  factor DECIMAL(12,3) NOT NULL DEFAULT 1,
  cantidad_base DECIMAL(14,3) NOT NULL,
  bonificacion_base DECIMAL(14,3) NOT NULL DEFAULT 0 COMMENT 'Unidades base sin cargo',
  precio_unitario DECIMAL(14,2) NOT NULL COMMENT 'Precio final por presentación',
  descuento_porc DECIMAL(6,2) NOT NULL DEFAULT 0,
  iva_alicuota DECIMAL(5,2) NOT NULL DEFAULT 21,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  reservado_base DECIMAL(14,3) NOT NULL DEFAULT 0,
  entregado_base DECIMAL(14,3) NULL,
  PRIMARY KEY (id),
  KEY idx_pi_pedido (pedido_id),
  KEY idx_pi_producto (producto_id),
  CONSTRAINT fk_pi_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_producto FOREIGN KEY (producto_id) REFERENCES productos (id),
  CONSTRAINT fk_pi_pres FOREIGN KEY (presentacion_id) REFERENCES producto_presentaciones (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comprobantes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tipo VARCHAR(4) NOT NULL COMMENT 'FA FB FC NCA NCB NCC NDA NDB NDC RM PR',
  letra CHAR(1) NOT NULL DEFAULT 'X',
  punto_venta_id INT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  vencimiento DATE NULL,
  cliente_id INT UNSIGNED NOT NULL,
  cliente_nombre VARCHAR(150) NOT NULL,
  cliente_documento VARCHAR(20) NULL,
  cliente_condicion_iva VARCHAR(4) NOT NULL DEFAULT 'CF',
  cliente_direccion VARCHAR(200) NULL,
  pedido_id INT UNSIGNED NULL,
  comprobante_asociado_id INT UNSIGNED NULL COMMENT 'Factura a la que se asocia una NC/ND',
  hoja_ruta_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  preventista_id INT UNSIGNED NULL,
  deposito_id INT UNSIGNED NULL,
  lista_precio_id INT UNSIGNED NULL,
  condicion_venta VARCHAR(10) NOT NULL DEFAULT 'contado',
  origen VARCHAR(12) NOT NULL DEFAULT 'oficina' COMMENT 'oficina | mostrador | reparto | pedido',
  neto_21 DECIMAL(14,2) NOT NULL DEFAULT 0,
  neto_105 DECIMAL(14,2) NOT NULL DEFAULT 0,
  neto_exento DECIMAL(14,2) NOT NULL DEFAULT 0,
  iva_21 DECIMAL(14,2) NOT NULL DEFAULT 0,
  iva_105 DECIMAL(14,2) NOT NULL DEFAULT 0,
  impuestos_internos DECIMAL(14,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Antes del descuento general',
  descuento_porc DECIMAL(6,2) NOT NULL DEFAULT 0,
  descuento_importe DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  costo_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  mueve_stock TINYINT(1) NOT NULL DEFAULT 1,
  afecta_cc TINYINT(1) NOT NULL DEFAULT 1,
  estado VARCHAR(10) NOT NULL DEFAULT 'emitido' COMMENT 'emitido | anulado',
  cae VARCHAR(20) NULL,
  cae_vencimiento DATE NULL,
  proveedor_facturacion VARCHAR(20) NOT NULL DEFAULT 'interno',
  observaciones VARCHAR(255) NULL,
  motivo_anulacion VARCHAR(255) NULL,
  anulado_por INT UNSIGNED NULL,
  anulado_en DATETIME NULL,
  liquidacion_id INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_comp_numero (tipo, punto_venta_id, numero),
  KEY idx_comp_fecha (fecha, estado),
  KEY idx_comp_cliente (cliente_id, fecha),
  KEY idx_comp_prev (preventista_id, fecha),
  KEY idx_comp_pedido (pedido_id),
  KEY idx_comp_hr (hoja_ruta_id),
  CONSTRAINT fk_comp_pv FOREIGN KEY (punto_venta_id) REFERENCES puntos_venta (id),
  CONSTRAINT fk_comp_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id),
  CONSTRAINT fk_comp_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id) ON DELETE SET NULL,
  CONSTRAINT fk_comp_asoc FOREIGN KEY (comprobante_asociado_id) REFERENCES comprobantes (id) ON DELETE SET NULL,
  CONSTRAINT fk_comp_hr FOREIGN KEY (hoja_ruta_id) REFERENCES hojas_ruta (id) ON DELETE SET NULL,
  CONSTRAINT fk_comp_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
  CONSTRAINT fk_comp_prev FOREIGN KEY (preventista_id) REFERENCES preventistas (id) ON DELETE SET NULL,
  CONSTRAINT fk_comp_deposito FOREIGN KEY (deposito_id) REFERENCES depositos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comprobante_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  comprobante_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NULL,
  pedido_item_id INT UNSIGNED NULL,
  descripcion VARCHAR(200) NOT NULL,
  presentacion_nombre VARCHAR(40) NOT NULL DEFAULT 'Unidad',
  cantidad DECIMAL(14,3) NOT NULL,
  factor DECIMAL(12,3) NOT NULL DEFAULT 1,
  cantidad_base DECIMAL(14,3) NOT NULL,
  bonificacion_base DECIMAL(14,3) NOT NULL DEFAULT 0,
  precio_unitario DECIMAL(14,2) NOT NULL COMMENT 'Precio final por presentación',
  descuento_porc DECIMAL(6,2) NOT NULL DEFAULT 0,
  iva_alicuota DECIMAL(5,2) NOT NULL DEFAULT 21,
  neto DECIMAL(14,2) NOT NULL DEFAULT 0,
  iva DECIMAL(14,2) NOT NULL DEFAULT 0,
  internos DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  costo_unitario DECIMAL(14,4) NOT NULL DEFAULT 0 COMMENT 'Costo por unidad base al momento de la venta',
  reingresa_stock TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Solo NC por devolución',
  PRIMARY KEY (id),
  KEY idx_ci_comp (comprobante_id),
  KEY idx_ci_producto (producto_id),
  CONSTRAINT fk_ci_comp FOREIGN KEY (comprobante_id) REFERENCES comprobantes (id) ON DELETE CASCADE,
  CONSTRAINT fk_ci_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recibos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uuid CHAR(36) NULL,
  punto_venta_id INT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  total DECIMAL(14,2) NOT NULL,
  origen VARCHAR(12) NOT NULL DEFAULT 'cobranza' COMMENT 'cobranza | mostrador | reparto',
  hoja_ruta_id INT UNSIGNED NULL,
  comprobante_id INT UNSIGNED NULL COMMENT 'Comprobante que originó el cobro (venta contado)',
  preventista_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'emitido',
  observaciones VARCHAR(255) NULL,
  motivo_anulacion VARCHAR(255) NULL,
  liquidacion_id INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_recibos_numero (punto_venta_id, numero),
  UNIQUE KEY uk_recibos_uuid (uuid),
  KEY idx_recibos_cliente (cliente_id, fecha),
  KEY idx_recibos_fecha (fecha),
  KEY idx_recibos_hr (hoja_ruta_id),
  CONSTRAINT fk_rcb_pv FOREIGN KEY (punto_venta_id) REFERENCES puntos_venta (id),
  CONSTRAINT fk_rcb_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id),
  CONSTRAINT fk_rcb_hr FOREIGN KEY (hoja_ruta_id) REFERENCES hojas_ruta (id) ON DELETE SET NULL,
  CONSTRAINT fk_rcb_comp FOREIGN KEY (comprobante_id) REFERENCES comprobantes (id) ON DELETE SET NULL,
  CONSTRAINT fk_rcb_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recibo_pagos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  recibo_id INT UNSIGNED NOT NULL,
  forma_pago VARCHAR(15) NOT NULL COMMENT 'efectivo | transferencia | mercadopago | tarjeta | cheque | retencion',
  importe DECIMAL(14,2) NOT NULL,
  caja_movimiento_id BIGINT UNSIGNED NULL,
  banco_movimiento_id BIGINT UNSIGNED NULL,
  cuenta_bancaria_id INT UNSIGNED NULL,
  cheque_id INT UNSIGNED NULL,
  referencia VARCHAR(60) NULL,
  pendiente_rendicion TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_rp_recibo (recibo_id),
  KEY idx_rp_forma (forma_pago),
  CONSTRAINT fk_rp_recibo FOREIGN KEY (recibo_id) REFERENCES recibos (id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_cuenta FOREIGN KEY (cuenta_bancaria_id) REFERENCES cuentas_bancarias (id),
  CONSTRAINT fk_rp_cheque FOREIGN KEY (cheque_id) REFERENCES cheques (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- REPARTO
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS hoja_ruta_paradas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hoja_ruta_id INT UNSIGNED NOT NULL,
  pedido_id INT UNSIGNED NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  orden INT NOT NULL DEFAULT 0,
  estado VARCHAR(12) NOT NULL DEFAULT 'pendiente' COMMENT 'pendiente | entregado | parcial | rechazado | cerrado',
  motivo VARCHAR(150) NULL,
  observaciones VARCHAR(255) NULL,
  evento_uuid CHAR(36) NULL,
  registrado_en DATETIME NULL,
  comprobante_id INT UNSIGNED NULL,
  recibo_id INT UNSIGNED NULL,
  reprogramado TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uk_hrp_pedido (hoja_ruta_id, pedido_id),
  UNIQUE KEY uk_hrp_evento (evento_uuid),
  KEY idx_hrp_cliente (cliente_id),
  CONSTRAINT fk_hrp_hr FOREIGN KEY (hoja_ruta_id) REFERENCES hojas_ruta (id) ON DELETE CASCADE,
  CONSTRAINT fk_hrp_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos (id),
  CONSTRAINT fk_hrp_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id),
  CONSTRAINT fk_hrp_comp FOREIGN KEY (comprobante_id) REFERENCES comprobantes (id) ON DELETE SET NULL,
  CONSTRAINT fk_hrp_recibo FOREIGN KEY (recibo_id) REFERENCES recibos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rendiciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hoja_ruta_id INT UNSIGNED NOT NULL,
  fecha DATETIME NOT NULL,
  efectivo_esperado DECIMAL(14,2) NOT NULL DEFAULT 0,
  efectivo_rendido DECIMAL(14,2) NOT NULL DEFAULT 0,
  diferencia_efectivo DECIMAL(14,2) NOT NULL DEFAULT 0,
  otros_medios_esperado DECIMAL(14,2) NOT NULL DEFAULT 0,
  otros_medios_confirmado DECIMAL(14,2) NOT NULL DEFAULT 0,
  diferencia_mercaderia DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Valorizada a costo',
  caja_sesion_id INT UNSIGNED NULL,
  ajuste_id INT UNSIGNED NULL,
  observaciones VARCHAR(255) NULL,
  usuario_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_rend_hr (hoja_ruta_id),
  CONSTRAINT fk_rend_hr FOREIGN KEY (hoja_ruta_id) REFERENCES hojas_ruta (id),
  CONSTRAINT fk_rend_sesion FOREIGN KEY (caja_sesion_id) REFERENCES caja_sesiones (id),
  CONSTRAINT fk_rend_ajuste FOREIGN KEY (ajuste_id) REFERENCES ajustes_stock (id) ON DELETE SET NULL,
  CONSTRAINT fk_rend_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rendicion_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rendicion_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cargado DECIMAL(14,3) NOT NULL DEFAULT 0,
  entregado DECIMAL(14,3) NOT NULL DEFAULT 0,
  devolucion_esperada DECIMAL(14,3) NOT NULL DEFAULT 0,
  devolucion_real DECIMAL(14,3) NOT NULL DEFAULT 0,
  diferencia DECIMAL(14,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_ri_rend (rendicion_id),
  CONSTRAINT fk_ri_rend FOREIGN KEY (rendicion_id) REFERENCES rendiciones (id) ON DELETE CASCADE,
  CONSTRAINT fk_ri_producto FOREIGN KEY (producto_id) REFERENCES productos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rendicion_pagos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rendicion_id INT UNSIGNED NOT NULL,
  forma_pago VARCHAR(15) NOT NULL,
  esperado DECIMAL(14,2) NOT NULL DEFAULT 0,
  rendido DECIMAL(14,2) NOT NULL DEFAULT 0,
  diferencia DECIMAL(14,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_rpa_rend (rendicion_id),
  CONSTRAINT fk_rpa_rend FOREIGN KEY (rendicion_id) REFERENCES rendiciones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- COMISIONES
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS comisiones_liquidaciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  preventista_id INT UNSIGNED NOT NULL,
  desde DATE NOT NULL,
  hasta DATE NOT NULL,
  base_calculo VARCHAR(10) NOT NULL,
  importe_base DECIMAL(14,2) NOT NULL DEFAULT 0,
  porcentaje DECIMAL(6,2) NOT NULL DEFAULT 0,
  importe DECIMAL(14,2) NOT NULL DEFAULT 0,
  estado VARCHAR(10) NOT NULL DEFAULT 'pendiente' COMMENT 'pendiente | pagada | anulada',
  observaciones VARCHAR(255) NULL,
  usuario_id INT UNSIGNED NULL,
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_cl_prev (preventista_id, desde),
  CONSTRAINT fk_cl_prev FOREIGN KEY (preventista_id) REFERENCES preventistas (id),
  CONSTRAINT fk_cl_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
