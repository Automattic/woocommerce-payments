/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';
import React from 'react';

/**
 * Internal dependencies
 */
import enqueueFraudScripts from 'fraud-scripts';
import ErrorBoundary from '../error-boundary';
import './style.scss';

interface PageProps {
	id?: string;
	isNarrow?: boolean;
	maxWidth?: string | number;
	className?: string;
}

// The React.FunctionComponent is helpful here to make the type declaration of the props a bit
// more concise; we get the `children` prop for free.
const Page: React.FC< React.PropsWithChildren< PageProps > > = ( {
	children,
	id = '',
	maxWidth,
	isNarrow,
	className = '',
} ) => {
	const customStyle = maxWidth ? { maxWidth } : undefined;
	const classNames = [ className, 'woocommerce-payments-page' ];
	if ( isNarrow ) {
		classNames.push( 'is-narrow' );
	}

	useEffect( () => {
		const fraudScriptsConfig =
			'undefined' !== typeof wcpaySettings
				? wcpaySettings.fraudServices
				: [];
		enqueueFraudScripts( fraudScriptsConfig );
	}, [] );

	return (
		<div
			id={ id }
			className={ classNames.join( ' ' ) }
			style={ customStyle }
		>
			<ErrorBoundary>{ children }</ErrorBoundary>
		</div>
	);
};

export default Page;
