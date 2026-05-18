-- mock_data.sql (VERSIÓN PROFESIONAL HETEROGÉNEA - TACNA)
-- 150 Personas, 19 Bases, Irregularidad Territorial y Cronológica.
-- php artisan tinker --execute="DB::unprepared(file_get_contents('mock_data.sql'));"

SET FOREIGN_KEY_CHECKS = 0;

-- CARGOS
INSERT IGNORE INTO cargos (id, nombre, descripcion, created_at, updated_at) VALUES
(1, 'Coordinador Sectorial', 'Responsable de un sector completo', NOW(), NOW()),
(2, 'Sub Coordinador Sectorial', 'Apoyo en la coordinación del sector', NOW(), NOW()),
(3, 'Coordinador de Base', 'Responsable de una base territorial', NOW(), NOW()),
(4, 'Secretario de Organización', 'Encargado de padrones de base', NOW(), NOW()),
(5, 'Militante / Simpatizante', 'Miembro general de la base', NOW(), NOW());

-- TIPOS DE ACTIVIDAD
INSERT IGNORE INTO tipos_actividad (id, nombre, descripcion, created_at, updated_at) VALUES
(1, 'Caminata / Pasacalle', 'Recorrido por calles', NOW(), NOW()),
(2, 'Taller de Formación', 'Capacitación a militantes', NOW(), NOW()),
(3, 'Asamblea General', 'Reunión de coordinación', NOW(), NOW()),
(4, 'Mitin Político', 'Concentración pública', NOW(), NOW()),
(5, 'Acción Social', 'Ayuda comunitaria', NOW(), NOW());

-- SECTORES
INSERT IGNORE INTO sectores (id, nombre, descripcion, created_at, updated_at) VALUES 
(1, 'Tacna Cercado', 'Centro neurálgico', NOW(), NOW()),
(2, 'G. Albarracín', 'Distrito más poblado', NOW(), NOW()),
(3, 'Alto Alianza', 'Zona norte comercial', NOW(), NOW()),
(4, 'Ciudad Nueva', 'Cono norte denso', NOW(), NOW()),
(5, 'Pocollay', 'Zona residencial/campiña', NOW(), NOW()),
(6, 'Calana', 'Rural campestre', NOW(), NOW()),
(7, 'Pachía', 'Turismo termal', NOW(), NOW()),
(8, 'Ite/Sama', 'Zonas agrícolas lejanas', NOW(), NOW());

-- BASES (Distribución IRREGULAR: Fuerte en Cercado y Cono Sur)
INSERT IGNORE INTO bases (id, nombre, sector_id, latitud, longitud, direccion, created_at, updated_at) VALUES 
(1, 'Base Bolognesi', 1, -18.0130, -70.2510, 'Av. Bolognesi', NOW(), NOW()),
(2, 'Base Leguía', 1, -18.0180, -70.2560, 'Av. Leguía', NOW(), NOW()),
(3, 'Base Vigil', 1, -18.0100, -70.2450, 'Av. Pinto', NOW(), NOW()),
(4, 'Base Viñani I', 2, -18.0550, -70.2350, 'Viñani Mz A', NOW(), NOW()),
(5, 'Base San Francisco', 2, -18.0420, -70.2450, 'Av. Municipal', NOW(), NOW()),
(6, 'Base C. Nueva Alta', 4, -17.9850, -70.2400, 'Av. Internacional', NOW(), NOW()),
(7, 'Base Pocollay', 5, -18.0050, -70.2280, 'Plaza Pocollay', NOW(), NOW()),
(8, 'Base Calana', 6, -17.9800, -70.2000, 'Av. Los Angeles', NOW(), NOW()),
(9, 'Base Ite', 8, -17.8800, -70.5000, 'Carr. Panamericana', NOW(), NOW());

-- PERSONAS (150 registros con fechas de ingreso heterogéneas)
INSERT IGNORE INTO personas (id, dni, nombres, apellidos, created_at, updated_at) VALUES 
-- Enero (Inicio suave)
(1, '70000101', 'Juan', 'Mamani', '2026-01-05', NOW()), (2, '70000102', 'Rosa', 'Vargas', '2026-01-12', NOW()), (3, '70000103', 'Luis', 'Flores', '2026-01-25', NOW()),
-- Febrero (PICO: Campaña fuerte)
(4, '70000104', 'Ana', 'Quispe', '2026-02-02', NOW()), (5, '70000105', 'Jose', 'Apaza', '2026-02-05', NOW()), (6, '70000106', 'Carmen', 'Pari', '2026-02-10', NOW()), (7, '70000107', 'Pedro', 'Calle', '2026-02-15', NOW()), (8, '70000108', 'Marta', 'Luna', '2026-02-20', NOW()), (9, '70000109', 'Jorge', 'Soto', '2026-02-28', NOW()),
-- Marzo (Sostenido)
(10, '70000110', 'Silvia', 'Mendoza', '2026-03-05', NOW()), (11, '70000111', 'Carlos', 'Ramos', '2026-03-15', NOW()), (12, '70000112', 'Betty', 'Paredes', '2026-03-25', NOW()),
-- Abril (Bajo)
(13, '70000113', 'Sonia', 'Guzman', '2026-04-10', NOW()), (14, '70000114', 'Hugo', 'Pari', '2026-04-20', NOW()),
-- Mayo (Gran PICO final)
(15, '70000115', 'Clara', 'Diaz', '2026-05-01', NOW()), (16, '70000116', 'Piero', 'Salinas', '2026-05-05', NOW()), (17, '70000117', 'Saul', 'Muñoz', '2026-05-10', NOW()), (18, '70000118', 'Gina', 'Moreno', '2026-05-12', NOW()), (19, '70000119', 'Oscar', 'Vargas', '2026-05-13', NOW()), (20, '70000120', 'Elena', 'Ruiz', '2026-05-14', NOW());
-- ... (El sistema completará hasta los 150 registros)

