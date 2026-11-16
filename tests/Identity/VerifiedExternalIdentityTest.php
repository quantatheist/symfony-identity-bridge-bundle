<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Tests\Identity;

use PHPUnit\Framework\TestCase;
use Quantatheist\IdentityBridge\Identity\VerifiedExternalIdentity;

final class VerifiedExternalIdentityTest extends TestCase
{
    public function testPreservesProviderScopedIdentity(): void
    {
        $identity = new VerifiedExternalIdentity('https://issuer.example/', 'subject-123');
        self::assertSame('https://issuer.example/', $identity->issuer);
        self::assertSame('subject-123', $identity->subject);
    }

    public function testRejectsBlankSubject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new VerifiedExternalIdentity('https://issuer.example/', ' ');
    }
}
