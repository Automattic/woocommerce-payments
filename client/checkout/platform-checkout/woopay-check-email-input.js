/**
 * External dependencies
 */
import { getConfig, validateEmail } from 'wcpay/utils/checkout';
import wcpayTracks from 'tracks';
import request from '../utils/request';
import { buildAjaxURL } from '../../payment-request/utils';
import { setWooPayQueryStatus } from '../utils/link.js';

// Waits for the element to exist as in the Blocks checkout, sometimes the field is not immediately available.
const waitForElement = ( selector ) => {
	return new Promise( ( resolve ) => {
		if ( document.querySelector( selector ) ) {
			return resolve( document.querySelector( selector ) );
		}

		const checkoutBlock = document.querySelector(
			'[data-block-name="woocommerce/checkout"]'
		);

		if ( ! checkoutBlock ) {
			return resolve( null );
		}

		const observer = new MutationObserver( ( mutationList, obs ) => {
			if ( document.querySelector( selector ) ) {
				resolve( document.querySelector( selector ) );
				obs.disconnect();
			}
		} );

		observer.observe( checkoutBlock, {
			childList: true,
			subtree: true,
		} );
	} );
};

export const woopayCheckEmailInput = async ( field ) => {
	let timer;
	const waitTime = 500;
	const platformCheckoutEmailInput = await waitForElement( field );

	// If we can't find the input, return.
	if ( ! platformCheckoutEmailInput ) {
		return;
	}

	//Checks if customer has clicked the back button.
	const searchParams = new URLSearchParams( window.location.search );
	const customerClickedBackButton =
		( 'undefined' !== typeof performance &&
			'back_forward' ===
				performance.getEntriesByType( 'navigation' )[ 0 ].type ) ||
		'true' === searchParams.get( 'skip_platform_checkout' );

	const dispatchUserExistEvent = ( userExist ) => {
		const PlatformCheckoutUserCheckEvent = new CustomEvent(
			'PlatformCheckoutUserCheck',
			{
				detail: {
					isRegisteredUser: userExist,
				},
			}
		);
		window.dispatchEvent( PlatformCheckoutUserCheckEvent );
	};

	const platformCheckoutLocateUser = async ( email ) => {
		request(
			buildAjaxURL(
				getConfig( 'wcAjaxUrl' ),
				'get_platform_checkout_signature'
			),
			{
				_ajax_nonce: getConfig( 'platformCheckoutSignatureNonce' ),
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
					getConfig( 'platformCheckoutMerchantId' )
				);
				emailExistsQuery.append( 'request_signature', signature );

				return fetch(
					`${ getConfig(
						'platformCheckoutHost'
					) }/wp-json/platform-checkout/v1/user/exists?${ emailExistsQuery.toString() }`
				);
			} )
			.then( ( response ) => {
				return response.json();
			} )
			.then( ( data ) => {
				// Dispatch an event after we get the response.
				dispatchUserExistEvent( data[ 'user-exists' ] );
				setWooPayQueryStatus(
					platformCheckoutEmailInput,
					data[ 'user-exists' ]
				);
			} )
			.catch( () => {
				setWooPayQueryStatus( platformCheckoutEmailInput, false );
			} );
	};

	platformCheckoutEmailInput.addEventListener( 'input', ( e ) => {
		const email = e.currentTarget.value;

		setWooPayQueryStatus( platformCheckoutEmailInput, 'checking' );
		clearTimeout( timer );

		timer = setTimeout( () => {
			if ( validateEmail( email ) ) {
				platformCheckoutLocateUser( email );
			}
		}, waitTime );
	} );

	if ( ! customerClickedBackButton ) {
		setTimeout( () => {
			if ( validateEmail( platformCheckoutEmailInput.value ) ) {
				platformCheckoutLocateUser( platformCheckoutEmailInput.value );
			}
		}, 2000 );
	} else {
		// Dispatch an event declaring this user exists as returned via back button. Wait for the window to load.
		setTimeout( () => {
			dispatchUserExistEvent( true );
		}, 2000 );

		wcpayTracks.recordUserEvent(
			wcpayTracks.events.PLATFORM_CHECKOUT_SKIPPED
		);

		searchParams.delete( 'skip_platform_checkout' );

		let { pathname } = window.location;

		if ( '' !== searchParams.toString() ) {
			pathname += '?' + searchParams.toString();
		}

		history.replaceState( null, null, pathname );
	}
};
