<?php
/**
 * Class ContainerException
 *
 * @package WooPayments
 */

namespace WooPayments\Internal\DependencyManagement;

use Psr\Container\NotFoundExceptionInterface;
use InvalidArgumentException;

/**
 * A class, representing container-related exceptions.
 */
class ContainerException extends InvalidArgumentException implements NotFoundExceptionInterface {
}
