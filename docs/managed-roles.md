# Group-to-role mapping (entity-free)

`ConfiguredGroupRoleMapper` maps an already verified OIDC identity's `groups` list to an explicit application role allowlist. Unknown groups grant **nothing**. It does not persist user roles.

```php
$mapper = new ConfiguredGroupRoleMapper([
    'registration-users' => ['ROLE_USER'],
    'registration-admins' => ['ROLE_ADMIN'],
]);

$mapped = $mapper->mappedRoles($verifiedExternalIdentity);
$roles = (new ManagedRoleSet(['ROLE_USER', 'ROLE_ADMIN']))
    ->synchronize($localUser->getRoles(), $mapped);
```

An application service must persist the resulting roles in its own User model, ideally in the same transaction as the account link or user update. Only roles declared as **managed** are replaced. Local roles outside this set are retained.

**Security warning:** `VerifiedExternalIdentity` is only a data carrier. You must use this helper exclusively with OIDC claims that were validated and whose group provenance is trusted; if claims were merged from a separate UserInfo endpoint, verify its integrity and subject binding before mapping. For administrators, consider a more conservative explicit approval policy. A missing `groups` claim removes managed roles, not local roles, if your app intentionally calls the synchronizer.

This helper does not automatically inject role state into a logged-in Symfony User. The host application owns when and how role changes are applied and whether they take effect on subsequent requests.
