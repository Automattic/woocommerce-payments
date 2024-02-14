/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { uniq } from 'lodash';
import { sprintf, __, _n } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { useCurrencies, useEnabledCurrencies } from '../../data';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import InlineNotice from 'components/inline-notice';
import PaymentMethodsMap from '../../payment-methods-map';

const formatListOfItems = ( items ) => {
	if ( items.length === 1 ) {
		return items[ 0 ];
	}

	if ( items.length === 2 ) {
		return items.join( ' ' + __( 'and', 'woocommerce-payments' ) + ' ' );
	}

	const lastItem = items.pop();
	return sprintf(
		__( '%s, and %s', 'woocommerce-payments' ),
		items.join( ', ' ),
		lastItem
	);
};

export const BuildMissingCurrenciesTooltipMessage = (
	paymentMethodLabel,
	missingCurrencies
) => {
	return sprintf(
		__(
			'%s requires the %s %s. In order to enable ' +
				'the payment method, you must add %s to your store.',
			'woocommerce-payments'
		),
		paymentMethodLabel,
		formatListOfItems( missingCurrencies ),
		_n(
			'currency',
			'currencies',
			missingCurrencies.length,
			'woocommerce-payments'
		),
		_n(
			'this currency',
			'these currencies',
			missingCurrencies.length,
			'woocommerce-payments'
		)
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

	let paymentMethodsWithMissingCurrencies = [];
	let missingCurrencyLabels = [];
	const missingCurrencies = [];

	selectedMethods.map( ( paymentMethod ) => {
		if ( typeof PaymentMethodsMap[ paymentMethod ] !== 'undefined' ) {
			PaymentMethodsMap[ paymentMethod ].currencies.map( ( currency ) => {
				if (
					! enabledCurrenciesIds.includes( currency.toLowerCase() )
				) {
					missingCurrencies.push( currency );

					paymentMethodsWithMissingCurrencies.push(
						PaymentMethodsMap[ paymentMethod ].label
					);

					const missingCurrencyInfo =
						currencyInfo &&
						currencyInfo.available &&
						currencyInfo.available[ currency ];

					const missingCurrencyLabel =
						missingCurrencyInfo != null
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

	missingCurrencyLabels = uniq( missingCurrencyLabels );
	paymentMethodsWithMissingCurrencies = uniq(
		paymentMethodsWithMissingCurrencies
	);

	if ( missingCurrencyLabels.length > 0 ) {
		return (
			<InlineNotice icon status="info" isDismissible={ false }>
				{ interpolateComponents( {
					mixedString: sprintf(
						__(
							"%s %s %s additional %s, so {{strong}}we'll add %s to your store{{/strong}}. " +
								'You can view & manage currencies later in settings.',
							'woocommerce-payments'
						),
						formatListOfItems(
							paymentMethodsWithMissingCurrencies
						),
						_n(
							'requires',
							'require',
							paymentMethodsWithMissingCurrencies.length,
							'woocommerce-payments'
						),
						missingCurrencyLabels.length === 1 ? 'an' : '',
						_n(
							'currency',
							'currencies',
							missingCurrencyLabels.length,
							'woocommerce-payments'
						),
						formatListOfItems( missingCurrencyLabels )
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
	const {
		featureFlags: { multiCurrency },
	} = useContext( WCPaySettingsContext );

	// Prevents loading currency data when the feature flag is disabled.
	if ( ! multiCurrency ) return null;

	return <CurrencyInformationForMethods { ...props } />;
};

export default CurrencyInformationForMethodsWrapper;
