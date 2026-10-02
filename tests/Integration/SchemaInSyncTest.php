<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * #571 : les migrations doivent produire exactement le schéma décrit par les
 * entités. La base de test est construite par `doctrine:migrations:migrate`
 * (CI comme local) : tout écart ici signifie qu'une entité a changé sans
 * migration, ou qu'une migration a laissé le schéma dériver.
 *
 * Pourquoi c'est un test : un écart permanent fait que chaque `make:migration`
 * embarque des instructions sans rapport (DROP parasites), qui partiraient
 * sur les 7 instances au déploiement si on oublie de les retirer à la main.
 *
 * En local, si ce test échoue alors que la CI est verte, la base de test a
 * dérivé : la recréer depuis les migrations
 *   php bin/console doctrine:schema:drop --full-database --force --env=test
 *   php bin/console doctrine:migrations:migrate --no-interaction --env=test
 */
final class SchemaInSyncTest extends KernelTestCase
{
    public function testMigrationsProduceTheSchemaDescribedByTheEntities(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $statements = (new SchemaTool($em))->getUpdateSchemaSql($em->getMetadataFactory()->getAllMetadata());
        // La table de suivi des migrations n'appartient à aucune entité : la
        // commande doctrine:schema:update l'ignore, SchemaTool non.
        $statements = array_values(array_filter(
            $statements,
            static fn (string $sql): bool => !str_contains($sql, 'doctrine_migration_versions'),
        ));

        $this->assertSame(
            [],
            $statements,
            "Le schéma issu des migrations diverge des entités. Ajouter la migration manquante (ou corriger le mapping) :\n" . implode("\n", $statements),
        );
    }
}
