/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';
import * as React from 'react';

/**
 * Internal dependencies
 */
import enqueueFraudScripts from 'fraud-scripts';
import './style.scss';

declare const wcpaySettings: any;

interface PageProps {
	isNarrow?: boolean;
	maxWidth?: string;
	className?: string;
}

// The React.FunctionComponent is helpful here to make the type declaration of the props a bit
// more concise; we get the `children` prop for free.
const Page: React.FC< PageProps > = ( {
	children,
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
		const fraudScriptsConfig: any[] =
			'undefined' !== typeof wcpaySettings
				? wcpaySettings.fraudServices
				: [];
		enqueueFraudScripts( fraudScriptsConfig );
	}, [] );

	return (
		<div className={ classNames.join( ' ' ) } style={ customStyle }>
			{ children }
		</div>
	);
};

export default Page;
