<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Tests\Roles;

use PHPUnit\Framework\TestCase;
use Quantatheist\IdentityBridge\Roles\ManagedRoleSynchronizer;

final class ManagedRoleSynchronizerTest extends TestCase
{
    public function testRevokesFormerAdminRoleButPreservesHouseholdRoles(): void
    {
        $sync = new ManagedRoleSynchronizer(['ROLE_USER', 'ROLE_ADMIN']);
        self::assertSame(
            ['ROLE_HOUSEHOLD_OWNER', 'ROLE_USER'],
            $sync->synchronize(['ROLE_ADMIN', 'ROLE_HOUSEHOLD_OWNER', 'ROLE_USER'], ['ROLE_USER'])
        );
    }

    public function testRejectsUndeclaredRolesFromMapper(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ManagedRoleSynchronizer(['ROLE_USER']))->synchronize([], ['ROLE_ADMIN']);
    }

    public function testRepeatedSynchronizationIsStable(): void
    {
        $sync = new ManagedRoleSynchronizer(['ROLE_USER', 'ROLE_ADMIN']);
        $roles = $sync->synchronize(['ROLE_LOCAL'], ['ROLE_ADMIN', 'ROLE_USER']);
        self::assertSame($roles, $sync->synchronize($roles, ['ROLE_ADMIN', 'ROLE_USER']));
    }
}
