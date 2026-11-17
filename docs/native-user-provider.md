# Symfony 8.2 user resolution using the Identity Bridge

**Requires Symfony 8.2 native `oidc_login`.** This bundle does not implement OIDC; Symfony verifies the ID token, state, nonce, PKCE, issuer and audience. The bridge only resolves the trusted claims to an existing local Symfony User.

## Host Symfony application example

```yaml
# config/services.yaml (merge with the existing configuration)
services:
    Quantatheist\IdentityBridge\Security\NativeOidcUserProvider:
        arguments:
            $resolver: '@App\\Security\\ExistingIdentityResolver'
            $persistentUserProvider: '@App\\Security\\LocalUserProvider'
            $trustedIssuers: ['%env(OIDC_PROVIDER_URI)%']
            $allowProvisioning: false

# config/packages/security.yaml (fragment)
security:
    providers:
        local_storage:
            id: App\Security\LocalUserProvider
        oidc_users:
            id: Quantatheist\IdentityBridge\Security\NativeOidcUserProvider

    firewalls:
        main:
            provider: oidc_users
            oidc_login:
                provider_uri: '%env(OIDC_PROVIDER_URI)%'
                discovery_uri: '%env(OIDC_WELL_KNOWN_URL)%'
                client_id: '%env(OIDC_CLIENT_ID)%'
                client_authentication:
                    client_secret_basic: '%env(OIDC_CLIENT_SECRET)%'
```

Replace the App service names with real services. The identity resolver must lookup a user linked to the exact `(issuer, sub)` pair. Local storage provider must support `refreshUser` and `supportsClass` for the app's real User class.

**Do not use the built-in `oidc: ~` provider when you want persisted local users with controlled provisioning**, because Symfony's built-in OIDC provider creates an in-memory user from verified claims; the custom provider is required to load your own account.

The bridge rejects an unknown issuer or a subject inconsistent with the identifier supplied by native Symfony OIDC. Missing local identities are denied by default.

### Explicit provisioning

To permit account creation, implement `UserProvisionerInterface` in the app and configure both `$provisioner` and `$allowProvisioning: true`. The application owns transactional exact issuer+sub linking, uniqueness constraints, locks, idempotency and authorization policies. Do not identify existing users by email or `uid`.

### Legacy Drenso

The native provider above is *not* a drop-in adapter for Drenso's authenticator. Keep the existing Drenso UserProvider until its separate adapter is implemented and integration-tested. This bundle intentionally does not override its `/login_check` default.

### Role mapping

`ExternalGroupRoleMapperInterface` is an app-owned adapter for trusted group claims. Integrate it with your application-owned user/role updater and preserve unmanaged local roles. Never let arbitrary group strings grant administrator rights without a configured allowlist.

### Security boundaries

This class must only be registered behind Symfony's native cryptographically validating `oidc_login` flow. Direct calls with untrusted arrays are not secure: `VerifiedExternalIdentity` is a data object, not a verified ID token.
