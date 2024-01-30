<?php

namespace Packages\Event\Tests\Service;

use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Event\InvalidEventException;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Model\Upload;
use Packages\Event\Service\EventValidationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class EventValidationServiceTest extends TestCase
{
    #[DataProvider('eventDataProvider')]
    public function testValidatingEvents(EventInterface $event, bool $isValid): void
    {
        $eventValidatorService = new EventValidationService(
            Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator()
        );

        if (!$isValid) {
            $this->expectException(InvalidEventException::class);
        }

        $valid = $eventValidatorService->validate($event);

        if ($isValid) {
            $this->assertTrue($valid);
        }
    }

    public static function eventDataProvider(): array
    {
        return [
            [
                new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'dd1100c9409de748590abfac1036e383a3e4de37',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit'),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                true
            ],
            [
                new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    owner: '',
                    repository: 'mock-repository',
                    commit: 'dd1100c9409de748590abfac1036e383a3e4de37',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37'),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                false
            ],
            [
                new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: '',
                    commit: 'dd1100c9409de748590abfac1036e383a3e4de37',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37'),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                false
            ],
            [
                new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'dd1100c9409de748590abfac1036e383a3e4de37',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37'),
                    pullRequest: '',
                    baseCommit: null,
                    baseRef: null
                ),
                false
            ],
            [
                new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: '395df8f7c51f007019cb30201c49e884b46b92fa',
                    parent: ['not-a-commit-hash',null],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37'),
                    pullRequest: 2,
                    baseCommit: null,
                    baseRef: null
                ),
                false
            ],
            [
                new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: '395df8f7c51f007019cb30201c49e884b46b92fa',
                    parent: ['dd1100c9409de748590abfac1036e383a3e4de37',null],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37'),
                    pullRequest: 'not-a-pull-request-number',
                    baseCommit: null,
                    baseRef: null
                ),
                false
            ],
            [
                new Upload(
                    uploadId: 'mock-uuid',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: '395df8f7c51f007019cb30201c49e884b46b92fa',
                    parent: ['dd1100c9409de748590abfac1036e383a3e4de37'],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37'),
                    pullRequest: 2,
                    baseCommit: null,
                    baseRef: null
                ),
                true
            ]
        ];
    }
}
