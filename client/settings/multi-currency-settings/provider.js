/**
 * External dependencies
 */
import { useCallback, useMemo, useState } from 'react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import WcPayMultiCurrencyContext from './context';
import { NAMESPACE } from '../../data/constants';

const WcPayMultiCurrencyContextProvider = ( {
	children,
	defaultIsAutomaticSwitchEnabled,
	defaultWillAddCurrencySwitcherToCartWidget,
} ) => {
	const [ isAutomaticSwitchEnabled, setIsAutomaticSwitchEnabled ] = useState(
		Boolean( defaultIsAutomaticSwitchEnabled )
	);
	const [
		willAddCurrencySelectorToCartWidget,
		setWillAddCurrencySelectorToCartWidget,
	] = useState( Boolean( defaultWillAddCurrencySwitcherToCartWidget ) );
	const [ status, setStatus ] = useState( 'resolved' );

	const updateAutomaticSwitchEnabled = useCallback(
		( value ) => {
			setStatus( 'pending' );
			return apiFetch( {
				path: `${ NAMESPACE }/automatic_switch_toggle`,
				method: 'POST',
				// eslint-disable-next-line camelcase
				data: { is_automatic_switch_enabled: Boolean( value ) },
			} )
				.then( () => {
					setIsAutomaticSwitchEnabled( Boolean( value ) );
					// the backend already takes care of this,
					// we're just duplicating the effort
					// to ensure that the non-MultiCurrency payment methods are removed when the flag is disabled
					if ( ! value ) {
						setIsAutomaticSwitchEnabled( false );
					}
					setStatus( 'resolved' );
				} )
				.catch( () => {
					setStatus( 'error' );
				} );
		},
		[ setStatus, setIsAutomaticSwitchEnabled ]
	);

	const updateAddCurrencySwitcherToCartWidget = useCallback(
		( value ) => {
			setStatus( 'pending' );
			return apiFetch( {
				path: `${ NAMESPACE }/cart_currency_switcher_toggle`,
				method: 'POST',
				// eslint-disable-next-line camelcase
				data: {
					will_add_currency_switcher_to_cart_widget: Boolean( value ),
				},
			} )
				.then( () => {
					setWillAddCurrencySelectorToCartWidget( Boolean( value ) );
					// the backend already takes care of this,
					// we're just duplicating the effort
					// to ensure that the non-MultiCurrency payment methods are removed when the flag is disabled
					if ( ! value ) {
						setWillAddCurrencySelectorToCartWidget( false );
					}
					setStatus( 'resolved' );
				} )
				.catch( () => {
					setStatus( 'error' );
				} );
		},
		[ setStatus, setWillAddCurrencySelectorToCartWidget ]
	);

	const contextValue = useMemo(
		() => ( {
			isAutomaticSwitchEnabled,
			setIsAutomaticSwitchEnabled: updateAutomaticSwitchEnabled,
			willAddCurrencySelectorToCartWidget,
			setWillAddCurrencySelectorToCartWidget: updateAddCurrencySwitcherToCartWidget,
			status,
		} ),
		[
			isAutomaticSwitchEnabled,
			updateAutomaticSwitchEnabled,
			willAddCurrencySelectorToCartWidget,
			updateAddCurrencySwitcherToCartWidget,
			status,
		]
	);

	return (
		<WcPayMultiCurrencyContext.Provider value={ contextValue }>
			{ children }
		</WcPayMultiCurrencyContext.Provider>
	);
};

export default WcPayMultiCurrencyContextProvider;
