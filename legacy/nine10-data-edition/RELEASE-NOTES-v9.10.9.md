# 9 Data Manager 9.10.9 — Conference Update

- Public provider CPTs remain discoverable in Post Editor/Data Manager, while non-public Elementor/ACF/configuration CPTs are excluded by default.
- Private/system plugin meta is read-only by default; owning providers may explicitly opt individual keys into editing.
- Managed schema registrations run last, skip existing provider slugs and expose a provider veto filter.
- Form selectors use the same public-content boundary and expose a separate post-type filter; REST meta writes require edit permission for the specific object.
- Category/Data upgrade routines run on admin_init rather than public plugins_loaded.
- Adds an additive suite ownership contract for ACF, Elementor and plugin-owned data.
- No Conference/Open Scholar/9stagram/Special CPT or taxonomy is registered or taken over by Data Manager.
