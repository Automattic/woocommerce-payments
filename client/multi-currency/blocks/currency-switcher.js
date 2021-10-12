/* eslint-disable max-len */
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
	title: __( 'Currency Switcher Block', 'woocommerce-payments' ),
	description: __(
		'Let your customers switch between your enabled currencies',
		'woocommerce-payments'
	),
	icon: (
		<svg
			xmlns="http://www.w3.org/2000/svg"
			baseProfile="tiny-ps"
			viewBox="0 0 24 24"
		>
			<path
				d="M13.5 8.1h.86c.34 1.81 1.54 2.9 3.2 2.9 1.58 0 2.64-.89 2.94-2.29h-1.11c-.23.72-.86 1.21-1.82 1.21-1 0-1.77-.67-2.06-1.82h2.78v-.65H15.4L15.38 7l.02-.46h2.89v-.65h-2.78c.3-1.13 1.06-1.81 2.05-1.81.94 0 1.63.59 1.84 1.38h1.1A2.86 2.86 0 0017.56 3c-1.65 0-2.85 1.09-3.19 2.89h-.87v.65h.78l-.02.46.02.45h-.78v.65zm-9 8.57v.78h1.8c.07.24.12.49.12.73 0 .87-.79 1.64-1.9 1.84V21h6.98v-1.01H6.57v-.07c.7-.33 1.25-.95 1.25-1.79 0-.23-.04-.45-.1-.68h2.72v-.78H7.43a3.6 3.6 0 01-.35-1.33c0-.83.77-1.33 2.12-1.33.74 0 1.51.13 1.85.29v-1.01A5.9 5.9 0 009.06 13c-2.15 0-3.44.87-3.44 2.28 0 .48.18.94.37 1.39H4.5z"
				className="prefix__shp0"
			/>
			<path
				fillRule="evenodd"
				d="M7.63 10.22c-1.72-.09-3.08-.75-3.13-1.85h1.41c.13.54.64.93 1.72 1.01V7.37l-.26-.05c-1.73-.27-2.66-.8-2.66-1.73 0-1.04 1.22-1.72 2.92-1.8V3h.8v.79c1.69.09 2.91.78 2.96 1.82H9.98c-.08-.57-.69-.91-1.55-.99v1.93l.31.05c1.79.28 2.76.78 2.76 1.77 0 1.13-1.35 1.77-3.07 1.85V11h-.8v-.78zm0-3.8v-1.8c-.98.08-1.48.47-1.48.89 0 .42.4.73 1.48.91zm.8 1.07v1.9c1.15-.07 1.64-.44 1.64-.93 0-.48-.39-.76-1.64-.97z"
				className="prefix__shp0"
			/>
			<path
				d="M17.63 21v-1.61h1.97v-.67h-1.97v-.87h1.97v-.67h-1.63L20.5 13h-1.4l-2.07 3.63h-.05L14.92 13H13.5l2.53 4.18H14.4v.67h1.97v.87H14.4v.67h1.97V21h1.26z"
				opacity={ 0.8 }
			/>
		</svg>
	),
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
			default: 14,
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
		const { isLoading } = useCurrencies();
		// eslint-disable-next-line react-hooks/rules-of-hooks
		const { enabledCurrencies } = useEnabledCurrencies();

		const enabledKeys = enabledCurrencies
			? Object.keys( enabledCurrencies )
			: [];

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
				padding: '2px',
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
					newBorderRadius === undefined ? 3 : newBorderRadius,
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
							'Multi-Currency settings',
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
							{ __(
								'Adjust and edit your Multi-Currency settings',
								'woocommerce-payments'
							) }
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
					<select
						name="currency"
						disabled={ isLoading }
						style={ styles.select }
					>
						{ isLoading && (
							<option>
								{ __( 'Loading…', 'woocommerce-payments' ) }
							</option>
						) }
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
									{ symbol &&
									enabledCurrencies[ code ].symbol !==
										enabledCurrencies[ code ].code
										? enabledCurrencies[ code ].symbol + ' '
										: '' }
									{ enabledCurrencies[ code ].code }
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
