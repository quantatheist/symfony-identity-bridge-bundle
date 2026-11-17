# Symfony Identity Bridge Bundle

Provider-neutral **identity resolution and managed role contracts** for existing OIDC authenticators. No Doctrine entities or migrations.

## Status: integration preview, NOT production-certified

- `NativeOidcUserProvider`: Symfony 8.2 native `oidc_login` adapter.
- `DrensoOidcUserProvider`: optional adapter for existing Symfony 8.1 + Drenso 4.x.
- `ExternalIdentityResolverInterface`: application-owned exact `(issuer, sub)` to User link.
- `UserProvisionerInterface`: explicit opt-in app-owned creation (disabled by default).
- `ConfiguredGroupRoleMapper` and `ManagedRoleSet`: allowlisted claim-to-role mapping with preservation of unmanaged local roles.
- `VerifiedExternalIdentity`: immutable identity data, **not a JWT validator**.

This bundle never performs its own OIDC login flow, redirects, signature verification or token refresh. Web login remains owned by native Symfony 8.2 or Drenso. All persistent User and external-identity entities belong to the host.

## Guides

- [Native Symfony 8.2 user provider](docs/native-user-provider.md)
- [Existing Symfony 8.1 Drenso adapter](docs/drenso-user-provider.md)
- [Authenticator ENV values and callbacks](docs/oidc-configuration.md)
- [Authentik setup checklist](docs/authentik-setup.md)
- [Trusted managed role mapping](docs/managed-roles.md)
- [Security architecture](docs/architecture.md)

## Development

Run `composer install` and `vendor/bin/phpunit` (GitHub Actions does this on every pull request).

**Before production:** verify real token provenance, Drenso ID token / UserInfo claim merging, issuer/subject stability, linking and provisioning race conditions, session refresh behavior, actual group source, account disabled/revoked, and each application's complete functional tests. No production deployment has been tested.
