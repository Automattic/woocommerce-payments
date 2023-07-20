/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { getConfig } from 'utils/checkout';
import { getTargetElement, validateEmail } from '../utils';
import wcpayTracks from 'tracks';

export const expressCheckoutIframe = async ( api, context, emailSelector ) => {
	const woopayEmailInput = await getTargetElement( emailSelector );
	let userEmail = '';

	const parentDiv = document.body;

	// Make the otp iframe wrapper.
	const iframeWrapper = document.createElement( 'div' );
	iframeWrapper.setAttribute( 'role', 'dialog' );
	iframeWrapper.setAttribute( 'aria-modal', 'true' );
	iframeWrapper.classList.add( 'woopay-otp-iframe-wrapper' );

	// Make the otp iframe.
	const iframe = document.createElement( 'iframe' );
	iframe.title = __( 'WooPay SMS code verification', 'woocommerce-payments' );
	iframe.classList.add( 'woopay-otp-iframe' );

	// To prevent twentytwenty.intrinsicRatioVideos from trying to resize the iframe.
	iframe.classList.add( 'intrinsic-ignore' );

	// Maybe we could make this a configurable option defined in PHP so it could be filtered by merchants.
	const fullScreenModalBreakpoint = 768;

	// Track the current state of the header. This default
	// value should match the default state on the platform.
	let iframeHeaderValue = true;
	const getWindowSize = () => {
		if (
			( fullScreenModalBreakpoint <= window.innerWidth &&
				iframeHeaderValue ) ||
			( fullScreenModalBreakpoint > window.innerWidth &&
				! iframeHeaderValue )
		) {
			iframeHeaderValue = ! iframeHeaderValue;
			iframe.contentWindow.postMessage(
				{
					action: 'setHeader',
					value: iframeHeaderValue,
				},
				getConfig( 'woopayHost' )
			);
		}

		// Prevent scrolling when the iframe is open.
		document.body.style.overflow = 'hidden';
	};

	/**
	 * Handles setting the iframe position based on the window size.
	 * It tries to be positioned at the center of the screen unless
	 * window is smaller than breakpoint which makes it full window size.
	 */
	const setPopoverPosition = () => {
		// If for some reason the iframe is not loaded, just return.
		if ( ! iframe ) {
			return;
		}

		// If the window width is less than the breakpoint, set iframe to full window
		if ( fullScreenModalBreakpoint >= window.innerWidth ) {
			iframe.style.left = '0';
			iframe.style.right = '';
			iframe.style.top = '0';
			return;
		}

		// Get references to the iframe bounding rects.
		const iframeRect = iframe.getBoundingClientRect();

		// Set the iframe top and left to be centered.
		iframe.style.top =
			Math.floor( window.innerHeight / 2 - iframeRect.height / 2 ) + 'px';
		iframe.style.left =
			Math.floor( window.innerWidth / 2 - iframeRect.width / 2 ) + 'px';
	};

	iframe.addEventListener( 'load', () => {
		// Set the initial value.
		iframeHeaderValue = true;

		// TODO send session info here
		iframe.contentWindow.postMessage(
			{
				action: 'setSessionData',
				value: window.sessionDataWooPay,
			},
			getConfig( 'woopayHost' )
		);

		getWindowSize();
		window.addEventListener( 'resize', getWindowSize );

		setPopoverPosition();
		window.addEventListener( 'resize', setPopoverPosition );

		iframe.classList.add( 'open' );
		wcpayTracks.recordUserEvent( wcpayTracks.events.WOOPAY_OTP_START );
	} );

	// Add the iframe to the wrapper.
	iframeWrapper.insertBefore( iframe, null );

	const showErrorMessage = () => {
		// Set the notice text.
		const errorMessage = __(
			'WooPay is unavailable at this time. Sorry for the inconvenience.',
			'woocommerce-payments'
		);

		// Handle Blocks Cart and Checkout notices.
		if ( wcSettings.wcBlocksConfig && 'product' !== context ) {
			// This handles adding the error notice to the cart page.
			wp.data
				.dispatch( 'core/notices' )
				?.createNotice( 'error', errorMessage, {
					context: `wc/${ context }`,
				} );
		} else {
			// We're either on a shortcode cart/checkout or single product page.
			fetch( getConfig( 'ajaxUrl' ), {
				method: 'POST',
				body: new URLSearchParams( {
					action: 'woopay_express_checkout_button_show_error_notice',
					_ajax_nonce: getConfig( 'woopayButtonNonce' ),
					context,
					message: errorMessage,
				} ),
			} )
				.then( ( response ) => response.json() )
				.then( ( response ) => {
					if ( response.success ) {
						// We need to manually add the notice to the page.
						const noticesWrapper = document.querySelector(
							'.woocommerce-notices-wrapper'
						);
						const wrapper = document.createElement( 'div' );
						wrapper.innerHTML = response.data.notice;
						noticesWrapper.insertBefore( wrapper, null );

						noticesWrapper.scrollIntoView( {
							behavior: 'smooth',
							block: 'center',
						} );
					}
				} );
		}
	};

	const closeIframe = () => {
		window.removeEventListener( 'resize', getWindowSize );
		window.removeEventListener( 'resize', setPopoverPosition );

		iframeWrapper.remove();
		iframe.classList.remove( 'open' );

		document.body.style.overflow = '';
	};

	iframeWrapper.addEventListener( 'click', closeIframe );

	const openIframe = ( email = '' ) => {
		// check and return if another otp iframe is already open.
		if ( document.querySelector( '.woopay-otp-iframe' ) ) {
			return;
		}

		const viewportWidth = window.document.documentElement.clientWidth;
		const viewportHeight = window.document.documentElement.clientHeight;

		const urlParams = new URLSearchParams();
		urlParams.append( 'testMode', getConfig( 'testMode' ) );
		urlParams.append(
			'needsHeader',
			fullScreenModalBreakpoint > window.innerWidth
		);
		urlParams.append( 'wcpayVersion', getConfig( 'wcpayVersionNumber' ) );

		if ( email && validateEmail( email ) ) {
			userEmail = email;
			urlParams.append( 'email', email );
		}
		urlParams.append( 'is_blocks', !! wcSettings.wcBlocksConfig );
		urlParams.append( 'is_express', 'true' );
		urlParams.append( 'express_context', context );
		urlParams.append( 'source_url', window.location.href );
		urlParams.append(
			'viewport',
			`${ viewportWidth }x${ viewportHeight }`
		);

		iframe.src = `${ getConfig(
			'woopayHost'
		) }/otp/?${ urlParams.toString() }`;

		// Insert the wrapper into the DOM.
		parentDiv.insertBefore( iframeWrapper, null );

		setPopoverPosition();

		// Focus the iframe.
		iframe.focus();
	};

	document.addEventListener( 'keyup', ( event ) => {
		if ( 'Escape' === event.key && closeIframe() ) {
			event.stopPropagation();
		}
	} );

	window.addEventListener( 'message', ( e ) => {
		if ( ! getConfig( 'woopayHost' ).startsWith( e.origin ) ) {
			return;
		}

		switch ( e.data.action ) {
			case 'otp_email_submitted':
				userEmail = e.data.userEmail;
				break;
			case 'redirect_to_platform_checkout_skip_session_init':
				wcpayTracks.recordUserEvent(
					wcpayTracks.events.WOOPAY_OTP_COMPLETE
				);
				if ( e.data.redirectUrl ) {
					window.location = e.data.redirectUrl;
				}
				break;
			case 'redirect_to_platform_checkout':
			case 'redirect_to_woopay':
				wcpayTracks.recordUserEvent(
					wcpayTracks.events.WOOPAY_OTP_COMPLETE
				);
				api.initWooPay(
					userEmail,
					e.data.platformCheckoutUserSession
				).then( ( response ) => {
					// Do nothing if the iframe has been closed.
					if ( ! document.querySelector( '.woopay-otp-iframe' ) ) {
						return;
					}
					if ( 'success' === response.result ) {
						window.location = response.url;
					} else {
						showErrorMessage();
						closeIframe( false );
					}
				} );
				break;
			case 'otp_validation_failed':
				wcpayTracks.recordUserEvent(
					wcpayTracks.events.WOOPAY_OTP_FAILED
				);
				break;
			case 'close_modal':
				closeIframe();
				break;
			case 'iframe_height':
				if ( 300 < e.data.height ) {
					if ( fullScreenModalBreakpoint <= window.innerWidth ) {
						// set height to given value
						iframe.style.height = e.data.height + 'px';

						// center top in window
						iframe.style.top =
							Math.floor(
								window.innerHeight / 2 - e.data.height / 2
							) + 'px';
					} else {
						iframe.style.height = '';
						iframe.style.top = '';
					}
				}
				break;
			default:
			// do nothing, only respond to expected actions.
		}
	} );

	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted ) {
			// Safari needs to close iframe with this.
			closeIframe( false );
		}
	} );

	openIframe( woopayEmailInput?.value );
};
