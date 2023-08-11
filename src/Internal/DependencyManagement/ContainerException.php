<?php
/**
 * Class ContainerException
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\DependencyManagement;

use Psr\Container\NotFoundExceptionInterface;
use InvalidArgumentException;

/**
 * A class, representing container-related exceptions.
 */
class ContainerException extends InvalidArgumentException implements NotFoundExceptionInterface {
}
