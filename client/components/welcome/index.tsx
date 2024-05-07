/**
 * External dependencies
 */
import React from 'react';
import { CardHeader, Flex, FlexItem } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { SelectField } from 'components/form/fields';
import { useAllDepositsOverviews } from 'data';
import { useSelectedCurrency } from 'overview/hooks';
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
 * A currency select control used in the welcome card header.
 */
const CurrencySelect: React.FC< {
	currencies: string[];
} > = ( { currencies } ) => {
	const { selectedCurrency, setSelectedCurrency } = useSelectedCurrency();

	const currencyOptions = currencies.map( ( currency ) => ( {
		name: currency.toUpperCase(),
		key: currency,
	} ) );

	return (
		<SelectField
			label="Currency"
			value={ currencyOptions.find(
				( option ) => option.key === selectedCurrency
			) }
			options={ currencyOptions }
			onChange={ ( { selectedItem } ) =>
				selectedItem && setSelectedCurrency( selectedItem?.key )
			}
		/>
	);
};

/**
 * Renders a welcome card header with a greeting and a currency select control if supported.
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
