/**
 * External dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import wcpayTracks from 'tracks';
import request from '../utils/request';
import { buildAjaxURL } from '../../payment-request/utils';
import { getTargetElement } from './utils';

export const woopayCheckEmailInput = async ( field ) => {
	let timer;
	const waitTime = 500;
	const platformCheckoutEmailInput = await getTargetElement( field );

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
			} )
			.catch( () => {} );
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

	platformCheckoutEmailInput.addEventListener( 'input', ( e ) => {
		const email = e.currentTarget.value;

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
