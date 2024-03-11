<?php

namespace App\Service\Templating;

use App\Enum\TemplateVariant;
use App\Exception\NoTemplateAvailableException;
use App\Extension\CoverageTemplateExtension;
use App\Extension\CoverageTemplateSecurityPolicy;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessageInterface;
use Packages\Message\PublishableMessage\PublishableLineCommentInterface;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\SandboxExtension;
use Twig\Extra\String\StringExtension;
use Twig\RuntimeLoader\ContainerRuntimeLoader;

final class TemplateRenderingService
{
    public function __construct(
        private readonly Environment $environment,
        #[TaggedLocator('app.template_available')]
        private readonly ContainerInterface $container,
        private readonly CoverageTemplateSecurityPolicy $securityPolicy,
        private readonly CoverageTemplateExtension $coverageTemplateExtension
    ) {
    }

    /**
     * Render a message using the appropriate template and return the markdown
     * which can be published.
     */
    public function render(
        PublishableMessageInterface $message,
        TemplateVariant $variant
    ): string {
        try {
            return $this->renderMessageWithTemplate(
                $message,
                $this->getTemplatePath($message, $variant)
            );
        } catch (LoaderError) {
            throw new NoTemplateAvailableException($message);
        }
    }

    /**
     * Get the path to the template for a specific message and variant.
     */
    private function getTemplatePath(
        PublishableMessageInterface $message,
        TemplateVariant $variant
    ): string {
        $folder = match (true) {
            $message instanceof PublishablePullRequestMessage => 'pull_request',
            $message instanceof PublishableCheckRunMessageInterface => 'check_run',
            $message instanceof PublishableLineCommentInterface => 'line_comment',
            default => throw new NoTemplateAvailableException($message)
        };

        return sprintf(
            '%s/%s.md.twig',
            $folder,
            $variant->value
        );
    }

    /**
     * Render a message using a specific template.
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function renderMessageWithTemplate(
        PublishableMessageInterface $message,
        string $template
    ): string {
        $environment = $this->getTemplatingEnvironment();

        return $environment->render(
            $template,
            [
                'event' => $message->getEvent(),
                'message' => $message
            ]
        );
    }

    /**
     * Create an extremely limited templating environment for rendering pull request
     * messages.
     *
     * This environment is sandboxed and has a custom runtime loader in order to seperate
     * the templating environment from the rest of the application.
     */
    private function getTemplatingEnvironment(): Environment
    {
        $environment = new Environment($this->environment->getLoader());

        /**
         * Enable the sandbox environment extension so that we can evaluate
         * untrusted template code in the future.
         */
        $environment->addExtension(new SandboxExtension($this->securityPolicy, true));

        /**
         * Allow the runtime loader to load runtime classes from the container.
         */
        $environment->addRuntimeLoader(new ContainerRuntimeLoader($this->container));

        /**
         * Add the coverage template extension to add custom functions for coverage data.
         */
        $environment->addExtension($this->coverageTemplateExtension);

        /**
         * Add simple string helpers provided by Twig. This includes string manipulation
         * using the UnicodeString class - line truncating strings at a certain length.
         */
        $environment->addExtension(new StringExtension());

        return $environment;
    }
}
