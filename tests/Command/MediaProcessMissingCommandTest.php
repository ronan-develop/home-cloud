<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\MediaProcessMissingCommand;
use App\Entity\File;
use App\Entity\Media;
use App\Interface\File\FileRepositoryInterface;
use App\Interface\Media\MediaProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
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
        $photo = $this->createStub(File::class);
        $pdf = $this->createStub(File::class);

        $fileRepository = $this->createStub(FileRepositoryInterface::class);
        $fileRepository->method('findWithoutMedia')->willReturn($this->toGenerator([$photo, $pdf]));

        $mediaProcessor = $this->createMock(MediaProcessorInterface::class);
        $mediaProcessor->expects($this->exactly(2))
            ->method('process')
            ->willReturnCallback(fn (File $file) => $file === $photo ? $this->createStub(Media::class) : null);

        $tester = $this->commandTester($fileRepository, $mediaProcessor);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('1 traité', $tester->getDisplay());
        $this->assertStringContainsString('1 ignoré', $tester->getDisplay());
    }

    public function testReportsNothingToDoWhenNoFileIsMissingMedia(): void
    {
        $fileRepository = $this->createStub(FileRepositoryInterface::class);
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
        $files = [$this->createStub(File::class), $this->createStub(File::class), $this->createStub(File::class)];

        $fileRepository = $this->createStub(FileRepositoryInterface::class);
        $fileRepository->method('findWithoutMedia')->willReturn($this->toGenerator($files));

        $mediaProcessor = $this->createStub(MediaProcessorInterface::class);
        $mediaProcessor->method('process')->willReturn($this->createStub(Media::class));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(3))->method('clear');

        $tester = $this->commandTester($fileRepository, $mediaProcessor, $entityManager);
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
    }

    // #504 : audit sécurité crons — sans catch, une exception levée au
    // milieu du rattrapage (ex. sur le 2e fichier sur 3) remonte à Symfony
    // Console, stack trace complète dans les logs crontab. Le fichier déjà
    // traité avant l'exception (MediaProcessor::process() flush
    // individuellement, cf. commentaire de classe) ne doit pas être perdu —
    // seule l'erreur doit être catchée et loggée proprement, pas de rollback.
    public function testCatchesExceptionMidLoopLogsItAndPreservesAlreadyProcessedWork(): void
    {
        $files = [$this->createStub(File::class), $this->createStub(File::class), $this->createStub(File::class)];

        $fileRepository = $this->createStub(FileRepositoryInterface::class);
        $fileRepository->method('findWithoutMedia')->willReturn($this->toGenerator($files));

        $mediaProcessor = $this->createMock(MediaProcessorInterface::class);
        $callCount = 0;
        $mediaProcessor->method('process')->willReturnCallback(function () use (&$callCount) {
            ++$callCount;
            if ($callCount === 2) {
                throw new \RuntimeException('Disque plein');
            }

            return $this->createStub(Media::class);
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('MediaProcessMissingCommand'), $this->arrayHasKey('exception'));

        $tester = $this->commandTester($fileRepository, $mediaProcessor, logger: $logger);
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        // Le premier fichier (traité avant l'exception au 2e) reste compté :
        // pas de rollback du travail déjà accompli.
        $this->assertStringContainsString('1 traité', $tester->getDisplay());
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
        ?LoggerInterface $logger = null,
    ): CommandTester {
        $command = new MediaProcessMissingCommand(
            $fileRepository,
            $mediaProcessor,
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $logger ?? new NullLogger(),
        );
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:media:process-missing'));
    }
}
