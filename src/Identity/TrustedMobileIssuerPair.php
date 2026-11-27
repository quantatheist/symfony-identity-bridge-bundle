<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Identity;

/**
 * An explicitly configured pair of OIDC providers in ONE authentik instance,
 * both configured for the SAME "hashed_user_id" subject mode.
 *
 * The caller must first validate both tokens using their own provider's exact
 * issuer, signature, audience, lifetime, client and token purpose. This class
 * performs no token validation and never creates or links accounts.
 */
final readonly class TrustedMobileIssuerPair
{
    public function __construct(
        public string $webIssuer,
        public string $mobileIssuer,
    ) {
        if (!$this->isTrustedIssuer($webIssuer) || !$this->isTrustedIssuer($mobileIssuer)
            || $webIssuer === $mobileIssuer
            || $this->origin($webIssuer) !== $this->origin($mobileIssuer)) {
            throw new \InvalidArgumentException('Web and Mobile must be distinct exact HTTPS issuers from one trusted authentik origin.');
        }
    }

    /** Only for already verified Mobile tokens and already persisted Web identities. */
    public function matches(VerifiedExternalIdentity $mobile, VerifiedExternalIdentity $web): bool
    {
        return $mobile->issuer === $this->mobileIssuer
            && $web->issuer === $this->webIssuer
            && hash_equals($web->subject, $mobile->subject);
    }

    private function isTrustedIssuer(string $issuer): bool
    {
        if ($issuer === '' || $issuer !== trim($issuer)
            || preg_match('/[\\x00-\\x20\\x7f\\\\]/', $issuer)
            || !str_ends_with($issuer, '/')) {
            return false;
        }
        $parts = parse_url($issuer);
        return is_array($parts) && ($parts['scheme'] ?? null) === 'https'
            && is_string($parts['host'] ?? null) && $parts['host'] !== ''
            && !isset($parts['user']) && !isset($parts['pass'])
            && !isset($parts['query']) && !isset($parts['fragment']);
    }

    private function origin(string $issuer): string
    {
        $parts = parse_url($issuer);
        return strtolower((string) $parts['scheme']).'://'
            .strtolower((string) $parts['host']).':'.(string) ($parts['port'] ?? 443);
    }
}
