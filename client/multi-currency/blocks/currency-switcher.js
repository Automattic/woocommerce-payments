/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { CheckboxControl, TextControl } from '@wordpress/components';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

registerBlockType( 'woocommerce-payments/multi-currency-switcher', {
	apiVersion: 2,
	title: __( 'Currency Switcher', 'woocommerce-payments' ),
	description: __(
		'Let your customers switch between your enabled currencies',
		'woocommerce-payments'
	),
	icon: 'admin-site', // TODO: check the icon to use (https://developer.wordpress.org/resource/dashicons)
	category: 'widgets',
	attributes: {
		title: {
			type: 'array',
		},
		symbol: {
			type: 'boolean',
			default: true,
		},
		flag: {
			type: 'boolean',
			default: false,
		},
	},
	edit: ( props ) => {
		const {
			attributes: { title, symbol, flag },
			setAttributes,
		} = props;

		// eslint-disable-next-line react-hooks/rules-of-hooks
		const blockProps = useBlockProps();

		const onChangeTitle = ( newTitle ) => {
			setAttributes( { title: newTitle } );
		};

		const onChangeFlag = ( newFlag ) => {
			setAttributes( { flag: newFlag } );
		};

		const onChangeSymbol = ( newSymbol ) => {
			setAttributes( { symbol: newSymbol } );
		};

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
					checked={ symbol }
					onChange={ onChangeSymbol }
				/>
				<CheckboxControl
					label={ __(
						'Display flags on supported devices',
						'woocommerce-payments'
					) }
					checked={ flag }
					onChange={ onChangeFlag }
				/>
			</div>
		);
	},
	save: () => {
		// Return null from the save function, because we need to use a dynamic block.
		// https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/creating-dynamic-blocks/
		return null;
	},
} );
