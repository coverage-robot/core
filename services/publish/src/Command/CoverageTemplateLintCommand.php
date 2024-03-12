<?php

namespace App\Command;

use Symfony\Bridge\Twig\Command\LintCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

#[AsCommand(
    name: 'lint:twig:coverage_templates',
    description: 'Lint the coverage template files and output encountered errors'
)]
final class CoverageTemplateLintCommand extends LintCommand
{
    public function __construct(
        #[Autowire(service: 'app.coverage_template_environment')]
        Environment $twig
    ) {
        parent::__construct($twig);
    }
}
