<?php

declare(strict_types=1);

namespace Nnahito\LaravelOpenApiRouter\Model;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Routing\Route;

class OpenApiModel
{
    /**
     * @var string このRouterのURL
     */
    private string $url;

    /**
     * @var \Illuminate\Validation\Rule[]|string[] FormValidationに設定されているバリデーションリスト
     */
    private array $rules;

    public function __construct(
        private Route $route,
        private RequestModel $requestModel,
    ) {
        $this->url = $this->route->uri();
        $this->rules = $this->requestModel->getRules();
    }

    public function makeOpenApiJson(): array
    {
        return $this->makeRequestSpecs();
    }

    /**
     * @return array|array[] FormRequestの情報を元にAPI仕様書をOpenAPIの仕様で作成します
     * @throws BindingResolutionException
     */
    private function makeRequestSpecs(): array
    {
        $result = [
            "/{$this->url}" => []
        ];

        foreach ($this->route->methods() as $httpMethod) {
            if ($httpMethod === 'HEAD') {
                continue;
            }

            $properties = [];
            $required = [];

            foreach ($this->rules as $field => $ruleStr) {
                $ruleArr = is_array($ruleStr) ? $ruleStr : explode('|', $ruleStr);

                $schema = [
                    'type' => 'string',
                ];

                if (in_array('integer', $ruleArr)) {
                    $schema['type'] = 'integer';
                } elseif (in_array('numeric', $ruleArr)) {
                    $schema['type'] = 'number';
                } elseif (in_array('boolean', $ruleArr)) {
                    $schema['type'] = 'boolean';
                } elseif (in_array('array', $ruleArr)) {
                    $schema['type'] = 'array';
                    $schema['items'] = ['type' => 'string'];
                }

                foreach ($ruleArr as $rule) {
                    if (!is_string($rule)) {
                        continue;
                    }

                    if ($rule === 'required') {
                        $required[] = $field;
                    }
                    if ($rule === 'email') {
                        $schema['format'] = 'email';
                    }
                    if (str_starts_with($rule, 'max:')) {
                        $max = substr($rule, 4);
                        if ($schema['type'] === 'string') {
                            $schema['maxLength'] = (int)$max;
                        } elseif (in_array($schema['type'], ['integer', 'number'])) {
                            $schema['maximum'] = (int)$max;
                        }
                    }
                    if (str_starts_with($rule, 'min:')) {
                        $min = substr($rule, 4);
                        if ($schema['type'] === 'string') {
                            $schema['minLength'] = (int)$min;
                        } elseif (in_array($schema['type'], ['integer', 'number'])) {
                            $schema['minimum'] = (int)$min;
                        }
                    }
                }

                $properties[$field] = $schema;
            }

            $schema = [
                'type' => 'object',
                'properties' => $properties,
            ];

            if (!empty($required)) {
                $schema['required'] = $required;
            }

            $controller = $this->route->getController();
            $method = $this->route->getActionMethod();

            $responseModel = new ResponseModel($controller, $method);
            $responseSchema = $responseModel->toOpenApiSchema();

            $result["/{$this->url}"][strtolower($httpMethod)] = [
                'summary' => "Auto generated for {$this->url}",
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => $schema
                        ]
                    ]
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Success response',
                        'content' => [
                            'application/json' => [
                                'schema' => $responseSchema
                            ]
                        ]
                    ]
                ]
            ];
        }

        return $result;
    }
}
