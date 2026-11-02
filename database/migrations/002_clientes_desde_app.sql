-- Migración 002: alta de clientes desde la app del preventista (también sin conexión).
-- uuid = identificador generado en el celular para no duplicar el cliente al reintentar el envío.
ALTER TABLE clientes ADD COLUMN uuid CHAR(36) NULL AFTER id;
ALTER TABLE clientes ADD UNIQUE KEY uk_clientes_uuid (uuid);
-- El rol Preventista pasa a poder dar de alta clientes
INSERT IGNORE INTO rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id FROM roles r JOIN permisos p ON p.modulo = 'clientes' AND p.accion = 'crear' WHERE r.slug = 'preventista';
