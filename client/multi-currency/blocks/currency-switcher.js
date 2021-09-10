/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	CheckboxControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { createElement, useState } from '@wordpress/element';
// import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import {
	useCurrencies,
	useDefaultCurrency,
	useEnabledCurrencies,
} from 'wcpay/data';

registerBlockType( 'woocommerce-payments/multi-currency-switcher', {
	apiVersion: 2,
	title: __( 'Currency Switcher', 'woocommerce-payments' ),
	description: __(
		'Let your customers switch between your enabled currencies',
		'woocommerce-payments'
	),
	icon: 'smiley',
	category: 'widgets',
	attributes: {
		title: {
			type: 'array',
		},
		symbol: {
			type: 'boolean',
		},
		flag: {
			type: 'boolean',
		},
	},
	edit: ( props ) => {
		const {
			attributes: { title, symbol, flag },
			setAttributes,
			className,
		} = props;

		const blockProps = useBlockProps();

		const onChangeTitle = ( title ) => {
			setAttributes( { title: title } );
		};

		const [ isFlagChecked, onChangeFlag ] = useState( flag );
		const [ isSymbolChecked, onChangeSymbol ] = useState( symbol );

		return (
			<div { ...blockProps }>
				<h3>{ __( 'Currency Switcher', 'woocommerce-payments' ) }</h3>

				<TextControl
					label={ __( 'Title', 'woocommerce-payments' ) }
					// help={ __(
					// 	'The title that will appear above the currency switcher. Leave blank for no title.',
					// 	'woocommerce-payments'
					// ) }
					onChange={ onChangeTitle }
					value={ title }
				/>
				<CheckboxControl
					label={ __(
						'Display currency symbols',
						'woocommerce-payments'
					) }
					checked={ isSymbolChecked }
					onChange={ onChangeSymbol }
				/>
				<CheckboxControl
					label={ __(
						'Display flags on supported devices',
						'woocommerce-payments'
					) }
					checked={ isFlagChecked }
					onChange={ onChangeFlag }
				/>
			</div>
		);
	},
	save: ( props ) => {
		// const defaultCurrency = useDefaultCurrency();
		// const enabledCurrencies = useEnabledCurrencies();

		// console.log( defaultCurrency );
		// console.log( enabledCurrencies );

		const onChangeCurrency = ( currency ) => {
			// TODO: All this would need to do is submit the form so maybe could be inline.
			print( currency );
		};

		const blockProps = useBlockProps.save();

		return (
			<div { ...blockProps }>
				<span className="widget-title">{ props.attributes.title }</span>
				<form>
					<select
						name="currency"
						aria-label={ props.attributes.title }
						onBlur="this.form.submit()"
					>
						<option value="USD">USD</option>
						<option value="EUR">EUR</option>
						<option value="JPY">JPY</option>
					</select>
				</form>
			</div>
			// <div { ...blockProps }>
			// 	<h3>This is the Currency Switcher Widget</h3>
			// </div>
			// <SelectControl
			// 	{ ...blockProps }
			// 	label={ props.attributes.title }
			// 	value={ [ 'a', 'b', 'c' ] }
			// 	onChange={ onChangeCurrency }
			// 	options={ [
			// 		{ value: 'jpy', label: 'JPY' },
			// 		{ value: 'eur', label: 'EUR' },
			// 		{ value: 'usd', label: 'USD' },
			// 	] }
			// />
		);
	},
} );
