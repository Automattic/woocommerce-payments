/**
 * External dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';

export const handlePlatformCheckoutPopup = async ( field, api ) => {
	const popupHeight = 700;
	const popupWidth = 1000;
	let wooPayWindow = null;

	const parentDiv = document.querySelector( '#contact_details' );

	const getPopupPosition = () => {
		// Fixes dual-screen position                             Most browsers      Firefox
		const dualScreenLeft = window.screenLeft !==  undefined ? window.screenLeft : window.screenX;
		const dualScreenTop = window.screenTop !==  undefined   ? window.screenTop  : window.screenY;

		const width = window.innerWidth
			? window.innerWidth
			: document.documentElement.clientWidth
			? document.documentElement.clientWidth
			: screen.width;
		const height = window.innerHeight
			? window.innerHeight
			: document.documentElement.clientHeight
			? document.documentElement.clientHeight
			: screen.height;

		const systemZoom = width / window.screen.availWidth;
		const left = (width - popupWidth) / 2 / systemZoom + dualScreenLeft
		const top = (height - popupHeight) / 2 / systemZoom + dualScreenTop

		return { top, left };
	};

	// add button for express checkout
	const openPopup = () => {
		api.initPlatformCheckoutSimple().then( ( response ) => {
			if ( 'success' === response.result ) {
				const popupUrl = response.url;
				const { top, left } = getPopupPosition();
				wooPayWindow = window.open(
					popupUrl,
					'wooPayPopup',
					`popup,width=1000,height=700,top=${ top },left=${ left }`
				);
			} else {
				alert('Failed response');
			}
		} );


	};

	const wooPayButton = document.createElement( 'button' );
	wooPayButton.type = 'button';
	wooPayButton.textContent = 'Checkout with WooPay';
	wooPayButton.addEventListener( 'click', openPopup );
	parentDiv.prepend( wooPayButton );

	window.addEventListener( 'message', ( e ) => {
		if ( ! getConfig( 'platformCheckoutHost' ).startsWith( e.origin ) ) {
			return;
		}

		switch ( e.data.action ) {
			case 'store_api_call':
				console.log( 'got our message!' );

				wooPayWindow.postMessage( { action: 'test_reply' }, '*' );
				break;
		}

	} );
};
