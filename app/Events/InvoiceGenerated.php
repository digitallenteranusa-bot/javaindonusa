<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoiceGenerated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $month,
        public int $year,
        public int $generated,
        public int $skipped,
        public array $errors,
    ) {}
}
