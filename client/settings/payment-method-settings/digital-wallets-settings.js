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
						help="Select a button label that fits best with the flow of purchase or payment experience on your store."
						selected={ cta }
						options={ [
							{ label: 'Only icon', value: 'only-icon' },
							{ label: 'Buy', value: 'buy' },
							{ label: 'Donate', value: 'donate' },
							{ label: 'Book', value: 'book' },
						] }
						onChange={ ( option ) => {
							setCta( option );
						} }
					/>
					<h4>{ __( 'Appearance', 'woocommerce-payments' ) }</h4>
					<RadioControl
						help="Note that larger buttons are more suitable for mobile use."
						label="Size"
						selected={ size }
						options={ [
							{ label: 'Default (40 px)', value: 'default' },
							{ label: 'Medium (48 px)', value: 'medium' },
							{ label: 'Large (56 px)', value: 'large' },
						] }
						onChange={ ( option ) => {
							setSize( option );
						} }
					/>
					<RadioControl
						// eslint-disable-next-line max-len
						help="Dark is recommended for white or light-colored backgrounds and light is recommended for dark or colored backgrounds."
						label="Theme"
						selected={ theme }
						options={ [
							{
								label: 'Dark',
								value: 'dark',
							},
							{
								label: 'Light',
								value: 'light',
							},
						] }
						onChange={ ( option ) => {
							setTheme( option );
						} }
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
