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
	checked,
	onChange,
	isBlocksCheckout,
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
				<div
					className={
						isBlocksCheckout ? 'wc-block-components-checkbox' : ''
					}
					style={ { marginTop: 16 } }
				>
					<label htmlFor="platform_checkout_marketing_optin">
						<input
							type="checkbox"
							checked={ checked }
							onChange={ onChange }
							name="platform_checkout_marketing_optin"
							id="platform_checkout_marketing_optin"
							value="true"
							className={ `save-details-checkbox ${
								isBlocksCheckout
									? 'wc-block-components-checkbox__input'
									: ''
							}` }
							aria-checked={ checked }
						/>
						{ isBlocksCheckout && (
							<svg
								className="wc-block-components-checkbox__mark"
								aria-hidden="true"
								xmlns="http://www.w3.org/2000/svg"
								viewBox="0 0 24 20"
							>
								<path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" />
							</svg>
						) }
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
