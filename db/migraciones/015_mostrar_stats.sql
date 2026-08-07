ALTER TABLE cromos ADD COLUMN IF NOT EXISTS mostrar_stats ENUM('artwork', 'debajo', 'ninguna') NOT NULL DEFAULT 'artwork' AFTER origen_importacion;

UPDATE cromos
SET mostrar_stats = 'debajo'
WHERE imagen != '' AND imagen IS NOT NULL
  AND (
    imagen LIKE '%ALL STARS%'
    OR imagen LIKE '%Apuesta Segura%'
    OR posicion IN ('ENT', 'GER', 'ESCUDO', 'PRESIDENTE')
  );
