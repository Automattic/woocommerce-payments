/**
 * Polyfills for React 18 hooks and behavior to support React 17
 * This is needed because @wordpress/components uses React 18 features
 */
const { useSyncExternalStore } = require( 'use-sync-external-store/shim' );

const React = require( 'react' );

// Add useSyncExternalStore to React if it doesn't exist
if ( ! React.useSyncExternalStore ) {
	React.useSyncExternalStore = useSyncExternalStore;
}
