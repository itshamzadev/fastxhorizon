<?php

namespace Modules\AI\app\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SafeTool implements Tool
{
    public function __construct(private readonly Tool $tool) {}

    public function name(): string
    {
        return is_callable([$this->tool, 'name']) ? $this->tool->name() : class_basename($this->tool);
    }

    public function description(): Stringable|string
    {
        return $this->tool->description();
    }

    public function schema(JsonSchema $schema): array
    {
        return $this->tool->schema($schema);
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return $this->tool->handle($request);
        } catch (\Throwable $e) {
            Log::error('AI shopping assistant tool failed', [
                'tool'    => $this->name(),
                'message' => $e->getMessage(),
            ]);

            return json_encode([
                'status'  => 'error',
                'message' => 'This action could not be completed right now. Tell the customer briefly that something '
                    . 'went wrong and offer to try again or help another way. Do NOT claim the action succeeded.',
            ]);
        }
    }
}
