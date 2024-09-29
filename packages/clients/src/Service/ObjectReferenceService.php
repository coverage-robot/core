<?php

namespace Packages\Clients\Service;

use AsyncAws\S3\Input\GetObjectRequest;
use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Http\Message\Authentication\Wsse;
use Packages\Clients\Model\Object\Reference;
use Packages\Clients\Tests\Client\ObjectReferenceClient;
use Packages\Clients\Tests\Client\ObjectReferenceClientInterface;
use Packages\Clients\Tests\Client\S3ClientInterface;
use Packages\Contracts\Environment\EnvironmentServiceInterface;
use Packages\Contracts\Event\EventInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

class ObjectReferenceService
{
    public function __construct(
        #[Autowire(value: '%object_reference_store.name%')]
        private readonly string $objectReferenceStoreName,
        #[Autowire(service: ObjectReferenceClient::class)]
        private readonly ObjectReferenceClientInterface $client,
        private readonly EnvironmentServiceInterface $environmentService,
        private readonly LoggerInterface $objectReferenceLogger
    ) {
    }

    /**
     * Resolve an object reference into a resource which contains the content of the object.
     *
     * @return resource
     */
    public function resolveReference(Reference $reference): mixed
    {
        if ($reference->getExpiration() < new DateTime()) {
            throw new RuntimeException(
                sprintf(
                    'Cannot resolve %s as it expired at %s',
                    (string) $reference,
                    $reference->getExpiration()
                        ->format(DateTimeInterface::ATOM)
                )
            );
        }

        $this->objectReferenceLogger->info(
            sprintf(
                'Resolving reference: %s',
                (string) $reference
            )
        );

        return fopen($reference->getSignedUrl(), 'r');
    }

    /**
     * Create a reference to a large object.
     *
     * @param string|resource $content
     */
    public function createReference(mixed $content, ?EventInterface $event = null): Reference
    {
        $key = sprintf(
            '%s/%s',
            $this->environmentService->getService()->value,
            Uuid::v4()
        );

        $metadata = [
            'service' => $this->environmentService->getService()->value,
            'event' => $event instanceof EventInterface ? (string) $event : '(not provided)',
            'created_at' => (new DateTime())->format(DateTimeInterface::ATOM),
        ];

        $this->client->putObject(
            new PutObjectRequest([
                'Bucket' => $this->objectReferenceStoreName,
                'Key' => $key,
                'Body' => $content,
                'Metadata' => $metadata
            ])
        );

        $expiration = new DateTimeImmutable('+1 day');

        $signedRequest = $this->client->presign(
            new GetObjectRequest([
                'Bucket' => $this->objectReferenceStoreName,
                'Key' => $key
            ]),
            $expiration
        );

        $reference = new Reference(
            $key,
            $signedRequest,
            $expiration
        );

        $this->objectReferenceLogger->info(
            sprintf(
                'New reference created: %s',
                (string) $reference
            )
        );

        return $reference;
    }
}
