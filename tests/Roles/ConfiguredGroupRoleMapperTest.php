<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Tests\Roles;

use PHPUnit\Framework\TestCase;
use Quantatheist\IdentityBridge\Identity\VerifiedExternalIdentity;
use Quantatheist\IdentityBridge\Roles\ConfiguredGroupRoleMapper;
use Quantatheist\IdentityBridge\Roles\ManagedRoleSet;

final class ConfiguredGroupRoleMapperTest extends TestCase
{
    private function identity(mixed $groups): VerifiedExternalIdentity
    {
        return new VerifiedExternalIdentity('https://idp.example.test/', 'subject', ['groups' => $groups]);
    }

    public function testMapsOnlyExplicitTrustedGroups(): void
    {
        $mapper = new ConfiguredGroupRoleMapper([
            'staff' => ['ROLE_USER'],
            'admins' => ['ROLE_ADMIN'],
        ]);
        self::assertSame(['ROLE_USER'], $mapper->mappedRoles($this->identity(['staff', 'unknown'])));
        self::assertSame([], $mapper->mappedRoles($this->identity(['unknown'])));
        self::assertSame([], $mapper->mappedRoles($this->identity('admins')));
    }

    public function testDoesNotGrantAdminForSubstring(): void
    {
        $mapper = new ConfiguredGroupRoleMapper(['admins' => ['ROLE_ADMIN']]);
        self::assertSame([], $mapper->mappedRoles($this->identity(['not-admins'])));
    }

    public function testManagedRolesCanBeRemovedWithoutChangingLocalRoles(): void
    {
        $sync = new ManagedRoleSet(['ROLE_USER', 'ROLE_ADMIN']);
        self::assertSame(
            ['ROLE_BILLING', 'ROLE_USER'],
            $sync->synchronize(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_BILLING'], ['ROLE_USER'])
        );
    }

    public function testUnmanagedMapperRoleIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ManagedRoleSet(['ROLE_USER']))->synchronize(['ROLE_USER'], ['ROLE_ADMIN']);
    }
}
