/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { getPaymentMethodSettingsUrl } from '../../utils';

/**
 * Internal dependencies
 */
import { Button, CheckboxControl } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import methodsConfiguration from '../../payment-methods-map';

const AmazonPayExpressCheckoutItem = (): React.ReactElement => {
	const [ isAmazonPayEnabled, setIsAmazonPayEnabled ] = useState( false );

	const {
		icon: AmazonPayIcon,
		label,
		description,
	} = methodsConfiguration.amazon_pay;

	return (
		<li className="express-checkout" id="express-checkouts-amazon-pay">
			<div className="express-checkout__row">
				<div className="express-checkout__checkbox">
					<CheckboxControl
						label={ label }
						checked={ isAmazonPayEnabled }
						onChange={ setIsAmazonPayEnabled }
						data-testid="amazon-pay-toggle"
						__nextHasNoMarginBottom
					/>
				</div>
				<div className="express-checkout__text-container">
					<div>
						<div className="express-checkout__subgroup">
							<div className="express-checkout__icon">
								<AmazonPayIcon />
							</div>
							<div className="express-checkout__label express-checkout__label-mobile">
								{ label }
							</div>
							<div className="express-checkout__label-container">
								<div className="express-checkout__label express-checkout__label-desktop">
									{ label }
								</div>
								<div className="express-checkout__description">
									{ description + ' ' }
									{ interpolateComponents( {
										mixedString: __(
											/* eslint-disable-next-line max-len */
											'By activating this feature, you accept ' +
												'{{stripeLink}}Stripe{{/stripeLink}} and ' +
												"{{amazonLink}}Amazon{{/amazonLink}}'s terms of use.",
											'woocommerce-payments'
										),
										/* eslint-disable jsx-a11y/anchor-has-content */
										components: {
											stripeLink: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://stripe.com/legal/ssa"
												/>
											),
											amazonLink: (
												<a
													target="_blank"
													rel="noreferrer"
													href="https://stripe.com/legal/amazon-pay"
												/>
											),
										},
										/* eslint-enable jsx-a11y/anchor-has-content */
									} ) }
								</div>
							</div>
						</div>
					</div>
					<div className="express-checkout__link">
						<Button
							href={ getPaymentMethodSettingsUrl( 'amazon_pay' ) }
							isSecondary
						>
							{ __( 'Customize', 'woocommerce-payments' ) }
						</Button>
					</div>
				</div>
			</div>
		</li>
	);
};

export default AmazonPayExpressCheckoutItem;
