<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Provisioning;

use Quantatheist\IdentityBridge\Identity\VerifiedExternalIdentity;
use Symfony\Component\Security\Core\User\UserInterface;

/** Application-controlled. No bundle listener automatically invokes this. */
interface UserProvisionerInterface
{
    public function provision(VerifiedExternalIdentity $identity): UserInterface;
}
