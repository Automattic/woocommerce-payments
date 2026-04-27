/**
 * External dependencies
 */
import React from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import TestToLiveNotice from 'components/test-to-live-notice';

const containerId = 'wcpay-test-to-live-notice';
let observer: MutationObserver | null = null;

/**
 * Try to mount the notice in the DOM.
 */
const tryMount = () => {
	let container = document.getElementById(
		containerId
	) as HTMLElement | null;

	// If the container is not found, create it.
	// The bundle is only enqueued on WC screens on the backend, so falling back to inserting
	// into the main content area is safe as we are always inside WooCommerce when this script runs.
	if ( ! container ) {
		container = document.createElement( 'div' );
		container.id = containerId;

		// Settings pages: insert after section nav or tab nav so the notice
		// lands between navigation and the settings fields.
		const sectionNav = document.querySelector( '#mainform .subsubsub' );
		const tabNav = document.querySelector(
			'#mainform .woo-nav-tab-wrapper'
		);
		const settingsAnchor = sectionNav ?? tabNav;

		if ( settingsAnchor ) {
			settingsAnchor.after( container );
		} else {
			// WC Admin SPA pages render .woocommerce-layout__main; classic WC
			// pages (Products, Orders list …) use #wpbody-content .wrap.
			const target =
				document.querySelector( '.woocommerce-layout__main' ) ??
				document.querySelector( '#wpbody-content .wrap' );

			if ( ! target ) {
				// Target not in the DOM yet — keep observing.
				return;
			}

			// On classic pages WordPress marks the notice insertion point with
			// .wp-header-end (after the page title and action buttons).
			const headerEnd = target.querySelector( '.wp-header-end' );
			if ( headerEnd ) {
				headerEnd.after( container );
			} else {
				target.prepend( container );
			}
		}
	}

	createRoot( container ).render( <TestToLiveNotice /> );
	observer?.disconnect();
};

observer = new MutationObserver( tryMount );
observer.observe( document.body, { childList: true, subtree: true } );
tryMount();
