<?php

namespace Packages\Telemetry\Service;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use TypeError;

/**
 * A basic line formatter for monolog which uses the Symfony Serializer to format JSON
 * objects (such as context) instead of PHPs built in `json_encode`.
 */
final class SymfonySerializerLineFormatter extends LineFormatter implements FormatterInterface
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        #[Autowire(value: "[%%channel%%] %%level_name%%: %%message%% %%context%% %%extra%%\n")]
        ?string $format = null,
        ?string $dateFormat = null,
        bool $allowInlineLineBreaks = false,
        bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = false,
    ) {
        parent::__construct(
            $format,
            $dateFormat,
            $allowInlineLineBreaks,
            $ignoreEmptyContextAndExtra,
            $includeStacktraces
        );
    }

    /**
     * Serialize data to JSON, using Symfony Serializer instead of the built in json_encode
     * behaviour.
     *
     * This is most helpful for Logger context, as it allows us to serialize objects using
     * getters and setters.
     */
    #[Override]
    protected function toJson($data, bool $ignoreErrors = false): string
    {
        try {
            return $this->serializer->serialize($data, 'json');
        } catch (TypeError | ExceptionInterface) {
            // Fallback to the default behaviour if Symfony Serializer fails
            return parent::toJson($data, $ignoreErrors);
        }
    }
}
