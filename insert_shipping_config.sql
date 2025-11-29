-- Este script inserta la configuración necesaria para empaque y seguro
-- Ejecuta este script en tu base de datos de PrestaShop

-- Eliminar configuración existente (si existe)
DELETE FROM ps_shipping_config WHERE name IN ('Empaque', 'Seguro', 'Peso volumetrico');

-- Insertar configuración de Empaque (5% del costo de envío)
INSERT INTO ps_shipping_config (id_carrier, name, min, max, value_number, date_add, date_upd)
VALUES (0, 'Empaque', NULL, NULL, 5.00, NOW(), NOW());

-- Insertar configuración de Peso Volumétrico (global)
INSERT INTO ps_shipping_config (id_carrier, name, min, max, value_number, date_add, date_upd)
VALUES (0, 'Peso volumetrico', NULL, NULL, 5000, NOW(), NOW());

-- Obtener IDs de carriers activos
-- Necesitas reemplazar CARRIER_ID_1 y CARRIER_ID_2 con los IDs reales de tus transportadoras

-- Para cada transportadora, insertar configuración de seguro
-- CASO A: Seguro por rango de peso (1% del valor declarado para pesos entre 0 y 10000 kg)
INSERT INTO ps_shipping_config (id_carrier, name, min, max, value_number, date_add, date_upd)
VALUES 
(1, 'Seguro', 0, 10000, 1.00, NOW(), NOW()),  -- Reemplaza 1 con el ID real de Envia
(2, 'Seguro', 0, 10000, 1.00, NOW(), NOW());  -- Reemplaza 2 con el ID real de Aldia

-- CASO B (Opcional): Seguro mínimo (si necesitas un valor mínimo de seguro)
-- INSERT INTO ps_shipping_config (id_carrier, name, min, max, value_number, date_add, date_upd)
-- VALUES 
-- (1, 'Seguro', 0, 0, 1000.00, NOW(), NOW()),  -- Seguro mínimo de $1000 para Envia
-- (2, 'Seguro', 0, 0, 1000.00, NOW(), NOW());  -- Seguro mínimo de $1000 para Aldia

-- Verificar los datos insertados
SELECT * FROM ps_shipping_config ORDER BY id_carrier, name;
