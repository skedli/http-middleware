# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0]

### Changed

- **BREAKING**: The `401 Unauthorized` response body produced by
  `AuthenticationMiddleware` now uses `"code": "UNAUTHORIZED"` instead of
  `"code": "TOKEN_VALIDATION_FAILED"`. The HTTP status code (`401`) and the
  `message` field are unchanged.

### Impact

Consumers that match the response `code` exactly (e.g., client libraries,
gateway error mappers, alert rules) must be updated to recognize
`UNAUTHORIZED`. Consumers relying only on the HTTP status code or the
`message` field are unaffected.

### Migration

Replace any equality check on `code == "TOKEN_VALIDATION_FAILED"` with
`code == "UNAUTHORIZED"` in downstream services and clients.
