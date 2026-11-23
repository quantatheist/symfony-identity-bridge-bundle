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

    public function testTrustedGroupAdapterMatchesVerifiedIdentityMapping(): void
    {
        $mapper = new ConfigurableGroupRoleMapper(['registration_admins' => ['ROLE_ADMIN']], ['ROLE_USER']);
        foreach ([['registration_admins'], ['unknown'], null, 'registration_admins', [' registration_admins'], ['registration_admins' => true]] as $groups) {
            self::assertSame(
                $mapper->mappedRoles($this->identity($groups)),
                $mapper->mappedRolesFromTrustedGroups($groups),
            );
        }
    }

    public function testGroupListDuplicatesDoNotDuplicateRoles(): void
    {
        $mapper = new ConfigurableGroupRoleMapper(['group' => ['ROLE_USER', 'ROLE_ADMIN']], ['ROLE_USER']);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $mapper->mappedRoles($this->identity(['group', 'group'])));
    }

    public function testDiagnosticReportsExactMatchesAndRoleOriginsWithoutClaims(): void
    {
        $mapper = new ConfigurableGroupRoleMapper(['registration_admins' => ['ROLE_ADMIN']], ['ROLE_USER']);
        $report = $mapper->diagnoseTrustedGroups(['registration_admins', 'other', 'registration_admins'], ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_LOCAL']);
        self::assertSame(['registration_admins', 'other'], $report['groups']);
        self::assertSame(['registration_admins' => ['ROLE_ADMIN']], $report['group_role_matches']);
        self::assertSame(['ROLE_USER'], $report['default_roles']);
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], $report['external_roles']);
        self::assertSame(['ROLE_LOCAL'], $report['local_roles']);
        self::assertTrue($report['has_role_admin']);
        self::assertArrayNotHasKey('claims', $report);
        self::assertArrayNotHasKey('token', $report);
    }

    public function testDiagnosticIgnoresInvalidGroupClaimsAndDoesNotInventAdmin(): void
    {
        $mapper = new ConfigurableGroupRoleMapper(['registration_admins' => ['ROLE_ADMIN']], ['ROLE_USER']);
        foreach (['registration_admins', ['registration_admins' => true], [['registration_admins']], [' registration_admins']] as $claim) {
            $report = $mapper->diagnoseTrustedGroups($claim, ['ROLE_USER']);
            self::assertSame([], $report['group_role_matches']);
            self::assertSame(['ROLE_USER'], $report['external_roles']);
            self::assertFalse($report['has_role_admin']);
        }
    }

    public function testRejectsInvalidConfiguredRoles(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ConfigurableGroupRoleMapper(['admins' => ['ROLE_SUPER USER']]);
    }
}
