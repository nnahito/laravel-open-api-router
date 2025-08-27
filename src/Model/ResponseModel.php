<?php

declare(strict_types=1);

namespace Nnahito\LaravelOpenApiRouter\Model;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use ReflectionMethod;
use ReflectionNamedType;

class ResponseModel
{
    private $controller;
    private $method;

    public function __construct($controller, $method)
    {
        $this->controller = $controller;
        $this->method = $method;
    }

    /**
     * レスポンスのOpenAPI Schemaを返す
     */
    public function toOpenApiSchema(): array
    {
        // コントローラーメソッドの戻り型ヒント判定
        $rm = new ReflectionMethod($this->controller, $this->method);
        $retType = $rm->getReturnType();

        if ($retType instanceof ReflectionNamedType) {
            $typeName = $retType->getName();
            // JsonResource, Arrayable, Model等ならそれらを使う
            if (is_subclass_of($typeName, Arrayable::class) ||
                is_subclass_of($typeName, JsonResource::class) ||
                is_subclass_of($typeName, Model::class)) {
                return $this->arrayableSchema($typeName);
            }
        }

        // 戻り値型明示なし、もしくは配列などシンプルなものの場合
        // "mixed" や "array" だったら配列サンプル
        // それ以外はstring型など簡易型で対応
        return [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
            ]
        ];
    }

    /**
     * Arrayable実装やResourceの場合のschemaを返すサンプル
     */
    private function arrayableSchema(string $className): array
    {
        /** @var Arrayable $object */
        $arraySample = $this->parseToArrayKeysFromSource($className);

        return $this->arrayToSchema($arraySample);
    }

    /**
     * 配列をOpenAPI Propertyに変換(最低限)
     */
    private function arrayToSchema($arr): array
    {
        $properties = [];
        foreach ($arr as $key) {
            $properties[$key] = ['type' => 'string'];
        }
        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }


    public function parseToArrayKeysFromSource(string $className): array
    {
        // ReflectionでtoArrayのソースを取得
        $ref = new \ReflectionClass($className);
        if (!$ref->hasMethod('toArray')) return [];
        $method = $ref->getMethod('toArray');
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $src = file($filename);
        $lines = array_slice($src, $startLine - 1, $endLine - $startLine + 1);

        // ソースを文字列化
        $joined = implode("", $lines);

        // return [ ... ]; の中身(複数行可)を取り出す
        if (!preg_match('/return\s*\[(.*)\];/s', $joined, $m)) {
            return [];
        }
        $arrSrc = $m[1];

        // 行ごとに 'キー' => または "キー" => を探して抽出
        preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>/', $arrSrc, $matches);

        return $matches[1] ?? [];
    }


}
