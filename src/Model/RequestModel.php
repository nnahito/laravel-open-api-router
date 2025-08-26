<?php

declare(strict_types=1);

namespace Nnahito\LaravelOpenApiRouter\Model;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use ReflectionException;
use ReflectionMethod;

class RequestModel
{
    private ?string $requestClassName;

    public function __construct(
        private string $controller,
        private string $method,
    ) {
        $this->requestClassName = $this->parse();
    }

    /**
     * FormRequestを継承したRequestClassの名前を取得します
     *
     * @return string|null
     */
    public function parse(): ?string
    {
        try {
            $rm = new ReflectionMethod($this->controller, $this->method);
            foreach ($rm->getParameters() as $param) {
                $type = $param->getType();
                if ($type && !$type->isBuiltin() && is_subclass_of($type->getName(), FormRequest::class)) {
                    return $type->getName();
                }
            }
        } catch (ReflectionException $e) {}

        return null;
    }

    /**
     * FormRequestClassかのチェックを行います
     *
     * @return bool
     */
    public function isFormRequestClass(): bool
    {
        return $this->requestClassName !== null;
    }

    /**
     * FormValidationのRule一覧を返します
     *
     * @return array<string, string|Rule>
     */
    public function getRules(): array
    {
        /** @var \Illuminate\Foundation\Http\FormRequest $request */
        $request = new $this->requestClassName();
        return $request->rules();
    }
}
