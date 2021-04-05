/** @format **/

/**
 * External dependencies
 */
import { useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Sends a updateOption request if an element with `.wcpay-menu-badge` is detected
 * to signal that the notification has been viewed.
 */
const useReadMenuNotificationBadge = () => {
	const optionsStoreDispatcher = useDispatch( 'wc/admin/options' );

	useEffect( () => {
		// We're only interested in running this only when jQuery
		// and option store exists.
		if ( window && window.jQuery && optionsStoreDispatcher ) {
			const $element = window.jQuery( '.wcpay-menu-badge' );
			// Attempt to remove menu badge only if it exists.
			if ( $element.length ) {
				$element.hide();
				optionsStoreDispatcher.updateOptions( {
					// eslint-disable-next-line camelcase
					wcpay_menu_badge_hidden: 'yes',
				} );
			}
		}
	} );
};

export default useReadMenuNotificationBadge;
