/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { sprintf, __ } from '@wordpress/i18n';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import { useCurrencies, useEnabledCurrencies } from '../../data';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import InlineNotice from '../inline-notice';
import PaymentMethodsMap from '../../payment-methods-map';

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

	const notices = [];
	const missingCurrencies = [];

	selectedMethods.map( ( paymentMethod ) => {
		if ( 'undefined' !== typeof PaymentMethodsMap[ paymentMethod ] ) {
			PaymentMethodsMap[ paymentMethod ].currencies.map( ( currency ) => {
				if (
					! enabledCurrenciesIds.includes( currency.toLowerCase() ) &&
					! missingCurrencies.includes( currency )
				) {
					missingCurrencies.push( currency );

					const paymentMethodsUsingCurrency = selectedMethods
						.map( ( pm ) => {
							return 0 <
								PaymentMethodsMap[ pm ].currencies.filter(
									( c ) => currency === c
								).length
								? PaymentMethodsMap[ pm ].label
								: false;
						} )
						.filter( ( _ ) => _ );

					const missingCurrency =
						currencyInfo.available[ currency ] || null;

					notices.push(
						<InlineNotice
							key={ currency }
							status="info"
							isDismissible={ false }
						>
							{ interpolateComponents( {
								mixedString: sprintf(
									__(
										"%s requires an additional currency, so {{strong}}we'll add %s to your store{{/strong}}. " +
											'You can view & manage currencies later in settings.',
										'woocommerce-payments'
									),
									paymentMethodsUsingCurrency.join( ', ' ),
									null != missingCurrency
										? missingCurrency.name +
												' (' +
												( undefined !==
												missingCurrency.symbol
													? missingCurrency.symbol
													: currency.toUpperCase() ) +
												')'
										: currency.toUpperCase()
								),
								components: {
									strong: <strong />,
								},
							} ) }
						</InlineNotice>
					);
				}
				return currency;
			} );
		}
		return paymentMethod;
	} );

	if ( 0 < notices.length ) {
		return <div style={ { marginTop: '24px' } }>{ notices }</div>;
	}
	return null;
};

const CurrencyInformationForMethodsWrapper = ( props ) => {
	const {
		featureFlags: { multiCurrency },
	} = useContext( WCPaySettingsContext );

	// prevents loading currency data when the feature flag is disabled
	if ( ! multiCurrency ) return null;

	return <CurrencyInformationForMethods { ...props } />;
};

export default CurrencyInformationForMethodsWrapper;
