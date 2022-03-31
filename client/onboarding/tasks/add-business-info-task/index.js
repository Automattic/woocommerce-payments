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
import OnboardingSelectControl from 'components/onboarding-select-control';
import { LoadableBlock } from 'components/loadable';
import { useBusinessTypes, useRequiredVerificationInfo } from 'data/onboarding';
import strings from '../../strings';
import Requirements from 'wcpay/onboarding/requirements';
import { getRequiredVerificationInfo } from 'wcpay/data/onboarding/resolvers';

const AddBusinessInfoTask = () => {
	const { isCompleted, setCompleted } = useContext( WizardTaskContext );
	const { businessTypes, isLoading } = useBusinessTypes();
	const {
		requiredFields,
		isRequirementsLoading,
	} = useRequiredVerificationInfo( 'US', 'individual', '' );
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
		setDisplayStructures( 0 < type.structures.length );

		if ( 0 === type.structures.length ) {
			getRequiredVerificationInfo( { country: 'US', type: 'company' } );
			setCompleted( true );
		}
	};

	const handleBusinessStructureUpdate = ( structure ) => {
		setBusinessStructure( structure );
		getRequiredVerificationInfo( { country: 'US', type: 'company' } );
		setCompleted( true );
	};

	return (
		<WizardTaskItem
			className="complete-business-info-task"
			index={ 1 }
			title={ strings.onboarding.heading }
		>
			<p className="wcpay-wizard-task__description-element subheading is-muted-color">
				{ strings.onboarding.description }
			</p>
			<LoadableBlock isLoading={ isLoading } numLines={ 20 }>
				<OnboardingSelectControl
					className="wcpay-onboarding-select"
					label={ __( 'Country', 'woocommerce-payments' ) }
					value={ businessCountry }
					onChange={ ( { selectedItem } ) =>
						handleBusinessCountryUpdate( selectedItem )
					}
					options={ businessTypes }
				/>
				{ businessCountry && (
					<OnboardingSelectControl
						className="wcpay-onboarding-select"
						label={ __( 'Business type', 'woocommerce-payments' ) }
						value={ businessType }
						onChange={ ( { selectedItem } ) =>
							handleBusinessTypeUpdate( selectedItem )
						}
						options={ businessCountry.types }
					/>
				) }
				{ businessType && displayStructures && (
					<OnboardingSelectControl
						className="wcpay-onboarding-select"
						label={ __(
							'Business Structure',
							'woocommerce-payments'
						) }
						value={ businessStructure }
						onChange={ ( { selectedItem } ) =>
							handleBusinessStructureUpdate( selectedItem )
						}
						options={ businessType.structures }
					/>
				) }
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ strings.onboarding.countryDescription }
				</p>
			</LoadableBlock>

			{ isCompleted && (
				<>
					{ isRequirementsLoading ? (
						<LoadableBlock
							isLoading={ isRequirementsLoading }
							numLines={ 20 }
						></LoadableBlock>
					) : (
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
								<Requirements
									type={ businessType }
									keys={ requiredFields }
								/>
							</CardBody>
						</Card>
					) }
				</>
			) }
		</WizardTaskItem>
	);
};

export default AddBusinessInfoTask;
