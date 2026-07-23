<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\BroadcastMailerInterface;
use App\Interface\UserRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Diffusion d'un message admin (#283) à tous les comptes de l'instance
 * courante — propriétaires et invités confondus. Chaque instance envoie ses
 * propres emails localement (DB isolée par instance), sans connaître les
 * autres — l'orchestration multi-instances est de la responsabilité de
 * BroadcastOrchestrator.
 */
final readonly class BroadcastMailer implements BroadcastMailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private UserRepositoryInterface $userRepository,
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
    ) {}

    public function sendToAllUsers(string $subject, string $htmlBody, bool $dryRun): int
    {
        $sent = 0;

        foreach ($this->userRepository->findAll() as $user) {
            if (\count($this->validator->validate($user->getEmail(), new Email())) > 0) {
                $this->logger->warning('Broadcast : email invalide ignoré', ['userId' => $user->getId()->toRfc4122()]);
                continue;
            }

            $sent++;

            if ($dryRun) {
                continue;
            }

            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@homecloud.fr'))
                ->to($user->getEmail())
                ->subject($subject)
                ->htmlTemplate('emails/broadcast_message.html.twig')
                ->context([
                    'subject'     => $subject,
                    'body'        => $htmlBody,
                    'accentColor' => EmailBranding::ACCENT_COLOR,
                ]);

            $this->mailer->send($email);
        }

        return $sent;
    }
}
