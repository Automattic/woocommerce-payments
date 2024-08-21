/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { uniq } from 'lodash';
import { sprintf, __ } from '@wordpress/i18n';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import {
	useAccountDomesticCurrency,
	useCurrencies,
	useEnabledCurrencies,
} from '../../data';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';
import InlineNotice from 'components/inline-notice';
import PaymentMethodsMap from '../../payment-methods-map';
import { formatListOfItems } from 'wcpay/utils/format-list-of-items';

const CurrencyInformationForMethods = ( { selectedMethods } ) => {
	const {
		isLoading: isLoadingCurrencyInformation,
		currencies: currencyInfo,
	} = useCurrencies();
	const { enabledCurrencies } = useEnabledCurrencies();
	const stripeAccountDomesticCurrency = useAccountDomesticCurrency().toUpperCase();

	if ( isLoadingCurrencyInformation ) {
		return null;
	}

	const enabledCurrenciesIds = Object.values( enabledCurrencies ).map(
		( currency ) => currency.id
	);

	let paymentMethodsWithMissingCurrencies = [];
	let missingCurrencyLabels = [];

	selectedMethods.forEach( ( paymentMethod ) => {
		// in case of payment methods accepting only domestic payments, we shouldn't add _all_ the currencies defined on the payment method.
		// instead, we should ensure that the merchant account's currency is set.
		const paymentMethodInformation = PaymentMethodsMap[ paymentMethod ];
		if ( ! paymentMethodInformation ) return;

		let currencies = paymentMethodInformation.currencies || [];
		if ( paymentMethodInformation.accepts_only_domestic_payment ) {
			currencies = [ stripeAccountDomesticCurrency ];
		}

		currencies.forEach( ( currency ) => {
			if ( enabledCurrenciesIds.includes( currency.toLowerCase() ) ) {
				return;
			}

			paymentMethodsWithMissingCurrencies.push(
				paymentMethodInformation.label
			);
			const missingCurrencyInfo = currencyInfo?.available?.[ currency ];

			const missingCurrencyLabel =
				missingCurrencyInfo != null
					? `${ missingCurrencyInfo.name } (${
							undefined !== missingCurrencyInfo.symbol
								? missingCurrencyInfo.symbol
								: currency.toUpperCase()
					  })`
					: currency.toUpperCase();

			missingCurrencyLabels.push( missingCurrencyLabel );
		} );
	} );

	missingCurrencyLabels = uniq( missingCurrencyLabels );
	paymentMethodsWithMissingCurrencies = uniq(
		paymentMethodsWithMissingCurrencies
	);

	if ( missingCurrencyLabels.length <= 0 ) {
		return null;
	}

	let stringFormat = '';
	if (
		paymentMethodsWithMissingCurrencies.length === 1 &&
		missingCurrencyLabels.length === 1
	) {
		stringFormat = __(
			/* translators: %1: name of payment method being setup %2: name of missing currency that will be added */
			"%1$s requires an additional currency, so {{strong}}we'll add %2$s to your store{{/strong}}. " +
				'You can view & manage currencies later in settings.',
			'woocommerce-payments'
		);
	} else if (
		paymentMethodsWithMissingCurrencies.length === 1 &&
		missingCurrencyLabels.length > 1
	) {
		stringFormat = __(
			/* translators: %1: name of payment method being setup %2: list of missing currencies that will be added */
			"%1$s requires additional currencies, so {{strong}}we'll add %2$s to your store{{/strong}}. " +
				'You can view & manage currencies later in settings.',
			'woocommerce-payments'
		);
	} else if (
		paymentMethodsWithMissingCurrencies.length > 1 &&
		missingCurrencyLabels.length === 1
	) {
		stringFormat = __(
			/* translators: %1: list of payment methods being setup %2: name of missing currency that will be added */
			"%1$s require an additional currency, so {{strong}}we'll add %2$s to your store{{/strong}}. " +
				'You can view & manage currencies later in settings.',
			'woocommerce-payments'
		);
	} else {
		stringFormat = __(
			/* translators: %1: list of payment methods being setup %2: list of missing currencies that will be added */
			"%1$s require additional currencies, so {{strong}}we'll add %2$s to your store{{/strong}}. " +
				'You can view & manage currencies later in settings.',
			'woocommerce-payments'
		);
	}

	return (
		<InlineNotice icon status="info" isDismissible={ false }>
			{ interpolateComponents( {
				mixedString: sprintf(
					stringFormat,
					formatListOfItems( paymentMethodsWithMissingCurrencies ),
					formatListOfItems( missingCurrencyLabels )
				),
				components: {
					strong: <strong />,
				},
			} ) }
		</InlineNotice>
	);
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
