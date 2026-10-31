# Secure Scholar v1 compatibility format

## `.scholar`

The first eight bytes are ASCII `SCHOLR1` followed by LF. A four-byte unsigned big-endian JSON length follows, then canonical UTF-8 JSON and consecutive AES-256-GCM encrypted chunks.

The header contains `format=SecureScholar`, `version=1`, UUID, title, kind, MIME type, plaintext size, 1 MiB chunk size and eight-byte random nonce prefix. Each chunk uses `nonce-prefix || chunk-index`; authenticated data is the SHA-256 header hash plus chunk index.

## `.scholarpass`

The JSON file contains `format=SecureScholarPasswordFile`, version 1, signed access body, ECDSA P-256 signature, public verification key and a random portable secret. The content key is PBKDF2-SHA256/AES-GCM wrapped under the portable secret plus optional private PIN.

Signed rules include content identity and header hash, recipient, start and expiry, maximum openings, session duration, optional installation code, seeking restriction, offline-only flag and PIN-required flag.

WordPress and Windows v1 use these exact files. An edition may impose a smaller source-size limit, but it must not silently change the format.
