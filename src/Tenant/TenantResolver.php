<?php

namespace App\Tenant;

use App\Repository\PrivateSpaceRepository;
use App\Entity\PrivateSpace;

class TenantResolver
{
    public function __construct(private PrivateSpaceRepository $repo, private string $mainDomain) {}

    /**
     * Résout un PrivateSpace à partir d'un host (ex: ronan.lenouvel.me).
     */
    public function resolveFromHost(string $host): ?PrivateSpace
    {
        // Normaliser le host : lower case
        $host = strtolower(trim($host));

        // Si le host ne contient pas le mainDomain, on ne résout pas
        if (!str_ends_with($host, $this->mainDomain)) {
            return null;
        }

        // extraire le subdomain avant le mainDomain
        $suffix = '.' . $this->mainDomain;
        $sub = substr($host, 0, -strlen($suffix));
        // si vide ou égal au mainDomain, invalide
        if (empty($sub)) {
            return null;
        }

        // cleanup
        $sub = rtrim($sub, '.');

        // Rechercher par name
        return $this->repo->findOneBy(['name' => $sub]);
    }

    public function getMainDomain(): string
    {
        return $this->mainDomain;
    }
}
