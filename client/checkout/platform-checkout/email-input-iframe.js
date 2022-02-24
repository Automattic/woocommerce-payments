/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { getConfig } from 'wcpay/utils/checkout';

export const handlePlatformCheckoutEmailInput = ( field, api ) => {
	let timer;
	const waitTime = 500;
	const platformCheckoutEmailInput = document.querySelector( field );
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
	iframe.title = __(
		'Platform checkout SMS code verification',
		'woocommerce-payments'
	);
	iframe.classList.add( 'platform-checkout-sms-otp-iframe' );

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

		// Check if the iframe is off the top of the screen and scroll back into view.
		if ( 0 >= iframe.getBoundingClientRect().top ) {
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
	} );

	// Add the iframe and iframe arrow to the wrapper.
	iframeWrapper.insertBefore( iframeArrow, null );
	iframeWrapper.insertBefore( iframe, null );

	const closeIframe = () => {
		window.removeEventListener( 'resize', getWindowSize );
		window.removeEventListener( 'resize', setPopoverPosition );

		iframeWrapper.remove();
		iframe.classList.remove( 'open' );
		platformCheckoutEmailInput.focus();

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

		iframe.src = `${ getConfig(
			'platformCheckoutHost'
		) }/sms-otp/?${ urlParams.toString() }`;

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

	const platformCheckoutLocateUser = ( email ) => {
		parentDiv.insertBefore( spinner, platformCheckoutEmailInput );

		const emailParam = new URLSearchParams();
		emailParam.append( 'email', email );

		fetch(
			`${ getConfig(
				'platformCheckoutHost'
			) }/wp-json/platform-checkout/v1/user/exists?${ emailParam.toString() }`
		)
			.then( ( response ) => response.json() )
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
