<?php

namespace App\Command;

use Doctrine\DBAL\DriverManager;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'app:tenant:check-db', description: 'Vérifie l\'existence d\'une DB tenant hc_{name} ; option --provision pour la créer et --migrate pour exécuter les migrations')]
final class TenantCheckDbCommand extends Command
{
    public function __construct(private ManagerRegistry $doctrine, private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant', InputArgument::REQUIRED, 'Nom du tenant (ex: alice)')
            ->addOption('provision', null, InputOption::VALUE_NONE, 'Créer la base si elle est manquante')
            ->addOption('migrate', null, InputOption::VALUE_NONE, 'Appliquer les migrations sur la DB tenant après provision');
    }

    private function slugDbName(string $tenant): string
    {
        $dbName = 'hc_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($tenant));
        return $dbName;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenant = (string) $input->getArgument('tenant');
        $provision = (bool) $input->getOption('provision');
        $migrate = (bool) $input->getOption('migrate');

        $dbName = $this->slugDbName($tenant);

        /** @var \Doctrine\ORM\EntityManager $defaultEm */
        $defaultEm = $this->doctrine->getManager();
        $defaultConn = $defaultEm->getConnection();
        $params = $defaultConn->getParams();

        // Build tenant-specific connection params
        if (isset($params['url'])) {
            $url = $params['url'];
            $parsed = parse_url($url);
            if ($parsed === false) {
                $output->writeln('<error>Impossible de parser le DATABASE_URL par défaut.</error>');
                return Command::FAILURE;
            }
            $scheme = $parsed['scheme'] ?? null;
            $user = $parsed['user'] ?? null;
            $pass = $parsed['pass'] ?? null;
            $host = $parsed['host'] ?? null;
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $tenantUrl = sprintf('%s://%s%s%s/%s', $scheme, $user ? $user . ($pass ? ':' . $pass : '') . '@' : '', $host ?? '', $port, $dbName);
            $tenantParams = ['url' => $tenantUrl];
            // For creating DB, use server-level URL without path
            $serverUrl = sprintf('%s://%s%s%s', $scheme, $user ? $user . ($pass ? ':' . $pass : '') . '@' : '', $host ?? '', $port);
            $serverParams = ['url' => $serverUrl];
        } else {
            $tenantParams = $params;
            $tenantParams['dbname'] = $dbName;
            $serverParams = $params;
            unset($serverParams['dbname']);
        }

        // Check if tenant DB exists by attempting to connect
        $exists = false;
        try {
            $conn = DriverManager::getConnection($tenantParams, $defaultConn->getConfiguration());
            $conn->connect();
            $exists = true;
            $conn->close();
        } catch (\Throwable $e) {
            $exists = false;
        }

        if ($exists) {
            $output->writeln(sprintf('<info>DB exists: %s</info>', $dbName));
        } else {
            $output->writeln(sprintf('<comment>DB missing: %s</comment>', $dbName));
            if (! $provision) {
                $output->writeln('<comment>Run with --provision to create the DB.</comment>');
                return Command::FAILURE;
            }

            // Attempt to create DB using server-level connection
            try {
                $serverConn = DriverManager::getConnection($serverParams, $defaultConn->getConfiguration());
                $platform = $serverConn->getDatabasePlatform();
                // MySQL compatible create statement
                $sql = sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', $dbName);
                $serverConn->executeStatement($sql);
                $output->writeln(sprintf('<info>Database %s created.</info>', $dbName));
                $this->logger->info('Tenant DB created', ['tenant' => $tenant, 'dbname' => $dbName]);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to create tenant DB', ['tenant' => $tenant, 'error' => $e->getMessage()]);
                $output->writeln('<error>Failed to create database: see logs for details.</error>');
                return Command::FAILURE;
            }
        }

        if ($migrate) {
            $output->writeln('<info>Running migrations for tenant DB...</info>');
            // Build an env with DATABASE_URL for the tenant and run migrations via console
            $env = array_merge($_SERVER, $_ENV);
            if (isset($tenantParams['url'])) {
                $env['DATABASE_URL'] = $tenantParams['url'];
            } else {
                // Build DSN from tenantParams
                $driver = $tenantParams['driver'] ?? $tenantParams['pdo'] ?? 'mysql';
                $user = $tenantParams['user'] ?? '';
                $pass = $tenantParams['password'] ?? '';
                $host = $tenantParams['host'] ?? '127.0.0.1';
                $port = isset($tenantParams['port']) ? ':' . $tenantParams['port'] : '';
                $env['DATABASE_URL'] = sprintf('mysql://%s%s@%s%s/%s', $user, $pass ? ':' . $pass : '', $host, $port, $dbName);
            }

            $process = new Process(['php', 'bin/console', 'doctrine:migrations:migrate', '--no-interaction']);
            $process->setEnv($env);
            $process->setTimeout(3600);
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

            if (! $process->isSuccessful()) {
                $this->logger->error('Tenant migrations failed', ['tenant' => $tenant, 'output' => $process->getErrorOutput()]);
                $output->writeln('<error>Migrations failed: see logs for details.</error>');
                return Command::FAILURE;
            }

            $output->writeln('<info>Migrations completed successfully for tenant DB.</info>');
        }

        return Command::SUCCESS;
    }
}
