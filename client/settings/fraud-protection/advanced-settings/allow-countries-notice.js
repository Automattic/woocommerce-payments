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

const AllowedCountriesNotice = ( { setting } ) => {
	const {
		advancedFraudProtectionSettings,
		protectionSettingsChanged,
	} = useContext( FraudPreventionSettingsContext );
	const [ isBlocking, setIsBlocking ] = useState(
		advancedFraudProtectionSettings[ setting ]?.block ?? false
	);
	useEffect( () => {
		setIsBlocking(
			advancedFraudProtectionSettings[ setting ]?.block ?? false
		);
	}, [
		advancedFraudProtectionSettings,
		setting,
		protectionSettingsChanged,
	] );

	const coreSettingsContainer =
		window.wcSettings.admin.preloadSettings.general;
	const areAllCountriesAllowed =
		'all' === coreSettingsContainer.woocommerce_allowed_countries;
	const countriesAllowedToSellTo =
		coreSettingsContainer.woocommerce_specific_allowed_countries;
	const countriesNotAllowedToSellTo =
		coreSettingsContainer.woocommerce_all_except_countries;
	const isSellingToSpecificCountries =
		'specific' === coreSettingsContainer.woocommerce_allowed_countries;

	return areAllCountriesAllowed ? (
		<FraudProtectionRuleCardNotice type={ 'warning' }>
			{ __(
				'Enabling this filter will not have any effect because you are selling to all countries.',
				'woocommerce-payments'
			) }
		</FraudProtectionRuleCardNotice>
	) : (
		<FraudProtectionRuleCardNotice type={ 'info' }>
			{ isSellingToSpecificCountries ? (
				<>
					{ isBlocking
						? __(
								'Orders outside from these countries will be blocked by the filter: ',
								'woocommerce-payments'
						  )
						: __(
								'Orders outside from these countries will be screened by the filter: ',
								'woocommerce-payments'
						  ) }
					<br />
					<strong>
						{ countriesAllowedToSellTo
							.map( ( d ) => {
								return window.wcSettings.countries[ d ];
							} )
							.join( ', ' ) }
					</strong>
				</>
			) : (
				<>
					{ isBlocking
						? __(
								'Orders from these countries will be blocked by the filter: ',
								'woocommerce-payments'
						  )
						: __(
								'Orders from these countries will be screened by the filter: ',
								'woocommerce-payments'
						  ) }
					<br />
					<strong>
						{ countriesNotAllowedToSellTo
							.map( ( d ) => {
								return window.wcSettings.countries[ d ];
							} )
							.join( ', ' ) }
					</strong>
				</>
			) }
		</FraudProtectionRuleCardNotice>
	);
};

export default AllowedCountriesNotice;
