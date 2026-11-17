# Drenso integration (Symfony 8.1)

This is an **optional** adapter, not a replacement for Drenso. Install `drenso/symfony-oidc-bundle:^4.5` in the host application.

The host application **must** provide:
- `ExternalIdentityResolverInterface`, resolving exact `(issuer, sub)` links;
- `UserProviderInterface` for local Symfony User storage/session refresh;
- a list of **exact trusted OIDC issuer strings** from the real metadata;
- an explicit `UserProvisionerInterface` and `allowProvisioning: true` **only when account creation is intended**.

Example host `config/services.yaml`:

```yaml
services:
    Quantatheist\IdentityBridge\Security\NativeOidcUserProvider:
        arguments:
            $resolver: '@App\\Security\\ExistingIdentityResolver'
            $persistentUserProvider: '@App\\Security\\LocalUserProvider'
            $trustedIssuers: ['%env(OIDC_ISSUER)%']
            $allowProvisioning: false

    Quantatheist\IdentityBridge\Security\DrensoOidcUserProvider:
        arguments:
            $identityResolver: '@Quantatheist\\IdentityBridge\\Security\\NativeOidcUserProvider'
            $persistentUserProvider: '@App\\Security\\LocalUserProvider'
            $requestStack: '@request_stack'
```

Example `config/packages/security.yaml` fragment:

```yaml
security:
    providers:
        oidc_users:
            id: Quantatheist\IdentityBridge\Security\DrensoOidcUserProvider
    firewalls:
        main:
            provider: oidc_users
            oidc:
                client: default
                login_path: /login
                check_path: /login_check
                user_identifier_property: sub
```

`OIDC_ISSUER` is the *published* `issuer` from the OIDC metadata. You may reuse any existing issuer env name; no name is mandatory.

The Drenso bundle still owns `drenso_oidc.clients.default.well_known_url`, `client_id`, `client_secret`, OIDC validation and the `/login_check` redirect. This adapter never exchanges tokens, never stores refresh tokens and never creates a new login route.

**Do not enable both this and Symfony native `oidc_login` on the same firewall.**

Drenso's user data can be based on ID token and/or UserInfo claims depending on its configuration. Verify in an integration test that `iss` and `sub` are sourced from the validated ID token rather than an independently untrusted source. If that guarantee cannot be established, **do not deploy this adapter**.

This adapter holds the pending resolved User in the **current request attributes**, not on a shared service instance. It is cleared after `loadOidcUser()`. Unknown users are rejected unless provisioning has been explicitly enabled in the underlying resolver. The app owns role mapping and account state checks.
