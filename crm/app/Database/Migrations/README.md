# Forward migrations

The existing `ventas` schema is adopted with `crm/docs/migration/baseline-adoption.sql`; it is not recreated by CodeIgniter migrations. Run the adoption script only after backup and characterization checks pass.

All schema changes after baseline `crm-2026-07-10-migracion` belong here as timestamped CodeIgniter migrations. Every forward migration must provide a safe `down()` operation when data preservation permits it and must be tested against a restored copy of the authoritative baseline.
