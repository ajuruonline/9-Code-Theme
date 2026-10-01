# 9Code Theme 14.1.2 — Optional Companion Isolation & Surface Contract

## Compatibility fixes
- Quick Actions settings no longer depend on the Mason admin menu.
- Quick Actions is Theme-owned and registers under the 9Code admin route.
- Mason is included in Quick Actions only when Mason is installed.
- The global “Mason missing” warning is removed because Mason is optional.
- Adds generic companion-owned surface handling.
- Header/Footer can be suppressed cleanly on a companion-owned application surface.
- Theme Quick Actions can be suppressed on an explicitly claimed companion admin surface.

## 9Page 9.77 compatibility
9Page 9.77 owns its application shell and may suppress ordinary Theme header/footer on its own surfaces. It remains an optional companion, not a core-suite dependency. Theme design tokens may still be inherited unless the companion opts out.

## Authority
Former Nine Page / 9.55 Page / 9 Code Page 0.3.x/0.4.x/0.5.x releases are historical provenance only and are not a current compatibility baseline.

## NEEDS LIVE TEST
- Theme + Core with Mason absent.
- Quick Actions settings route for administrator and editor roles.
- Optional Mason activation/deactivation.
- 9Page 9.77 surface filter integration.
- Header/Footer browser/mobile behavior on claimed and ordinary surfaces.
