# Ultron Rabbit Audit — 9.10 Data Edition v9.10.1

Static hardening release derived from v9.10.0.

## Corrected
- 9Form taxonomy editing now requires the taxonomy assign capability.
- 9Form can only assign existing terms; it cannot create taxonomy structure.
- Data Edition AI data routing no longer creates missing terms through Data Engine.
- Contact Author adds a filterable 60-second default rate limit and message length bounds without storing message content.
- User-facing retired Ultron wording was replaced with 9.10 Data Edition wording while legacy internal identifiers remain for compatibility.

## Boundaries preserved
- Post create/edit routes through 9 Post Manager.
- ACF and safe plugin-meta updates route through Data Engine atomic import/version capture.
- Taxonomy structure creation remains a 9 Category Manager responsibility.
- AI routing remains through Nine AI Manager/Data Edition integration contracts.
- 9Form stores no post, ACF/meta, taxonomy, or message content of its own.

## Verification scope
Fresh static verification includes PHP syntax, JavaScript parse, ZIP integrity, plugin/version checks, bundled engine version checks, shell direct-write firewall, and route-contract assertions. Live WordPress execution, SMTP delivery, Elementor rendering, and browser/mobile interaction require staging-site testing.
