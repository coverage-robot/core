<?php

namespace App\Query\Trait;

use App\Enum\QueryParameter;
use App\Model\QueryParameterBag;

trait ScopeAwareTrait
{
    private static function getRepositoryScope(?QueryParameterBag $parameterBag): string
    {
        $filters = [];

        if ($parameterBag && $parameterBag->has(QueryParameter::REPOSITORY)) {
            /** @var string $repository */
            $repository = $parameterBag->get(QueryParameter::REPOSITORY);

            $filters[] = <<<SQL
            repository = "{$repository}"
            SQL;
        }

        if ($parameterBag && $parameterBag->has(QueryParameter::OWNER)) {
            /** @var string $owner */
            $owner = $parameterBag->get(QueryParameter::OWNER);

            $filters[] = <<<SQL
            owner = "{$owner}"
            SQL;
        }

        if ($parameterBag && $parameterBag->has(QueryParameter::PROVIDER)) {
            /** @var string $provider */
            $provider = $parameterBag->get(QueryParameter::PROVIDER)->value;

            $filters[] = <<<SQL
            provider = "{$provider}"
            SQL;
        }

        return implode("\nAND ", $filters);
    }

    private static function getCommitScope(?QueryParameterBag $parameterBag): string
    {
        if ($parameterBag && $parameterBag->has(QueryParameter::COMMIT)) {
            /** @var array<array-key, string> $commits */
            $commits = $parameterBag->get(QueryParameter::COMMIT);

            if (is_string($commits)) {
                return <<<SQL
                commit = "{$commits}"
                SQL;
            }

            $commits = implode('","', $commits);

            return <<<SQL
            commit IN ("{$commits}")
            SQL;
        }

        return '';
    }

    private static function getLimit(?QueryParameterBag $parameterBag): string
    {
        if ($parameterBag && $parameterBag->has(QueryParameter::LIMIT)) {
            return 'LIMIT ' . (string)$parameterBag->get(QueryParameter::LIMIT);
        }

        return '';
    }
}
