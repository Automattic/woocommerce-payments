/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';
import { getConfig } from 'wcpay/utils/checkout';

export const handlePlatformCheckoutEmailInput = ( field ) => {
	let timer;
	const waitTime = 500;
	const platformCheckoutEmailInput = document.querySelector( field );
	const spinner = document.createElement( 'div' );
	const parentDiv = platformCheckoutEmailInput.parentNode;
	spinner.classList.add( 'wc-block-components-spinner' );

	const iframeWrapper = document.createElement( 'div' );
	iframeWrapper.setAttribute( 'role', 'dialog' );
	iframeWrapper.setAttribute( 'aria-modal', 'true' );
	iframeWrapper.classList.add( 'platform-checkout-sms-otp-iframe-wrapper' );
	const iframe = document.createElement( 'iframe' );
	iframe.title = __(
		'Platform checkout SMS code verification',
		'woocommerce-payments'
	);
	iframe.classList.add( 'platform-checkout-sms-otp-iframe' );
	iframe.addEventListener( 'load', () => {
		iframe.classList.add( 'open' );
	} );
	iframeWrapper.insertBefore( iframe, null );

	const closeIframe = () => {
		iframeWrapper.remove();
		iframe.classList.remove( 'open' );
		platformCheckoutEmailInput.focus();
	};
	iframeWrapper.addEventListener( 'click', closeIframe );

	const openIframe = ( email ) => {
		iframe.src =
			getConfig( 'platformCheckoutHost' ) + '/sms-otp/?email=' + email;
		parentDiv.insertBefore( iframeWrapper, null );
		iframe.focus();
	};

	document.addEventListener( 'keyup', ( event ) => {
		const key = event.which || event.keyCode;

		if ( 27 === key && closeIframe() ) {
			event.stopPropagation();
		}
	} );

	const platformCheckoutLocateUser = ( email ) => {
		parentDiv.insertBefore( spinner, platformCheckoutEmailInput );

		fetch(
			getConfig( 'platformCheckoutHost' ) +
				'/wp-json/platform-checkout/v1/user/exists?email=' +
				email
		)
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data[ 'user-exists' ] ) {
					openIframe( email );
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
		const email = e.currentTarget.value;

		clearTimeout( timer );
		spinner.remove();

		timer = setTimeout( () => {
			if ( validateEmail( email ) ) {
				platformCheckoutLocateUser( email );
			}
		}, waitTime );
	} );

	// Track the current state of the header. This default
	// value should match the default state on the platform.
	let iframeHeaderValue = true;
	const getWindowSize = () => {
		if ( 768 >= window.innerWidth && ! iframeHeaderValue ) {
			iframeHeaderValue = ! iframeHeaderValue;
			iframe.contentWindow.postMessage(
				{
					action: 'setHeader',
					value: true,
				},
				getConfig( 'platformCheckoutHost' )
			);
		}

		if ( 768 < window.innerWidth && iframeHeaderValue ) {
			iframeHeaderValue = ! iframeHeaderValue;
			iframe.contentWindow.postMessage(
				{
					action: 'setHeader',
					value: false,
				},
				getConfig( 'platformCheckoutHost' )
			);
		}
	};
	// Set the initial value.
	getWindowSize();

	// Do this on debounced resize.
	const debouncedGetWindowSize = debounce( getWindowSize, 100 );
	window.addEventListener( 'resize', debouncedGetWindowSize );
};
