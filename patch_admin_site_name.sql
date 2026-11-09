-- One-time: align saved admin panel site name with Daet Public Market branding.
-- Safe to run multiple times (only updates legacy names).

UPDATE admin_profiles
SET site_name = 'Daet Public Market'
WHERE site_name IN (
  'Market Clearance System',
  'Daet Public Market Clearance',
  'Daet Public Market Clearance System',
  'Daet Public System',
  'Daet Public Market Office — Daet Public System'
);
