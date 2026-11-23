<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Roles;

/**
 * Reconcile only the subset of roles owned by the identity provider.
 * Business-specific/local roles must survive. Stale external grants are revoked.
 */
final readonly class ManagedRoleSynchronizer
{
    /** @var list<string> */
    private array $managedRoles;

    /** @param list<string> $managedRoles */
    public function __construct(array $managedRoles)
    {
        foreach ($managedRoles as $role) {
            if (!is_string($role) || !preg_match('/^ROLE_[A-Z0-9_]+$/D', $role)) {
                throw new \InvalidArgumentException('Invalid managed role.');
            }
        }
        $this->managedRoles = array_values(array_unique($managedRoles));
    }

    /**
     * @param list<string> $currentRoles
     * @param list<string> $mappedRoles
     * @return list<string>
     */
    public function synchronize(array $currentRoles, array $mappedRoles): array
    {
        foreach ([$currentRoles, $mappedRoles] as $roles) {
            foreach ($roles as $role) {
                if (!is_string($role)) {
                    throw new \InvalidArgumentException('Invalid role value.');
                }
            }
        }
        foreach ($mappedRoles as $role) {
            if (!in_array($role, $this->managedRoles, true)) {
                throw new \InvalidArgumentException('Mapper returned an unmanaged role.');
            }
        }
        $local = array_values(array_filter($currentRoles, fn (string $role): bool =>
            !in_array($role, $this->managedRoles, true)
        ));

        return array_values(array_unique([...$local, ...$mappedRoles]));
    }
}
