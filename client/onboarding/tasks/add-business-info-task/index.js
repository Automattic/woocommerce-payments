/** @format */

/**
 * External dependencies
 */
import React, { useState, useContext, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import WizardTaskItem from 'additional-methods-setup/wizard/task-item';
import WizardTaskContext from 'additional-methods-setup/wizard/task/context';
import CustomSelectControl from 'components/custom-select-control';
import { LoadableBlock } from 'components/loadable';
import { useBusinessTypes } from 'data/onboarding';
import strings from '../../strings';

const AddBusinessInfoTask = () => {
	const { isCompleted, setCompleted } = useContext( WizardTaskContext );
	const { businessTypes, isLoading } = useBusinessTypes();

	const accountCountry = businessTypes.find(
		( b ) => b.key === wcpaySettings.connect.country
	);
	const [ businessCountry, setBusinessCountry ] = useState( accountCountry );
	const [ businessType, setBusinessType ] = useState( '' );
	const [ businessStructure, setBusinessStructure ] = useState( '' );
	const [ displayStructures, setDisplayStructures ] = useState( false );

	useEffect( () => {
		setBusinessCountry( accountCountry );
	}, [ accountCountry ] );

	const handleBusinessCountryUpdate = ( country ) => {
		setBusinessCountry( country );
		setBusinessType( '' );
		setBusinessStructure( '' );
		setDisplayStructures( false );
		setCompleted( false );
	};

	const handleBusinessTypeUpdate = ( type ) => {
		setBusinessType( type );
		setBusinessStructure( '' );
		setCompleted( 0 === type.structures.length );
		setDisplayStructures( 0 < type.structures.length );
	};

	const handleBusinessStructureUpdate = ( structure ) => {
		setBusinessStructure( structure );
		setCompleted( true );
	};

	return (
		<WizardTaskItem
			className="complete-business-info-task"
			index={ 1 }
			title={ strings.onboarding.heading }
		>
			<p className="complete-business-info-task__subheading">
				{ strings.onboarding.description }
			</p>
			<LoadableBlock isLoading={ isLoading } numLines={ 4 }>
				<CustomSelectControl
					label={ __( 'Country', 'woocommerce-payments' ) }
					value={ businessCountry }
					onChange={ ( { selectedItem } ) =>
						handleBusinessCountryUpdate( selectedItem )
					}
					options={ businessTypes }
				/>
				<p className="complete-business-info-task__description">
					{ strings.onboarding.countryDescription }
				</p>
				{ businessCountry && (
					<CustomSelectControl
						label={ __( 'Business type', 'woocommerce-payments' ) }
						value={ businessType }
						options={ businessCountry.types }
						placeholder={ __(
							'What type of business do you run?',
							'woocommerce-payments'
						) }
						onChange={ ( { selectedItem } ) =>
							handleBusinessTypeUpdate( selectedItem )
						}
					>
						{ ( item ) => (
							<div>
								<div>{ item.name }</div>
								<div className="complete-business-info-task__option-description">
									{ item.description }
								</div>
							</div>
						) }
					</CustomSelectControl>
				) }
				{ businessType && displayStructures && (
					<CustomSelectControl
						label={ __(
							'Business Structure',
							'woocommerce-payments'
						) }
						value={ businessStructure }
						options={ businessType.structures }
						placeholder={ __(
							'What’s the legal structure of your business?',
							'woocommerce-payments'
						) }
						onChange={ ( { selectedItem } ) =>
							handleBusinessStructureUpdate( selectedItem )
						}
					/>
				) }
			</LoadableBlock>
			<LoadableBlock isLoading={ isLoading } numLines={ 4 } />
			{ isCompleted && (
				<Card size="large" className="wcpay-required-info-card">
					<CardBody>
						<p>
							<b>
								{ __(
									"To verify your details, we'll require:",
									'woocommerce-payments'
								) }
							</b>
						</p>
					</CardBody>
				</Card>
			) }
		</WizardTaskItem>
	);
};

export default AddBusinessInfoTask;
