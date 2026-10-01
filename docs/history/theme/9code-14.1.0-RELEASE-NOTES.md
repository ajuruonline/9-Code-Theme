# 9Code 14 Theme — Block Edition 14.1.0

## Call Your Shot
**14.1.0 — Suite Consolidation & Mason Extraction Operation**

14.1.0 is the official retirement point for the v13 human-facing release line. It preserves the v13 gains and the 14.0 Block Edition, then extracts Mason into its own suite plugin so Theme, Mason and 9Page have one owner each.

## Preserved from v13
- Header/Footer v9.2.0 is Theme-owned; old standalone runtime remains retirement/provenance only.
- Popular Sites are full-site templates and change the bundled Skin plus site personality.
- Skins remain fast visual identity variants; 13 Popular Site families expose 130 skins.
- Theme Skin is the default Header/Footer visual authority.
- Dashboard/admin identity, tablet/mobile hardening and safe plugin-install workflow gains are retained.
- Unified AI/Data Manager remains Core-owned.

## Preserved from 14.0.0
- Theme Quick Actions and per-user configuration.
- `.block` / `.nineblock.json` portable package doctrine.
- 9Block public rendering remains restricted to `host => 9page`.
- trusted engine adapter model.
- one block = one primary job.

## Changed in 14.1.0
- Mason is no longer Theme-owned code. It is the standalone **9 Mason 14.1.0** suite plugin.
- Theme no longer declares any `ninecode_mason_*` API function.
- Core API and Theme API advance to 14.
- Core's legacy five-button quick-control renderer is retired; Theme Quick Actions is the single launcher owner.
- 9Page development receives a separate integration-contract package and must consume, not duplicate, Mason.
