<?php

namespace App\Service\Event;

use Exception;
use JsonException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class IngestCompleteEventTransportSerializer implements SerializerInterface
{
    /**
     * @throws Exception
     */
    public function decode(array $encodedEnvelope): Envelope
    {
        throw new Exception('The transport serializer is not intended to be used for decoding messages.');
    }

    /**
     * @throws JsonException
     */
    public function encode(Envelope $envelope): array
    {
        return [
            'body' => json_encode($envelope->getMessage(), JSON_THROW_ON_ERROR),
        ];
    }
}
