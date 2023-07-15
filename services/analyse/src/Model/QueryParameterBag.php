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

        // Store the main upload model in the parameter bag
        $parameters->set(QueryParameter::UPLOAD, $upload);

        // Extract core parameters from upload model for ease of use
        $parameters->set(QueryParameter::COMMIT, $upload->getCommit());
        $parameters->set(QueryParameter::OWNER, $upload->getOwner());
        $parameters->set(QueryParameter::REPOSITORY, $upload->getRepository());
        $parameters->set(QueryParameter::PROVIDER, $upload->getProvider());

        return $parameters;
    }
}
