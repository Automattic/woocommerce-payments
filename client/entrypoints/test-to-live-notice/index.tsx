/**
 * External dependencies
 */
import React from 'react';
import { createRoot, Root } from 'react-dom/client';

/**
 * Internal dependencies
 */
import TestToLiveNotice from './components/notice';

const containerId = 'wcpay-test-to-live-notice';
let mountedContainer: HTMLElement | null = null;
let root: Root | null = null;

/**
 * Try to mount the notice in the DOM. Idempotent: if the previously-mounted
 * container is still attached, this is a no-op; if it's been detached (e.g.
 * the WC Admin SPA replaced .woocommerce-layout__main on navigation), the
 * old root is unmounted and a fresh container is inserted and re-rendered.
 */
const tryMount = () => {
	if ( mountedContainer && document.body.contains( mountedContainer ) ) {
		return;
	}

	// Stale root from a now-detached container — clean up before recreating.
	if ( root ) {
		try {
			root.unmount();
		} catch {
			// Container already gone; ignore.
		}
		root = null;
		mountedContainer = null;
	}

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

			// Classic pages with tabs (e.g. WC Status) render .nav-tab-wrapper
			// after the page title; insert after the tabs. Otherwise fall back to .wp-header-end, which
			// WordPress marks as the notice insertion point.
			const navTab = target.querySelector( '.nav-tab-wrapper' );
			const headerEnd = target.querySelector( '.wp-header-end' );
			const anchor = navTab ?? headerEnd;
			if ( anchor ) {
				anchor.after( container );
			} else {
				target.prepend( container );
			}
		}
	}

	root = createRoot( container );
	root.render( <TestToLiveNotice /> );
	mountedContainer = container;
};

// Keep the observer running for the lifetime of the page: WC Admin's SPA
// can replace .woocommerce-layout__main on route changes, which detaches
// our container — we re-mount on the next mutation. Observing
// #wpbody-content (the stable WP admin content wrapper) instead of body
// scopes mutations to the area we actually care about; fall back to body
// if it isn't available yet.
const observer = new MutationObserver( tryMount );
const observeTarget =
	document.getElementById( 'wpbody-content' ) ?? document.body;
observer.observe( observeTarget, { childList: true, subtree: true } );
tryMount();
