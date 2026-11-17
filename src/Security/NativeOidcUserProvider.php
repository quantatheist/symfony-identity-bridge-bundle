<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Security;

use Quantatheist\IdentityBridge\Identity\ExternalIdentityResolverInterface;
use Quantatheist\IdentityBridge\Identity\VerifiedExternalIdentity;
use Quantatheist\IdentityBridge\Provisioning\UserProvisionerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * User provider for Symfony 8.2 native oidc_login.
 *
 * Use ONLY behind Symfony's native, cryptographically validating oidc_login
 * authenticator. This provider does not independently verify ID token claims.
 *
 * @implements AttributesBasedUserProviderInterface<UserInterface>
 */
final readonly class NativeOidcUserProvider implements AttributesBasedUserProviderInterface
{
    /**
     * @param list<string> $trustedIssuers The exact issuer strings published in OIDC metadata.
     */
    public function __construct(
        private ExternalIdentityResolverInterface $resolver,
        private UserProviderInterface $persistentUserProvider,
        private array $trustedIssuers,
        private ?UserProvisionerInterface $provisioner = null,
        private bool $allowProvisioning = false,
    ) {
        if ([] === $trustedIssuers) {
            throw new \InvalidArgumentException('Configure at least one trusted OIDC issuer.');
        }

        foreach ($trustedIssuers as $issuer) {
            if (!is_string($issuer) || '' === trim($issuer) || !str_starts_with($issuer, 'https://')) {
                throw new \InvalidArgumentException('Trusted OIDC issuers must be explicit HTTPS URLs.');
            }
        }

        if ($allowProvisioning && null === $provisioner) {
            throw new \InvalidArgumentException('Provisioning requires an explicit application provisioner.');
        }
    }

    /** @param array<string, mixed> $attributes */
    public function loadUserByIdentifier(string $identifier, array $attributes = []): UserInterface
    {
        $issuer = $attributes['iss'] ?? null;
        $subject = $attributes['sub'] ?? null;

        if (!is_string($issuer) || !in_array($issuer, $this->trustedIssuers, true)
            || !is_string($subject) || '' === trim($subject)
            || !hash_equals($subject, $identifier)) {
            throw new AuthenticationException('OIDC identity claims do not match the configured identity policy.');
        }

        $identity = new VerifiedExternalIdentity($issuer, $subject, $attributes);
        $user = $this->resolver->resolve($identity);
        if (null !== $user) {
            return $user;
        }

        if ($this->allowProvisioning && null !== $this->provisioner) {
            // The application's provisioner owns locking, transactionality, audit
            // logging and the persistence of the exact issuer+subject association.
            return $this->provisioner->provision($identity);
        }

        $exception = new UserNotFoundException('No linked local user for this OIDC identity.');
        $exception->setUserIdentifier($identifier);
        throw $exception;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->persistentUserProvider->refreshUser($user);
    }

    public function supportsClass(string $class): bool
    {
        return $this->persistentUserProvider->supportsClass($class);
    }
}
