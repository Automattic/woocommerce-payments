/**
 * External dependencies
 */
import React, { useState, useCallback, useEffect } from 'react';
import {
	Card,
	CardHeader,
	CardBody,
	CardFooter,
	Button,
	SelectControl,
	TextControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import './upe-appearance-editor.scss';

function componentToHex( val ) {
	const a = Number( val ).toString( 16 );
	return a.length === 1 ? '0' + a : a;
}

function rgbToHex( rgb ) {
	if ( rgb.startsWith( '#' ) ) {
		return rgb;
	}
	return '#' + rgb.match( /\d+/g ).map( componentToHex ).join( '' );
}

const fieldDefaults = {
	type: 'text',
	transformInput: ( value ) => value,
	transformValue: ( value ) => value,
};

const borderStyleFieldDefaults = {
	...fieldDefaults,
	type: 'select',
	options: [
		{ label: 'None', value: 'none' },
		{ label: 'Solid', value: 'solid' },
		{ label: 'Dashed', value: 'dashed' },
		{ label: 'Dotted', value: 'dotted' },
		{ label: 'Double', value: 'double' },
		{ label: 'Groove', value: 'groove' },
		{ label: 'Ridge', value: 'ridge' },
		{ label: 'Inset', value: 'inset' },
		{ label: 'Outset', value: 'outset' },
	],
};

const colorFieldDefaults = {
	...fieldDefaults,
	type: 'color',
	transformInput: rgbToHex,
};

const pxFieldDefaults = {
	...fieldDefaults,
	type: 'number',
	step: 1,
	transformInput: ( value ) => value.replace( 'px', '' ),
	transformValue: ( value ) => `${ value }px`,
};

const RulesFields = [
	{
		...colorFieldDefaults,
		label: 'Background Color',
		property: 'backgroundColor',
	},
	{
		...colorFieldDefaults,
		label: 'Text Color',
		property: 'color',
	},
	{
		...pxFieldDefaults,
		label: 'Font Size',
		property: 'fontSize',
	},
	{
		...pxFieldDefaults,
		label: 'Line Height',
		property: 'lineHeight',
	},
	{
		...borderStyleFieldDefaults,
		label: 'Border style',
		property: 'borderBottomStyle',
		linkedProperties: [
			'borderLeftStyle',
			'borderRightStyle',
			'borderTopStyle',
		],
	},
	{
		...colorFieldDefaults,
		label: 'Border color',
		property: 'borderBottomColor',
		linkedProperties: [
			'borderLeftColor',
			'borderRightColor',
			'borderTopColor',
		],
	},
	{
		...pxFieldDefaults,
		label: 'Border radius',
		property: 'borderTopLeftRadius',
		linkedProperties: [
			'borderTopRightRadius',
			'borderBottomLeftRadius',
			'borderBottomRightRadius',
		],
	},
	{
		...pxFieldDefaults,
		label: 'Border bottom width',
		property: 'borderBottomWidth',
	},
	// {
	// 	...borderStyleFieldDefaults,
	// 	label: 'Border left style',
	// 	property: 'borderLeftStyle',
	// },
	// {
	// 	...colorFieldDefaults,
	// 	label: 'Border left color',
	// 	property: 'borderLeftColor',
	// },
	{
		...pxFieldDefaults,
		label: 'Border left width',
		property: 'borderLeftWidth',
	},
	// {
	// 	...borderStyleFieldDefaults,
	// 	label: 'Border right style',
	// 	property: 'borderRightStyle',
	// },
	// {
	// 	...colorFieldDefaults,
	// 	label: 'Border right color',
	// 	property: 'borderRightColor',
	// },
	{
		...pxFieldDefaults,
		label: 'Border right width',
		property: 'borderRightWidth',
	},
	// {
	// 	...borderStyleFieldDefaults,
	// 	label: 'Border top style',
	// 	property: 'borderTopStyle',
	// },
	// {
	// 	...colorFieldDefaults,
	// 	label: 'Border top color',
	// 	property: 'borderTopColor',
	// },
	{
		...pxFieldDefaults,
		label: 'Border top width',
		property: 'borderTopWidth',
	},
	{
		...fieldDefaults,
		label: 'Box Shadow',
		property: 'boxShadow',
	},
];

let initialPosition = 'bottom-left';
try {
	initialPosition =
		localStorage.getItem( 'upe-appearance-editor-position' ) ||
		'bottom-left';
} catch ( error ) {}

export function UpeAppearanceEditor( {
	initialAppearance,
	elementsLocation,
	api,
	applyAppearance,
	sections = [ 'labels', 'inputs', 'text', 'pmme' ],
} ) {
	const [ position, setPosition ] = useState( initialPosition );
	const [ appearance, setAppearance ] = useState( initialAppearance );

	const onPositionChange = useCallback( ( e ) => {
		try {
			localStorage.setItem(
				'upe-appearance-editor-position',
				e.target.value
			);
		} catch ( error ) {}
		setPosition( e.target.value );
	}, [] );

	useEffect( () => {
		applyAppearance( appearance );
	}, [ appearance, applyAppearance ] );

	const saveAppearance = useCallback( () => {
		applyAppearance( appearance );
		api.saveUPEAppearance( appearance, elementsLocation );
	}, [ appearance, api, elementsLocation, applyAppearance ] );

	const mapFieldsForRule = ( rule ) => {
		return RulesFields.map( ( field ) => {
			if ( ! ( field.property in appearance.rules[ rule ] ) ) {
				return null;
			}
			const onChange = ( value ) => {
				const transformedValue = field.transformValue( value );
				const newRuleValue = {
					...appearance.rules[ rule ],
					[ field.property ]: transformedValue,
				};

				if ( field.linkedProperties ) {
					field.linkedProperties.forEach( ( property ) => {
						newRuleValue[ property ] = transformedValue;
					} );
				}

				setAppearance( {
					...appearance,
					rules: {
						...appearance.rules,
						[ rule ]: newRuleValue,
					},
				} );
			};
			const value = field.transformInput(
				appearance.rules[ rule ][ field.property ]
			);
			if ( field.type === 'select' ) {
				return (
					<SelectControl
						key={ field.property }
						label={ field.label }
						value={ value }
						options={ field.options }
						onChange={ onChange }
					/>
				);
			}
			return (
				<TextControl
					key={ field.property }
					label={ field.label }
					type={ field.type }
					value={ value }
					onChange={ onChange }
					step={ field.step }
				/>
			);
		} );
	};

	return (
		<Card className={ `upe-appearance-editor ${ position }` }>
			<CardHeader>
				{ ' ' }
				Appearance Editor ({ elementsLocation })
				<select
					value={ position }
					onBlur={ onPositionChange }
					onChange={ onPositionChange }
				>
					<option value="bottom-left">Bottom Left</option>
					<option value="bottom-right">Bottom Right</option>
					<option value="top-left">Top Left</option>
					<option value="top-right">Top Right</option>
				</select>
			</CardHeader>

			<CardBody>
				{ sections.includes( 'labels' ) && (
					<fieldset>
						<legend>Labels</legend>
						<SelectControl
							label="Positioning"
							value={ appearance.labels }
							options={ [
								{ label: 'Above', value: 'above' },
								{ label: 'Floating', value: 'floating' },
							] }
							onChange={ ( value ) =>
								setAppearance( {
									...appearance,
									labels: value,
								} )
							}
						/>
						{ mapFieldsForRule( '.Label' ) }
					</fieldset>
				) }

				{ sections.includes( 'inputs' ) && (
					<fieldset>
						<legend>Inputs</legend>
						{ mapFieldsForRule( '.Input' ) }
					</fieldset>
				) }

				{ sections.includes( 'inputs' ) && (
					<fieldset>
						<legend>
							Inputs (Invalid)&nbsp;
							<Button
								size="small"
								onClick={ () => {
									setAppearance( {
										...appearance,
										rules: {
											...appearance.rules,
											'.Input--invalid': {
												...appearance.rules[ '.Input' ],
											},
										},
									} );
								} }
							>
								Copy from Inputs
							</Button>
						</legend>
						{ mapFieldsForRule( '.Input--invalid' ) }
					</fieldset>
				) }

				{ sections.includes( 'text' ) && (
					<fieldset>
						<legend>Text (Redirect Payment Methods)</legend>
						{ mapFieldsForRule( '.Text--redirect' ) }
					</fieldset>
				) }

				{ sections.includes( 'pmme' ) && (
					<fieldset>
						<legend>
							Payment Messaging Elements (Klarna, Afterpay, etc.)
							<br />
							<small>
								Require Saving and a Page Reload to take effect
							</small>
						</legend>
						<SelectControl
							label="Icon Theme"
							value={ appearance.theme }
							options={ [
								{ label: 'Regular Icons', value: 'stripe' },
								{
									label: 'For Dark Backgrounds',
									value: 'night',
								},
							] }
							onChange={ ( value ) =>
								setAppearance( { ...appearance, theme: value } )
							}
						/>
						<TextControl
							label="Font Size"
							type="number"
							value={ appearance.variables.fontSizeBase.replace(
								'px',
								''
							) }
							onChange={ ( value ) =>
								setAppearance( {
									...appearance,
									variables: {
										...appearance.variables,
										fontSizeBase: `${ value }px`,
									},
								} )
							}
						/>
						{ /* <TextControl
						label="Background Color"
						type="color"
						value={ rgbToHex(
							appearance.variables.colorBackground
						) }
						onChange={ ( value ) =>
							setAppearance( {
								...appearance,
								variables: {
									...appearance.variables,
									colorBackground: value,
								},
							} )
						}
					/> */ }

						<TextControl
							label="Text Color"
							type="color"
							value={ rgbToHex( appearance.variables.colorText ) }
							onChange={ ( value ) =>
								setAppearance( {
									...appearance,
									variables: {
										...appearance.variables,
										colorText: value,
									},
								} )
							}
						/>
					</fieldset>
				) }
			</CardBody>
			<CardFooter>
				<Button variant="primary" onClick={ saveAppearance }>
					Save
				</Button>
			</CardFooter>
		</Card>
	);
}
