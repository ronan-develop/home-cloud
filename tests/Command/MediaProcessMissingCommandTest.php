<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\MediaProcessMissingCommand;
use App\Entity\File;
use App\Entity\Media;
use App\Interface\FileRepositoryInterface;
use App\Interface\MediaProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Rattrapage des fichiers restés sans Media : constaté sur une instance de
 * prod, des photos uploadées via une route qui ne dispatchait pas
 * MediaProcessMessage (avant le fix async post-upload, cf. #251) n'ont
 * jamais reçu de vignette. MediaProcessor::process() étant idempotent, cette
 * commande peut aussi servir de filet de sécurité récurrent.
 */
final class MediaProcessMissingCommandTest extends TestCase
{
    public function testProcessesEachFileWithoutMedia(): void
    {
        $photo = $this->createMock(File::class);
        $pdf = $this->createMock(File::class);

        $fileRepository = $this->createMock(FileRepositoryInterface::class);
        $fileRepository->method('findWithoutMedia')->willReturn($this->toGenerator([$photo, $pdf]));

        $mediaProcessor = $this->createMock(MediaProcessorInterface::class);
        $mediaProcessor->expects($this->exactly(2))
            ->method('process')
            ->willReturnCallback(fn (File $file) => $file === $photo ? $this->createMock(Media::class) : null);

        $tester = $this->commandTester($fileRepository, $mediaProcessor);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('1 traité', $tester->getDisplay());
        $this->assertStringContainsString('1 ignoré', $tester->getDisplay());
    }

    public function testReportsNothingToDoWhenNoFileIsMissingMedia(): void
    {
        $fileRepository = $this->createMock(FileRepositoryInterface::class);
        $fileRepository->method('findWithoutMedia')->willReturn($this->toGenerator([]));

        $mediaProcessor = $this->createMock(MediaProcessorInterface::class);
        $mediaProcessor->expects($this->never())->method('process');

        $tester = $this->commandTester($fileRepository, $mediaProcessor);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('0 traité', $tester->getDisplay());
    }

    /**
     * #365 : sur un rattrapage de plusieurs centaines de fichiers, l'UnitOfWork
     * Doctrine accumulait toutes les entités déjà traitées sans jamais les
     * libérer, jusqu'à l'OOM kill. On détache donc après chaque fichier — pas
     * seulement tous les N — puisque MediaProcessor::process() flush déjà
     * individuellement (rien n'est en attente à perdre).
     */
    public function testClearsEntityManagerAfterEachProcessedFile(): void
    {
        $files = [$this->createMock(File::class), $this->createMock(File::class), $this->createMock(File::class)];

        $fileRepository = $this->createMock(FileRepositoryInterface::class);
        $fileRepository->method('findWithoutMedia')->willReturn($this->toGenerator($files));

        $mediaProcessor = $this->createMock(MediaProcessorInterface::class);
        $mediaProcessor->method('process')->willReturn($this->createMock(Media::class));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(3))->method('clear');

        $tester = $this->commandTester($fileRepository, $mediaProcessor, $entityManager);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }

    /**
     * @param list<File> $files
     */
    private function toGenerator(array $files): \Generator
    {
        yield from $files;
    }

    private function commandTester(
        FileRepositoryInterface $fileRepository,
        MediaProcessorInterface $mediaProcessor,
        ?EntityManagerInterface $entityManager = null,
    ): CommandTester {
        $command = new MediaProcessMissingCommand($fileRepository, $mediaProcessor, $entityManager ?? $this->createMock(EntityManagerInterface::class));
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:media:process-missing'));
    }
}
