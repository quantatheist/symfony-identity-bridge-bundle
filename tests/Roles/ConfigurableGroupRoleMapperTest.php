<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Tests\Roles;

use PHPUnit\Framework\TestCase;
use Quantatheist\IdentityBridge\Identity\VerifiedExternalIdentity;
use Quantatheist\IdentityBridge\Roles\ConfigurableGroupRoleMapper;

final class ConfigurableGroupRoleMapperTest extends TestCase
{
    private function identity(mixed $groups): VerifiedExternalIdentity
    {
        return new VerifiedExternalIdentity('https://issuer.example/', 'subject', ['groups' => $groups]);
    }

    public function testExactGroupsAllowTeatimeAndNailpolishmentMappings(): void
    {
        $mapper = new ConfigurableGroupRoleMapper([
            'teatime_users' => ['ROLE_USER'],
            'teatime_admins' => ['ROLE_USER', 'ROLE_ADMIN'],
        ]);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $mapper->mappedRoles($this->identity(['teatime_admins'])));
        self::assertSame([], $mapper->mappedRoles($this->identity(['Teatime_admins', 'teatime_admins_extra'])));
    }

    public function testRegistrationCanUseSessionOnlyRolePolicy(): void
    {
        $mapper = new ConfigurableGroupRoleMapper(['registration_admins' => ['ROLE_ADMIN']], ['ROLE_USER']);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $mapper->mappedRoles($this->identity(['registration_admins'])));
        foreach ([null, 'registration_admins', 13, [['registration_admins']], [' registration_admins']] as $invalid) {
            self::assertSame(['ROLE_USER'], $mapper->mappedRoles($this->identity($invalid)));
        }
    }

    public function testGroupListDuplicatesDoNotDuplicateRoles(): void
    {
        $mapper = new ConfigurableGroupRoleMapper(['group' => ['ROLE_USER', 'ROLE_ADMIN']], ['ROLE_USER']);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $mapper->mappedRoles($this->identity(['group', 'group'])));
    }

    public function testRejectsInvalidConfiguredRoles(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ConfigurableGroupRoleMapper(['admins' => ['ROLE_SUPER USER']]);
    }
}
