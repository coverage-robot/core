<?php

namespace Packages\Telemetry\Service;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * A basic line formatter for monolog which uses the Symfony Serializer to format JSON
 * objects (such as context) instead of PHPs built in `json_encode`.
 */
class SymfonySerializerLineFormatter extends LineFormatter implements FormatterInterface
{
    private SerializerInterface $serializer;

    public function __construct(
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
    public function toJson($data, bool $ignoreErrors = false): string
    {
        return $this->serializer->serialize($data, 'json');
    }

    #[Required]
    public function withSerializer(
        #[Autowire(service: SerializerInterface::class)]
        SerializerInterface $serializer
    ): static {
        $this->serializer = $serializer;

        return $this;
    }
}
