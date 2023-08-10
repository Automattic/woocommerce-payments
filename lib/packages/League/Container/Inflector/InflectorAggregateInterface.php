<?php

declare(strict_types=1);

namespace WCPay\Vendor\League\Container\Inflector;

use IteratorAggregate;
use WCPay\Vendor\League\Container\ContainerAwareInterface;

interface InflectorAggregateInterface extends ContainerAwareInterface, IteratorAggregate
{
    public function add(string $type, callable $callback = null): Inflector;
    public function inflect(object $object);
}
