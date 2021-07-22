/**
 * External dependencies
 */
import React, { useContext } from 'react';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import { useCurrencies, useEnabledCurrencies } from '../../data';
import './styles.scss';
import WCPaySettingsContext from '../../settings/wcpay-settings-context';

const CurrencyInformationForMethods = ( { selectedMethods } ) => {
	const { isLoading: isLoadingCurrencyInformation } = useCurrencies();
	const { enabledCurrencies } = useEnabledCurrencies();

	if ( isLoadingCurrencyInformation ) {
		return null;
	}

	// if EUR is already enabled, no need to display the info message
	const enabledCurrenciesIds = Object.values( enabledCurrencies ).map(
		( currency ) => currency.id
	);
	if ( enabledCurrenciesIds.includes( 'eur' ) ) {
		return null;
	}

	const enabledMethodsRequiringEuros = selectedMethods.filter( ( method ) =>
		[ 'giropay', 'sepa_debit', 'sofort' ].includes( method )
	);

	if ( 0 === enabledMethodsRequiringEuros.length ) {
		return null;
	}

	return (
		<Notice
			spokenMessage=""
			isDismissible={ false }
			className="wcpay-currency-notice"
		>
			{ interpolateComponents( {
				mixedString: __(
					"The selected methods require an additional currency, so {{strong}}we'll add Euro (€) to your store{{/strong}}. " +
						'You can view & manage currencies later in settings.',
					'woocommerce-payments'
				),
				components: {
					strong: <strong />,
				},
			} ) }
		</Notice>
	);
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
