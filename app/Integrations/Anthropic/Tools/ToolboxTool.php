<?php

declare(strict_types=1);

namespace App\Integrations\Anthropic\Tools;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/** One model-callable tool: a name, a schema and a handler that returns the toolbox's structured result as JSON. */
final class ToolboxTool implements Tool
{
    /**
     * @param  Closure(JsonSchema): array<string, mixed>  $schema
     * @param  Closure(Request): array<string, mixed>  $handler
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description,
        private readonly Closure $schema,
        private readonly Closure $handler,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function schema(JsonSchema $schema): array
    {
        return ($this->schema)($schema);
    }

    public function handle(Request $request): string
    {
        return (string) json_encode(($this->handler)($request), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
