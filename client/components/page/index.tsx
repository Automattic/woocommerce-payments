/**
 * External dependencies
 */
import { useEffect } from '@wordpress/element';
import React from 'react';

/**
 * Internal dependencies
 */
import enqueueFraudScripts from 'fraud-scripts';
import './style.scss';
// eslint-disable-next-line no-duplicate-imports
import { ReactElement, ReactNode } from 'react';

declare const wcpaySettings: any;

interface PageProps {
	children: ReactNode;
	isNarrow?: boolean;
	maxWidth?: string;
	className?: string;
}

const Page: React.FunctionComponent< PageProps > = ( {
	children,
	maxWidth,
	isNarrow,
	className = '',
} ): ReactElement => {
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
