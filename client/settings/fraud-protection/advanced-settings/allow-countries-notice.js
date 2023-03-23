/**
 * External dependencies
 */

import React, { useContext, useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import FraudPreventionSettingsContext from './context';
import FraudProtectionRuleCardNotice from './rule-card-notice';
import { getSettingCountries, getSupportedCountriesType } from './utils';

const getNoticeText = ( filterType, blocking ) => {
	if ( 'all_except' === filterType ) {
		return blocking
			? __(
					'Orders from these countries will be blocked by the filter: ',
					'woocommerce-payments'
			  )
			: __(
					'Orders from these countries will be screened by the filter: ',
					'woocommerce-payments'
			  );
	} else if ( 'specific' === filterType ) {
		return blocking
			? __(
					'Orders outside from these countries will be blocked by the filter: ',
					'woocommerce-payments'
			  )
			: __(
					'Orders outside from these countries will be screened by the filter: ',
					'woocommerce-payments'
			  );
	}
};

const AllowedCountriesNotice = ( { setting } ) => {
	const { protectionSettingsUI, protectionSettingsChanged } = useContext(
		FraudPreventionSettingsContext
	);
	const [ isBlocking, setIsBlocking ] = useState(
		protectionSettingsUI[ setting ]?.block ?? false
	);
	useEffect( () => {
		setIsBlocking( protectionSettingsUI[ setting ]?.block ?? false );
	}, [ protectionSettingsUI, setting, protectionSettingsChanged ] );

	const supportedCountriesType = getSupportedCountriesType();
	const settingCountries = getSettingCountries();

	if ( 'all' === supportedCountriesType ) {
		return (
			<FraudProtectionRuleCardNotice type={ 'warning' }>
				{ __(
					'Enabling this filter will not have any effect because you are selling to all countries.',
					'woocommerce-payments'
				) }
			</FraudProtectionRuleCardNotice>
		);
	}
	return (
		<FraudProtectionRuleCardNotice type={ 'info' }>
			{ getNoticeText( supportedCountriesType, isBlocking ) }
			<strong>
				{ settingCountries
					.map( ( countryCode ) => {
						return (
							window.wcSettings.countries[ countryCode ] ?? false
						);
					} )
					.filter( ( element ) => element )
					.join( ', ' ) }
			</strong>
		</FraudProtectionRuleCardNotice>
	);
};

export default AllowedCountriesNotice;
