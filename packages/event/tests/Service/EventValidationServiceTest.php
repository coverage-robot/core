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

class EventValidationServiceTest extends TestCase
{
    #[DataProvider('eventDataProvider')]
    public function testValidatingEvents(EventInterface $event, bool $isValid): void
    {
        $eventValidatorService = new EventValidationService();

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
                    commit: 'mock-commit',
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
                    commit: 'mock-commit',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit'),
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
                    commit: 'mock-commit',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit'),
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
                    commit: 'mock-commit',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit'),
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
                    commit: 'mock-commit',
                    parent: [1,2,3,null],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit'),
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
                    commit: 'mock-commit',
                    parent: ['1', '2', '3'],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit'),
                    pullRequest: 2,
                    baseCommit: null,
                    baseRef: null
                ),
                true
            ]
        ];
    }
}
