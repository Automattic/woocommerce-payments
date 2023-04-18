/**
 * External dependencies
 */
import React from 'react';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/checkout';

interface apiResponse {
	url: Location;
}

interface wcpayApi {
	initWooPay: () => Promise< apiResponse >;
}

interface woopayButtonProps {
	isStatic?: boolean;
	api: wcpayApi;
}

const WooPay = ( { isStatic, api }: woopayButtonProps ) => {
	const [ isLoading, setIsLoading ] = useState( false );

	const buttonContent = (
		<span>
			{ interpolateComponents( {
				mixedString: __(
					'Buy now with{{br/}}woopay',
					'woocommerce-payments'
				),
				components: { br: <br /> },
			} ) }
		</span>
	);

	if ( isStatic ) {
		return buttonContent;
	}

	const onClick = () => {
		setIsLoading( true );
		api.initWooPay().then( ( response ) => {
			window.location = response.url;
			setIsLoading( false );
		} );
	};

	return (
		<>
			<button
				onClick={ onClick }
				type="button"
				style={ {
					backgroundColor: '#874FB8',
					color: '#fff',
					width: '100%',
				} }
				disabled={ isLoading }
			>
				{ buttonContent }
			</button>
		</>
	);
};

interface expressPaymentMethod {
	name: string;
	content: JSX.Element;
	edit: JSX.Element;
	canMakePayment: boolean | Promise< boolean > | ( () => boolean );
	paymentMethodId: string;
	supports: {
		features: string[];
	};
}

export const woopayPaymentMethod = (
	api: wcpayApi
): expressPaymentMethod => ( {
	name: 'woopay',
	content: <WooPay api={ api } />,
	edit: <WooPay isStatic={ true } api={ api } />,
	canMakePayment: () => true,
	paymentMethodId: 'woocommerce_payments',
	supports: {
		features: getConfig( 'features' ),
	},
} );
