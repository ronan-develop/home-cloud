<?php

namespace App\Tenant;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\DBAL\DriverManager;
use Psr\Log\LoggerInterface;

class TenantConnectionFactory
{
    private array $cache = [];

    public function __construct(private ManagerRegistry $doctrine, private LoggerInterface $logger) {}

    /**
     * Retourne un EntityManager connecté à la base dédiée du tenant.
     * Le nom de la base suit la convention: hc_{tenantName}
     */
    public function getEntityManagerForTenant(string $tenantName): EntityManagerInterface
    {
        $key = $tenantName;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        /** @var \Doctrine\ORM\EntityManager $defaultEm */
        $defaultEm = $this->doctrine->getManager();
        $defaultConn = $defaultEm->getConnection();
        $params = $defaultConn->getParams();

        // determine target dbname
        $dbName = 'hc_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($tenantName));

        // prepare new connection params
        if (isset($params['url'])) {
            // modify DSN url by replacing path/dbname
            $url = $params['url'];
            $parsed = parse_url($url);
            if ($parsed === false) {
                throw new \RuntimeException('Unable to parse DATABASE_URL');
            }
            // rebuild path with dbName
            $scheme = $parsed['scheme'] ?? null;
            $user = $parsed['user'] ?? null;
            $pass = $parsed['pass'] ?? null;
            $host = $parsed['host'] ?? null;
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $newUrl = sprintf('%s://%s%s%s/%s', $scheme, $user ? $user . ($pass ? ':' . $pass : '') . '@' : '', $host ?? '', $port, $dbName);
            $connParams = ['url' => $newUrl];
        } else {
            $connParams = $params;
            $connParams['dbname'] = $dbName;
        }

        // open connection
        try {
            $conn = DriverManager::getConnection($connParams, $defaultConn->getConfiguration());
        } catch (\Throwable $e) {
            // Log full error for administrators, but do not expose DB creation details to the caller
            $this->logger->error('Tenant DB connection failed', ['tenant' => $tenantName, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Impossible de se connecter à la base du tenant. Contactez l\'administrateur.');
        }

        // create entity manager with same ORM configuration and event manager
        // Use a class-string call so static analysers (phpstan/psalm) understand the static constructor
        try {
            // Instantiate a new EntityManager with the same ORM configuration and event manager.
            // Using the constructor avoids static-method false positives from static analysers.
            $em = new EntityManager($conn, $defaultEm->getConfiguration(), $defaultEm->getEventManager());
        } catch (\Throwable $e) {
            // Log full error for administrators, but keep the exception message generic for callers
            $this->logger->error('Failed to create tenant EntityManager', ['tenant' => $tenantName, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Impossible d\'initialiser l\'entity manager du tenant. Contactez l\'administrateur.');
        }

        $this->cache[$key] = $em;

        $this->logger->info('Created tenant EntityManager', ['tenant' => $tenantName, 'dbname' => $dbName]);

        return $em;
    }
}
