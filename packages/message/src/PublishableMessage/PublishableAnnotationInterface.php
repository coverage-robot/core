<?php

namespace Packages\Message\PublishableMessage;

interface PublishableAnnotationInterface
{
    public function getFileName(): string;

    public function getStartLineNumber(): int;

    public function getEndLineNumber(): int;
}
