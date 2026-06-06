/**
 * Per-tab React error boundary for the WSN Hub.
 *
 * WHY THIS HELPER EXISTS:
 * Without an error boundary wrapping each tab, a single uncaught render-time
 * error blanks the entire WSN Hub — the user sees a blank screen with only a
 * console error to explain it. Realistic crash sources we have hit or expect:
 *
 *   1. `MediaUpload.open()` can throw synchronously if the WP media frame
 *      script failed to load (e.g., a plugin conflict or a CSP that blocks
 *      `media-views.js`). The throw bubbles out of an onClick handler and
 *      tears down the parent React tree.
 *
 *   2. A missing or mistyped import path produces a `ReferenceError` the first
 *      time the component renders. Without a boundary this kills the entire
 *      hub instead of just the offending tab.
 *
 *   3. Several WSN Hub tabs lean on `@wordpress/components` experimental APIs
 *      (`__experimentalHStack`, etc.). When a future WP version removes one,
 *      the component renders `undefined`, React throws "Element type is
 *      invalid", and again the whole hub disappears.
 *
 * Wrapping each tab in <TabErrorBoundary> isolates the blast radius: the
 * broken tab shows a recoverable fallback, the rest of the hub keeps working.
 *
 * NOTE: Error boundaries REQUIRE the class component API — `componentDidCatch`
 * and `getDerivedStateFromError` have no hook equivalents. We also import
 * React from 'react' directly because `@wordpress/element` re-exports the
 * functional surface only and does not expose `React.Component` reliably
 * across WP versions.
 */

/**
 * External dependencies
 */
import React from 'react';
import { Notice, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Class-based error boundary. Catches errors thrown anywhere in its child
 * tree during render, in lifecycle methods, and in constructors of the whole
 * tree below it, then renders a recoverable fallback Notice in their place.
 */
export class TabErrorBoundary extends React.Component {
	constructor( props ) {
		super( props );
		this.state = { error: null };
	}

	static getDerivedStateFromError( error ) {
		// React calls this during the render phase when a child throws —
		// returning { error } promotes us into the error state. This is the
		// canonical "set state on error" hook for class-based boundaries.
		return { error };
	}

	componentDidCatch() {
		// React calls this during the commit phase, AFTER
		// getDerivedStateFromError has already set our error state.
		// Reserved for side-effect logging / error reporting. We
		// intentionally do not re-throw — the boundary's job is to contain
		// the failure to this subtree. No setState here: calling setState
		// after getDerivedStateFromError would queue a redundant render.
	}

	render() {
		if ( this.state.error ) {
			return (
				<Notice status="error" isDismissible={ false }>
					{ __(
						'This panel failed to load. Refresh the page to try again.',
						'woocommerce-payments'
					) }{ ' ' }
					<Button
						variant="link"
						onClick={ () => window.location.reload() }
					>
						{ __( 'Refresh', 'woocommerce-payments' ) }
					</Button>
				</Notice>
			);
		}

		return this.props.children;
	}
}
