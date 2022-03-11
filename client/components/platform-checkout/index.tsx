/**
 * External dependencies
 */
import React from 'react';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/checkout';
import './style.scss';

interface apiResponse {
	url: Location;
	result?: string;
}

interface wcpayApi {
	initPlatformCheckout: () => Promise< apiResponse >;
}

interface platformCheckoutButtonProps {
	isStatic?: boolean;
	api: wcpayApi;
}

const SuccessNotice = () => {
	return (
		<Notice
			status="success"
			isDismissible={ false }
			className="init-platform-checkout-notice"
		>
			{ __(
				'Redirecting to platform checkout. Please wait…',
				'woocommerce-payments'
			) }
		</Notice>
	);
};

const ErrorNotice = () => {
	return (
		<Notice
			status="error"
			isDismissible={ false }
			className="init-platform-checkout-notice"
		>
			{ __(
				'An error occurred while redirecting to platform checkout. Please try again.',
				'woocommerce-payments'
			) }
		</Notice>
	);
};

const PlatformCheckout = ( { isStatic, api }: platformCheckoutButtonProps ) => {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ responseStatus, setResponseStatus ] = useState( '' );

	const buttonContent = (
		<span>
			{ interpolateComponents( {
				mixedString: __(
					'Buy now with{{br/}}platform checkout',
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
		api.initPlatformCheckout().then( ( response ) => {
			if ( response?.result === 'success' ) {
				window.location = response.url;
				setResponseStatus( 'success' );
			} else {
				setResponseStatus( 'error' );
			}
			setIsLoading( false );
		} );
	};

	return (
		<>
			{ responseStatus === 'success' && <SuccessNotice /> }
			{ responseStatus === 'error' && <ErrorNotice /> }
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

export const platformCheckoutPaymentMethod = (
	api: wcpayApi
): expressPaymentMethod => ( {
	name: 'platform_checkout',
	content: <PlatformCheckout api={ api } />,
	edit: <PlatformCheckout isStatic={ true } api={ api } />,
	canMakePayment: () => true,
	paymentMethodId: 'woocommerce_payments',
	supports: {
		features: getConfig( 'features' ),
	},
} );
