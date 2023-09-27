<?php

declare(strict_types=1);

namespace WCPay\Vendor\League\Container\Argument\Literal;

use WCPay\Vendor\League\Container\Argument\LiteralArgument;

class FloatArgument extends LiteralArgument
{
    public function __construct(float $value)
    {
        parent::__construct($value, LiteralArgument::TYPE_FLOAT);
    }
}
