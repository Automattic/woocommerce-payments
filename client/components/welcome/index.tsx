/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { CardHeader, Flex, FlexItem } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Internal dependencies
 */
import { useAllDepositsOverviews } from 'data';
import { useSelectedCurrency } from 'overview/hooks';
import FilterSelectControl from 'components/filter-select-control';
import { getCurrency } from 'utils/currency';
import { useCurrentWpUser } from './hooks';
import './style.scss';

type TimeOfDay = 'morning' | 'afternoon' | 'evening';
const greetingStrings = {
	withName: {
		/** translators: %s name of the person being greeted. */
		morning: __( 'Good morning, %s', 'woocommerce-payments' ),
		/** translators: %s name of the person being greeted. */
		afternoon: __( 'Good afternoon, %s', 'woocommerce-payments' ),
		/** translators: %s name of the person being greeted. */
		evening: __( 'Good evening, %s', 'woocommerce-payments' ),
	},
	withoutName: {
		morning: __( 'Good morning', 'woocommerce-payments' ),
		afternoon: __( 'Good afternoon', 'woocommerce-payments' ),
		evening: __( 'Good evening', 'woocommerce-payments' ),
	},
};

/**
 * Calculates the time of day based on the browser's time.
 *
 * @param {Date} date A date object to calculate the time of day for. Defaults to the current time.
 * @return {TimeOfDay} The time of day. One of 'morning', 'afternoon' or 'evening'.
 */
const getTimeOfDayString = ( date: Date = new Date() ): TimeOfDay => {
	const hour = date.getHours();
	// Morning 5am -11.59am
	if ( hour >= 5 && hour < 12 ) {
		return 'morning';
	}
	// Afternoon 12pm – 4:59pm
	if ( hour >= 12 && hour < 17 ) {
		return 'afternoon';
	}
	// Evening 5pm – 4:59am
	return 'evening';
};

/**
 * Returns a greeting string based on the time of day and a given name, if provided.
 *
 * @param {string} name A name to include in the greeting, optional.
 * @param {Date} date A date object to calculate the time of day for. Defaults to the current time.
 * @return {string} A greeting string.
 */
const getGreeting = ( name?: string, date: Date = new Date() ): string => {
	const timeOfDay = getTimeOfDayString( date );
	let greeting = greetingStrings.withoutName[ timeOfDay ];
	if ( name ) {
		greeting = sprintf( greetingStrings.withName[ timeOfDay ], name );
	}
	greeting += ' 👋';
	return greeting;
};

/**
 * Returns an select option object for a currency select control.
 */
const getCurrencyOption = (
	currency: string
): {
	name: string;
	key: string;
} => {
	const { code, symbol } = getCurrency( currency )?.getCurrencyConfig() || {};
	const currencySymbolDecoded = decodeEntities( symbol || '' );

	if (
		// Show just the currency the currency code is used as the name, e.g. 'EUR'
		// if no currency config is found,
		! code ||
		! symbol ||
		// or if the symbol is identical to the currency code, e.g. 'CHF CHF'.
		currencySymbolDecoded === code
	) {
		return {
			name: currency.toUpperCase(),
			key: currency,
		};
	}
	return {
		// A rendered name of the currency with symbol, e.g. `EUR €`.
		name: `${ code } ${ currencySymbolDecoded }`,
		key: currency,
	};
};

/**
 * Custom hook to get the selected currency from the URL query parameter 'selected_currency'.
 * If no currency is selected, the store's default currency will be selected.
 */
const useSelectedCurrencyWithDefault = ( currencies: string[] ) => {
	const { selectedCurrency, setSelectedCurrency } = useSelectedCurrency();

	useEffect( () => {
		// The selected currency is invalid if:
		// * no currency is explicitly selected via URL query, or
		// * no currency is found for the provided query parameter.
		const isSelectedCurrencyInvalid =
			! selectedCurrency ||
			! currencies.find(
				( currency ) =>
					currency.toLowerCase() === selectedCurrency.toLowerCase()
			);

		// Select the store's default currency if the selected currency is invalid.
		if ( isSelectedCurrencyInvalid && currencies.length > 0 ) {
			setSelectedCurrency( currencies[ 0 ].toLowerCase() );
		}
	}, [ currencies, selectedCurrency, setSelectedCurrency ] );

	return { selectedCurrency, setSelectedCurrency };
};

/**
 * Renders a currency select input used for the Payments Overview page.
 * Should only be rendered if there are multiple deposit currencies available.
 */
const CurrencySelect: React.FC< {
	currencies: string[];
} > = ( { currencies } ) => {
	const currencyOptions = currencies.map( getCurrencyOption );
	const {
		selectedCurrency,
		setSelectedCurrency,
	} = useSelectedCurrencyWithDefault( currencies );

	return (
		<FilterSelectControl
			label="Currency"
			value={ currencyOptions.find(
				( option ) => option.key === selectedCurrency
			) }
			options={ currencyOptions }
			onChange={ ( { selectedItem } ) =>
				// TODO: record tracks event.
				selectedItem && setSelectedCurrency( selectedItem?.key )
			}
		/>
	);
};

/**
 * Renders a welcome card header with a greeting and a currency select input if supported.
 */
const Welcome: React.FC = () => {
	const { user } = useCurrentWpUser();
	const greeting = getGreeting( user?.first_name );
	const { overviews } = useAllDepositsOverviews();
	const currencies =
		overviews?.currencies.map( ( currencyObj ) => currencyObj.currency ) ||
		[];
	const renderCurrencySelect = currencies.length > 1;

	return (
		<CardHeader className="wcpay-welcome">
			<Flex
				align="center"
				justify="space-between"
				className="wcpay-welcome__flex"
			>
				<FlexItem className="wcpay-welcome__flex__greeting">
					{ greeting }
				</FlexItem>

				{ renderCurrencySelect && (
					<FlexItem>
						<CurrencySelect currencies={ currencies } />
					</FlexItem>
				) }
			</Flex>
		</CardHeader>
	);
};

export default Welcome;
