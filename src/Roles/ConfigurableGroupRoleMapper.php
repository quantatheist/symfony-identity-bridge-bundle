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
        return $this->mappedRolesFromTrustedGroups($identity->claims['groups'] ?? []);
    }

    /**
     * Map a group claim supplied by a host OIDC adapter after provider verification.
     * This method performs NO claim or token verification. Use only after Drenso
     * (or another trusted authenticator) has validated the OIDC response.
     *
     * @return list<string>
     */
    public function mappedRolesFromTrustedGroups(mixed $groups): array
    {
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

    /**
     * Explain role decisions for an already authenticated user.
     * Do not pass unverified claims. This returns only group names and roles,
     * never tokens, subjects, identifiers or the complete claims payload.
     *
     * @param list<string> $effectiveRoles Roles currently assigned by the host
     * @return array{groups: list<string>, group_role_matches: array<string, list<string>>, default_roles: list<string>, external_roles: list<string>, local_roles: list<string>, effective_roles: list<string>, has_role_admin: bool}
     */
    public function diagnoseTrustedGroups(mixed $groups, array $effectiveRoles = []): array
    {
        $groups = is_array($groups) && array_is_list($groups) ? $groups : [];
        $groups = array_values(array_unique(array_filter($groups, static fn (mixed $value): bool =>
            is_string($value) && $value !== '' && trim($value) === $value
        )));
        $matches = [];
        foreach ($this->groupRoles as $group => $roles) {
            if (in_array($group, $groups, true)) {
                $matches[$group] = $roles;
            }
        }
        $externalRoles = $this->mappedRolesFromTrustedGroups($groups);
        // A role that is both locally and externally granted is attributed to the
        // external policy; the origin of overlapping roles cannot be inferred.
        $localRoles = array_values(array_diff($effectiveRoles, $externalRoles));

        return [
            'groups' => $groups,
            'group_role_matches' => $matches,
            'default_roles' => $this->defaultRoles,
            'external_roles' => $externalRoles,
            'local_roles' => $localRoles,
            'effective_roles' => array_values(array_unique($effectiveRoles)),
            'has_role_admin' => in_array('ROLE_ADMIN', $effectiveRoles, true),
        ];
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
