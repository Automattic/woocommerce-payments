<?php
/**
 * Stub the WC Core Fulfillment class so the provider's class_exists()
 * detection succeeds inside the PHPUnit process.
 *
 * @package WooCommerce\Payments\Tests
 */

namespace Automattic\WooCommerce\Admin\Features\Fulfillments;

if ( ! class_exists( __NAMESPACE__ . '\\Fulfillment' ) ) {
	// phpcs:ignore Squiz.Commenting.ClassComment.Missing
	class Fulfillment {}
}
