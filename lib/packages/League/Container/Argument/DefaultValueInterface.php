<?php

declare(strict_types=1);

namespace WCPay\Vendor\League\Container\Argument;

interface DefaultValueInterface extends ArgumentInterface
{
    /**
     * @return mixed
     */
    public function getDefaultValue();
}
