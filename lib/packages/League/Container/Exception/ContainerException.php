<?php

declare(strict_types=1);

namespace WCPay\Vendor\League\Container\Exception;

use WCPay\Vendor\Psr\Container\ContainerExceptionInterface;
use RuntimeException;

class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
