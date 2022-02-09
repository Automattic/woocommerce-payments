/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';
import { getConfig } from 'wcpay/utils/checkout';

export const handlePlatformCheckoutEmailInput = ( field, api ) => {
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

	// Track the current state of the header. This default
	// value should match the default state on the platform.
	let iframeHeaderValue = true;
	const getWindowSize = () => {
		if (
			( 768 <= window.innerWidth && iframeHeaderValue ) ||
			( 768 > window.innerWidth && ! iframeHeaderValue )
		) {
			iframeHeaderValue = ! iframeHeaderValue;
			iframe.contentWindow.postMessage(
				{
					action: 'setHeader',
					value: iframeHeaderValue,
				},
				getConfig( 'platformCheckoutHost' )
			);
		}
		if ( iframeHeaderValue ) {
			document.body.style.overflow = 'hidden';
			iframe.style.height = '';
			iframe.style.top = '';
		} else {
			document.body.style.overflow = '';
		}
	};
	// Do this on debounced resize.
	const debouncedGetWindowSize = debounce( getWindowSize, 100 );

	iframe.addEventListener( 'load', () => {
		// Set the initial value.
		getWindowSize();
		window.addEventListener( 'resize', debouncedGetWindowSize );
		iframe.classList.add( 'open' );
	} );
	iframeWrapper.insertBefore( iframe, null );

	const closeIframe = () => {
		debouncedGetWindowSize.cancel();
		window.removeEventListener( 'resize', debouncedGetWindowSize );
		iframeWrapper.remove();
		iframe.classList.remove( 'open' );
		platformCheckoutEmailInput.focus();
		document.body.style.overflow = '';
	};
	iframeWrapper.addEventListener( 'click', closeIframe );

	const openIframe = ( email ) => {
		iframe.src =
			getConfig( 'platformCheckoutHost' ) + '/sms-otp/?email=' + email;
		parentDiv.insertBefore( iframeWrapper, null );
		iframe.focus();
	};

	document.addEventListener( 'keyup', ( event ) => {
		if ( 'Escape' === event.key && closeIframe() ) {
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

	window.addEventListener( 'message', ( e ) => {
		if ( ! getConfig( 'platformCheckoutHost' ).startsWith( e.origin ) ) {
			return;
		}

		switch ( e.data.action ) {
			case 'redirect_to_platform_checkout':
				api.initPlatformCheckout().then( ( response ) => {
					window.location = response.url;
				} );
				break;
			case 'close_modal':
				closeIframe();
				break;
			case 'iframe_height':
				if ( 768 < window.innerWidth ) {
					iframe.style.height = e.data.height + 'px';
					iframe.style.top = Math.floor( e.data.height / -2 ) + 'px';
				} else {
					iframe.style.height = '';
					iframe.style.top = '';
				}
				break;
			default:
			// do nothing, only respond to expected actions.
		}
	} );
};
