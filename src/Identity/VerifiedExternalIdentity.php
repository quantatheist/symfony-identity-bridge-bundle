<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Identity;

/**
 * Trusted OIDC identity data. Construct only after the OIDC client / Symfony Security
 * has verified the signature, issuer, audience, lifetime and OIDC protocol claims.
 * This value object does not perform any JWT verification.
 */
final readonly class VerifiedExternalIdentity
{
    /** @param array<string, mixed> $claims */
    public function __construct(
        public string $issuer,
        public string $subject,
        public array $claims = [],
    ) {
        if ('' === trim($issuer) || '' === trim($subject) || trim($issuer) !== $issuer || trim($subject) !== $subject) {
            throw new \InvalidArgumentException('Issuer and subject must be non-empty and canonical.');
        }
    }
}
