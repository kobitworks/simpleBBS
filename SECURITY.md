# Security policy

This repository is a reference implementation of an anonymous SQLite bulletin board.
It is **not** an authentication/abuse-prevention system.

## Reporting vulnerabilities

Please contact repository maintainers privately or use GitHub private vulnerability
reporting if enabled. Do not post proof-of-concept attacks or credentials in public
issues. Rotate exposed secrets immediately.

## Secure deployment

- Only `public/` may be used as the web document root.
- Never expose `.storage/`, `.env`, `vendor/`, the source tree, or SQLite backups.
- Use TLS, a non-root PHP user, restrictive filesystem permissions, and an updated
  PHP runtime. The HTTP entry point uses owner-only permissions for new SQLite files.
- Anonymous board creation is disabled unless `SIMPLEBBS_ADMIN_TOKEN` is supplied
  as a random, 32+ character **server environment variable**. Do not commit tokens.
- Anonymous posts are editable only by the browser session that created them.
  Existing posts do not gain ownership automatically. This is not durable account
  authentication; disabling cookies or ending a session loses editing permission.
- All state-changing HTTP requests require a CSRF token. Restrict requests at the
  reverse proxy as well (especially posting/API endpoints) to prevent spam and DoS.
- Errors return generic messages; internal exception messages remain in server logs.
- Restrict access to service endpoints and set monitoring/alerts for elevated 403,
  404, and 5xx rates. Ensure backup files cannot be served over HTTP.

## Limits

This patch does **not** provide login, moderation, durable author accounts,
database quota enforcement, IP-based rate limiting, or abuse detection.
Deploy behind an appropriately configured firewall, preferably with network-level
rate limits and bot protection. Consider a maintained, supported BBS platform
before accepting untrusted Internet users at scale.
