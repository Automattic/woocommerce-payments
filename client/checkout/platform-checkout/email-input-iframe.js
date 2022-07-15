/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { getConfig } from 'wcpay/utils/checkout';
import wcpayTracks from 'tracks';
import request from '../utils/request';
import showErrorCheckout from '../utils/show-error-checkout';

export const handlePlatformCheckoutEmailInput = ( field, api ) => {
	let timer;
	const waitTime = 500;
	const platformCheckoutEmailInput = document.querySelector( field );

	// If we can't find the input, return.
	if ( ! platformCheckoutEmailInput ) {
		return;
	}

	const spinner = document.createElement( 'div' );
	const parentDiv = platformCheckoutEmailInput.parentNode;
	spinner.classList.add( 'wc-block-components-spinner' );

	// Make the iframe wrapper.
	const iframeWrapper = document.createElement( 'div' );
	iframeWrapper.setAttribute( 'role', 'dialog' );
	iframeWrapper.setAttribute( 'aria-modal', 'true' );
	iframeWrapper.classList.add( 'platform-checkout-sms-otp-iframe-wrapper' );

	// Make the iframe.
	const iframe = document.createElement( 'iframe' );
	iframe.title = __( 'WooPay SMS code verification', 'woocommerce-payments' );
	iframe.classList.add( 'platform-checkout-sms-otp-iframe' );

	// To prevent twentytwenty.intrinsicRatioVideos from trying to resize the iframe.
	iframe.classList.add( 'intrinsic-ignore' );

	// Make the iframe arrow.
	const iframeArrow = document.createElement( 'span' );
	iframeArrow.setAttribute( 'aria-hidden', 'true' );
	iframeArrow.classList.add( 'arrow' );

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
				getConfig( 'platformCheckoutHost' )
			);
		}

		// Prevent scrolling when the iframe is open.
		document.body.style.overflow = 'hidden';
	};

	/**
	 * Handles setting the iframe popover position based on the input field.
	 * It tries to be positioned at the right of the input field unless the
	 * window is too narrow, then it sticks 50px from the right edge of the
	 * screen.
	 */
	const setPopoverPosition = () => {
		// If for some reason the iframe is not loaded, just return.
		if ( ! iframe ) {
			return;
		}

		// If the window width is less than the breakpoint, reset the styles and return.
		if ( fullScreenModalBreakpoint >= window.innerWidth ) {
			iframe.style.left = '0';
			iframe.style.right = '';
			return;
		}

		/**
		 * If the iframe is off the top of the screen
		 * OR the iframe is off the bottom of the screen
		 * scroll the window so the iframe is in view.
		 */
		if (
			0 >= iframe.getBoundingClientRect().top ||
			0 >=
				window.innerHeight -
					( iframe.getBoundingClientRect().height +
						iframe.getBoundingClientRect().top )
		) {
			const topOffset = 50;
			const scrollTop =
				document.documentElement.scrollTop +
				platformCheckoutEmailInput.getBoundingClientRect().top -
				iframe.getBoundingClientRect().height / 2 -
				topOffset;
			window.scrollTo( {
				top: scrollTop,
			} );
		}

		// Get references to the iframe and input field bounding rects.
		const anchorRect = platformCheckoutEmailInput.getBoundingClientRect();
		const iframeRect = iframe.getBoundingClientRect();

		// Set the iframe top.
		iframe.style.top =
			Math.floor( anchorRect.top - iframeRect.height / 2 ) + 'px';

		// Set the arrow top.
		iframeArrow.style.top =
			Math.floor(
				anchorRect.top +
					anchorRect.height / 2 -
					parseFloat(
						window.getComputedStyle( iframeArrow )[
							'border-right-width'
						]
					)
			) + 'px';

		// Check if the iframe is off the right edge of the screen. If so, stick it to the right edge of the window.
		if (
			50 >=
			window.innerWidth - ( anchorRect.right + iframeRect.width )
		) {
			iframe.style.left = 'auto';
			iframeArrow.style.left = 'auto';
			iframe.style.right = '50px';
			iframeArrow.style.right = `${ iframeRect.width + 50 }px`;
		} else {
			iframe.style.left = `${ anchorRect.right + 5 }px`;
			iframe.style.right = '';
			iframeArrow.style.left = `${ anchorRect.right - 10 }px`;
			iframeArrow.style.right = '';
		}
	};

	iframe.addEventListener( 'load', () => {
		// Set the initial value.
		iframeHeaderValue = true;

		getWindowSize();
		window.addEventListener( 'resize', getWindowSize );

		setPopoverPosition();
		window.addEventListener( 'resize', setPopoverPosition );

		iframe.classList.add( 'open' );
		wcpayTracks.recordUserEvent(
			wcpayTracks.events.PLATFORM_CHECKOUT_OTP_START
		);
	} );

	// Add the iframe and iframe arrow to the wrapper.
	iframeWrapper.insertBefore( iframeArrow, null );
	iframeWrapper.insertBefore( iframe, null );

	// Error message to display when there's an error contacting WooPay.
	const errorMessage = document.createElement( 'div' );
	errorMessage.textContent = __(
		'WooPay is unavailable at this time. Please complete your checkout below. Sorry for the inconvenience.',
		'woocommerce-payments'
	);

	const closeIframe = ( focus = true ) => {
		window.removeEventListener( 'resize', getWindowSize );
		window.removeEventListener( 'resize', setPopoverPosition );

		iframeWrapper.remove();
		iframe.classList.remove( 'open' );

		if ( focus ) {
			platformCheckoutEmailInput.focus();
		}

		document.body.style.overflow = '';
	};

	iframeWrapper.addEventListener( 'click', closeIframe );

	const openIframe = ( email ) => {
		const urlParams = new URLSearchParams();
		urlParams.append( 'email', email );
		urlParams.append(
			'needsHeader',
			fullScreenModalBreakpoint > window.innerWidth
		);
		urlParams.append( 'wcpayVersion', getConfig( 'wcpayVersionNumber' ) );

		iframe.src = `${ getConfig(
			'platformCheckoutHost'
		) }/sms-otp/?${ urlParams.toString() }`;

		// Insert the wrapper into the DOM.
		parentDiv.insertBefore( iframeWrapper, null );

		setPopoverPosition();

		// Focus the iframe.
		iframe.focus();
	};

	const showErrorMessage = () => {
		parentDiv.insertBefore(
			errorMessage,
			platformCheckoutEmailInput.nextSibling
		);
	};

	document.addEventListener( 'keyup', ( event ) => {
		if ( 'Escape' === event.key && closeIframe() ) {
			event.stopPropagation();
		}
	} );

	const platformCheckoutLocateUser = async ( email ) => {
		parentDiv.insertBefore( spinner, platformCheckoutEmailInput );

		if ( parentDiv.contains( errorMessage ) ) {
			parentDiv.removeChild( errorMessage );
		}

		if ( 'undefined' !== typeof wcPayPlatformCheckoutSubscriptions ) {
			try {
				const userExistsData = await request(
					wcPayPlatformCheckoutSubscriptions.user_exists_url,
					{
						email,
					}
				);

				if ( userExistsData[ 'user-exists' ] ) {
					showErrorCheckout( userExistsData.message, false );
					spinner.remove();
					return;
				}
			} catch {
				showErrorMessage();
				spinner.remove();
			}
		}

		const emailExistsQuery = new URLSearchParams();
		emailExistsQuery.append( 'email', email );
		emailExistsQuery.append( 'test_mode', !! getConfig( 'testMode' ) );
		emailExistsQuery.append(
			'wcpay_version',
			getConfig( 'wcpayVersionNumber' )
		);

		fetch(
			`${ getConfig(
				'platformCheckoutHost'
			) }/wp-json/platform-checkout/v1/user/exists?${ emailExistsQuery.toString() }`
		)
			.then( ( response ) => {
				if ( 200 !== response.status ) {
					showErrorMessage();
				}

				return response.json();
			} )
			.then( ( data ) => {
				// Dispatch an event after we get the response.
				const PlatformCheckoutUserCheckEvent = new CustomEvent(
					'PlatformCheckoutUserCheck',
					{
						detail: {
							isRegisteredUser: data[ 'user-exists' ],
						},
					}
				);
				window.dispatchEvent( PlatformCheckoutUserCheckEvent );

				if ( data[ 'user-exists' ] ) {
					openIframe( email );
				} else if ( 'rest_invalid_param' !== data.code ) {
					wcpayTracks.recordUserEvent(
						wcpayTracks.events.PLATFORM_CHECKOUT_OFFERED
					);
				}
			} )
			.catch( () => {
				showErrorMessage();
			} )
			.finally( () => {
				spinner.remove();
			} );
	};

	const validateEmail = ( value ) => {
		/* Borrowed from WooCommerce checkout.js with a slight tweak to add `{2,}` to the end and make the TLD at least 2 characters. */
		/* eslint-disable */
		const pattern = new RegExp(
			/^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[0-9a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]){2,}\.?$/i
		);
		/* eslint-enable */
		return pattern.test( value );
	};

	// Prevent show platform checkout iframe if the page comes from
	// the back button on platform checkout itself.
	window.addEventListener( 'pageshow', function ( event ) {
		// Detect browser back button.
		const historyTraversal =
			event.persisted ||
			( 'undefined' !== typeof performance &&
				'back_forward' ===
					performance.getEntriesByType( 'navigation' )[ 0 ].type );

		const searchParams = new URLSearchParams( window.location.search );

		if (
			! historyTraversal &&
			'true' !== searchParams.get( 'skip_platform_checkout' )
		) {
			// Check the initial value of the email input and trigger input validation.
			if ( validateEmail( platformCheckoutEmailInput.value ) ) {
				platformCheckoutLocateUser( platformCheckoutEmailInput.value );
			}
		} else {
			searchParams.delete( 'skip_platform_checkout' );

			let { pathname } = window.location;

			if ( '' !== searchParams.toString() ) {
				pathname += '?' + searchParams.toString();
			}

			history.replaceState( null, null, pathname );

			// Safari needs to close iframe with this.
			closeIframe( false );
		}
	} );

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
				wcpayTracks.recordUserEvent(
					wcpayTracks.events.PLATFORM_CHECKOUT_OTP_COMPLETE
				);
				api.initPlatformCheckout(
					platformCheckoutEmailInput.value,
					e.data.platformCheckoutUserSession
				).then( ( response ) => {
					if ( 'success' === response.result ) {
						window.location = response.url;
					} else {
						showErrorMessage();
						closeIframe();
					}
				} );
				break;
			case 'otp_validation_failed':
				wcpayTracks.recordUserEvent(
					wcpayTracks.events.PLATFORM_CHECKOUT_OTP_FAILED
				);
				break;
			case 'close_modal':
				closeIframe();
				break;
			case 'iframe_height':
				if ( 300 < e.data.height ) {
					if ( fullScreenModalBreakpoint <= window.innerWidth ) {
						// attach iframe to right side of platformCheckoutEmailInput.

						iframe.style.height = e.data.height + 'px';

						const inputRect = platformCheckoutEmailInput.getBoundingClientRect();

						// iframe top is the input top minus the iframe height.
						iframe.style.top =
							Math.floor( inputRect.top - e.data.height / 2 ) +
							'px';
						// Arrow top is the input top plus half the input height minus the border width.
						iframeArrow.style.top =
							Math.floor(
								inputRect.top +
									inputRect.height / 2 -
									parseFloat(
										window.getComputedStyle( iframeArrow )[
											'border-right-width'
										]
									)
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
};
