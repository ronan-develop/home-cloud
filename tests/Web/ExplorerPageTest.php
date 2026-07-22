<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Folder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels de l'explorateur de fichiers web sur la route /explorer.
 * Migré de FileExplorerTest pour la séparation home/explorer.
 */
final class ExplorerPageTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email = 'explorer@example.com', string $password = 'secret123'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User($email, 'Explorer');
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Récupère un token CSRF valide en le lisant depuis /explorer, comme le
     * ferait le navigateur. Les intentions "file-upload" (ImportCard) et
     * "delete-file" (FileCard) génèrent des tokens distincts et non
     * interchangeables : il faut cibler le bon formulaire.
     */
    private function uploadCsrfToken(): string
    {
        $crawler = $this->client->request('GET', '/explorer');

        return $crawler->filter('#main-upload-form input[name="_token"]')->attr('value');
    }

    /**
     * FileCard (et son token "delete-file") n'est rendu que dans la vue d'un
     * dossier précis : ExplorerController::index() laisse $files vide à la
     * racine (aucun $currentFolder). Il faut donc naviguer vers le dossier.
     */
    private function deleteCsrfToken(string $folderId): string
    {
        $crawler = $this->client->request('GET', '/explorer?folder=' . $folderId);

        return $crawler->filter('.file-actions input[name="_token"]')->first()->attr('value');
    }

    private function login(string $email = 'explorer@example.com', string $password = 'secret123'): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);

        // Après submit, vérifier si on est redirigé (status 302)
        if ($this->client->getResponse()->isRedirect()) {
            $this->client->followRedirect();
        }

        // Depuis #185, la redirection post-login pointe vers le dashboard (/) —
        // ces tests ciblent l'explorateur, on y navigue donc explicitement.
        $this->client->request('GET', '/explorer');
    }

    // --- Route /explorer ---

    public function testExplorerRouteShowsFileExplorer(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="file-explorer"]');
    }

    public function testExplorerRouteShowsUploadZone(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="upload-zone"]');
    }

    public function testExplorerSectionTitleIsAligned(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.section-title');
    }

    // --- Upload redirects to /explorer ---

    public function testUploadFileRedirectsToExplorer(): void
    {
        $this->createUser();
        $this->login();

        $tmpFile = tempnam(sys_get_temp_dir(), 'hc_test_') . '.txt';
        file_put_contents($tmpFile, 'Hello HomeCloud');
        $uploaded = new UploadedFile($tmpFile, 'test.txt', 'text/plain', null, true);

        $this->client->request('POST', '/files/upload', ['_token' => $this->uploadCsrfToken()], ['file' => $uploaded]);
        $this->assertResponseRedirects('/explorer');
    }

    public function testUploadedFileAppearsInExplorerList(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Uploads', $user);
        $this->em->persist($folder);
        $this->em->flush();
        $folderId = $folder->getId()->toRfc4122();

        $this->login();

        $tmpFile = tempnam(sys_get_temp_dir(), 'hc_test_') . '.txt';
        file_put_contents($tmpFile, 'Hello HomeCloud');
        $uploaded = new UploadedFile($tmpFile, 'test-visible.txt', 'text/plain', null, true);

        $this->client->request('POST', '/files/upload', ['folder_id' => $folderId, '_token' => $this->uploadCsrfToken()], ['file' => $uploaded]);
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-testid="file-list"]', 'test-visible.txt');
    }

    public function testUploadWithoutCsrfTokenThrows403(): void
    {
        $this->createUser();
        $this->login();

        $tmpFile = tempnam(sys_get_temp_dir(), 'hc_test_') . '.txt';
        file_put_contents($tmpFile, 'Hello HomeCloud');
        $uploaded = new UploadedFile($tmpFile, 'test.txt', 'text/plain', null, true);

        $this->client->request('POST', '/files/upload', [], ['file' => $uploaded]);
        $this->assertResponseStatusCodeSame(403);
    }

    // --- Delete redirects to /explorer ---

    public function testDeleteFileRedirectsToExplorer(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Uploads', $user);
        $this->em->persist($folder);

        $file = new \App\Entity\File('delete-me.txt', 'text/plain', 10, 'test/path.txt', $folder, $user);
        $this->em->persist($file);
        $this->em->flush();

        $fileId = $file->getId()->toRfc4122();

        $this->login();
        $token = $this->deleteCsrfToken($folder->getId()->toRfc4122());

        $this->client->request('POST', "/files/{$fileId}/delete", ['_token' => $token]);
        $this->assertResponseRedirects('/explorer');
    }

    public function testDeleteWithoutCsrfTokenThrows403(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Uploads', $user);
        $this->em->persist($folder);
        $file = new \App\Entity\File('delete-me.txt', 'text/plain', 10, 'test/path.txt', $folder, $user);
        $this->em->persist($file);
        $this->em->flush();

        $this->login();

        $this->client->request('POST', "/files/{$file->getId()->toRfc4122()}/delete");
        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteFileNotOwnedReturns403(): void
    {
        $owner = $this->createUser('owner@example.com');

        $folder = new Folder('Uploads', $owner);
        $this->em->persist($folder);
        $file = new \App\Entity\File('private.txt', 'text/plain', 10, 'test/private.txt', $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        $this->createUser('attacker@example.com', 'secret123');
        $this->login('attacker@example.com');

        // Un token CSRF n'est pas lié à une ressource précise : n'importe quel
        // token "delete-file" valide de la session de l'attaquant suffit pour
        // atteindre le contrôle d'ownership visé par ce test. Il faut donc que
        // l'attaquant ait au moins un fichier à lui pour que /explorer rende
        // le formulaire FileCard porteur de ce token.
        //
        // Recharge l'utilisateur : login() a fait plusieurs requêtes HTTP qui
        // ont rebooté le kernel et détaché l'EntityManager du test — persister
        // avec un $attacker créé avant le login provoquerait une réinsertion
        // en doublon (UniqueConstraintViolationException sur l'ID).
        $attacker = $this->em->getRepository(User::class)->findOneBy(['email' => 'attacker@example.com']);
        $attackerFolder = new Folder('Uploads-Attacker', $attacker);
        $this->em->persist($attackerFolder);
        $this->em->persist(new \App\Entity\File('decoy.txt', 'text/plain', 1, 'test/decoy.txt', $attackerFolder, $attacker));
        $this->em->flush();

        $token = $this->deleteCsrfToken($attackerFolder->getId()->toRfc4122());

        $this->client->request('POST', "/files/{$file->getId()->toRfc4122()}/delete", ['_token' => $token]);
        $this->assertResponseStatusCodeSame(403);
    }

    // --- Layout tests (migré de WebLayoutTest) ---

    public function testSectionTitleDossiersIsAligned(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $sectionTitle = $this->client->getCrawler()->filter('.section-title');
        $this->assertCount(1, $sectionTitle, 'Le titre section "Dossiers" doit exister');

        $icon = $sectionTitle->filter('.section-title-icon');
        $this->assertCount(1, $icon, 'Le titre "Dossiers" doit avoir une icône');

        $this->assertStringContainsString('Dossiers', $sectionTitle->text(), 'Le titre doit contenir "Dossiers"');
    }

    public function testBreadcrumbsAreDisplayed(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $breadcrumbs = $this->client->getCrawler()->filter('.main-breadcrumbs');
        $this->assertCount(1, $breadcrumbs, 'Les breadcrumbs doivent être affichées');
    }

    public function testNoDoubloSectionTitle(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $sectionTitles = $this->client->getCrawler()->filter('.section-title');
        $this->assertCount(1, $sectionTitles, 'Il ne doit avoir qu\'une seule section-title "Dossiers"');
    }

    /**
     * ImportCard::$currentFolder est typé `string` mais explorer.html.twig lui
     * passait l'objet Folder entier (:current-folder="currentFolder") — le
     * hidden input folder_id restait vide même en naviguant dans un sous-dossier,
     * donc les uploads (drag & drop ou bouton Parcourir) atterrissaient toujours
     * dans le dossier par défaut au lieu du dossier affiché à l'écran.
     */
    public function testImportCardFolderIdReflectsCurrentFolderWhenBrowsingSubfolder(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Sous-dossier', $user);
        $this->em->persist($folder);
        $this->em->flush();
        $folderId = $folder->getId()->toRfc4122();

        $this->login();

        $crawler = $this->client->request('GET', '/explorer?folder=' . $folderId);

        $this->assertResponseIsSuccessful();
        $hiddenInput = $crawler->filter('#main-upload-form input[name="folder_id"]');
        $this->assertSame($folderId, $hiddenInput->attr('value'));
    }

    public function testImportCardUsesCloudIcon(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();

        $importCardHtml = $this->client->getCrawler()->filter('.import-card')->html() ?: '';
        $this->assertStringNotContainsString('☁️', $importCardHtml, 'Pas d\'emoji ☁️ dans import-card — doit être remplacé par SVG');

        $cloudIcons = $this->client->getCrawler()->filter('.import-card svg.hc-icon-cloud');
        $this->assertGreaterThanOrEqual(1, $cloudIcons->count(), 'ImportCard doit avoir icône cloud SVG');
    }

    // --- Alignement design system (dashboard) ---

    public function testExplorerHasPageHeaderWithTitle(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mes fichiers');
    }

    public function testExplorerHeaderShowsFolderAndFileCount(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Uploads', $user);
        $this->em->persist($folder);
        $this->em->flush();

        $this->login();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.hc-page-sub', 'dossier');
    }

    public function testExplorerHasNoHardcodedLegacyColors(): void
    {
        $this->createUser();
        $this->login();

        $this->assertResponseIsSuccessful();
        $crawler = $this->client->getCrawler();
        $scoped = ($crawler->filter('.import-card')->html() ?: '')
            . ($crawler->filter('[data-testid="file-explorer"]')->html() ?: '');

        foreach (['#a5b4fc', '#e0c3fc', '#8ec5fc', 'bg-blue-500', 'text-white'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $scoped,
                sprintf('L\'explorateur ne doit plus contenir "%s" (couleur legacy hors design system)', $forbidden)
            );
        }
    }

    // --- Partage de dossier ---

    public function testFolderCardHasShareButtonAndModal(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Documents partagés', $user);
        $this->em->persist($folder);
        $this->em->flush();

        $this->login();

        $crawler = $this->client->request('GET', '/explorer');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="share-folder-btn-' . $folder->getId()->toRfc4122() . '"]');
        $this->assertSelectorExists('form[action*="/share-create"] input[value="' . $folder->getId()->toRfc4122() . '"]');
    }

    /**
     * #241 — le bouton "Visualiser" (ouvre app_file_view en inline) ne doit
     * apparaître que pour les PDF, pas pour les autres types de fichiers.
     */
    public function testViewButtonAppearsOnlyForPdfFiles(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Docs', $user);
        $this->em->persist($folder);
        $this->em->flush();
        $folderId = $folder->getId()->toRfc4122();

        $this->login();
        $token = $this->uploadCsrfToken();

        $pdfTmp = tempnam(sys_get_temp_dir(), 'hc_pdf_');
        file_put_contents($pdfTmp, '%PDF-1.4 fake content');
        $pdfUpload = new UploadedFile($pdfTmp, 'rapport.pdf', 'application/pdf', null, true);
        $this->client->request('POST', '/files/upload', ['folder_id' => $folderId, '_token' => $token], ['file' => $pdfUpload]);
        $this->client->followRedirect();

        $txtTmp = tempnam(sys_get_temp_dir(), 'hc_txt_') . '.txt';
        file_put_contents($txtTmp, 'contenu texte');
        $txtUpload = new UploadedFile($txtTmp, 'notes.txt', 'text/plain', null, true);
        $this->client->request('POST', '/files/upload', ['folder_id' => $folderId, '_token' => $this->uploadCsrfToken()], ['file' => $txtUpload]);
        $crawler = $this->client->followRedirect();

        $this->assertResponseIsSuccessful();

        $pdfFile = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['originalName' => 'rapport.pdf']);
        $txtFile = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['originalName' => 'notes.txt']);

        $this->assertSelectorExists('[data-testid="view-pdf-btn-' . $pdfFile->getId()->toRfc4122() . '"]');
        $this->assertCount(
            0,
            $crawler->filter('[data-testid="view-pdf-btn-' . $txtFile->getId()->toRfc4122() . '"]'),
            'Un fichier non-PDF ne doit pas avoir de bouton "Visualiser"'
        );
    }

    /**
     * #312 volet 1 — FileCard affiche l'<img> quand Media.thumbnailPath existe.
     */
    public function testFileCardShowsThumbnailWhenMediaExists(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Photos', $user);
        $this->em->persist($folder);

        // Fichier avec Media/thumbnailPath
        $photoFile = new \App\Entity\File('photo.jpg', 'image/jpeg', 1024, 'test/photo.jpg', $folder, $user);
        $this->em->persist($photoFile);

        $media = new \App\Entity\Media($photoFile, 'photo');
        $media->setThumbnailPath('thumbs/abc123.jpg');
        $this->em->persist($media);

        // Fichier sans Media
        $pdfFile = new \App\Entity\File('doc.pdf', 'application/pdf', 2048, 'test/doc.pdf', $folder, $user);
        $this->em->persist($pdfFile);

        $this->em->flush();
        $folderId = $folder->getId()->toRfc4122();

        $this->login();

        $crawler = $this->client->request('GET', '/explorer?folder=' . $folderId);

        $this->assertResponseIsSuccessful();

        // Vérifier qu'il y a bien 2 fichiers affichés
        $this->assertSelectorExists('[data-testid="file-list"]');
        // La grille contient au moins 2 cartes
        $cards = $crawler->filter('.hc-item-card');
        $this->assertGreaterThanOrEqual(2, $cards->count(), 'Attendu 2 fichiers dans le dossier');

        // Chercher la carte par le nom de fichier unique (l'<img> est dedans)
        $photoCard = null;
        $cards->each(function ($card, $key) use (&$photoCard) {
            $name = $card->filter('.hc-item-name')->text();
            if (strpos($name, 'photo.jpg') !== false) {
                $photoCard = $card;
            }
        });

        $this->assertNotNull($photoCard, 'Carte photo.jpg non trouvée');
        // Le photo doit avoir une <img> (pas une icône SVG)
        $this->assertCount(1, $photoCard->filter('img.hc-item-thumb-img'), 'La photo doit avoir une vignette');
    }

    public function testViewButtonAppearsOnlyForImageAndVideoFiles(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Médias', $user);
        $this->em->persist($folder);
        $this->em->flush();
        $folderId = $folder->getId()->toRfc4122();

        $this->login();
        $token = $this->uploadCsrfToken();

        $imgTmp = tempnam(sys_get_temp_dir(), 'hc_img_') . '.jpg';
        file_put_contents($imgTmp, 'fake jpeg content');
        $imgUpload = new UploadedFile($imgTmp, 'photo.jpg', 'image/jpeg', null, true);
        $this->client->request('POST', '/files/upload', ['folder_id' => $folderId, '_token' => $token], ['file' => $imgUpload]);
        $this->client->followRedirect();

        $videoTmp = tempnam(sys_get_temp_dir(), 'hc_vid_') . '.mp4';
        file_put_contents($videoTmp, 'fake mp4 content');
        $videoUpload = new UploadedFile($videoTmp, 'clip.mp4', 'video/mp4', null, true);
        $this->client->request('POST', '/files/upload', ['folder_id' => $folderId, '_token' => $this->uploadCsrfToken()], ['file' => $videoUpload]);
        $this->client->followRedirect();

        $txtTmp = tempnam(sys_get_temp_dir(), 'hc_txt_') . '.txt';
        file_put_contents($txtTmp, 'contenu texte');
        $txtUpload = new UploadedFile($txtTmp, 'notes.txt', 'text/plain', null, true);
        $this->client->request('POST', '/files/upload', ['folder_id' => $folderId, '_token' => $this->uploadCsrfToken()], ['file' => $txtUpload]);
        $crawler = $this->client->followRedirect();

        $this->assertResponseIsSuccessful();

        $imgFile = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['originalName' => 'photo.jpg']);
        $videoFile = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['originalName' => 'clip.mp4']);
        $txtFile = $this->em->getRepository(\App\Entity\File::class)->findOneBy(['originalName' => 'notes.txt']);

        $this->assertSelectorExists('[data-testid="view-media-btn-' . $imgFile->getId()->toRfc4122() . '"]');
        $this->assertSelectorExists('[data-testid="view-media-btn-' . $videoFile->getId()->toRfc4122() . '"]');
        $this->assertCount(
            0,
            $crawler->filter('[data-testid="view-media-btn-' . $txtFile->getId()->toRfc4122() . '"]'),
            'Un fichier texte ne doit pas avoir de bouton de visualisation média'
        );
    }
}
