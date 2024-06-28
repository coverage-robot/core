<?php

namespace Packages\Event\Tests\Service;

use Packages\Contracts\Event\EventInterface;
use Packages\Contracts\Provider\Provider;
use Packages\Contracts\Tag\Tag;
use Packages\Event\Exception\InvalidEventException;
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
                    uploadId: '019060e8-2249-768c-8b88-70592c698d4f',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'dd1100c9409de748590abfac1036e383a3e4de37',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'mock-commit', [11]),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                true
            ],
            [
                new Upload(
                    uploadId: '019060e8-64ff-75b7-a92a-f45719e2b559',
                    provider: Provider::GITHUB,
                    owner: '',
                    repository: 'mock-repository',
                    commit: 'dd1100c9409de748590abfac1036e383a3e4de37',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37', [12]),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                false
            ],
            [
                new Upload(
                    uploadId: '019060e8-8f64-722b-ba88-cf1992aff368',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: '',
                    commit: 'dd1100c9409de748590abfac1036e383a3e4de37',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37', [13]),
                    pullRequest: null,
                    baseCommit: null,
                    baseRef: null
                ),
                false
            ],
            [
                new Upload(
                    uploadId: '019060e8-e5c3-7bbf-8e35-79fa251c7856',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: 'dd1100c9409de748590abfac1036e383a3e4de37',
                    parent: [],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37', [14]),
                    pullRequest: '',
                    baseCommit: null,
                    baseRef: null
                ),
                false
            ],
            [
                new Upload(
                    uploadId: '019060e9-1e8f-7b03-8168-41cac329238a',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: '395df8f7c51f007019cb30201c49e884b46b92fa',
                    parent: ['not-a-commit-hash',null],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37', [15]),
                    pullRequest: 2,
                    baseCommit: null,
                    baseRef: null
                ),
                false
            ],
            [
                new Upload(
                    uploadId: '019060e9-4ce2-7748-bea9-15e497cd4b5a',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: '395df8f7c51f007019cb30201c49e884b46b92fa',
                    parent: ['dd1100c9409de748590abfac1036e383a3e4de37',null],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37', [16]),
                    pullRequest: 'not-a-pull-request-number',
                    baseCommit: null,
                    baseRef: null
                ),
                false
            ],
            [
                new Upload(
                    uploadId: '019060e9-9f4f-754d-9de2-3b6106eb91d4',
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    commit: '395df8f7c51f007019cb30201c49e884b46b92fa',
                    parent: ['dd1100c9409de748590abfac1036e383a3e4de37'],
                    ref: 'mock-ref',
                    projectRoot: '',
                    tag: new Tag('1', 'dd1100c9409de748590abfac1036e383a3e4de37', [17]),
                    pullRequest: 2,
                    baseCommit: null,
                    baseRef: null
                ),
                true
            ]
        ];
    }
}
