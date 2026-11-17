<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Tests\Security;

use PHPUnit\Framework\TestCase;
use Quantatheist\IdentityBridge\Identity\ExternalIdentityResolverInterface;
use Quantatheist\IdentityBridge\Identity\VerifiedExternalIdentity;
use Quantatheist\IdentityBridge\Provisioning\UserProvisionerInterface;
use Quantatheist\IdentityBridge\Security\NativeOidcUserProvider;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class NativeOidcUserProviderTest extends TestCase
{
    private const ISSUER = 'https://auth.example.test/';

    private function persistence(): UserProviderInterface
    {
        return $this->createMock(UserProviderInterface::class);
    }

    private function claims(): array
    {
        return ['iss' => self::ISSUER, 'sub' => 'external-sub'];
    }

    public function testKnownIdentityResolvesExistingUser(): void
    {
        $user = $this->createMock(UserInterface::class);
        $resolver = $this->createMock(ExternalIdentityResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->with(self::callback(
            fn (VerifiedExternalIdentity $identity): bool => $identity->issuer === self::ISSUER
                && $identity->subject === 'external-sub'
        ))->willReturn($user);

        $provider = new NativeOidcUserProvider($resolver, $this->persistence(), [self::ISSUER]);
        self::assertSame($user, $provider->loadUserByIdentifier('external-sub', $this->claims()));
    }

    public function testUnknownIdentityDeniedByDefault(): void
    {
        $resolver = $this->createMock(ExternalIdentityResolverInterface::class);
        $resolver->method('resolve')->willReturn(null);
        $this->expectException(UserNotFoundException::class);
        (new NativeOidcUserProvider($resolver, $this->persistence(), [self::ISSUER]))
            ->loadUserByIdentifier('external-sub', $this->claims());
    }

    public function testWrongIssuerRejectedBeforeResolution(): void
    {
        $resolver = $this->createMock(ExternalIdentityResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');
        $claims = $this->claims();
        $claims['iss'] = 'https://attacker.example/';
        $this->expectException(AuthenticationException::class);
        (new NativeOidcUserProvider($resolver, $this->persistence(), [self::ISSUER]))
            ->loadUserByIdentifier('external-sub', $claims);
    }

    public function testWrongSubjectRejectedBeforeResolution(): void
    {
        $resolver = $this->createMock(ExternalIdentityResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');
        $this->expectException(AuthenticationException::class);
        (new NativeOidcUserProvider($resolver, $this->persistence(), [self::ISSUER]))
            ->loadUserByIdentifier('another-sub', $this->claims());
    }

    public function testProvisioningRequiresExplicitOptIn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NativeOidcUserProvider(
            $this->createMock(ExternalIdentityResolverInterface::class),
            $this->persistence(),
            [self::ISSUER],
            allowProvisioning: true,
        );
    }

    public function testExplicitProvisionerCanCreateUnknownUser(): void
    {
        $resolver = $this->createMock(ExternalIdentityResolverInterface::class);
        $resolver->method('resolve')->willReturn(null);
        $user = $this->createMock(UserInterface::class);
        $provisioner = $this->createMock(UserProvisionerInterface::class);
        $provisioner->expects(self::once())->method('provision')->willReturn($user);
        $provider = new NativeOidcUserProvider(
            $resolver, $this->persistence(), [self::ISSUER], $provisioner, true
        );
        self::assertSame($user, $provider->loadUserByIdentifier('external-sub', $this->claims()));
    }

    public function testRefreshAndSupportsClassAreDelegated(): void
    {
        $resolver = $this->createMock(ExternalIdentityResolverInterface::class);
        $storage = $this->createMock(UserProviderInterface::class);
        $user = $this->createMock(UserInterface::class);
        $storage->expects(self::once())->method('supportsClass')->with('App\\User')->willReturn(true);
        $storage->expects(self::once())->method('refreshUser')->with($user)->willReturn($user);
        $provider = new NativeOidcUserProvider($resolver, $storage, [self::ISSUER]);
        self::assertTrue($provider->supportsClass('App\\User'));
        self::assertSame($user, $provider->refreshUser($user));
    }
}
