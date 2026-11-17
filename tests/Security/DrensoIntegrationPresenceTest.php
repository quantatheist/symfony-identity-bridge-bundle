<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Tests\Security;

use PHPUnit\Framework\TestCase;
use Quantatheist\IdentityBridge\Security\DrensoOidcUserProvider;
use Drenso\OidcBundle\Security\UserProvider\OidcUserProviderInterface;

final class DrensoIntegrationPresenceTest extends TestCase
{
    public function testOptionalAdapterImplementsDrensoProviderContract(): void
    {
        if (!interface_exists(OidcUserProviderInterface::class)) {
            self::markTestSkipped('Optional Drenso dependency not installed.');
        }
        self::assertTrue(is_a(DrensoOidcUserProvider::class, OidcUserProviderInterface::class, true));
    }
}
