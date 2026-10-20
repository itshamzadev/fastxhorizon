<?php

namespace Modules\AI\app\Services\ShoppingAssistant;

class QueryGuardService
{
    public const OFF_TOPIC_FALLBACK_EN =
        'I can only help with shopping here — finding products, adding products to your cart, '
        . 'and store details like shipping and delivery. What are you shopping for today?';

    public static function offTopicFallback(): string
    {
        return translate('I_can_only_help_with_shopping_here_finding_products_adding_products_to_your_cart_and_store_details_like_shipping_and_delivery_What_are_you_shopping_for_today');
    }

    private const SHOPPING_SIGNALS = [
        'buy', 'purchase', 'order', 'shop', 'find', 'show', 'get', 'need',
        'want', 'looking for', 'search', 'recommend', 'suggest',
        'price', 'cost', 'cheap', 'expensive', 'discount', 'sale', 'deal',
        'offer', 'promo', 'coupon', 'cart', 'checkout', 'payment',
        'shipping', 'delivery', 'track', 'return', 'refund', 'exchange',
        'size', 'color', 'colour', 'style', 'brand', 'model', 'design',
        'material', 'quality', 'rating', 'review', 'stock', 'available',
        'phone', 'mobile', 'laptop', 'computer', 'tablet', 'watch', 'tv',
        'shirt', 't-shirt', 'dress', 'pants', 'shoes', 'bag', 'jacket',
        'suit', 'clothing', 'clothes', 'outfit', 'fashion', 'wear',
        'furniture', 'chair', 'table', 'sofa', 'bed',
        'kitchen', 'appliance', 'toy', 'book', 'game', 'gadget',
        'jewelry', 'ring', 'necklace', 'accessory', 'beauty', 'skincare',
        'perfume', 'cosmetic', 'makeup', 'headphone', 'speaker', 'camera',
        'similar', 'alternative', 'like this', 'product', 'item',
    ];

    private const PURE_GREETINGS = [
        'hi', 'hello', 'hey', 'hiya', 'howdy', 'greetings', 'sup',
        'good morning', 'good afternoon', 'good evening', 'good night',
        'bye', 'goodbye', 'see you', 'see ya', 'cya', 'take care',
        'thanks', 'thank you', 'thx', 'ty', 'appreciate it', 'cheers',
        'ok', 'okay', 'k', 'cool', 'great', 'nice', 'awesome', 'perfect',
        'yes', 'no', 'nope', 'yep', 'yup', 'sure', 'alright',
        'lol', 'lmao', 'haha', 'hehe',
        'who are you', 'what are you', 'are you a bot', 'are you ai',
        'are you human', 'are you real',
    ];

    private const OFF_TOPIC_PHRASES = [
        'what is the weather', 'weather today', 'will it rain',
        'weather forecast', 'temperature outside', 'what is the temperature',
        'how hot is', 'how cold is',
        'who is the president', 'who is the prime minister',
        'current news', 'latest news', 'political party', 'should i vote',
        'who won the election',
        'solve this equation', 'do my homework', 'write an essay',
        'math problem', 'history homework', 'science assignment',
        'explain quantum', 'what is photosynthesis', 'help me study',
        'what is the formula', 'write my thesis',
        'what is the capital of', 'capital city of', 'population of',
        'who invented', 'when was invented', 'what year did',
        'who wrote', 'who directed', 'what happened in',
        'distance between', 'how many countries',
        'how to cook', 'recipe for', 'how do i bake', 'what ingredients',
        'cooking instructions', 'how to prepare food', 'how to make pasta',
        'how to make cake', 'how to make soup',
        'i have a fever', 'my symptoms are', 'what medicine should',
        'medical advice', 'doctor advice', 'health problem', 'is this normal',
        'should i see a doctor', 'what disease', 'how to treat illness',
        'write me a poem', 'write a story', 'write me a song',
        'compose a poem', 'tell me a joke', 'tell me a story',
        'give me a riddle', 'write lyrics',
        'write a program', 'write code for', 'debug my code',
        'fix my computer', 'how to install software', 'programming language',
        'sql query', 'javascript function', 'python script',
        'directions to', 'how far is', 'how do i get to',
        'flights to', 'hotel near', 'travel guide for',
        'best country to visit', 'visa requirements',
        'relationship advice', 'dating tips', 'how do i ask someone out',
        'my girlfriend', 'my boyfriend', 'my husband', 'my wife',
        'how to make friends', 'social anxiety',
        'on amazon', 'on alibaba', 'on aliexpress', 'on ebay',
        'on walmart', 'buy from amazon', 'find on amazon',
        'search on google', 'check alibaba',
    ];

    private const HARMFUL_PHRASES = [
        'how to make a bomb', 'how to make explosives',
        'how to kill', 'how to hurt', 'how to harm',
        'how to hack', 'how to steal', 'credit card fraud',
        'child porn', 'child abuse material',
        'suicide method', 'how to commit suicide',
    ];

    public function check(string $message, array $history = []): ?array
    {
        $normalized = strtolower(trim($message));

        if ($this->isGibberish($normalized)) {
            return [
                'reply'  => translate("I_did_not_quite_catch_that_Could_you_describe_what_product_you_are_looking_for"),
                'intent' => 'blocked',
            ];
        }

        if ($this->isHarmful($normalized)) {
            return [
                'reply'  => translate("I_am_unable_to_assist_with_that_request"),
                'intent' => 'blocked',
            ];
        }

        if (empty($history) && $this->isPureGreeting($normalized)) {
            return [
                'reply'  => translate("Hi_there_I_am_your_shopping_assistant_Tell_me_what_you_are_looking_for_and_I_will_find_the_best_options_for_you"),
                'intent' => 'smalltalk',
            ];
        }

        if (!$this->hasShoppingSignal($normalized) && $this->isOffTopic($normalized)) {
            return [
                'reply'  => self::offTopicFallback(),
                'intent' => 'off_topic',
            ];
        }

        return null;
    }

    private function isGibberish(string $text): bool
    {
        if (mb_strlen($text) < 2) {
            return true;
        }

        if (preg_match('/^(.)\1{4,}$/', $text)) {
            return true;
        }

        if (!preg_match('/[\p{L}\p{N}]/u', $text)) {
            return true;
        }

        return false;
    }

    private function isHarmful(string $text): bool
    {
        foreach (self::HARMFUL_PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }
        return false;
    }

    private function isPureGreeting(string $text): bool
    {
        $stripped = rtrim($text, " \t!.,?:;…");
        return in_array($stripped, self::PURE_GREETINGS, strict: true);
    }

    private function hasShoppingSignal(string $text): bool
    {
        foreach (self::SHOPPING_SIGNALS as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }
        return false;
    }

    private function isOffTopic(string $text): bool
    {
        foreach (self::OFF_TOPIC_PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }
        return false;
    }
}
