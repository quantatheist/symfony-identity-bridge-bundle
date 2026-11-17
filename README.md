# Symfony Identity Bridge Bundle

Provider-neutral **interfaces** for integrating a verified OIDC identity with Symfony users. No entities or migrations.

## Status

**Initial contracts only. Do not install expecting a working login or user provisioning.** The package does not register an authenticator, validate tokens or create accounts.

See [architecture and security boundaries](docs/architecture.md).

## Contracts

- `ExternalIdentityResolverInterface`: resolve an existing local user for a verified issuer/subject pair, else return null.
- `UserProvisionerInterface`: explicit application-owned provisioning policy; never called automatically.
- `ExternalGroupRoleMapperInterface`: map already trusted external groups to a controlled list of Symfony roles.
- `VerifiedExternalIdentity`: immutable identity data; callers are responsible for providing only already-verified claims.

The host app retains its User entity, identity-link model, permissions, database schema and audit policies. Symfony 8.2 native `oidc_login` is the intended browser-login integration point.

## Integration configuration

See [OIDC configuration (native Symfony 8.2 / Drenso)](docs/oidc-configuration.md) for Authentik values, exact callback/redirect URI registration, and existing application ENV compatibility. No new web OIDC env variables are introduced by this bundle.

## Development

`composer install` then `vendor/bin/phpunit`. No networked identity provider is required for the contract tests.
