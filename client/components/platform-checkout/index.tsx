/**
 * External dependencies
 */
import React, { useCallback, useEffect } from 'react';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import { getConfig } from 'utils/checkout';

interface apiResponse {
	url: Location;
}

interface wcpayApi {
	initPlatformCheckout: () => Promise< apiResponse >;
}

interface platformCheckoutButtonProps {
	isStatic?: boolean;
	api: wcpayApi;
}

const PlatformCheckout = ( { isStatic, api }: platformCheckoutButtonProps ) => {
	const [ isLoading, setIsLoading ] = useState( false );

	const listener = useCallback(
		( e ) => {
			if ( getConfig( 'platformCheckoutHost' ) !== e.origin ) {
				return;
			}

			if ( 'redirect_to_platform_checkout' === e.data.action ) {
				api.initPlatformCheckout().then( ( response ) => {
					window.location = response.url;
				} );
			}
		},
		[ api ]
	);

	useEffect( () => {
		window.addEventListener( 'message', listener );

		return () => {
			window.removeEventListener( 'message', listener );
		};
	}, [ listener ] );

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
			<iframe
				title={ __(
					'Platform checkout SMS code verification',
					'woocommerce-payments'
				) }
				className="platform-checkout-sms-otp-iframe"
				src={ `${ getConfig( 'platformCheckoutHost' ) }/sms-otp/` }
			/>
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