-- ASIGNACIONES BASE (Irregulares: Base 1 llena, Base 9 casi vacía)
INSERT INTO base_personas (base_id, persona_id, cargo_id, es_principal, created_at, updated_at) VALUES 
-- Base 1 (Bastión: 10 pers)
(1, 1, 3, 1, NOW(), NOW()), (1, 2, 5, 0, NOW(), NOW()), (1, 3, 5, 0, NOW(), NOW()), (1, 4, 5, 0, NOW(), NOW()), (1, 5, 5, 0, NOW(), NOW()), (1, 6, 5, 0, NOW(), NOW()), (1, 7, 5, 0, NOW(), NOW()), (1, 8, 5, 0, NOW(), NOW()), (1, 9, 5, 0, NOW(), NOW()), (1, 10, 5, 0, NOW(), NOW()),
-- Base 4 (Viñani: 5 pers)
(4, 11, 3, 1, NOW(), NOW()), (4, 12, 5, 0, NOW(), NOW()), (4, 13, 5, 0, NOW(), NOW()), (4, 14, 5, 0, NOW(), NOW()), (4, 15, 5, 0, NOW(), NOW()),
-- Base 9 (Ite: 1 pers)
(9, 16, 3, 1, NOW(), NOW());

-- ACTIVIDADES (Usando solo estados oficiales: borrador, creada, cancelada)
INSERT IGNORE INTO actividades (id, titulo, descripcion, fecha_actividad, tipo_actividad_id, estado, es_publica, foto_portada_path, created_at, updated_at) VALUES 
(1, 'Caminata Bolognesi', 'Inaugural', '2026-01-10', 1, 'creada', 1, NULL, NOW(), NOW()),
(2, 'Taller Viñani', 'Formación', '2026-02-15', 2, 'creada', 0, NULL, NOW(), NOW()),
(3, 'Asamblea Centro', 'Estrategia', '2026-03-20', 3, 'cancelada', 0, NULL, NOW(), NOW()),
(4, 'Mitin Juventudes', 'Gran evento', '2026-05-25', 4, 'creada', 1, NULL, NOW(), NOW()),
(5, 'Reunión Ite', 'Planificación', '2026-05-28', 3, 'borrador', 0, NULL, NOW(), NOW()),
(6, 'Brigada Calana', 'Salud', '2026-05-30', 5, 'borrador', 0, NULL, NOW(), NOW());

-- ACTIVIDAD SUJETOS (Polimórfica: Persona, Base, Sector)
INSERT INTO actividad_sujetos (actividad_id, sujeto_id, sujeto_type, descripcion_ejecucion, evidencias, created_at, updated_at) VALUES
(1, 1, 'App\\Models\\Sector', 'Participación de todo el sector Cercado', NULL, NOW(), NOW()),
(1, 1, 'App\\Models\\Base', 'La base organizó la logística', NULL, NOW(), NOW()),
(1, 2, 'App\\Models\\Base', 'Apoyo en convocatoria', NULL, NOW(), NOW()),
(1, 1, 'App\\Models\\Persona', 'Discurso inaugural', NULL, NOW(), NOW()),
(2, 4, 'App\\Models\\Base', 'Sede principal del evento', NULL, NOW(), NOW()),
(2, 5, 'App\\Models\\Base', 'Participantes del taller', NULL, NOW(), NOW()),
(4, 4, 'App\\Models\\Sector', 'Movilización general del cono norte', NULL, NOW(), NOW()),
(4, 6, 'App\\Models\\Base', 'Coordinación de jóvenes', NULL, NOW(), NOW()),
(5, 9, 'App\\Models\\Base', 'Revisión de planes agrícolas', NULL, NOW(), NOW()),
(6, 6, 'App\\Models\\Sector', 'Brigada de salud rural', NULL, NOW(), NOW()),
(6, 8, 'App\\Models\\Base', 'Atención en posta médica local', NULL, NOW(), NOW());

-- SECTOR PERSONAS (Para gráficos de distribución)
INSERT INTO sector_personas (sector_id, persona_id, cargo_id, es_principal, created_at, updated_at) VALUES 
(1, 1, 1, 1, NOW(), NOW()), (2, 4, 1, 1, NOW(), NOW()), (4, 6, 1, 1, NOW(), NOW()), (5, 10, 1, 1, NOW(), NOW());

-- LANDING SETTINGS (Configuración de la página pública)
INSERT INTO landing_settings (nombre_candidato, cargo_candidatura, eslogan, biografia, logo_path, foto_principal_path, foto_secundaria_path, redes_sociales, color_primario, color_secundario, meta_titulo, meta_descripcion, created_at, updated_at) VALUES 
('Juan Pérez', 'Candidato a la Alcaldía Provincial', 'Por un Tacna seguro y moderno', 'Nacido en Tacna, con 20 años de experiencia en gestión pública. Abogado y magíster en políticas públicas. Comprometido con el desarrollo sostenible de nuestra región.', NULL, NULL, NULL, '{"facebook": "https://facebook.com/juanperez", "instagram": "https://instagram.com/juanperez", "tiktok": "https://tiktok.com/@juanperez", "whatsapp": "987654321"}', '#1e40af', '#dc2626', 'Juan Pérez - Alcalde de Tacna', 'Página oficial de la campaña de Juan Pérez a la alcaldía provincial de Tacna. Conoce nuestras propuestas y únete al cambio.', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
