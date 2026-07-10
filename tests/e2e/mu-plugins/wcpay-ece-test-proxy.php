<?php
/**
 * Plugin Name: WCPay ECE Test Proxy
 * Description: Fakes a Stripe Express Checkout wallet sheet so E2E tests can drive Google Pay / Apple Pay / Amazon Pay, which are otherwise browser/OS UI Playwright can't reach. Test-env only, gated behind the WCPAY_ECE_TEST_PROXY constant.
 *
 * WordPress only auto-loads top-level mu-plugin files, so the loader sits at the
 * mu-plugins root and inlines the proxy JS from its sibling subdirectory.
 *
 * @package WooCommerce\Payments\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injects the ECE proxy script inline in <head>, before Stripe.js.
 *
 * @return void
 */
function wcpay_ece_test_proxy_inject() {
	if ( ! defined( 'WCPAY_ECE_TEST_PROXY' ) || ! WCPAY_ECE_TEST_PROXY ) {
		return;
	}

	// ECE never renders in wp-admin, and wrapping window.Stripe there just adds
	// blast radius.
	if ( is_admin() ) {
		return;
	}

	// Refuse to run on a live account. The constant is mode-blind, so a leaked
	// constant in a production wp-config is all that stands between this
	// fake-payment proxy and a live checkout. is_test()/is_dev() throw before
	// Mode initializes, so treat any failure as "not test" and bail.
	if ( ! class_exists( 'WC_Payments' ) || ! method_exists( 'WC_Payments', 'mode' ) ) {
		return;
	}
	try {
		$mode = WC_Payments::mode();
		if ( ! $mode->is_test() && ! $mode->is_dev() ) {
			return;
		}
	} catch ( \Exception $e ) {
		return;
	}

	// Injected on every front-end page, but the proxy only wraps window.Stripe -
	// it stays inert until something calls elements.create( 'expressCheckout' ),
	// so no ECE-render gate is needed.
	$proxy_js = @file_get_contents( __DIR__ . '/wcpay-ece-test-proxy/proxy.js' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $proxy_js ) {
		return;
	}

	// Inline at wp_head priority 0 so it runs before the enqueued js.stripe.com
	// script defines window.Stripe - the proxy has to install its window.Stripe
	// get/set trap before that assignment happens.
	// wp_print_inline_script_tag over a raw echo so CSP nonce filters apply.
	wp_print_inline_script_tag( $proxy_js );
}
add_action( 'wp_head', 'wcpay_ece_test_proxy_inject', 0 );
