<?php

namespace App\Model;

use App\Enum\QueryParameter;
use Packages\Models\Model\Upload;
use WeakMap;

/**
 * @psalm-suppress MixedInferredReturnType
 * @psalm-suppress MixedReturnStatement
 */
class QueryParameterBag
{
    private WeakMap $parameters;

    public function __construct()
    {
        $this->parameters = new WeakMap();
    }

    /**
     * @param QueryParameter $key
     * @return (
     *  $key is QueryParameter::COMMIT ?
     *      string :
     *      ($key is QueryParameter::UPLOAD ?
     *          Upload :
     *          ($key is QueryParameter::LINE_SCOPE ?
     *              array :
     *              int
     *          )
     *      )
     * )|null
     */
    public function get(QueryParameter $key): mixed
    {
        return $this->parameters[$key] ?? null;
    }

    public function has(QueryParameter $key): bool
    {
        return isset($this->parameters[$key]);
    }

    public function set(QueryParameter $key, array|int|string|Upload $value): void
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
