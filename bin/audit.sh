#!/usr/bin/env bash
# Static audit with PHP_CodeSniffer. Compatibility findings block CI;
# escaping/SQL findings are advisory until independently triaged.
set -uo pipefail
cd "$(dirname "$0")/.."
[ -x vendor/bin/phpcs ] || COMPOSER_ALLOW_SUPERUSER=1 composer install -q --no-interaction || exit 2
vendor/bin/phpcs --config-set installed_paths "$(pwd)/vendor/wp-coding-standards/wpcs,$(pwd)/vendor/phpcompatibility/php-compatibility,$(pwd)/vendor/phpcompatibility/phpcompatibility-wp,$(pwd)/vendor/phpcompatibility/phpcompatibility-paragonie,$(pwd)/vendor/phpcsstandards/phpcsutils,$(pwd)/vendor/phpcsstandards/phpcsextra" >/dev/null || { echo "AUDIT TOOL FAILURE: phpcs configuration"; exit 2; }
echo "== PHP 7.4+ compatibility and deprecated WordPress functions (blocking)"
vendor/bin/phpcs -q --standard=phpcs.xml.dist --sniffs=PHPCompatibility.FunctionUse.RemovedFunctions,PHPCompatibility.FunctionUse.NewFunctions,PHPCompatibility.Syntax.NewDynamicAccessToStatic,PHPCompatibility.ParameterValues.RemovedNonCryptoHash,PHPCompatibility.Classes.RemovedClasses,WordPress.WP.DeprecatedFunctions,WordPress.WP.DeprecatedParameters,PHPCompatibility.Keywords.ForbiddenNames --report=summary || { echo "AUDIT FAILED: compatibility/deprecation"; exit 1; }
echo "== Escaping / SQL findings (advisory, NOT independently cleared)"
# Do not pipe through tail: retain file, line, and sniff details for independent review.
# A nonzero phpcs exit without a recognizable findings report is a tool failure.
report="$(vendor/bin/phpcs -q --standard=phpcs.xml.dist --sniffs=WordPress.Security.EscapeOutput,WordPress.DB.PreparedSQL --report=full 2>&1)"
status=$?
[ -z "$report" ] || printf '%s\n' "$report"
if [ "$status" -eq 0 ]; then
  echo "AUDIT CLEAN: escaping/SQL"
elif printf '%s\n' "$report" | grep -Eq 'FOUND [1-9][0-9]* ERRORS?|A TOTAL OF [1-9][0-9]* ERRORS?|FOUND [0-9]+ ERRORS? AND [1-9][0-9]* WARNINGS?'; then
  echo "AUDIT ADVISORY FINDINGS: inspect every file/line/sniff; release remains subject to independent triage"
else
  echo "AUDIT TOOL FAILURE: escaping/SQL scan did not return a recognizable findings report" >&2
  exit 2
fi
