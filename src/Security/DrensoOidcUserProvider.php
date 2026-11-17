<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Security;

use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use Drenso\OidcBundle\Security\UserProvider\OidcUserProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Optional adapter for Drenso 4.x. Requires drenso/symfony-oidc-bundle.
 * Drenso verifies the OIDC token; never call ensureUserExists with untrusted data.
 *
 * @implements OidcUserProviderInterface<UserInterface>
 */
final readonly class DrensoOidcUserProvider implements OidcUserProviderInterface
{
    private const REQUEST_USER = '_identity_bridge_drenso_authenticated_user';

    public function __construct(
        private NativeOidcUserProvider $identityResolver,
        private UserProviderInterface $persistentUserProvider,
        private RequestStack $requestStack,
    ) {
    }

    public function ensureUserExists(string $userIdentifier, OidcUserData $userData, OidcTokens $tokens): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            throw new AuthenticationException('OIDC callback request is missing.');
        }

        // Include only the claims needed by identity and role adapters. This
        // adapter must be invoked only after Drenso's verified OIDC processing.
        $claims = [];
        foreach ([
            'iss', 'sub', 'email', 'email_verified', 'name',
            'preferred_username', 'given_name', 'family_name', 'groups',
        ] as $name) {
            $value = $userData->getUserData($name);
            if (null !== $value) {
                $claims[$name] = $value;
            }
        }

        // Reject when Drenso's selected user ID is not exactly the ID-token sub,
        // or when issuer is outside the configured trusted issuer list.
        $request->attributes->remove(self::REQUEST_USER);
        $user = $this->identityResolver->loadUserByIdentifier($userIdentifier, $claims);
        $request->attributes->set(self::REQUEST_USER, [
            'identifier' => $userIdentifier,
            'user' => $user,
        ]);
    }

    public function loadOidcUser(string $userIdentifier): UserInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        $pending = $request?->attributes->get(self::REQUEST_USER);
        $request?->attributes->remove(self::REQUEST_USER);
        if (!is_array($pending)
            || ($pending['identifier'] ?? null) !== $userIdentifier
            || !($pending['user'] ?? null) instanceof UserInterface) {
            throw new UserNotFoundException('OIDC login has no verified pending local user.');
        }

        return $pending['user'];
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->persistentUserProvider->loadUserByIdentifier($identifier);
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
