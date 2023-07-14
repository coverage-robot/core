<?php

namespace App\Model;

use App\Enum\QueryParameter;
use Packages\Models\Model\Upload;
use WeakMap;

class QueryParameterBag
{
    private WeakMap $parameters;

    public function __construct()
    {
        $this->parameters = new WeakMap();
    }

    public function get(QueryParameter $key): mixed
    {
        return $this->parameters[$key] ?? null;
    }

    public function has(QueryParameter $key): bool
    {
        return isset($this->parameters[$key]);
    }

    public function set(QueryParameter $key, mixed $value): void
    {
        $this->parameters[$key] = $value;
    }

    public static function fromUpload(Upload $upload): self
    {
        $parameters = new self();
        $parameters->set(QueryParameter::UPLOAD, $upload);
        $parameters->set(QueryParameter::COMMIT, $upload->getRepository());

        return $parameters;
    }
}
