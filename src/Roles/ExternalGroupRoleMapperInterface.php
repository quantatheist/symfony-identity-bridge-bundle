<?php

declare(strict_types=1);

namespace Quantatheist\IdentityBridge\Roles;

use Quantatheist\IdentityBridge\Identity\VerifiedExternalIdentity;

interface ExternalGroupRoleMapperInterface
{
    /** @return list<string> Only roles explicitly managed by this mapper. */
    public function mappedRoles(VerifiedExternalIdentity $identity): array;
}
