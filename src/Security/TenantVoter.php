<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\Token;

class TenantVoter extends Voter
{
    protected function supports(string $attribute, $subject): bool
    {
        // This voter only handles the special attribute 'TENANT_MATCH'
        return $attribute === 'TENANT_MATCH';
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $request = $subject;
        if (! method_exists($request, 'attributes')) {
            return false;
        }

        $requestTenant = $request->attributes->get('tenant');

        // Extract tenant claim from token if available
        $decoded = null;
        try {
            $decoded = $token->getAttribute('token');
        } catch (\Throwable $e) {
            $decoded = null;
        }

        $tenantClaim = is_array($decoded) && isset($decoded['tenant']) ? $decoded['tenant'] : null;

        if ($requestTenant && $tenantClaim) {
            return (string) $requestTenant === (string) $tenantClaim;
        }

        // If no claim present, deny by default
        return false;
    }
}
