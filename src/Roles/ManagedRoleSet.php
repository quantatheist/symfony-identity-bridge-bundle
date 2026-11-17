<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Roles;

/** Pure calculation; the application's User repository owns persistence. */
final readonly class ManagedRoleSet
{
    /**
     * @param list<string> $managedRoles
     */
    public function __construct(private array $managedRoles)
    {
        foreach ($managedRoles as $role) {
            if (!is_string($role) || !preg_match('/^ROLE_[A-Z0-9_]+$/D', $role)) {
                throw new \InvalidArgumentException('Managed role names must be valid.');
            }
        }
    }

    /**
     * @param list<string> $existingRoles
     * @param list<string> $mappedRoles
     * @return list<string>
     */
    public function synchronize(array $existingRoles, array $mappedRoles): array
    {
        foreach ($mappedRoles as $role) {
            if (!in_array($role, $this->managedRoles, true)) {
                throw new \InvalidArgumentException('A mapper returned an unmanaged role.');
            }
        }

        return array_values(array_unique([
            ...array_values(array_diff($existingRoles, $this->managedRoles)),
            ...$mappedRoles,
        ]));
    }
}
