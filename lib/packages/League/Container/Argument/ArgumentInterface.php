<?php

declare(strict_types=1);

namespace WooPayments\Vendor\League\Container\Argument;

interface ArgumentInterface
{
    /**
     * @return mixed
     */
    public function getValue();
}
