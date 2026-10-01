# 9Core 14.1.1 — Plugin Assistance & Diagnostics Contract

- Adds the additive `ninecodepress_component_contracts` registry/filter so Theme, Mason and future plugins can report compatibility metadata without hard dependencies.
- Adds `ninecodepress_semantic_host_context()` with host-plugin ownership defaults.
- Diagnostics schema advances to 3 and now exports component contracts, Mason contract and semantic-host policy.
- Fixes stale Site Health text that incorrectly said API 13 and recognizes the retained `9code-13-theme` upgrade slug as the API-14 Theme line.
- No plugin business logic or data ownership moves into Core.

**NEEDS LIVE TEST:** WordPress Site Health and Diagnostics download on a real stack.
