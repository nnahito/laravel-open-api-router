<?php

declare(strict_types=1);

namespace Nnahito\LaravelOpenApiRouter;

use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ReflectionMethod;
use Symfony\Component\Yaml\Yaml;

class Router2OpenApi extends Command
{
    protected $signature = 'app:laravel-router2-open-api';
    protected $description = 'Generate OpenAPI 3.0 spec from Laravel routes';

    public function handle()
    {
        $paths = [];

        $routeList = Route::getRoutes();
        foreach ($routeList->getRoutes() as $route) {
            $action = $route->getAction('uses');
            if (is_string($action) === false) {
                continue;
            }

            $divideAction = explode('@', $action);
            if (count($divideAction) !== 2) {
                continue;
            }
            $controller = $divideAction[0];
            $method = $divideAction[1];

            if (!is_string($method) || !Str::startsWith($controller, 'App\Http\Controllers')) {
                continue;
            }
            if (!class_exists($controller) || !method_exists($controller, $method)) {
                continue;
            }

            $formRequestClass = $this->findFormRequestClass($controller, $method);
            if ($formRequestClass === null) {
                continue;
            }

            /** @var \Illuminate\Foundation\Http\FormRequest $request */
            $request = new $formRequestClass();
            $rules = $request->rules();

            // ルートごとの JSON schema を生成
            $jsonSpec = $this->makeOpenApiJson($route->uri(), $rules, $route);

            // merge paths
            $paths = array_merge_recursive($paths, $jsonSpec);
        }

        // OpenAPI全体を組み立て
        $openApi = $this->buildOpenApiSpec($paths);

        // JSON保存
        Storage::put('public/openapi.json', json_encode($openApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // YAML保存
        $yaml = Yaml::dump($openApi, 20, 2, Yaml::DUMP_OBJECT_AS_MAP);
        Storage::put('public/openapi.yaml', $yaml);

        $this->info("✅ OpenAPI spec generated: storage/app/public/openapi.(json|yaml)");
    }

    private function findFormRequestClass($controller, $method): ?string
    {
        $rm = new ReflectionMethod($controller, $method);
        foreach ($rm->getParameters() as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin() && is_subclass_of($type->getName(), FormRequest::class)) {
                return $type->getName();
            }
        }
        return null;
    }

    private function makeOpenApiJson(string $uri, array $rules, $route): array
    {
        $result = [
            "/{$uri}" => []
        ];

        foreach ($route->methods() as $httpMethod) {
            if ($httpMethod === 'HEAD') {
                continue;
            }

            $properties = [];
            $required = [];

            foreach ($rules as $field => $ruleStr) {
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

            $result["/{$uri}"][strtolower($httpMethod)] = [
                'summary' => "Auto generated for {$uri}",
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
                        'description' => 'Success response'
                    ]
                ]
            ];
        }

        return $result;
    }

    private function buildOpenApiSpec(array $paths): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => config('app.name', 'Laravel API'),
                'version' => '1.0.0',
                'description' => 'Generated from Laravel routes and FormRequest',
            ],
            'servers' => [
                ['url' => config('app.url')],
            ],
            'paths' => $paths,
            'components' => [
                'schemas' => new \stdClass(), // 今は空、拡張余地あり
            ]
        ];
    }
}
