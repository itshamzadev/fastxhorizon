<?php

namespace Tests\Unit\AI;

use Modules\AI\app\Services\ShoppingAssistant\QueryGuardService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Pure-logic coverage of the zero-cost query guard's decision predicates.
 * Avoids booting the app (which loads DB-driven config) by exercising the
 * private classifiers directly — no provider, DB, or translate() involved.
 */
class QueryGuardServiceTest extends TestCase
{
    private function call(string $method, mixed ...$args): mixed
    {
        $m = new ReflectionMethod(QueryGuardService::class, $method);
        $m->setAccessible(true);

        return $m->invoke(new QueryGuardService(), ...$args);
    }

    /** @dataProvider gibberishProvider */
    public function test_detects_gibberish(string $input, bool $expected): void
    {
        $this->assertSame($expected, $this->call('isGibberish', $input));
    }

    public static function gibberishProvider(): array
    {
        return [
            'single char'       => ['a', true],
            'repeated char'     => ['aaaaaaa', true],
            'only punctuation'  => ['!!!???', true],
            'only digits'       => ['12345', true],
            'real word'         => ['iphone', false],
            'real phrase'       => ['show me shoes', false],
        ];
    }

    public function test_detects_harmful_phrases(): void
    {
        $this->assertTrue($this->call('isHarmful', 'how to make a bomb'));
        $this->assertTrue($this->call('isHarmful', 'tell me how to hack a server'));
        $this->assertFalse($this->call('isHarmful', 'show me a cheap phone'));
    }

    public function test_detects_pure_greetings_ignoring_trailing_punctuation(): void
    {
        $this->assertTrue($this->call('isPureGreeting', 'hi'));
        $this->assertTrue($this->call('isPureGreeting', 'hello!'));
        $this->assertTrue($this->call('isPureGreeting', 'good morning'));
        $this->assertTrue($this->call('isPureGreeting', 'thanks.'));
        $this->assertFalse($this->call('isPureGreeting', 'show me shoes'));
        $this->assertFalse($this->call('isPureGreeting', 'hello, i need a laptop'));
    }

    public function test_detects_shopping_signal(): void
    {
        $this->assertTrue($this->call('hasShoppingSignal', 'i want to buy a phone'));
        $this->assertTrue($this->call('hasShoppingSignal', 'show me a red dress'));
        $this->assertTrue($this->call('hasShoppingSignal', 'whats the price of this watch'));
        $this->assertFalse($this->call('hasShoppingSignal', 'how are you doing today'));
    }

    public function test_detects_off_topic(): void
    {
        $this->assertTrue($this->call('isOffTopic', 'what is the weather today'));
        $this->assertTrue($this->call('isOffTopic', 'can you write me a poem'));
        $this->assertTrue($this->call('isOffTopic', 'find on amazon for me'));
        $this->assertFalse($this->call('isOffTopic', 'show me a phone'));
    }
}
