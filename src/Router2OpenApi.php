<?php

declare(strict_types=1);

namespace Nnahito\LaravelOpenApiRouter;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Nnahito\LaravelOpenApiRouter\Model\OpenApiModel;
use Nnahito\LaravelOpenApiRouter\Model\RequestModel;

class Router2OpenApi extends Command
{
    /**
     * @var string command name
     */
    protected $signature = 'app:laravel-router2-open-api';

    /**
     * @var string command description
     */
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

            $requestModel = new RequestModel($controller, $method);
            if ($requestModel->isFormRequestClass() === false) {
                continue;
            }

            // ルートごとの JSON schema を生成
            $openApiModel = new OpenApiModel($route, $requestModel);
            $jsonSpec = $openApiModel->makeOpenApiJson();

            // merge paths
            $paths = array_merge_recursive($paths, $jsonSpec);
        }

        // OpenAPI全体を組み立て
        $openApi = $this->buildOpenApiSpec($paths);

        // JSON保存
        file_put_contents(storage_path('openapi.json'), json_encode($openApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("✅ OpenAPI spec generated: storage/app/public/openapi.(json|yaml)");
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
