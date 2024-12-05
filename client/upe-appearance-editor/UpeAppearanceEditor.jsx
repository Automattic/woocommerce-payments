/**
 * External dependencies
 */
import React, { useState, useCallback, useEffect } from 'react';
import {
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
import fieldsDefinition, { rgbToHex } from './fields-definition';

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
	const [ loading, setLoading ] = useState( false );
	const [ appearance, setAppearance ] = useState( initialAppearance );
	const [ displayState, setDisplayState ] = useState( 'collapsed' );

	const toggleDisplay = useCallback( () => {
		setDisplayState(
			displayState === 'collapsed' ? 'expanded' : 'collapsed'
		);
	}, [ displayState ] );

	const togglePosition = useCallback( () => {
		const newPosition =
			position === 'bottom-left' ? 'bottom-right' : 'bottom-left';
		try {
			localStorage.setItem(
				'upe-appearance-editor-position',
				newPosition
			);
		} catch ( error ) {}
		setPosition( newPosition );
	}, [ position ] );

	useEffect( () => {
		applyAppearance( appearance );
	}, [ appearance, applyAppearance ] );

	const saveAppearance = useCallback( () => {
		setLoading( true );
		applyAppearance( appearance );
		api.saveUPEAppearance(
			appearance,
			elementsLocation,
			'persistent'
		).then( () => {
			setLoading( false );
		} );
	}, [ appearance, api, elementsLocation, applyAppearance ] );

	const resetAppearance = useCallback( () => {
		setLoading( true );
		api.resetUPEAppearance( elementsLocation ).then( () => {
			window.location.reload();
		} );
	}, [ api, elementsLocation ] );

	const mapFieldsForRule = ( rule ) => {
		return fieldsDefinition.map( ( field ) => {
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
		<div
			className={ `upe-appearance-editor ${ position } ${ displayState }` }
		>
			<div>
				<CardHeader onClick={ toggleDisplay }>
					<span>Customize WooPayments</span>
				</CardHeader>

				<CardBody>
					<small className="elements-location">
						Form: { elementsLocation }
					</small>
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
									className="small"
									onClick={ () => {
										setAppearance( {
											...appearance,
											rules: {
												...appearance.rules,
												'.Input--invalid': {
													...appearance.rules[
														'.Input'
													],
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
								Payment Messaging Elements (Klarna, Afterpay,
								etc.)
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
									setAppearance( {
										...appearance,
										theme: value,
									} )
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
								value={ rgbToHex(
									appearance.variables.colorText
								) }
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
							<small>
								Requires Saving and a Page Reload to take effect
							</small>
						</fieldset>
					) }
				</CardBody>
				<CardFooter>
					<Button onClick={ togglePosition } className="button small">
						{ position === 'bottom-left' ? '→' : '←' }
					</Button>

					<Button
						className="button"
						disabled={ loading }
						onClick={ resetAppearance }
					>
						Reset
					</Button>

					<Button
						className="alt"
						disabled={ loading }
						onClick={ saveAppearance }
					>
						Save
					</Button>
				</CardFooter>
			</div>
		</div>
	);
}
