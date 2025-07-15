/**
 * External dependencies
 */
import React, { useState, useEffect, useCallback } from 'react';
import ReactDOM from 'react-dom';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import MultiCurrencySettings from './settings/multi-currency';
import SingleCurrencySettings from './settings/single-currency';
import MultiCurrencySettingsContext from './context';
import UnbundledWpComponentsProvider from 'wcpay/wordpress-components-context/unbundled-wp-components-provider';
import useConfirmNavigation from 'wcpay/utils/use-confirm-navigation';

const MultiCurrencySettingsPage = () => {
	const [
		currencyCodeToShowSettingsFor,
		_setCurrencyCodeToShowSettingsFor,
	] = useState( null );
	const [ isCurrentScreenDirty, setIsCurrentScreenDirty ] = useState( false );
	useEffect(
		useConfirmNavigation( () => {
			if ( isCurrentScreenDirty ) {
				return __(
					'There are unsaved changes on this page. Are you sure you want to leave and discard the unsaved changes?',
					'woocommerce-payments'
				);
			}
		} ),
		[ isCurrentScreenDirty ]
	);
	useEffect( () => {
		setIsCurrentScreenDirty( false );
	}, [ currencyCodeToShowSettingsFor ] );
	const setCurrencyCodeToShowSettingsFor = useCallback(
		( currency ) => {
			if (
				confirm(
					__(
						'There are unsaved changes on this page. Are you sure you want to leave and discard the unsaved changes?',
						'woocommerce-payments'
					)
				)
			) {
				_setCurrencyCodeToShowSettingsFor( currency );
			}
		},
		[ isCurrentScreenDirty ]
	);

	return (
		<MultiCurrencySettingsContext.Provider
			value={ {
				currencyCodeToShowSettingsFor,
				setCurrencyCodeToShowSettingsFor,
				isCurrentScreenDirty,
				setIsCurrentScreenDirty,
			} }
		>
			{ ! currencyCodeToShowSettingsFor ? (
				<MultiCurrencySettings />
			) : (
				<SingleCurrencySettings />
			) }
		</MultiCurrencySettingsContext.Provider>
	);
};

ReactDOM.render(
	<UnbundledWpComponentsProvider>
		<MultiCurrencySettingsPage />
	</UnbundledWpComponentsProvider>,
	document.querySelector( '#wcpay_multi_currency_settings_container' )
);
