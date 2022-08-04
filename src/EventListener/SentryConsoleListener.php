<?php

declare(strict_types=1);

namespace Brainbits\MonologSentry\EventListener;

use Psr\Log\LoggerInterface;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function sprintf;

final class SentryConsoleListener implements EventSubscriberInterface
{
    public function __construct(private HubInterface $hub, private LoggerInterface $logger)
    {
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $command = $command ? $command->getName() : 'N/A';
        $command ??= 'N/A';

        $this->hub->configureScope(static fn (Scope $scope) => $scope->setTag('command', $command));
    }

    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $exitCode = $event->getExitCode();
        $command = $event->getCommand();
        $error = $event->getError();

        $this->hub->configureScope(static fn (Scope $scope) => $scope->setTag('exit_code', (string) $exitCode));

        $message = sprintf(
            '%s: %s (uncaught exception) at %s line %s while running console command `%s`',
            $error::class,
            $error->getMessage(),
            $error->getFile(),
            $error->getLine(),
            $command ? $command->getName() : '-',
        );

        $this->logger->critical(
            $message,
            [
                'exception' => $error,
                'command' => $command ? $command->getName() : '-',
                'arguments' => $event->getInput()->getArguments(),
                'options' => $event->getInput()->getOptions(),
            ],
        );
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $statusCode = $event->getExitCode();
        $command = $event->getCommand();

        if ($statusCode === 0) {
            return;
        }

        if ($statusCode > 255) {
            $statusCode = 255;
            $event->setExitCode($statusCode);
        }

        $commandName = 'N/A';
        if ($command && $command->getName()) {
            $commandName = $command->getName();
        }

        $this->logger->warning(
            sprintf(
                'Command `%s` exited with status code %d',
                $commandName,
                $statusCode,
            ),
        );
    }

    /**
     * @return mixed[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => ['onConsoleCommand', 1],
            ConsoleEvents::ERROR => ['onConsoleError', 1],
            ConsoleEvents::TERMINATE => ['onConsoleTerminate', 1],
        ];
    }
}
