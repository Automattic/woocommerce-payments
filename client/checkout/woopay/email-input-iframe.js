/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { getConfig } from 'wcpay/utils/checkout';
import { recordUserEvent, getTracksIdentity } from 'tracks';
import request from '../utils/request';
import { buildAjaxURL } from '../../payment-request/utils';
import {
	getTargetElement,
	validateEmail,
	appendRedirectionParams,
	shouldSkipWooPay,
	deleteSkipWooPayCookie,
} from './utils';

export const handleWooPayEmailInput = async (
	field,
	api,
	isBlocksCheckout = false
) => {
	let timer;
	const waitTime = 500;
	const woopayEmailInput = await getTargetElement( field );
	const tracksUserId = await getTracksIdentity();

	// If we can't find the input, return.
	if ( ! woopayEmailInput ) {
		return;
	}

	const spinner = document.createElement( 'div' );
	const parentDiv = woopayEmailInput.parentNode;
	spinner.classList.add( 'wc-block-components-spinner' );

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
	const isSkipWoopayCookieSet = shouldSkipWooPay();
	const customerClickedBackButton =
		( typeof performance !== 'undefined' &&
			performance.getEntriesByType( 'navigation' )[ 0 ].type ===
				'back_forward' ) ||
		searchParams.get( 'skip_woopay' ) === 'true' ||
		isSkipWoopayCookieSet; // We enforce and extend the skipping to the entire user session.

	if ( customerClickedBackButton && ! isSkipWoopayCookieSet ) {
		const now = new Date();
		const followingDay = new Date( now.getTime() + 24 * 60 * 60 * 1000 ); // 24 hours later
		document.cookie = `skip_woopay=1; path=/; expires=${ followingDay.toUTCString() }`;
	}

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
		if ( fullScreenModalBreakpoint > window.innerWidth ) {
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

		if ( getConfig( 'isWoopayFirstPartyAuthEnabled' ) ) {
			request(
				buildAjaxURL( getConfig( 'wcAjaxUrl' ), 'get_woopay_session' ),
				{
					_ajax_nonce: getConfig( 'woopaySessionNonce' ),
					order_id: getConfig( 'order_id' ),
					key: getConfig( 'key' ),
					billing_email: getConfig( 'billing_email' ),
				}
			).then( ( response ) => {
				if ( response?.data?.session ) {
					iframe.contentWindow.postMessage(
						{
							action: 'setSessionData',
							value: response,
						},
						getConfig( 'woopayHost' )
					);
				}
			} );
		}

		getWindowSize();
		window.addEventListener( 'resize', getWindowSize );

		setPopoverPosition();
		window.addEventListener( 'resize', setPopoverPosition );

		iframe.classList.add( 'open' );
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
		urlParams.append(
			'source_url',
			wcSettings?.storePages?.checkout?.permalink
		);
		urlParams.append(
			'viewport',
			`${ viewportWidth }x${ viewportHeight }`
		);

		if ( tracksUserId ) {
			urlParams.append( 'tracksUserIdentity', tracksUserId );
		}

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

		recordUserEvent( 'checkout_email_address_woopay_check' );

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

				throw new Error(
					__(
						'Request for signature failed.',
						'woocommerce-payments'
					)
				);
			} )
			.then( ( data ) => {
				if ( data.signature ) {
					return data.signature;
				}

				throw new Error(
					__( 'Signature not found.', 'woocommerce-payments' )
				);
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
					recordUserEvent( 'checkout_woopay_save_my_info_offered' );

					if ( window.woopayCheckout?.PRE_CHECK_SAVE_MY_INFO ) {
						recordUserEvent( 'checkout_save_my_info_click', {
							status: 'checked',
						} );
					}
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

	woopayEmailInput.addEventListener( 'input', ( e ) => {
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
			case 'redirect_to_woopay_skip_session_init':
				if ( e.data.redirectUrl ) {
					deleteSkipWooPayCookie();
					window.location = appendRedirectionParams(
						e.data.redirectUrl
					);
				}
				break;
			case 'redirect_to_platform_checkout':
			case 'redirect_to_woopay':
				const promise = api.initWooPay(
					woopayEmailInput.value,
					e.data.platformCheckoutUserSession
				);

				// The <Login> component on WooPay re-renders sending the `redirect_to_platform_checkout` message twice.
				// `api.initWooPay` skips the request the second time and returns undefined.
				if ( ! promise ) {
					break;
				}

				promise
					.then( ( response ) => {
						// Do nothing if the iframe has been closed.
						if (
							! document.querySelector( '.woopay-otp-iframe' )
						) {
							return;
						}
						if ( response.result === 'success' ) {
							deleteSkipWooPayCookie();
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

	if ( customerClickedBackButton ) {
		// Dispatch an event declaring this user exists as returned via back button. Wait for the window to load.
		setTimeout( () => {
			dispatchUserExistEvent( true );
		}, 2000 );

		recordUserEvent( 'woopay_skipped', {} );

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
