/**
 * External dependencies
 */
import {__} from "@wordpress/i18n";
import { getConfig } from "wcpay/utils/checkout";

export const handlePlatformCheckoutEmailInput = ( field ) => {
	let timer;
	const waitTime = 500;
	const platformCheckoutEmailInput = document.querySelector( field );
	const spinner = document.createElement( 'div' );
	const parentDiv = platformCheckoutEmailInput.parentNode;
	spinner.classList.add( 'wc-block-components-spinner' );

	const platformCheckoutLocateUser = ( email ) => {
		parentDiv.insertBefore( spinner, platformCheckoutEmailInput );

		fetch(
			getConfig( 'platformCheckoutHost' ) + '/wp-json/platform-checkout/v1/user/exists?email='+email)
			.then(response => response.json())
			.then( ( data ) => {
				if ( data['user-exists'] ) {
					const iframeWrapper = document.createElement( 'div' );
					iframeWrapper.classList.add( 'platform-checkout-sms-otp-iframe-wrapper' );
					const iframe = document.createElement( 'iframe' );
					iframe.title = __(
						'Platform checkout SMS code verification',
						'woocommerce-payments'
					);
					iframe.classList.add( 'platform-checkout-sms-otp-iframe' );
					iframe.src = getConfig( 'platformCheckoutHost' ) + '/sms-otp/?email='+email;
					iframeWrapper.insertBefore( iframe, null );
					parentDiv.insertBefore( iframeWrapper, null );
 				}
			} )
			.finally( () => {
				spinner.remove();
			} );
	};

	const validateEmail = ( value ) => {
		const input = document.createElement( 'input' );
		input.type = 'email';
		input.required = true;
		input.value = value;

		return input.checkValidity() || false;
	};

	platformCheckoutEmailInput.addEventListener( 'input', ( e ) => {
		const input = e.currentTarget.value;

		clearTimeout( timer );
		spinner.remove();

		timer = setTimeout( () => {
			if ( validateEmail( input ) ) {
				platformCheckoutLocateUser( input );
			}
		}, waitTime );
	} );
};
