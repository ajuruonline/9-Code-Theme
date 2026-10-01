# 9Code 14 Block Edition — Current Architecture

## Suite

- **9Code Theme** — safe WordPress presentation shell and design tokens.
- **9Core** — shared contracts/infrastructure.
- **9 Data Manager** — editorial, taxonomy, forms, responses and backup/data workflows.

WordPress owns routing, Pages and Posts. Plugin CPTs/templates own their presentation. The Theme assists through tokens and generic compatibility rather than taking over plugin surfaces.

The experimental functional Page layer is discontinued. Flyer sites should use the flyer/CPT plugin directly.
