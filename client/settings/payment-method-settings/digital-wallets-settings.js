/** @format */
/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, RadioControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './index.scss';

const DigitalWalletsSettings = () => {
	const [ cta, setCta ] = useState( 'buy' );
	const [ size, setSize ] = useState( 'default' );
	const [ theme, setTheme ] = useState( 'dark' );

	return (
		<>
			<Card>
				<CardBody>
					<h4>{ __( 'Call to action', 'woocommerce-payments' ) }</h4>
					<RadioControl
						help={ __(
							'Select a button label that fits best with the flow of purchase or payment experience on your store.',
							'woocommerce-payments'
						) }
						selected={ cta }
						options={ [
							{
								label: __(
									'Only icon',
									'woocommerce-payments'
								),
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
						] }
						onChange={ setCta }
					/>
					<h4>{ __( 'Appearance', 'woocommerce-payments' ) }</h4>
					<RadioControl
						help={ __(
							'Note that larger buttons are more suitable for mobile use.',
							'woocommerce-payments'
						) }
						label={ __( 'Size', 'woocommerce-payments' ) }
						selected={ size }
						options={ [
							{
								label: __(
									'Default (40 px)',
									'woocommerce-payments'
								),
								value: 'default',
							},
							{
								label: __(
									'Medium (48 px)',
									'woocommerce-payments'
								),
								value: 'medium',
							},
							{
								label: __(
									'Large (56 px)',
									'woocommerce-payments'
								),
								value: 'large',
							},
						] }
						onChange={ setSize }
					/>
					<RadioControl
						help={ __(
							// eslint-disable-next-line max-len
							'Dark is recommended for white or light-colored backgrounds and light is recommended for dark or colored backgrounds.',
							'woocommerce-payments'
						) }
						label={ __( 'Theme', 'woocommerce-payments' ) }
						selected={ theme }
						options={ [
							{
								label: __( 'Dark', 'woocommerce-payments' ),
								value: 'dark',
							},
							{
								label: __( 'Light', 'woocommerce-payments' ),
								value: 'light',
							},
						] }
						onChange={ setTheme }
					/>
				</CardBody>
			</Card>
			<div className="woocommerce-payments_digital-wallets__action">
				<Button
					isPrimary
					onClick={ () => {
						return false;
					} }
				>
					{ __( 'Save changes', 'woocommerce-payments' ) }
				</Button>
			</div>
		</>
	);
};

export default DigitalWalletsSettings;
