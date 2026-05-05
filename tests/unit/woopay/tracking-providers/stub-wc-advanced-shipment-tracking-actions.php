<?php
/**
 * Stub the AST sentinel class so the provider's class_exists() detection
 * succeeds inside the PHPUnit process. Both WC Shipment Tracking and AST
 * share the same `_wc_shipment_tracking_items` meta key.
 *
 * @package WooCommerce\Payments\Tests
 */

if ( ! class_exists( 'WC_Advanced_Shipment_Tracking_Actions' ) ) {
	// phpcs:ignore Squiz.Commenting.ClassComment.Missing
	class WC_Advanced_Shipment_Tracking_Actions {}
}
