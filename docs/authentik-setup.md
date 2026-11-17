# Authentik configuration for Symfony web login and Flutter/API clients

This is a practical setup checklist, **not** a new authentication protocol. The bundle adds no callback and does not issue or refresh mobile tokens.

## Create separate OAuth2/OIDC providers/clients

| Authentik field / decision | Symfony browser client | Flutter/mobile client |
| --- | --- | --- |
| Application / Provider name | `teatime-web` or `nailpolishment-web` | `teatime-mobile` or `nailpolishment-mobile` |
| Client type | **Confidential** (secret stored server-side) | **Public** (no secret) |
| Grant / flow | Authorization Code | Authorization Code + PKCE S256 |
| Callback / redirect URI | Symfony 8.2: `/oidc/callback`; Drenso: `/login_check` by default | App-specific registered custom scheme / verified app link (as supported by SDK and OS) |
| OAuth scopes | `openid profile email`; `groups` only when trusted and needed | `openid profile email offline_access` when refresh is required; add server-approved API scopes |
| Signing keys | Use configured trusted signing key (e.g. RS256) | Use configured trusted signing key (e.g. RS256) |
| Token renewal | Native Symfony 8.2 token refresher, when configured and needed | Existing Flutter `oidc` SDK calls Authentik token endpoint with refresh token |
| API audience | Not interchangeable with web client ID | Explicitly require intended Symfony API audience on access tokens |
| Provisioning | Application-owned opt-in, disabled by default | Mobile API requests never silently auto-link accounts |

Use **exact redirect URI allowlists**, not wildcards. Do not use the Symfony web callback for Flutter, or a mobile callback for the Symfony web login.

## Local HTTPS example

If Symfony is genuinely served at `https://localhost:8000`:

- **Native Symfony 8.2:** Authentik redirect URI `https://localhost:8000/oidc/callback`
- **Drenso:** Authentik redirect URI `https://localhost:8000/login_check`

If the actual server uses `http://localhost:8000`, the URI would need to be `http://localhost:8000/... ` instead, but only use HTTP loopback redirects when your exact Authentik deployment permits them for local development. A browser trusting a local HTTPS CA is preferable. Do not disable TLS validation in Symfony or the OIDC SDK to work around certificate issues.

If a Symfony application has a base path (e.g. `/teatime`), include it in the registered callback. For a reverse proxy, configure trusted proxies/hosts so Symfony generates the actual externally accessible origin. Never derive OAuth destinations from an untrusted Host header.

## Where to copy values from Authentik

| Authentik | Native Symfony 8.2 `oidc_login` | Drenso `drenso_oidc` |
| --- | --- | --- |
| Actual OIDC `issuer` from discovery JSON | `provider_uri: '%env(OIDC_PROVIDER_URI)%'` | Discovered via `well_known_url`; check exact issuer |
| Exact `.well-known/openid-configuration` URL for the **web** provider | `discovery_uri: '%env(OIDC_WELL_KNOWN_URL)%'` when necessary | `well_known_url: '%env(OIDC_WELL_KNOWN_URL)%'` |
| Web OAuth **Client ID** | `client_id: '%env(OIDC_CLIENT_ID)%'` | same option and conventional ENV name |
| Web OAuth **Client Secret** | `client_secret_basic: '%env(OIDC_CLIENT_SECRET)%'` | `client_secret: '%env(OIDC_CLIENT_SECRET)%'` |

`OIDC_PROVIDER_URI`, `OIDC_WELL_KNOWN_URL`, etc. are **example naming conventions**, not magically read by Symfony or Drenso. The app `security.yaml` / `drenso_oidc.yaml` reads the envs explicitly. Existing Nailpolishment and Teatime variable names can remain as-is.

Authentik can advertise a **global issuer** but provide a provider-specific discovery URL; these may not be the same URL. Use the exact `issuer` field for Symfony `provider_uri` and the exact provider metadata URL for `discovery_uri`. Don't construct the issuer by guessing the provider slug.

## Token and subject security acceptance

1. The identity is scoped to the exact `(issuer, sub)` pair. If two OAuth providers issue different `sub` for the same human, do not merge identities by email or `uid`. Verify issuer/sub mode against a real Authentik instance before enabling account linkage.
2. Sign and validate ID and access tokens with established library mechanisms, never bundle-specific cryptography. Explicitly configure allowed signing algorithms and key rotation.
3. The API must require an **access** token for its audience and approved clients; an OIDC ID token must not accidentally pass as an API bearer token. Symfony 8.2 supports RFC 9068 `at+jwt` token typing when the issuer actually conforms. If not, adopt a documented token-purpose strategy (e.g. mandatory introspection).
4. For refresh, allow Authentik's refresh-token grant and the appropriate `offline_access` mapping, check expiration/rotation/revocation settings, and keep refresh tokens out of Symfony API requests.
5. Verify `email_verified` source before relying on it for account invitation or linking. Group-claim provenance and mapping must be trusted; never grant administrator roles from unvalidated arbitrary client claims.
6. A browser logout, mobile local logout, provider end-session, refresh-token revocation and immediate API-access-token invalidation are **different guarantees**. Test them separately.
7. For API scope and group authorization, retain project-owned voters and membership policies.

## Operations and acceptance

- Confirm public issuer, discovery URL, signing key, web client ID and secret, exact HTTPS redirect, and **separate mobile public client**.
- Confirm native `oidc_login` PKCE/state/nonce and Drenso `code_challenge_method: S256` on a real test login.
- Ensure the native Symfony callback route exists: `security.route_loader` imported, then `bin/console debug:router`.
- Test `/oidc/callback` for native Symfony, `/login_check` for Drenso, and the explicitly registered Flutter callback for mobile.
- Inspect only redacted token claims for `iss`, `aud`, `azp`, `sub`, `exp`, `scope` and token-purpose evidence; never commit real credentials.
- Test JWKS rotation, account disabled, token revocation, provider downtime, refresh failure and group-role removal.
- For reverse proxies, ensure external HTTPS scheme and canonical host are recognized and callback query parameters are not logged.

## Official references

- https://symfony.com/blog/new-in-symfony-8-2-openid-connect-login
- https://symfony.com/doc/8.2/security/oidc_login.html
- https://symfony.com/doc/8.2/security/access_token.html
- https://github.com/Drenso/symfony-oidc
- https://docs.goauthentik.io/add-secure-apps/providers/oauth2/
