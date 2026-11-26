<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Tests\Identity;

use PHPUnit\Framework\TestCase;
use Quantatheist\IdentityBridge\Identity\TrustedMobileIssuerPair;
use Quantatheist\IdentityBridge\Identity\VerifiedExternalIdentity;

final class TrustedMobileIssuerPairTest extends TestCase
{
    public function testMatchingHashedSubjectAcrossTwoVerifiedIssuers(): void
    {
        $pair = new TrustedMobileIssuerPair('https://auth.example/application/o/web/', 'https://auth.example/application/o/mobile/');
        self::assertTrue($pair->matches(
            new VerifiedExternalIdentity('https://auth.example/application/o/mobile/', 'stable-hash'),
            new VerifiedExternalIdentity('https://auth.example/application/o/web/', 'stable-hash'),
        ));
        self::assertFalse($pair->matches(
            new VerifiedExternalIdentity('https://auth.example/application/o/mobile/', 'different'),
            new VerifiedExternalIdentity('https://auth.example/application/o/web/', 'stable-hash'),
        ));
        self::assertFalse($pair->matches(
            new VerifiedExternalIdentity('https://foreign.example/application/o/mobile/', 'stable-hash'),
            new VerifiedExternalIdentity('https://auth.example/application/o/web/', 'stable-hash'),
        ));
    }

    public function testForeignOriginAndUnsafeIssuerFailClosed(): void
    {
        foreach ([
            ['https://auth.example/application/o/web/', 'https://other.example/application/o/mobile/'],
            ['https://auth.example/application/o/web/', 'http://auth.example/application/o/mobile/'],
            ['https://auth.example/application/o/web/', 'https://auth.example/application/o/web/'],
            ['https://auth.example/application/o/web/', 'https://auth.example/application/o/mobile/?x=1'],
        ] as [$web, $mobile]) {
            try {
                new TrustedMobileIssuerPair($web, $mobile);
                self::fail('Unsafe pair was accepted.');
            } catch (\InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }
}
