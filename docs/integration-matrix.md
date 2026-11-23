# Identity Bridge – three distinct host policies

This package normalizes verified OIDC identities and externally managed role mapping; it does **not** implement JWT validation, an Authentik client, or database persistence.

| Host | Existing identity | Provisioning | Role mapping |
| --- | --- | --- | --- |
| Teatime | Unique `(issuer, subject)` in `UserIdentity` | `UserProvisioningService` creates a User and first Household | Only Authentik-provided `teatime_users`/`teatime_admins`; keep household permissions local |
| Nailpolishment | Existing `User.externalId = sub` | Web OIDC provider creates local user; API does not | `nail_users`/`nail_admins`; do not auto-link differing subjects |
| Registration | Session-only `OidcUser`, no admin DB entry | No local OIDC user provisioning | All signed-in users get `ROLE_USER`; exact `registration_admins` grants `ROLE_ADMIN` |

## Portable role policy

```php
$mapper = new ConfigurableGroupRoleMapper(
    ['registration_admins' => ['ROLE_ADMIN']],
    ['ROLE_USER'],
);
$identity = new VerifiedExternalIdentity($verifiedIssuer, $verifiedSubject, [
    'groups' => $verifiedIdTokenGroups,
]);
$roles = $mapper->mappedRoles($identity);
```

**Security prerequisite:** Pass verified provider claims only; the bundle cannot assert they were verified. For roles use signed ID-token groups (or another explicitly trusted group source), never request query parameters or unauthenticated payloads. In particular, a current session may contain stale groups until the host reauthenticates.

Applications with local roles can reconcile only the identity-managed subset:

```php
$roles = (new ManagedRoleSynchronizer(['ROLE_USER', 'ROLE_ADMIN']))
    ->synchronize($existingRoles, $mappedRoles);
```

Do not store household memberships in Symfony global role lists. This helper is appropriate only for applications that actually mix local and externally managed global roles.

## Deliberate non-goals

- No default role should be granted unless an application requests one. Registration intentionally grants `ROLE_USER` to signed-in users; Teatime and Nailpolishment do not.
- No unconditional creation of local users. No email-based account linking. No shared User entity.
- No implicit `uid` to `sub` conversion. Nailpolishment's reported cross-provider subject mismatch requires independently verified remediation before any migration.
- Keep Drenso OIDC or native Symfony 8.2 behind the application's firewall: these role helpers work with either.
- No forced `security.yaml` replacement. Host adapters must be proven by application integration tests before production adoption.
