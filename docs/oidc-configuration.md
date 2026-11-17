# OIDC setup: Symfony 8.2 native or Drenso

> This is an integration guide, not an active bundle authenticator. The host application owns `security.yaml`, OIDC client registration, and user provisioning.

## Rule: configure the underlying OIDC authenticator directly

The Identity Bridge must NOT define duplicate environment variables for issuer, discovery, client credentials or callback routes.
Symfony has **no reserved OIDC environment-variable names**: the examples use `OIDC_PROVIDER_URI`, `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`. Symfony and Drenso each consume values through their own YAML configuration.

**Select one web-login authenticator per firewall**; do not activate native `oidc_login` and Drenso's `oidc` simultaneously for the same browser login without a separately designed migration and security review.

| Authentik value | Native Symfony 8.2 | Drenso |
| --- | --- | --- |
| Published issuer | `oidc_login.provider_uri: %env(OIDC_PROVIDER_URI)%` | must match issuer in fetched discovery |
| Exact provider metadata URL | optional `oidc_login.discovery_uri: %env(OIDC_WELL_KNOWN_URL)%` | `drenso_oidc.clients.default.well_known_url: %env(OIDC_WELL_KNOWN_URL)%` |
| Web OAuth client ID | `%env(OIDC_CLIENT_ID)%` | `%env(OIDC_CLIENT_ID)%` |
| Web confidential client secret | `%env(OIDC_CLIENT_SECRET)%` | `%env(OIDC_CLIENT_SECRET)%` |
| Authorization-code callback | `oidc_login.check_path` (default `/oidc/callback`) | `security.firewalls.main.oidc.check_path` (default `/login_check`); align Drenso client `redirect_route` if set |
| PKCE | handled natively by `oidc_login` | explicitly `code_challenge_method: S256` |
| Session/logout | native Symfony Security | Drenso + Symfony Security |

Never put client secrets into an application bootstrap, mobile app, Git repository, log or query string. Store secrets in Symfony Secrets or a deployment secret manager; `.env` examples are placeholders only.

### Native Symfony 8.2

Copy and adapt [the native example](../examples/symfony-8.2-native-security.yaml). Import `security.route_loader` in `config/routes/security.yaml` if not already done (Flex recipes may do this). The default web callback is:

`https://YOUR-APP-ORIGIN/oidc/callback`

The value above is an **AuthentiK allowed redirect URI** for the corresponding **confidential web OAuth client**. Replace the origin with your actual externally reachable HTTPS application origin (including any base path). If `check_path` is customized, register that actual absolute callback instead. Verify with `bin/console debug:router`.

For an Authentik **global issuer** with provider-specific discovery, set BOTH:
- `OIDC_PROVIDER_URI` = the exact `issuer` field in the metadata document;
- `OIDC_WELL_KNOWN_URL` = the exact provider-specific `https://.../.well-known/openid-configuration` URL.

The `issuer` returned by discovery must match the configured provider URI exactly. Do not derive an issuer by trimming or adding provider paths or slashes.

### Drenso (existing Symfony apps)

Copy and adapt [the Drenso example](../examples/drenso-security.yaml). The typical default callback is:

`https://YOUR-APP-ORIGIN/login_check`

If `check_path` is `/login/oidc/check`, register `https://YOUR-APP-ORIGIN/login/oidc/check` instead. `redirect_route` must match the route/path the Drenso client puts into its authorization request; it is **not an additional callback** to register indiscriminately. Confirm the generated redirect URI at the IdP and the Symfony route configuration.

### Existing applications (no forced renaming)

- Nailpolishment already uses `OIDC_WELL_KNOWN_URL`, `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET` with Drenso and `/login_check`. Keep these unchanged until a deliberate migration.
- Teatime already uses `OIDC_DISCOVERY_URL`, `OIDC_WEB_CLIENT_ID`, `OIDC_WEB_CLIENT_SECRET`, `OIDC_WEB_REDIRECT_URI`. **Keep those names** in its Drenso config until migration. The example above is a clean-project convention, not a forced rename.
- Switching to native Symfony 8.2 changes the authenticator and the default callback; register the new URI in Authentik **before** cutover and remove obsolete registrations after verified rollout.

### Mobile and API are separate clients

Flutter uses a **public** OAuth client with its own `client_id`, no client secret, PKCE S256, and a mobile-specific registered callback (for example a verified app link). Do not register Symfony's web callback for Flutter or publish the web client's secret. Flutter receives authorization codes and refreshes tokens **directly with Authentik**.

Symfony's stateless API firewall checks *access* tokens using `access_token`. It does not have an OAuth login redirect or refresh-token endpoint. The API bundle publishes only explicitly approved **public mobile** configuration and uses server-side audience, issuer, token-purpose and client policies.

### Safe Authentik checklist

1. Create a **confidential web provider/application** for Symfony web login; enable Authorization Code; register the exact HTTPS callback.
2. Configure a separate **public mobile client** for each mobile app/platform as required; enforce PKCE S256 and register the exact mobile URI. No client secret.
3. Confirm the exact metadata `issuer`, provider-scoped discovery URL and signing keys. Configure a trustworthy issuer; never weaken TLS verification.
4. Verify `state`, `nonce`, PKCE and callback behavior with a real IdP integration test.
5. Check access-token target audience, provider/client binding, purpose and refresh grants separately; a successful web ID-token login does **not** certify the API token profile.
6. Only if needed: register a separately approved post-logout redirect URL; it is distinct from the login callback.
7. Do not auto-link identities by email or assumed stable `uid`; keep local account mapping application-owned.

## Official documentation

- https://symfony.com/blog/new-in-symfony-8-2-openid-connect-login
- https://symfony.com/doc/8.2/security/oidc_login.html
- https://github.com/Drenso/symfony-oidc
