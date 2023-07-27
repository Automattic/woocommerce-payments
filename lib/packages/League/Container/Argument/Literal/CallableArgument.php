<?php

declare(strict_types=1);

namespace WooPayments\Vendor\League\Container\Argument\Literal;

use WooPayments\Vendor\League\Container\Argument\LiteralArgument;

class CallableArgument extends LiteralArgument
{
    public function __construct(callable $value)
    {
        parent::__construct($value, LiteralArgument::TYPE_CALLABLE);
    }
}
