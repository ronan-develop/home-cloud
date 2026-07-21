<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[AsCommand(name: 'app:create-user', description: 'Crée un utilisateur')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email')
            ->addArgument('displayName', InputArgument::OPTIONAL, 'Nom affiché', 'User')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe (omis : le compte est créé sans mot de passe, à définir via un lien de réinitialisation)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = new User($input->getArgument('email'), $input->getArgument('displayName'));
        $password = $input->getArgument('password');

        $this->em->persist($user);

        if ($password === null) {
            $this->em->flush();

            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
            $resetUrl = $this->urlGenerator->generate(
                'web_reset_password_confirm',
                ['token' => $resetToken->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $io->success('User créé : ' . $user->getEmail());
            $io->writeln('Définissez le mot de passe ici (lien valable 1h) :');
            $io->writeln($resetUrl);

            return Command::SUCCESS;
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->flush();

        $io->success('User créé : ' . $user->getEmail());

        return Command::SUCCESS;
    }
}
