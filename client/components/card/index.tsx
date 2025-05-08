/**
 * External dependencies
 */
import React, { ComponentProps, useContext } from 'react';
import { Card as BundledWordPressComponentsCard } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { WordPressComponentsContext } from 'wcpay/wordpress-components-context/context';

const WcpayCard = (
	props: ComponentProps< typeof BundledWordPressComponentsCard >
) => {
	const context = useContext( WordPressComponentsContext );

	if ( ! context ) {
		return <BundledWordPressComponentsCard { ...props } />;
	}

	const { Card } = context;

	return <Card { ...props } />;
};

export default WcpayCard;
