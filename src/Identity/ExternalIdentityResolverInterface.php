<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Identity;

use Symfony\Component\Security\Core\User\UserInterface;

interface ExternalIdentityResolverInterface
{
    /** No linked local account returns null. Never implicitly link by email/username. */
    public function resolve(VerifiedExternalIdentity $identity): ?UserInterface;
}
