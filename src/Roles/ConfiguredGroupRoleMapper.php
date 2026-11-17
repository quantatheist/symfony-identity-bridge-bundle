<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Roles;

use Quantatheist\IdentityBridge\Identity\VerifiedExternalIdentity;

/**
 * Explicit, provider-neutral allowlist mapping of trusted groups to app roles.
 * Role claims must already have been validated by the OIDC authenticator.
 */
final readonly class ConfiguredGroupRoleMapper implements ExternalGroupRoleMapperInterface
{
    /**
     * @param array<string, list<string>> $groupToRoles
     */
    public function __construct(
        private array $groupToRoles,
        private string $groupClaim = 'groups',
    ) {
        if ('' === trim($groupClaim)) {
            throw new \InvalidArgumentException('Group claim cannot be blank.');
        }

        foreach ($groupToRoles as $group => $roles) {
            if (!is_string($group) || '' === trim($group) || !is_array($roles) || !array_is_list($roles)) {
                throw new \InvalidArgumentException('Invalid group role mapping.');
            }
            foreach ($roles as $role) {
                if (!is_string($role) || !preg_match('/^ROLE_[A-Z0-9_]+$/D', $role)) {
                    throw new \InvalidArgumentException('Mapped roles must be explicit Symfony ROLE_* names.');
                }
            }
        }
    }

    public function mappedRoles(VerifiedExternalIdentity $identity): array
    {
        $groups = $identity->claims[$this->groupClaim] ?? [];
        if (!is_array($groups) || !array_is_list($groups)) {
            return [];
        }

        $roles = [];
        foreach ($groups as $group) {
            if (!is_string($group) || !array_key_exists($group, $this->groupToRoles)) {
                continue;
            }

            foreach ($this->groupToRoles[$group] as $role) {
                $roles[$role] = true;
            }
        }

        return array_keys($roles);
    }
}
