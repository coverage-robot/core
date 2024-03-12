<?php

namespace App\Service\Templating;

use App\Enum\TemplateVariant;
use App\Exception\NoTemplateAvailableException;
use Packages\Contracts\PublishableMessage\PublishableMessageInterface;
use Packages\Message\PublishableMessage\PublishableCheckRunMessageInterface;
use Packages\Message\PublishableMessage\PublishableLineCommentInterface;
use Packages\Message\PublishableMessage\PublishablePullRequestMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class TemplateRenderingService
{
    public function __construct(
        #[Autowire(service: 'app.coverage_template_environment')]
        private readonly Environment $environment
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
        return $this->environment->render(
            $template,
            [
                'event' => $message->getEvent(),
                'message' => $message
            ]
        );
    }
}
