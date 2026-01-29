/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import {
	Card,
	CheckboxControl,
	BaseControl,
	RadioControl,
} from '@wordpress/components';
import {
	usePaymentRequestButtonSize,
	useAmazonPayEnabledSettings,
	useAmazonPayLocations,
} from 'wcpay/data';
import interpolateComponents from '@automattic/interpolate-components';
import ExpressCheckoutSettingsNotices from './express-checkout-settings-notices';

const makeButtonSizeText = ( string ) =>
	interpolateComponents( {
		mixedString: string,
		components: {
			helpText: (
				<span className="payment-method-settings__option-muted-text" />
			),
		},
	} );

const buttonSizeOptions = [
	{
		label: makeButtonSizeText(
			__(
				'Small {{helpText}}(40 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'small',
	},
	{
		label: makeButtonSizeText(
			__(
				'Medium {{helpText}}(48 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'medium',
	},
	{
		label: makeButtonSizeText(
			__(
				'Large {{helpText}}(55 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'large',
	},
];

const GeneralSettings = () => {
	const [ size, setSize ] = usePaymentRequestButtonSize();

	return (
		<CardBody className="wcpay-card-body">
			<ExpressCheckoutSettingsNotices currentMethod="amazon_pay" />
			<RadioControl
				label={ __( 'Button size', 'woocommerce-payments' ) }
				selected={ size }
				options={ buttonSizeOptions }
				onChange={ setSize }
			/>
		</CardBody>
	);
};

const AmazonPaySettings = ( { section } ) => {
	const [
		isAmazonPayEnabled,
		updateIsAmazonPayEnabled,
	] = useAmazonPayEnabledSettings();
	const [
		amazonPayLocations,
		updateAmazonPayLocations,
	] = useAmazonPayLocations();

	const makeLocationChangeHandler = ( location ) => ( isChecked ) => {
		updateAmazonPayLocations( location, isChecked );
	};

	return (
		<Card>
			{ section === 'enable' && (
				<CardBody className="wcpay-card-body">
					<div className="wcpay-payment-request-settings__enable">
						<CheckboxControl
							className="wcpay-payment-request-settings__enable__checkbox"
							checked={ isAmazonPayEnabled }
							onChange={ updateIsAmazonPayEnabled }
							label={ __(
								'Enable Amazon Pay as an express payment button',
								'woocommerce-payments'
							) }
							help={ __(
								'Show Amazon Pay buttons on store pages for faster purchases. ' +
									'Customers with Amazon accounts can use their stored payment information.',
								'woocommerce-payments'
							) }
							__nextHasNoMarginBottom
						/>
						{ /* eslint-disable-next-line @wordpress/no-base-control-with-label-without-id */ }
						<BaseControl
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						>
							<ul className="payment-request-settings__location">
								<li>
									<CheckboxControl
										disabled={ ! isAmazonPayEnabled }
										checked={
											isAmazonPayEnabled &&
											amazonPayLocations.includes(
												'product'
											)
										}
										onChange={ makeLocationChangeHandler(
											'product'
										) }
										label={ __(
											'Show on product page',
											'woocommerce-payments'
										) }
										__nextHasNoMarginBottom
									/>
								</li>
								<li>
									<CheckboxControl
										disabled={ ! isAmazonPayEnabled }
										checked={
											isAmazonPayEnabled &&
											amazonPayLocations.includes(
												'cart'
											)
										}
										onChange={ makeLocationChangeHandler(
											'cart'
										) }
										label={ __(
											'Show on cart page',
											'woocommerce-payments'
										) }
										__nextHasNoMarginBottom
									/>
								</li>
								<li>
									<CheckboxControl
										disabled={ ! isAmazonPayEnabled }
										checked={
											isAmazonPayEnabled &&
											amazonPayLocations.includes(
												'checkout'
											)
										}
										onChange={ makeLocationChangeHandler(
											'checkout'
										) }
										label={ __(
											'Show on checkout page',
											'woocommerce-payments'
										) }
										__nextHasNoMarginBottom
									/>
								</li>
							</ul>
						</BaseControl>
					</div>
				</CardBody>
			) }

			{ section === 'general' && <GeneralSettings /> }
		</Card>
	);
};

export default AmazonPaySettings;
