# Approved template assets

Copied from `plantilla/assets` for the CRM migration:

- Global: Bootstrap, theme CSS, vendor bundle, common initializer, favicon, logos, and required icon fonts.
- Listings: DataTables core and Bootstrap 5 adapter.
- Forms/filters: Select2 and daterangepicker.
- Dashboard: ApexCharts and dashboard initializer.

Global load order is Bootstrap CSS, vendor CSS, theme CSS, page CSS; then vendor JS, common initializer, and page JS. Page plugins must be loaded through layout sections. Source maps, SCSS, demos, galleries, flags, and unrelated plugins are intentionally excluded.
