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

[Authentik setup and redirect matrix](docs/authentik-setup.md)

## Development

`composer install` then `vendor/bin/phpunit`. No networked identity provider is required for the contract tests.

## Reusable role policies (experimental integration)

`Roles\\ConfigurableGroupRoleMapper` maps exact, trusted group claims to an explicit allowlist; `Roles\\ManagedRoleSynchronizer` removes stale externally managed roles without deleting application-owned roles. See [three-project integration matrix](docs/integration-matrix.md). This does not yet constitute a working login/authenticator; host integration and real-provider tests remain required.

### Existing verified user providers (Drenso)

A host that already receives authenticated OIDC group data from Drenso may use
`ConfigurableGroupRoleMapper::mappedRolesFromTrustedGroups($groups)` without
inventing an issuer or subject just to map roles. **This method does not verify
claims**: call it only inside the trusted OIDC user-provider flow, never with
request parameters or decoded unverified JWTs. For new provider-neutral adapters,
prefer `mappedRoles(VerifiedExternalIdentity $identity)`.

Neither method refreshes Authentik membership automatically: cached sessions
continue to reflect the last authenticated group claim until reauthentication.

### Role diagnostics (developer-only hosts)

`ConfigurableGroupRoleMapper::diagnoseTrustedGroups($trustedGroups, $currentRoles)`
provides a structured explanation of the trusted group's exact role matches,
explicit default roles, group/default-derived externally managed roles, currently
effective roles, whether `ROLE_ADMIN` is effective, and roles outside this mapper
policy (reported as `local_roles`). Overlapping local/external role origins cannot
be determined from the final role list: overlapping roles are attributed to the
mapper policy, and `local_roles` means **not covered by this mapper**.

This is a pure computation helper, **not an HTTP endpoint**. Host apps must
restrict any UI/route displaying it to development and explicitly authorized
users. Pass only a previously authenticated OIDC session's group values and role
list; do not include raw claims, tokens, secrets, email or subjects. Because
sessions can be stale, reauthenticate after Authentik membership changes.
