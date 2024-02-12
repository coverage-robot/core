<?php

namespace App\Extension;

use Override;
use Twig\Sandbox\SecurityPolicy;
use Twig\Sandbox\SecurityPolicyInterface;
use Twig\TwigFunction;

final class CoverageTemplateSecurityPolicy implements SecurityPolicyInterface
{
    private SecurityPolicyInterface $policy;

    public function __construct(
        private readonly CoverageTemplateExtension $extension,
        array $allowedTags = [],
        array $allowedFilters = [],
        array $allowedMethods = [],
        array $allowedProperties = [],
        array $allowedFunctions = []
    ) {
        $this->policy = new SecurityPolicy(
            $allowedTags,
            $allowedFilters,
            $allowedMethods,
            $allowedProperties,
            [
                ...$allowedFunctions,
                ...$this->getFunctionNames()
            ]
        );
    }

    #[Override]
    public function checkSecurity($tags, $filters, $functions): void
    {
        $this->policy->checkSecurity($tags, $filters, $functions);
    }

    #[Override]
    public function checkMethodAllowed($obj, $method): void
    {
        $this->policy->checkMethodAllowed($obj, $method);
    }

    #[Override]
    public function checkPropertyAllowed($obj, $property): void
    {
        $this->policy->checkPropertyAllowed($obj, $property);
    }

    /**
     * @return string[]
     */
    private function getFunctionNames(): array
    {
        return array_map(
            fn (TwigFunction $function) => $function->getName(),
            $this->extension->getFunctions()
        );
    }
}
