/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, RadioControl } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';

/**
 * Internal dependencies
 */
import {
	useDigitalWalletsButtonActionType,
	useDigitalWalletsButtonSize,
	useDigitalWalletsButtonTheme,
} from '../../data';

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
				'Default {{helpText}}(40 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'default',
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
				'Large {{helpText}}(56 px){{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'large',
	},
];
const buttonActionOptions = [
	{
		label: __( 'Only icon', 'woocommerce-payments' ),
		value: 'only-icon',
	},
	{
		label: __( 'Buy', 'woocommerce-payments' ),
		value: 'buy',
	},
	{
		label: __( 'Donate', 'woocommerce-payments' ),
		value: 'donate',
	},
	{
		label: __( 'Book', 'woocommerce-payments' ),
		value: 'book',
	},
];

const makeButtonThemeText = ( string ) =>
	interpolateComponents( {
		mixedString: string,
		components: {
			br: <br />,
			helpText: (
				<span className="payment-method-settings__option-help-text" />
			),
		},
	} );
const buttonThemeOptions = [
	{
		label: makeButtonThemeText(
			__(
				'Dark {{br/}}{{helpText}}Recommended for white or light-colored backgrounds with high contrast.{{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'dark',
	},
	{
		label: makeButtonThemeText(
			__(
				'Light {{br/}}{{helpText}}Recommended for dark or colored backgrounds with high contrast.{{/helpText}}',
				'woocommerce-payments'
			)
		),
		value: 'light',
	},
];

const DigitalWalletsSettings = () => {
	const [ actionType, setActionType ] = useDigitalWalletsButtonActionType();
	const [ size, setSize ] = useDigitalWalletsButtonSize();
	const [ theme, setTheme ] = useDigitalWalletsButtonTheme();

	return (
		<Card>
			<CardBody>
				<h4>{ __( 'Call to action', 'woocommerce-payments' ) }</h4>
				<RadioControl
					label={ __( 'Call to action', 'woocommerce-payments' ) }
					hideLabelFromVision
					help={ __(
						'Select a button label that fits best with the flow of purchase or payment experience on your store.',
						'woocommerce-payments'
					) }
					selected={ actionType }
					options={ buttonActionOptions }
					onChange={ setActionType }
				/>
				<h4>{ __( 'Appearance', 'woocommerce-payments' ) }</h4>
				<RadioControl
					help={ __(
						'Note that larger buttons are more suitable for mobile use.',
						'woocommerce-payments'
					) }
					label={ __( 'Size', 'woocommerce-payments' ) }
					selected={ size }
					options={ buttonSizeOptions }
					onChange={ setSize }
				/>
				<RadioControl
					label={ __( 'Theme', 'woocommerce-payments' ) }
					selected={ theme }
					options={ buttonThemeOptions }
					onChange={ setTheme }
				/>
			</CardBody>
		</Card>
	);
};

export default DigitalWalletsSettings;
