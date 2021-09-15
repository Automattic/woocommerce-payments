/**
 * Internal dependencies
 */
import {
	useCurrencies,
	useDefaultCurrency,
	useEnabledCurrencies,
} from 'wcpay/data';

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	CheckboxControl,
	PanelBody,
	TextControl,
	ColorPalette,
	RangeControl,
} from '@wordpress/components';
import { registerBlockType } from '@wordpress/blocks';
import {
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';

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
		textSize: {
			type: 'integer',
			default: 11,
		},
		textLineHeight: {
			type: 'float',
			default: 1.2,
		},
		textColor: {
			type: 'string',
			default: '#000000',
		},
		border: {
			type: 'boolean',
			default: false,
		},
		borderRadius: {
			type: 'string',
			default: 3,
		},
		borderColor: {
			type: 'string',
			default: '#000000',
		},
		backgroundColor: {
			type: 'string',
			default: 'transparent',
		},
	},
	edit: ( props ) => {
		const {
			attributes: {
				title,
				symbol,
				flag,
				textSize,
				textLineHeight,
				textColor,
				border,
				borderRadius,
				borderColor,
				backgroundColor,
			},
			setAttributes,
		} = props;

		const colors = [
			{ name: 'red', color: '#f00' },
			{ name: 'white', color: '#fff' },
			{ name: 'blue', color: '#00f' },
		];

		const { isLoading } = useCurrencies();
		const defaultCurrency = useDefaultCurrency();
		const {
			enabledCurrencies,
		} = useEnabledCurrencies();

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

		const onChangeTextSize = ( newTextSize ) => {
			setAttributes( {
				textSize: newTextSize === undefined ? 11 : newTextSize,
			} );
		};

		const onChangeTextLineHeight = ( newTextLineHeight ) => {
			setAttributes( {
				textLineHeight:
					newTextLineHeight === undefined
						? 'normal'
						: newTextLineHeight,
			} );
		};

		const onChangeTextColor = ( newTextColor ) => {
			setAttributes( {
				textColor:
					newTextColor === undefined ? '#000000' : newTextColor,
			} );
		};

		const onChangeBorder = ( newBorder ) => {
			setAttributes( {
				border: newBorder === undefined ? false : newBorder,
			} );
		};

		const onChangeBorderRadius = ( newBorderRadius ) => {
			setAttributes( {
				borderRadius:
					newBorderRadius === undefined ? '3px' : newBorderRadius,
			} );
		};

		const onChangeBorderColor = ( newBorderColor ) => {
			setAttributes( {
				borderColor:
					newBorderColor === undefined ? '#000000' : newBorderColor,
			} );
		};

		const onChangeBackgroundColor = ( newBackgroundColor ) => {
			setAttributes( {
				borderColor:
					newBackgroundColor === undefined
						? 'transparent'
						: newBackgroundColor,
			} );
		};

		return (
			<div { ...blockProps }>
				<InspectorControls key="setting">
					<PanelBody
						title={ __(
							'Multi-Currency Settings',
							'woocommerce-payments'
						) }
					>
						<a
							href={
								'/wp-admin/admin.php?page=wc-settings&tab=wcpay_multi_currency'
							}
							target="_blank"
							rel="noreferrer"
						>
							Adjust and edit your Multi-Currency settings
						</a>
					</PanelBody>
					<PanelBody title={ __( 'Layout', 'woocommerce-payments' ) }>
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
								'Display flags',
								'woocommerce-payments'
							) }
							checked={ flag }
							onChange={ onChangeFlag }
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
							label={ __( 'Border', 'woocommerce-payments' ) }
							checked={ border }
							onChange={ onChangeBorder }
						/>

						<RangeControl
							value={ borderRadius }
							onChange={ onChangeBorderRadius }
							min={ 1 }
							max={ 20 }
						/>
					</PanelBody>
					<PanelBody
						title={ __( 'Typography', 'woocommerce-payments' ) }
					>
						<RangeControl
							value={ textSize }
							onChange={ onChangeTextSize }
							min={ 6 }
							max={ 48 }
						/>
						<RangeControl
							value={ textLineHeight }
							onChange={ onChangeTextLineHeight }
							min={ 1 }
							max={ 3 }
							step={ 0.1 }
						/>
					</PanelBody>
					<PanelBody
						title={ __( 'Color settings', 'woocommerce-payments' ) }
					>
						<ColorPalette
							onChange={ onChangeTextColor }
							value={ textColor }
							colors={ colors }
							label={ __( 'Text', 'woocommerce-payments' ) }
						/>

						<ColorPalette
							onChange={ onChangeBackgroundColor }
							value={ backgroundColor }
							colors={ colors }
							label={ __( 'Background', 'woocommerce-payments' ) }
						/>

						<ColorPalette
							onChange={ onChangeBorderColor }
							value={ borderColor }
							colors={ colors }
							label={ __( 'Border', 'woocommerce-payments' ) }
						/>
					</PanelBody>
				</InspectorControls>

				<span className="gamma widget-title">
					{ title === undefined ? 'Currency Switcher' : title }
				</span>

				<select name="currency" aria-label={ title }>
					{ enabledCurrencies.map( ( currency, i ) => (
						<option key={ i } value={ currency['code'] }>
							{ flag ? currency['flag'] + ' ' : '' }
							{ symbol ? currency['symbol'] + ' ' : '' }
							{ currency['name'] }
						</option>
					) ) }
				</select>
			</div>
		);
	},
	save: () => {
		// Return null from the save function, because we need to use a dynamic block.
		// https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/creating-dynamic-blocks/
		return null;
	},
} );
