<?php

declare(strict_types=1);

namespace App\Tests\Web;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FolderBrowserComponentTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $user = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'browser-test@homecloud.local']);
        if (!$user) {
            $user = new \App\Entity\User('browser-test@homecloud.local', 'Browser Test');
            $ref = new \ReflectionProperty($user, 'id');
            $ref->setValue($user, \Symfony\Component\Uid\Uuid::v4());
            $em->persist($user);
            $em->flush();
        }
        static::ensureKernelShutdown();
    }

    /**
     * Test fonctionnel : Affichage correct si la racine a plusieurs enfants directs
     */
    public function testAffichageRacineAvecPlusieursEnfants()
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();

        // Mock du service FolderTreeFactory pour créer la racine et les enfants personnalisés
        $mockFactory = $this->createMock(\App\Factory\FolderTreeFactory::class);
        $mockFactory->method('ensureDefaultTree')->willReturnCallback(function () use ($em) {
            $user = $em->getRepository(\App\Entity\User::class)->findOneBy([]);
            // Purge dossiers
            $folders = $em->getRepository(\App\Entity\Folder::class)->findAll();
            foreach ($folders as $folder) {
                $em->remove($folder);
            }
            $em->flush();

            // Création racine
            $root = new \App\Entity\Folder('Dossiers', $user, null);
            $em->persist($root);
            $em->flush();
            $root = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);

            // Création enfants personnalisés
            $enfant1 = new \App\Entity\Folder('Enfant1', $user, $root);
            $enfant2 = new \App\Entity\Folder('Enfant2', $user, $root);
            $enfant3 = new \App\Entity\Folder('Enfant3', $user, $root);
            $em->persist($enfant1);
            $em->persist($enfant2);
            $em->persist($enfant3);
            $em->flush();
            $em->clear();
            // Recharge la racine depuis la base pour garantir la structure
            return $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);
        });
        $container->set(\App\Factory\FolderTreeFactory::class, $mockFactory);

        // Suppression de tous les dossiers pour garantir une base propre
        $folders = $em->getRepository(\App\Entity\Folder::class)->findAll();
        foreach ($folders as $folder) {
            $em->remove($folder);
        }
        $em->flush();

        // Création de la racine
        $user = $em->getRepository(\App\Entity\User::class)->findOneBy([]);
        // Suppression de tous les dossiers pour garantir une base propre
        $folders = $em->getRepository(\App\Entity\Folder::class)->findAll();
        foreach ($folders as $folder) {
            $em->remove($folder);
        }
        $em->flush();

        // Création de la racine avec le user utilisé par le controller
        $root = new \App\Entity\Folder('Dossiers', $user, null);
        $em->persist($root);
        $em->flush();

        // Récupération de la racine persistée
        $root = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);

        // Ajout de plusieurs enfants directs liés à la racine récupérée
        $enfant1 = new \App\Entity\Folder('Enfant1', $user, $root);
        $enfant2 = new \App\Entity\Folder('Enfant2', $user, $root);
        $enfant3 = new \App\Entity\Folder('Enfant3', $user, $root);
        $em->persist($enfant1);
        $em->persist($enfant2);
        $em->persist($enfant3);
        $em->flush();

        // Récupération de la racine pour garantir l'hydratation Doctrine
        $root = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);

        $crawler = $client->request('GET', '/web/folders');

        // La racine doit être présente
        $this->assertSelectorTextContains('.lg-card', 'Dossiers');

        // Les enfants doivent être présents
        $this->assertStringContainsString('Enfant1', $client->getResponse()->getContent());
        $this->assertStringContainsString('Enfant2', $client->getResponse()->getContent());
        $this->assertStringContainsString('Enfant3', $client->getResponse()->getContent());

        // Vérifie la présence des enfants dans le HTML généré
        $html = $client->getResponse()->getContent();
        $this->assertStringContainsString('Enfant1', $html);
        $this->assertStringContainsString('Enfant2', $html);
        $this->assertStringContainsString('Enfant3', $html);

        // Dump du HTML généré pour diagnostic
        file_put_contents('/tmp/folderbrowser_test.html', $html);
    }
    public function testAffichageRacineEtArborescenceComplete()
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();

        // Mock du factory pour arborescence par défaut (toto/tata)
        $mockFactory = $this->createMock(\App\Factory\FolderTreeFactory::class);
        $mockFactory->method('ensureDefaultTree')->willReturnCallback(function () use ($em) {
            $user = $em->getRepository(\App\Entity\User::class)->findOneBy([]);
            $root = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);
            if (!$root) {
                $root = new \App\Entity\Folder('Dossiers', $user, null);
                $em->persist($root);
                $em->flush();
            }
            $root = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);
            $toto = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'toto', 'parent' => $root]);
            if (!$toto) {
                $toto = new \App\Entity\Folder('toto', $user, $root);
                $em->persist($toto);
                $em->flush();
            }
            $tata = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'tata', 'parent' => $toto]);
            if (!$tata) {
                $tata = new \App\Entity\Folder('tata', $user, $toto);
                $em->persist($tata);
                $em->flush();
            }
            return $root;
        });
        $container->set(\App\Factory\FolderTreeFactory::class, $mockFactory);

        $crawler = $client->request('GET', '/web/folders');

        // Vérifie la présence de la racine 'Dossiers'
        $this->assertSelectorTextContains('.lg-card', 'Dossiers');

        // Vérifie la présence d'au moins un dossier enfant
        $this->assertGreaterThan(1, $crawler->filter('.lg-card')->count(), 'Il doit y avoir au moins la racine et un enfant');
    }
    public function testAffichageArborescenceImbriqueeRed()
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();

        // Mock du factory pour arborescence par défaut (toto/tata)
        $mockFactory = $this->createMock(\App\Factory\FolderTreeFactory::class);
        $mockFactory->method('ensureDefaultTree')->willReturnCallback(function () use ($em) {
            $user = $em->getRepository(\App\Entity\User::class)->findOneBy([]);
            $root = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);
            if (!$root) {
                $root = new \App\Entity\Folder('Dossiers', $user, null);
                $em->persist($root);
                $em->flush();
            }
            $root = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);
            $toto = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'toto', 'parent' => $root]);
            if (!$toto) {
                $toto = new \App\Entity\Folder('toto', $user, $root);
                $em->persist($toto);
                $em->flush();
            }
            $tata = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'tata', 'parent' => $toto]);
            if (!$tata) {
                $tata = new \App\Entity\Folder('tata', $user, $toto);
                $em->persist($tata);
                $em->flush();
            }
            $em->clear();
            return $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);
        });
        $container->set(\App\Factory\FolderTreeFactory::class, $mockFactory);

        $crawler = $client->request('GET', '/web/folders');

        // On attend la racine "Dossiers"
        $this->assertSelectorTextContains('.lg-card', 'Dossiers');

        // On attend un dossier enfant "toto" dans n'importe quelle carte
        $this->assertStringContainsString('toto', $client->getResponse()->getContent());

        // On attend un sous-dossier "tata" imbriqué
        $this->assertStringContainsString('tata', $client->getResponse()->getContent());
    }
    /**
     * Test fonctionnel : Affichage d'une arborescence imbriquée (racine > enfant > sous-enfant)
     */
    public function testAffichageArborescenceImbriqueeComplete()
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();

        // Mock du factory pour arborescence par défaut (toto/tata)
        $mockFactory = $this->createMock(\App\Factory\FolderTreeFactory::class);
        $mockFactory->method('ensureDefaultTree')->willReturnCallback(function () use ($em) {
            $user = $em->getRepository(\App\Entity\User::class)->findOneBy([]);
            $root = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);
            if (!$root) {
                $root = new \App\Entity\Folder('Dossiers', $user, null);
                $em->persist($root);
                $em->flush();
            }
            $root = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);
            $toto = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'toto', 'parent' => $root]);
            if (!$toto) {
                $toto = new \App\Entity\Folder('toto', $user, $root);
                $em->persist($toto);
                $em->flush();
            }
            $tata = $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'tata', 'parent' => $toto]);
            if (!$tata) {
                $tata = new \App\Entity\Folder('tata', $user, $toto);
                $em->persist($tata);
                $em->flush();
            }
            $em->clear();
            return $em->getRepository(\App\Entity\Folder::class)->findOneBy(['name' => 'Dossiers', 'parent' => null]);
        });
        $container->set(\App\Factory\FolderTreeFactory::class, $mockFactory);

        $crawler = $client->request('GET', '/web/folders');

        // Vérifie la présence de la racine
        $this->assertSelectorTextContains('.lg-card', 'Dossiers');

        // Vérifie la présence d'un dossier enfant (ex: "toto")
        $this->assertStringContainsString('toto', $client->getResponse()->getContent());

        // Vérifie la présence d'un sous-dossier imbriqué (ex: "tata")
        $this->assertStringContainsString('tata', $client->getResponse()->getContent());
    }
    /**
     * Test fonctionnel : Affichage correct si la racine n'a aucun enfant (cas vide)
     */
    public function testAffichageRacineSansEnfant()
    {
        $client = static::createClient();
        $container = $client->getContainer();
        $em = $container->get('doctrine')->getManager();

        // Suppression de tous les dossiers pour garantir une base vide
        $folders = $em->getRepository(\App\Entity\Folder::class)->findAll();
        foreach ($folders as $folder) {
            $em->remove($folder);
        }
        $em->flush();

        // Création explicite de la racine "Dossiers" sans enfant
        $user = $em->getRepository(\App\Entity\User::class)->findOneBy([]);
        $root = new \App\Entity\Folder('Dossiers', $user, null);
        $em->persist($root);
        $em->flush();

        $crawler = $client->request('GET', '/web/folders');

        // La racine doit être présente
        $this->assertSelectorTextContains('.lg-card', 'Dossiers');

        // Vérifie que la racine n'a pas de sous-cartes (absence d'éléments enfants dans son bloc)
        $racineCard = $crawler->filter('.lg-card')->first();
        // On suppose que les sous-cartes sont des .lg-card imbriqués dans le bloc racine
        $sousCartes = $racineCard->filter('.lg-card');
        // Il ne doit y avoir qu'une seule carte (la racine elle-même)
        $this->assertEquals(1, $sousCartes->count(), 'La racine ne doit pas contenir de sous-cartes');
    }

    public function testAffichageArborescenceRed()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/web/folders'); // URL à adapter selon le routing

        // Dump du HTML généré pour diagnostic
        file_put_contents('/tmp/folderbrowser_test.html', $client->getResponse()->getContent());

        // Test : on attend la présence du dossier racine "Dossiers" dans l'arborescence
        $this->assertSelectorTextContains('.lg-card', 'Dossiers');
    }
}
