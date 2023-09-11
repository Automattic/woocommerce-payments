<?php

declare(strict_types=1);

namespace WCPay\Vendor\League\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;
use InvalidArgumentException;

class NotFoundException extends InvalidArgumentException implements NotFoundExceptionInterface
{
}
