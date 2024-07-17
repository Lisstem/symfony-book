<?php

namespace App\MessageHandler;

use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\SpamChecker;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\NotificationEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler]
final class CommentMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SpamChecker            $spamChecker,
        private readonly CommentRepository      $commentRepository,
        private readonly MessageBusInterface    $messageBus,
        private readonly WorkflowInterface      $commentStateMachine,
        private readonly MailerInterface        $mailer,
        #[Autowire('%admin_email%')] private readonly string $adminEmail,
        private readonly ?LoggerInterface       $logger = null
    ) {

    }
    public function __invoke(CommentMessage $message): void
    {
        $comment = $this->commentRepository->find($message->getId());
        if (!$comment) {
            return;
        }

        if ($this->commentStateMachine->can($comment, 'accept')) {
            $score = $this->spamChecker->getSpamScore($comment, $message->getContext());
            $transition = match ($score) {
                2 => 'reject_spam',
                1 => 'might_be_spam',
                default => 'accept',
            };
            $this->commentStateMachine->apply($comment, $transition);
            $this->entityManager->flush();
            $this->messageBus->dispatch($message);
        } elseif ($this->commentStateMachine->can($comment, 'publish') || $this->commentStateMachine->can($comment, 'publish_ham')) {
            $this->sendCommentNotification($comment);
        } elseif ($this->logger) {
            $this->logger->debug('Dropping comment message', ['comment' => $comment->getId(), 'state' => $comment->getState()]);
        }
    }

    private function sendCommentNotification($comment): void
    {
        $this->mailer->send((new NotificationEmail())
            ->subject('new comment posted')
            ->htmlTemplate('emails/comment_notification.html.twig')
            ->to($this->adminEmail)
            ->context(['comment' => $comment]));
    }
}
