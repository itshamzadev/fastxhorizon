<?php

namespace Tests\Unit\AI;

use Modules\AI\app\Services\ShoppingAssistant\AgentLoopService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The follow-up heuristic decides whether a turn may use the history-free global
 * cache (standalone) or must keep a context-aware key. A false negative would
 * serve a context-dependent turn a context-free answer, so the bias toward
 * "follow-up" matters. Pure regex — exercised without the heavy constructor.
 */
class AgentLoopFollowUpTest extends TestCase
{
    private function isFollowUp(string $normalized): bool
    {
        $service = (new ReflectionClass(AgentLoopService::class))->newInstanceWithoutConstructor();
        $m = new ReflectionMethod(AgentLoopService::class, 'isFollowUp');
        $m->setAccessible(true);

        return (bool) $m->invoke($service, $normalized);
    }

    /** @dataProvider followUpProvider */
    public function test_follow_up_detection(string $message, bool $expected): void
    {
        $this->assertSame($expected, $this->isFollowUp(strtolower(trim($message))));
    }

    public static function followUpProvider(): array
    {
        return [
            'cheaper'        => ['show me cheaper ones', true],
            'the red one'    => ['i want the red one', true],
            'pronoun it'     => ['add it to my cart', true],
            'show more'      => ['show more', true],
            'others'         => ['what about the others', true],
            'similar'        => ['show me similar', true],
            'standalone'     => ['iphone 15 pro', false],
            'fresh search'   => ['gaming laptop under 1000', false],
        ];
    }
}
