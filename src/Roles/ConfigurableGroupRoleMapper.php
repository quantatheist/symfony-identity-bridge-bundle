<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Roles;

use Quantatheist\IdentityBridge\Identity\VerifiedExternalIdentity;

/**
 * Map trusted OIDC group names to a fixed, application-approved set of Symfony roles.
 * No provider calls and no trust elevation: the caller must pass a verified identity.
 */
final readonly class ConfigurableGroupRoleMapper implements ExternalGroupRoleMapperInterface
{
    /** @var array<string, list<string>> */
    private array $groupRoles;
    /** @var list<string> */
    private array $defaultRoles;

    /**
     * @param array<string, list<string>> $groupRoles Exact (case-sensitive) group name => managed roles.
     * @param list<string> $defaultRoles Roles explicitly granted regardless of groups.
     */
    public function __construct(array $groupRoles, array $defaultRoles = [])
    {
        foreach ($groupRoles as $group => $roles) {
            if (!is_string($group) || '' === trim($group) || $group !== trim($group) || !is_array($roles)) {
                throw new \InvalidArgumentException('Invalid configured group.');
            }
            $this->validateRoles($roles);
        }
        $this->validateRoles($defaultRoles);
        $this->groupRoles = $groupRoles;
        $this->defaultRoles = array_values(array_unique($defaultRoles));
    }

    public function mappedRoles(VerifiedExternalIdentity $identity): array
    {
        $groups = $identity->claims['groups'] ?? [];
        // Invalid/missing claims never confer group-derived privileges.
        if (!is_array($groups) || !array_is_list($groups)) {
            $groups = [];
        }
        $groups = array_values(array_filter($groups, static fn (mixed $value): bool =>
            is_string($value) && $value !== '' && $value === trim($value)
        ));
        $roles = $this->defaultRoles;
        foreach ($this->groupRoles as $group => $managed) {
            if (in_array($group, $groups, true)) {
                array_push($roles, ...$managed);
            }
        }

        return array_values(array_unique($roles));
    }

    /** @param array<mixed> $roles */
    private function validateRoles(array $roles): void
    {
        if (!array_is_list($roles)) {
            throw new \InvalidArgumentException('Roles must be a list.');
        }
        foreach ($roles as $role) {
            if (!is_string($role) || !preg_match('/^ROLE_[A-Z0-9_]+$/D', $role)) {
                throw new \InvalidArgumentException('Invalid configured role.');
            }
        }
    }
}
