#!/usr/bin/env bash
# Read-only byte-integrity gate. Call with an immutable, reviewed expected digest.
set -euo pipefail
if [[ $# -ne 2 ]]; then
  echo 'ARCHIVE_SHA256_USAGE: expected <file> <64-character-lowercase-sha256>' >&2
  exit 2
fi
archive="$1"
expected="$2"
if [[ ! "$expected" =~ ^[0-9a-f]{64}$ ]]; then
  echo 'ARCHIVE_SHA256_INVALID_EXPECTED: digest must be 64 lowercase hex characters.' >&2
  exit 2
fi
if [[ ! -f "$archive" ]]; then
  echo 'ARCHIVE_SHA256_FILE_MISSING: refusing to install.' >&2
  exit 2
fi
actual="$(sha256sum -- "$archive" | awk '{print $1}')"
if [[ "$actual" != "$expected" ]]; then
  echo "ARCHIVE_SHA256_MISMATCH: expected=$expected actual=$actual" >&2
  exit 1
fi
echo "ARCHIVE_SHA256_VERIFIED=$actual"
