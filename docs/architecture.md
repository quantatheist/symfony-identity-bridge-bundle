# Architecture and security boundaries

This bundle is a **contract-only starting point** for identity resolution. It does not implement OIDC, validate a JWT, persist users or install authentication listeners.

- Use native Symfony 8.2 `oidc_login` for browser login once verified against the installed Symfony version; on Symfony 8.1 retain the application's proven OIDC authenticator until migrated.
- Only construct `VerifiedExternalIdentity` after native OIDC verification. It is a data carrier, **not a validation or trust boundary**.
- The canonical external identifier is the exact (`issuer`, `sub`) pair. An identity-link repository belongs to the host application.
- No automatic provisioning, account linking, Doctrine entities, migrations, or LDAP writes are provided.
- `UserProvisionerInterface` is an **opt-in extension point**; the host application must decide when it may run.
- Roles mapped from already verified trusted group claims must be limited to a well-defined managed set; do not replace all application roles implicitly.
- Never join accounts based on email or unverified `uid`; provider-specific `sub` behavior must be tested.
- API token validation is outside this bundle and belongs to Symfony Security / the OAuth Resource Server bundle.

Planned milestones: native Symfony user-provider bridge, tested explicit provisioning strategy, managed-role synchronisation with tests, Teatime and Nailpolishment adapters. No production-ready authenticator exists yet.
