/**
 * External dependencies
 */
import React, { ComponentProps, useContext } from 'react';
import { Card as WordPressComponentsCard } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { WordPressComponentsContext } from 'wcpay/wordpress-components-context/context';

const WcpayCard = (
	props: ComponentProps< typeof WordPressComponentsCard >
) => {
	const { Card } = useContext( WordPressComponentsContext );

	return <Card { ...props } />;
};

export default WcpayCard;
