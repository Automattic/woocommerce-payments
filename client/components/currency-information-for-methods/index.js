/**
 * External dependencies
 */
import React from 'react';
import _ from 'lodash';
import { sprintf, __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import { useCurrencies, useEnabledCurrencies } from '../../data';
import InlineNotice from '../inline-notice';
import PaymentMethodsMap from '../../payment-methods-map';

const ListToCommaSeparatedSentencePartConverter = ( items ) => {
	if ( 1 === items.length ) {
		return items[ 0 ];
	} else if ( 2 === items.length ) {
		return items.join( ' ' + __( 'and', 'woocommerce-payments' ) + ' ' );
	}
	const lastItem = items.pop();
	return (
		items.join( ', ' ) +
		' ' +
		__( 'and', 'woocommerce-payments' ) +
		' ' +
		lastItem
	);
};

const CurrencyInformationForMethods = ( { selectedMethods } ) => {
	const {
		isLoading: isLoadingCurrencyInformation,
		currencies: currencyInfo,
	} = useCurrencies();
	const { enabledCurrencies } = useEnabledCurrencies();

	if ( isLoadingCurrencyInformation ) {
		return null;
	}

	const enabledCurrenciesIds = Object.values( enabledCurrencies ).map(
		( currency ) => currency.id
	);

	const paymentMethodsWithMissingCurrencies = [];
	const missingCurrencies = [];
	const missingCurrencyLabels = [];

	selectedMethods.map( ( paymentMethod ) => {
		if ( 'undefined' !== typeof PaymentMethodsMap[ paymentMethod ] ) {
			PaymentMethodsMap[ paymentMethod ].currencies.map( ( currency ) => {
				if (
					! enabledCurrenciesIds.includes( currency.toLowerCase() )
				) {
					missingCurrencies.push( currency );

					paymentMethodsWithMissingCurrencies.push(
						PaymentMethodsMap[ paymentMethod ].label
					);

					const missingCurrencyInfo =
						currencyInfo.available[ currency ] || null;

					const missingCurrencyLabel =
						null != missingCurrencyInfo
							? missingCurrencyInfo.name +
							  ' (' +
							  ( undefined !== missingCurrencyInfo.symbol
									? missingCurrencyInfo.symbol
									: currency.toUpperCase() ) +
							  ')'
							: currency.toUpperCase();

					missingCurrencyLabels.push( missingCurrencyLabel );
				}
				return currency;
			} );
		}
		return paymentMethod;
	} );

	if ( 0 < missingCurrencies.length ) {
		return (
			<InlineNotice status="info" isDismissible={ false }>
				{ interpolateComponents( {
					mixedString: sprintf(
						__(
							"%s require additional currencies, so {{strong}}we'll add %s to your store{{/strong}}. " +
								'You can view & manage currencies later in settings.',
							'woocommerce-payments'
						),
						ListToCommaSeparatedSentencePartConverter(
							_.uniq( paymentMethodsWithMissingCurrencies )
						),
						ListToCommaSeparatedSentencePartConverter(
							_.uniq( missingCurrencyLabels )
						)
					),
					components: {
						strong: <strong />,
					},
				} ) }
			</InlineNotice>
		);
	}
	return null;
};

const CurrencyInformationForMethodsWrapper = ( props ) => {
	return <CurrencyInformationForMethods { ...props } />;
};

export default CurrencyInformationForMethodsWrapper;
