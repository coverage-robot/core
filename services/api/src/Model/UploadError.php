<?php

namespace App\Model;

use Exception;

class UploadError
{
    private string $message;

    public function __construct(
        string|Exception $message
    ) {
        if ($message instanceof Exception) {
            $message = $message->getMessage();
        }

        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
