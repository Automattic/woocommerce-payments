/**
 * Internal dependencies
 */
import { useEnabledCurrencies, useCurrencies } from 'wcpay/data';

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	CheckboxControl,
	PanelBody,
	RangeControl,
} from '@wordpress/components';
import { registerBlockType } from '@wordpress/blocks';
import {
	ColorPaletteControl,
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
	icon: 'money-alt',
	category: 'widgets',
	attributes: {
		symbol: {
			type: 'boolean',
			default: true,
		},
		flag: {
			type: 'boolean',
			default: false,
		},
		fontSize: {
			type: 'integer',
			default: 13,
		},
		fontLineHeight: {
			type: 'float',
			default: 1.5,
		},
		fontColor: {
			type: 'string',
			default: '#000000',
		},
		border: {
			type: 'boolean',
			default: true,
		},
		borderRadius: {
			type: 'integer',
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
				symbol,
				flag,
				fontSize,
				fontLineHeight,
				fontColor,
				border,
				borderRadius,
				borderColor,
				backgroundColor,
			},
			setAttributes,
		} = props;

		// eslint-disable-next-line react-hooks/rules-of-hooks
		const { enabledCurrencies } = useEnabledCurrencies();
		// eslint-disable-next-line react-hooks/rules-of-hooks
		const { isLoading } = useCurrencies();
		const enabledKeys = enabledCurrencies
			? Object.keys( enabledCurrencies )
			: [];

		// In case there is a problem retrieving enabled currencies.
		const placeholders = [
			{ id: 'usd', code: 'USD', flag: '🇺🇸', symbol: '$' },
			{ id: 'eur', code: 'EUR', flag: '🇪🇺', symbol: '€' },
			{ id: 'jpy', code: 'JPY', flag: '🇯🇵', symbol: '¥' },
		];

		const styles = {
			div: {
				lineHeight: fontLineHeight,
			},
			select: {
				fontSize: fontSize,
				color: fontColor,
				backgroundColor: backgroundColor,
				borderWidth: border ? '1px' : '0px',
				borderColor: borderColor,
				borderRadius: borderRadius,
			},
		};

		// eslint-disable-next-line react-hooks/rules-of-hooks
		const blockProps = useBlockProps();

		const onChangeFlag = ( newFlag ) => {
			setAttributes( { flag: newFlag } );
		};

		const onChangeSymbol = ( newSymbol ) => {
			setAttributes( { symbol: newSymbol } );
		};

		const onChangeFontSize = ( newFontSize ) => {
			setAttributes( {
				fontSize: newFontSize === undefined ? 11 : newFontSize,
			} );
		};

		const onChangeFontLineHeight = ( newFontLineHeight ) => {
			setAttributes( {
				fontLineHeight:
					newFontLineHeight === undefined
						? 'normal'
						: newFontLineHeight,
			} );
		};

		const onChangeFontColor = ( newFontColor ) => {
			setAttributes( {
				fontColor:
					newFontColor === undefined ? '#000000' : newFontColor,
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
				backgroundColor:
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
							label={ __(
								'Border radius',
								'woocommerce-payments'
							) }
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
							label={ __( 'Size', 'woocommerce-payments' ) }
							value={ fontSize }
							onChange={ onChangeFontSize }
							min={ 6 }
							max={ 48 }
						/>
						<RangeControl
							label={ __(
								'Line height',
								'woocommerce-payments'
							) }
							value={ fontLineHeight }
							onChange={ onChangeFontLineHeight }
							min={ 1 }
							max={ 3 }
							step={ 0.1 }
						/>
					</PanelBody>
					<PanelBody
						title={ __( 'Color settings', 'woocommerce-payments' ) }
					>
						<ColorPaletteControl
							onChange={ onChangeFontColor }
							value={ fontColor }
							label={ __( 'Text', 'woocommerce-payments' ) }
						/>

						<ColorPaletteControl
							onChange={ onChangeBackgroundColor }
							value={ backgroundColor }
							label={ __( 'Background', 'woocommerce-payments' ) }
						/>

						<ColorPaletteControl
							onChange={ onChangeBorderColor }
							value={ borderColor }
							label={ __( 'Border', 'woocommerce-payments' ) }
						/>
					</PanelBody>
				</InspectorControls>

				<div className="currency-switcher-holder" style={ styles.div }>
					<select name="currency" style={ styles.select }>
						{ ! isLoading &&
							enabledCurrencies &&
							enabledKeys.map( ( code ) => (
								<option
									key={ enabledCurrencies[ code ].id }
									value={ enabledCurrencies[ code ].code }
								>
									{ flag
										? enabledCurrencies[ code ].flag + ' '
										: '' }
									{ symbol
										? enabledCurrencies[ code ].symbol + ' '
										: '' }
									{ enabledCurrencies[ code ].code }
								</option>
							) ) }
						{ isLoading &&
							[ 0, 1, 2 ].map( ( i ) => (
								<option
									key={ placeholders[ i ].id }
									value={ placeholders[ i ].code }
								>
									{ flag ? placeholders[ i ].flag + ' ' : '' }
									{ symbol
										? placeholders[ i ].symbol + ' '
										: '' }
									{ placeholders[ i ].code }
								</option>
							) ) }
					</select>
				</div>
			</div>
		);
	},
	save: () => {
		// Return null from the save function, because we need to use a dynamic block.
		// https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/creating-dynamic-blocks/
		return null;
	},
} );
