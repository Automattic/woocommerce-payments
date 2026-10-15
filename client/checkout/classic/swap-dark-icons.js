/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';
import { getIconTheme } from 'wcpay/checkout/utils/icon-theme';

/**
 * Swaps the non-card payment method icons to their dark variants when the
 * classic checkout background is dark, and back when it is light.
 */
export function swapDarkIcons() {
	const useDark = getIconTheme( 'classic' ) === 'night';

	document.querySelectorAll( '.wcpay-upe-element' ).forEach( ( el ) => {
		const type = el.dataset.paymentMethodType;
		if ( type === 'card' ) {
			return;
		}
		const config = getUPEConfig( 'paymentMethodsConfig' )?.[ type ];
		const targetIcon = useDark ? config?.darkIcon : config?.icon;
		if ( targetIcon ) {
			el.closest( '.wc_payment_method' )
				?.querySelectorAll( 'label img' )
				.forEach( ( img ) => {
					img.src = targetIcon;
				} );
		}
	} );
}
