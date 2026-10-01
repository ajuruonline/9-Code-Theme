# 9Code Theme 14.1.14 — Header/Footer Master-Off Repair

- Header and Footer are forcibly reset OFF once on upgrade.
- Master switches now live inside their actual WordPress settings forms.
- OFF is submitted explicitly and only literal `yes` is accepted as ON.
- Footer direct/Elementor rendering now obeys the same master guard.
- Header/Footer compatibility assets do not load when both surfaces are OFF.
- Preserved standalone Header/Footer options and legacy Core flags are reset OFF once to prevent a second renderer from surviving the switch.
- Switch labels update immediately in the admin UI.
