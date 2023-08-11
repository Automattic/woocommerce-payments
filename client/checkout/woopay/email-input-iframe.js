/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { getConfig } from 'wcpay/utils/checkout';
import wcpayTracks from 'tracks';
import request from '../utils/request';
import { buildAjaxURL } from '../../payment-request/utils';
import { getTargetElement, validateEmail } from './utils';

export const handleWooPayEmailInput = async (
	field,
	api,
	isBlocksCheckout = false
) => {
	let timer;
	const waitTime = 500;
	const woopayEmailInput = await getTargetElement( field );
	let hasCheckedLoginSession = false;

	// If we can't find the input, return.
	if ( ! woopayEmailInput ) {
		return;
	}

	const spinner = document.createElement( 'div' );
	const parentDiv = woopayEmailInput.parentNode;
	spinner.classList.add( 'wc-block-components-spinner' );

	// Make the login session iframe wrapper.
	const loginSessionIframeWrapper = document.createElement( 'div' );
	loginSessionIframeWrapper.setAttribute( 'role', 'dialog' );
	loginSessionIframeWrapper.setAttribute( 'aria-modal', 'true' );

	// Make the login session iframe.
	const loginSessionIframe = document.createElement( 'iframe' );
	loginSessionIframe.title = __(
		'WooPay Login Session',
		'woocommerce-payments'
	);
	loginSessionIframe.classList.add( 'woopay-login-session-iframe' );

	// To prevent twentytwenty.intrinsicRatioVideos from trying to resize the iframe.
	loginSessionIframe.classList.add( 'intrinsic-ignore' );

	loginSessionIframeWrapper.insertBefore( loginSessionIframe, null );

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

	// Make the iframe arrow.
	const iframeArrow = document.createElement( 'span' );
	iframeArrow.setAttribute( 'aria-hidden', 'true' );
	iframeArrow.classList.add( 'arrow' );

	// Maybe we could make this a configurable option defined in PHP so it could be filtered by merchants.
	const fullScreenModalBreakpoint = 768;

	//Checks if customer has clicked the back button to prevent auto redirect
	const searchParams = new URLSearchParams( window.location.search );
	const customerClickedBackButton =
		( typeof performance !== 'undefined' &&
			performance.getEntriesByType( 'navigation' )[ 0 ].type ===
				'back_forward' ) ||
		searchParams.get( 'skip_woopay' ) === 'true';

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
			iframe.getBoundingClientRect().top <= 0 ||
			window.innerHeight -
				( iframe.getBoundingClientRect().height +
					iframe.getBoundingClientRect().top ) <=
				0
		) {
			const topOffset = 50;
			const scrollTop =
				document.documentElement.scrollTop +
				woopayEmailInput.getBoundingClientRect().top -
				iframe.getBoundingClientRect().height / 2 -
				topOffset;
			window.scrollTo( {
				top: scrollTop,
			} );
		}

		// Get references to the iframe and input field bounding rects.
		const anchorRect = woopayEmailInput.getBoundingClientRect();
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
			window.innerWidth - ( anchorRect.right + iframeRect.width ) <=
			50
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

		request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'get_woopay_session' ),
			{
				_ajax_nonce: getConfig( 'woopaySessionNonce' ),
			}
		).then( ( response ) => {
			if ( response.data.session ) {
				iframe.contentWindow.postMessage(
					{
						action: 'setSessionData',
						value: response,
					},
					getConfig( 'woopayHost' )
				);
			}
		} );

		getWindowSize();
		window.addEventListener( 'resize', getWindowSize );

		setPopoverPosition();
		window.addEventListener( 'resize', setPopoverPosition );

		iframe.classList.add( 'open' );
		wcpayTracks.recordUserEvent(
			wcpayTracks.events.WOOPAY_OTP_START,
			[],
			true
		);
	} );

	// Add the iframe and iframe arrow to the wrapper.
	iframeWrapper.insertBefore( iframeArrow, null );
	iframeWrapper.insertBefore( iframe, null );

	// Error message to display when there's an error contacting WooPay.
	const errorMessage = document.createElement( 'div' );
	errorMessage.style[ 'white-space' ] = 'normal';
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
			woopayEmailInput.focus();
		}

		document.body.style.overflow = '';
	};

	iframeWrapper.addEventListener( 'click', closeIframe );

	const openIframe = ( email ) => {
		// check and return if another otp iframe is already open.
		if ( document.querySelector( '.woopay-otp-iframe' ) ) {
			return;
		}

		const viewportWidth = window.document.documentElement.clientWidth;
		const viewportHeight = window.document.documentElement.clientHeight;

		const urlParams = new URLSearchParams();
		urlParams.append( 'email', email );
		urlParams.append( 'testMode', getConfig( 'testMode' ) );
		urlParams.append(
			'needsHeader',
			fullScreenModalBreakpoint > window.innerWidth
		);
		urlParams.append( 'wcpayVersion', getConfig( 'wcpayVersionNumber' ) );
		urlParams.append( 'is_blocks', isBlocksCheckout ? 'true' : 'false' );
		urlParams.append( 'source_url', window.location.href );
		urlParams.append(
			'viewport',
			`${ viewportWidth }x${ viewportHeight }`
		);
		urlParams.append(
			'tracksUserIdentity',
			JSON.stringify( getConfig( 'tracksUserIdentity' ) )
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

	const showErrorMessage = () => {
		parentDiv.insertBefore( errorMessage, woopayEmailInput.nextSibling );
	};

	document.addEventListener( 'keyup', ( event ) => {
		if ( event.key === 'Escape' && closeIframe() ) {
			event.stopPropagation();
		}
	} );

	// Cancel woopay request and close iframe
	// when user clicks Place Order before it loads.
	const abortController = new AbortController();
	const { signal } = abortController;

	signal.addEventListener( 'abort', () => {
		spinner.remove();
		closeIframe( false );
	} );

	if ( isBlocksCheckout ) {
		const formSubmitButton = await getTargetElement(
			'button.wc-block-components-checkout-place-order-button'
		);
		formSubmitButton.addEventListener( 'click', () => {
			abortController.abort();
		} );
	} else {
		document
			.querySelector( 'form[name="checkout"]' )
			.addEventListener( 'submit', () => {
				abortController.abort();
			} );
	}

	const dispatchUserExistEvent = ( userExist ) => {
		const woopayUserCheckEvent = new CustomEvent( 'woopayUserCheck', {
			detail: {
				isRegisteredUser: userExist,
			},
		} );
		window.dispatchEvent( woopayUserCheckEvent );
	};

	const woopayLocateUser = async ( email ) => {
		parentDiv.insertBefore( spinner, woopayEmailInput );

		if ( parentDiv.contains( errorMessage ) ) {
			parentDiv.removeChild( errorMessage );
		}

		request(
			buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'get_woopay_signature' ),
			{
				_ajax_nonce: getConfig( 'woopaySignatureNonce' ),
			}
		)
			.then( ( response ) => {
				if ( response.success ) {
					return response.data;
				}

				throw new Error( 'Request for signature failed.' );
			} )
			.then( ( data ) => {
				if ( data.signature ) {
					return data.signature;
				}

				throw new Error( 'Signature not found.' );
			} )
			.then( ( signature ) => {
				const emailExistsQuery = new URLSearchParams();
				emailExistsQuery.append( 'email', email );
				emailExistsQuery.append(
					'test_mode',
					!! getConfig( 'testMode' )
				);
				emailExistsQuery.append(
					'wcpay_version',
					getConfig( 'wcpayVersionNumber' )
				);
				emailExistsQuery.append(
					'blog_id',
					getConfig( 'woopayMerchantId' )
				);
				emailExistsQuery.append( 'request_signature', signature );

				return fetch(
					`${ getConfig(
						'woopayHost'
					) }/wp-json/platform-checkout/v1/user/exists?${ emailExistsQuery.toString() }`,
					{
						signal,
					}
				);
			} )
			.then( ( response ) => {
				if ( response.status !== 200 ) {
					showErrorMessage();
				}

				return response.json();
			} )
			.then( ( data ) => {
				// Dispatch an event after we get the response.
				dispatchUserExistEvent( data[ 'user-exists' ] );

				if ( data[ 'user-exists' ] ) {
					openIframe( email );
				} else if ( data.code !== 'rest_invalid_param' ) {
					wcpayTracks.recordUserEvent(
						wcpayTracks.events.WOOPAY_OFFERED,
						[],
						true
					);
				}
			} )
			.catch( ( err ) => {
				// Only show the error if it's not an AbortError,
				// it occur when the fetch request is aborted because user
				// clicked the Place Order button while loading.
				if ( err.name !== 'AbortError' ) {
					showErrorMessage();
				}
			} )
			.finally( () => {
				spinner.remove();
			} );
	};

	const closeLoginSessionIframe = () => {
		loginSessionIframeWrapper.remove();
		loginSessionIframe.classList.remove( 'open' );
		woopayEmailInput.focus( {
			preventScroll: true,
		} );

		// Check the initial value of the email input and trigger input validation.
		if ( validateEmail( woopayEmailInput.value ) ) {
			woopayLocateUser( woopayEmailInput.value );
		}
	};

	const openLoginSessionIframe = ( email ) => {
		const emailParam = new URLSearchParams();

		if ( validateEmail( email ) ) {
			parentDiv.insertBefore( spinner, woopayEmailInput );
			emailParam.append( 'email', email );
			emailParam.append( 'test_mode', !! getConfig( 'testMode' ) );
		}

		loginSessionIframe.src = `${ getConfig(
			'woopayHost'
		) }/login-session?${ emailParam.toString() }`;

		// Insert the wrapper into the DOM.
		parentDiv.insertBefore( loginSessionIframeWrapper, null );

		// Focus the iframe.
		loginSessionIframe.focus();

		// fallback to close the login session iframe in case failed to receive event
		// via postMessage.
		setTimeout( () => {
			if ( ! hasCheckedLoginSession ) {
				closeLoginSessionIframe();
			}
		}, 15000 );
	};

	woopayEmailInput.addEventListener( 'input', ( e ) => {
		if ( ! hasCheckedLoginSession && ! customerClickedBackButton ) {
			if ( customerClickedBackButton ) {
				openLoginSessionIframe( woopayEmailInput.value );
			}

			return;
		}

		const email = e.currentTarget.value;

		clearTimeout( timer );
		spinner.remove();

		timer = setTimeout( () => {
			if ( validateEmail( email ) ) {
				woopayLocateUser( email );
			}
		}, waitTime );
	} );

	window.addEventListener( 'message', ( e ) => {
		if ( ! getConfig( 'woopayHost' ).startsWith( e.origin ) ) {
			return;
		}

		switch ( e.data.action ) {
			case 'auto_redirect_to_platform_checkout':
			case 'auto_redirect_to_woopay':
				hasCheckedLoginSession = true;
				api.initWooPay(
					e.data.userEmail,
					e.data.platformCheckoutUserSession
				)
					.then( ( response ) => {
						if ( response.result === 'success' ) {
							loginSessionIframeWrapper.classList.add(
								'woopay-login-session-iframe-wrapper'
							);
							loginSessionIframe.classList.add( 'open' );
							wcpayTracks.recordUserEvent(
								wcpayTracks.events.WOOPAY_AUTO_REDIRECT
							);
							spinner.remove();
							// Do nothing if the iframe has been closed.
							if (
								! document.querySelector(
									'.woopay-login-session-iframe'
								)
							) {
								return;
							}
							window.location = response.url;
						} else {
							closeLoginSessionIframe();
						}
					} )
					.catch( ( err ) => {
						// Only show the error if it's not an AbortError,
						// it occurs when the fetch request is aborted because user
						// clicked the Place Order button while loading.
						if ( err.name !== 'AbortError' ) {
							showErrorMessage();
						}
					} )
					.finally( () => {
						spinner.remove();
					} );
				break;
			case 'close_auto_redirection_modal':
				hasCheckedLoginSession = true;
				closeLoginSessionIframe();
				break;
			case 'redirect_to_woopay_skip_session_init':
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
					wcpayTracks.events.WOOPAY_OTP_COMPLETE,
					[],
					true
				);
				api.initWooPay(
					woopayEmailInput.value,
					e.data.platformCheckoutUserSession
				)
					.then( ( response ) => {
						// Do nothing if the iframe has been closed.
						if (
							! document.querySelector( '.woopay-otp-iframe' )
						) {
							return;
						}
						if ( response.result === 'success' ) {
							window.location = response.url;
						} else {
							showErrorMessage();
							closeIframe( false );
						}
					} )
					.catch( () => {
						showErrorMessage();
						closeIframe( false );
					} );
				break;
			case 'otp_validation_failed':
				wcpayTracks.recordUserEvent(
					wcpayTracks.events.WOOPAY_OTP_FAILED,
					[],
					true
				);
				break;
			case 'close_modal':
				closeIframe();
				break;
			case 'iframe_height':
				if ( e.data.height > 300 ) {
					if ( fullScreenModalBreakpoint <= window.innerWidth ) {
						// attach iframe to right side of woopayEmailInput.

						iframe.style.height = e.data.height + 'px';

						const inputRect = woopayEmailInput.getBoundingClientRect();

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

	window.addEventListener( 'pageshow', function ( event ) {
		if ( event.persisted ) {
			// Safari needs to close iframe with this.
			closeIframe( false );
		}
	} );

	if ( ! customerClickedBackButton ) {
		// Check if user already has a WooPay login session.
		if ( ! hasCheckedLoginSession ) {
			openLoginSessionIframe( woopayEmailInput.value );
		}
	} else {
		// Dispatch an event declaring this user exists as returned via back button. Wait for the window to load.
		setTimeout( () => {
			dispatchUserExistEvent( true );
		}, 2000 );

		wcpayTracks.recordUserEvent(
			wcpayTracks.events.WOOPAY_SKIPPED,
			[],
			true
		);

		searchParams.delete( 'skip_woopay' );

		let { pathname } = window.location;

		if ( searchParams.toString() !== '' ) {
			pathname += '?' + searchParams.toString();
		}

		history.replaceState( null, null, pathname );

		// Safari needs to close iframe with this.
		closeIframe( false );
	}
};
