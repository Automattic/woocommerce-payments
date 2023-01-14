/**
 * External dependencies
 */
import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import marketingSingleOptInCountries from './marketing-single-optin-countries';

const PlatformCheckoutMarketingOptIn = ( {
	country,
	isMarketOptInChecked,
	onChange,
} ) => {
	const [ needMarketingSingleOptIn, setNeedMarketingSingleOptIn ] = useState(
		false
	);

	useEffect( () => {
		const countryNeedSingleOptIn = marketingSingleOptInCountries.includes(
			country
		);

		setNeedMarketingSingleOptIn( countryNeedSingleOptIn );
	}, [ country ] );

	return (
		<>
			{ needMarketingSingleOptIn ? (
				<div>
					<label htmlFor="platform_checkout_marketing_optin">
						<input
							type="checkbox"
							checked={ isMarketOptInChecked }
							onChange={ onChange }
							name="platform_checkout_marketing_optin"
							id="platform_checkout_marketing_optin"
							value="true"
							className="save-details-checkbox"
							aria-checked={ isMarketOptInChecked }
						/>
						<span>
							{ __(
								'Opt-in for marketing messages',
								'woocommerce-payments'
							) }
						</span>
					</label>
				</div>
			) : (
				<input
					type="hidden"
					name="platform_checkout_marketing_optin"
					value="true"
				/>
			) }
		</>
	);
};

export default PlatformCheckoutMarketingOptIn;
