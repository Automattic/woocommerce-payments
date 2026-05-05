<?php
/**
 * Stub the WC Shipment Tracking sentinel class so the provider's
 * class_exists() detection succeeds inside the PHPUnit process.
 *
 * @package WooCommerce\Payments\Tests
 */

if ( ! class_exists( 'WC_Shipment_Tracking_Actions' ) ) {
	// phpcs:ignore Squiz.Commenting.ClassComment.Missing
	class WC_Shipment_Tracking_Actions {}
}
