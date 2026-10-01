#!/usr/bin/env bash
# Static audit with PHP_CodeSniffer (WordPress + PHPCompatibility standards). Fails on PHP-compatibility
# errors and deprecated WordPress functions; escaping/SQL findings are reported as a count to review
# (many are already-escaped values, see docs/AUDIT.md).
set -uo pipefail
cd "$(dirname "$0")/.."
[ -x vendor/bin/phpcs ] || COMPOSER_ALLOW_SUPERUSER=1 composer install -q --no-interaction
vendor/bin/phpcs --config-set installed_paths "$(pwd)/vendor/wp-coding-standards/wpcs,$(pwd)/vendor/phpcompatibility/php-compatibility,$(pwd)/vendor/phpcompatibility/phpcompatibility-wp,$(pwd)/vendor/phpcompatibility/phpcompatibility-paragonie,$(pwd)/vendor/phpcsstandards/phpcsutils,$(pwd)/vendor/phpcsstandards/phpcsextra" >/dev/null
echo "== PHP 7.4+ compatibility and deprecated WordPress functions (must be clean)"
vendor/bin/phpcs -q --standard=phpcs.xml.dist --sniffs=PHPCompatibility.FunctionUse.RemovedFunctions,PHPCompatibility.FunctionUse.NewFunctions,PHPCompatibility.Syntax.NewDynamicAccessToStatic,PHPCompatibility.ParameterValues.RemovedNonCryptoHash,PHPCompatibility.Classes.RemovedClasses,WordPress.WP.DeprecatedFunctions,WordPress.WP.DeprecatedParameters,PHPCompatibility.Keywords.ForbiddenNames --report=summary || { echo "AUDIT FAILED"; exit 1; }
echo "== Escaping / SQL findings to review"
vendor/bin/phpcs -q --standard=phpcs.xml.dist --sniffs=WordPress.Security.EscapeOutput,WordPress.DB.PreparedSQL --report=summary | tail -5
echo "AUDIT OK"
