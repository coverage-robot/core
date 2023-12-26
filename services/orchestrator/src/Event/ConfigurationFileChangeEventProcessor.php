<?php

namespace App\Event;

use Github\Exception\ErrorException;
use InvalidArgumentException;
use Override;
use Packages\Clients\Client\Github\GithubAppInstallationClient;
use Packages\Configuration\Constant\ConfigurationFile;
use Packages\Configuration\Service\ConfigurationFileService;
use Packages\Contracts\Event\Event;
use Packages\Contracts\Event\EventInterface;
use Packages\Event\Model\ConfigurationFileChange;
use Psr\Log\LoggerInterface;

class ConfigurationFileChangeEventProcessor implements EventProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface $eventProcessorLogger,
        private readonly GithubAppInstallationClient $githubAppInstallationClient,
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
