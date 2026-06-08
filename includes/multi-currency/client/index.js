/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
	getQuery,
	updateQueryString,
	getHistory,
} from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import MultiCurrencySettings from './settings/multi-currency';
import SingleCurrencySettings from './settings/single-currency';
import MultiCurrencySettingsContext from './context';

const MultiCurrencySettingsPage = () => {
	const [ currencyCodeToShowSettingsFor, setCurrencyCode ] = useState(
		() => getQuery().currency || null
	);

	// The URL is the source of truth for which view is shown. Listening to
	// history changes is what makes the browser back/forward buttons (and
	// shareable deep links) switch views without a full page reload. We listen
	// on the history object rather than via addHistoryListener because the
	// latter fires its synthetic event before the URL is actually updated, so
	// reading the query inside it would give the previous currency.
	useEffect( () => {
		return getHistory().listen( () => {
			setCurrencyCode( getQuery().currency || null );
		} );
	}, [] );

	// Navigate by updating the URL (a soft pushState) so each currency view
	// gets its own browser history entry and the back button returns to the
	// list.
	const setCurrencyCodeToShowSettingsFor = ( code ) => {
		updateQueryString( { currency: code || undefined } );
	};

	return (
		<MultiCurrencySettingsContext.Provider
			value={ {
				currencyCodeToShowSettingsFor,
				setCurrencyCodeToShowSettingsFor,
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

const container = document.querySelector(
	'#wcpay_multi_currency_settings_container'
);
if ( container ) {
	const root = createRoot( container );
	root.render( <MultiCurrencySettingsPage /> );
}
