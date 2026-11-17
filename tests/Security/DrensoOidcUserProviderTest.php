<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Tests\Security;

use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use PHPUnit\Framework\TestCase;
use Quantatheist\IdentityBridge\Identity\ExternalIdentityResolverInterface;
use Quantatheist\IdentityBridge\Provisioning\UserProvisionerInterface;
use Quantatheist\IdentityBridge\Security\DrensoOidcUserProvider;
use Quantatheist\IdentityBridge\Security\NativeOidcUserProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class DrensoOidcUserProviderTest extends TestCase
{
    private function tokens(): OidcTokens
    {
        return new OidcTokens((object) ['access_token' => 'dummy', 'id_token' => 'dummy']);
    }

    private function provider(
        ExternalIdentityResolverInterface $resolver,
        RequestStack $requestStack,
        ?UserProvisionerInterface $provisioner = null,
        bool $allowProvisioning = false,
    ): DrensoOidcUserProvider {
        $store = $this->createMock(UserProviderInterface::class);
        $bridge = new NativeOidcUserProvider(
            $resolver, $store, ['https://auth.example.test/'],
            $provisioner, $allowProvisioning
        );
        return new DrensoOidcUserProvider($bridge, $store, $requestStack);
    }

    public function testKnownIdentityIsLoadedExactlyOnceInCallbackRequest(): void
    {
        $user = $this->createMock(UserInterface::class);
        $resolver = $this->createMock(ExternalIdentityResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->willReturn($user);
        $requests = new RequestStack();
        $requests->push(new Request());

        $provider = $this->provider($resolver, $requests);
        $provider->ensureUserExists('subject', new OidcUserData([
            'iss' => 'https://auth.example.test/',
            'sub' => 'subject',
            'groups' => ['members'],
        ]), $this->tokens());

        self::assertSame($user, $provider->loadOidcUser('subject'));
        $this->expectException(UserNotFoundException::class);
        $provider->loadOidcUser('subject');
    }

    public function testUntrustedIssuerIsRejectedBeforeResolvingUser(): void
    {
        $resolver = $this->createMock(ExternalIdentityResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');
        $requests = new RequestStack();
        $requests->push(new Request());
        $this->expectException(AuthenticationException::class);
        $this->provider($resolver, $requests)->ensureUserExists(
            'subject',
            new OidcUserData(['iss' => 'https://other.example.test/', 'sub' => 'subject']),
            $this->tokens()
        );
    }

    public function testUnknownIdentityCannotProvisionWithoutOptIn(): void
    {
        $resolver = $this->createMock(ExternalIdentityResolverInterface::class);
        $resolver->method('resolve')->willReturn(null);
        $requests = new RequestStack();
        $requests->push(new Request());
        $this->expectException(UserNotFoundException::class);
        $this->provider($resolver, $requests)->ensureUserExists(
            'subject',
            new OidcUserData(['iss' => 'https://auth.example.test/', 'sub' => 'subject']),
            $this->tokens()
        );
    }
}
