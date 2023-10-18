<?php
/**
 * Interface HasHooksInterface
 *
 * @package WooCommerce\Payments
 */

namespace WCPay\Internal\Hooks;

use WCPay\Internal\Proxy\HooksProxy;

/**
 * An interface for all classes, which have hooks.
 */
interface HasHooksInterface {
	/**
	 * Registers all necessary hooks.
	 *
	 * @param HooksProxy $hooks_proxy The proxy for registering WP hooks.
	 */
	public function init_hooks( HooksProxy $hooks_proxy );
}
