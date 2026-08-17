# Security policy

## Supported versions

Until LaraTimeCode reaches a stable release, security fixes are applied to the
latest published version only.

## Reporting a vulnerability

Do not open a public issue and do not attach production `.repro` files.

Use GitHub's **Report a vulnerability** button in the repository Security tab. If
private vulnerability reporting is unavailable, email
`leonidtimo17@gmail.com` with the subject `LaraTimeCode security report`.

Include the affected version, impact, reproduction steps, and a minimal sanitized
example. You should receive an acknowledgement within seven days.

## Snapshot handling

Treat every snapshot as potentially sensitive even after redaction:

- keep encryption enabled outside local development;
- restrict access to `storage/laratimecode`;
- use short retention limits;
- inspect snapshots before sharing;
- remove snapshots after investigation.
