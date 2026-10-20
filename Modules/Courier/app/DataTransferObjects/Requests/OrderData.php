<?php

namespace Modules\Courier\app\DataTransferObjects\Requests;

class OrderData
{
    public function __construct(
        public readonly string        $hostOrderReference,
        public readonly RecipientData $recipient,
        public readonly float         $codAmount,
        public readonly float         $weight,
        public readonly array         $items = [],
        public readonly ?string       $providerStoreId = null,
        public readonly ?string       $note = null,
        public readonly ?string       $itemDescription = null,
        public readonly ?int          $quantity = null,
        public readonly array         $meta = [],
    ) {}
}
