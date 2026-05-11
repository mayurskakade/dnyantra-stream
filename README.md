# Private-First Streaming Monorepo
Personal/private media platform with PHP backend/admin, Flutter mobile app, MySQL, optional Redis, Cloudflare Stream + R2 integrations.

## Security and legal defaults
- All content defaults to `private` and `personal_only`.
- Public playback is only rights-cleared and always short-lived signed sessions.
- No permanent public stream URLs.

## Structure
- `/backend` API + admin portal
- `/mobile` Flutter app
- `/docker` local infra
