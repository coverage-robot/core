<?php

namespace App\Tests\Mock\Factory;

use App\Enum\EnvironmentVariable;
use App\Query\QueryInterface;
use App\Query\Result\QueryResultInterface;
use Packages\Contracts\Environment\Environment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

class MockQueryFactory
{
    /**
     * @param string|null $queryString
     * @param mixed|null $parsedResults
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function createMock(
        TestCase $testCase,
        ContainerInterface $container,
        string $queryClass,
        ?string $queryString,
        QueryResultInterface $parsedResults
    ): QueryInterface|MockObject {
        $mockQuery = $testCase->getMockBuilder($queryClass)
            ->setConstructorArgs(
                [
                    $container->get(SerializerInterface::class),
                    MockEnvironmentServiceFactory::getMock(
                        $testCase,
                        Environment::TESTING,
                        [
                            EnvironmentVariable::BIGQUERY_LINE_COVERAGE_TABLE->value => 'mock-line-coverage-table',
                            EnvironmentVariable::BIGQUERY_UPLOAD_TABLE->value => 'mock-upload-table',
                        ]
                    )
                ]
            )
            ->onlyMethods(['getQuery', 'parseResults'])
            ->getMock();

        $mockQuery->expects($queryString !== null ? $testCase::any() : $testCase::never())
            ->method('getQuery')
            ->willReturn($queryString ?? '');

        $mockQuery->method('parseResults')
            ->willReturn($parsedResults);

        return $mockQuery;
    }
}
