<?php

namespace App\Event;

use Github\Exception\ErrorException;
use InvalidArgumentException;
use Override;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Clients\Client\Github\GithubAppInstallationClientInterface;
use Packages\Configuration\Constant\ConfigurationFile;
use Packages\Configuration\Service\ConfigurationFileService;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Model\ConfigurationFileChange;
use Packages\Event\Processor\EventProcessorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ConfigurationFileChangeEventProcessor implements EventProcessorInterface
{
    private const array REFS = [
        'main',
        'master'
    ];

    public function __construct(
        private readonly LoggerInterface $eventProcessorLogger,
        #[Autowire(service: GithubAppInstallationClient::class)]
        private readonly GithubAppInstallationClientInterface $githubAppInstallationClient,
        private readonly ConfigurationFileService $configurationFileService
    ) {
    }

    #[Override]
    public function process(EventInterface $event): bool
    {
        if (!$event instanceof ConfigurationFileChange) {
            $this->eventProcessorLogger->critical(
                'Event is not intended to be processed by this processor',
                [
                    'event' => $event
                ]
            );

            return false;
        }

        if (
            !in_array(
                $event->getRef(),
                self::REFS,
                true
            )
        ) {
            $this->eventProcessorLogger->info(
                sprintf(
                    'Ignoring as configuration file change event (%s) is not for a main ref',
                    (string)$event
                )
            );

            return true;
        }

        $this->githubAppInstallationClient->authenticateAsRepositoryOwner($event->getOwner());

        try {
            $configurationFile = $this->githubAppInstallationClient->repo()
                ->contents()
                ->download(
                    $event->getOwner(),
                    $event->getRepository(),
                    ConfigurationFile::PATH,
                    $event->getCommit()
                );

            if ($configurationFile === null) {
                $this->eventProcessorLogger->error(
                    sprintf(
                        'Configuration file returned as null for %s.',
                        (string)$event
                    )
                );

                return false;
            }

            return $this->configurationFileService->parseAndPersistFile(
                $event->getProvider(),
                $event->getOwner(),
                $event->getRepository(),
                $configurationFile
            );
        } catch (ErrorException | InvalidArgumentException $e) {
            $this->eventProcessorLogger->error(
                sprintf(
                    'Could not retrieve configuration file from provider for %s.',
                    (string)$event
                ),
                [
                    'exception' => $e
                ]
            );

            return false;
        }
    }

    #[Override]
    public static function getEvent(): string
    {
        return Event::CONFIGURATION_FILE_CHANGE->value;
    }
}
