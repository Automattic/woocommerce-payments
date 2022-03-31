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

const AddBusinessInfoTask = () => {
	const { isCompleted, setCompleted } = useContext( WizardTaskContext );
	const { businessTypes, isLoading } = useBusinessTypes();
	const {
		requirementKeys,
		isRequirementsLoading,
		submitRequiredVerificationInfoUpdate,
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
		setCompleted( 0 === type.structures.length );
		setDisplayStructures( 0 < type.structures.length );

		if ( isCompleted ) {
			submitRequiredVerificationInfoUpdate( 'US', 'company' );
		}
	};

	const handleBusinessStructureUpdate = ( structure ) => {
		setBusinessStructure( structure );
		setCompleted( true );

		if ( isCompleted ) {
			submitRequiredVerificationInfoUpdate( 'US', 'non_profit' );
		}
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

			{ isCompleted && isRequirementsLoading && (
				<Card size="large" className="wcpay-required-info-card">
					<CardBody>
						<p>
							<b>
								{ __(
									"To verify your details, we'll require:",
									'woocommerce-payments'
								) }
							</b>
							<Requirements
								type={ businessType }
								keys={ requirementKeys }
							/>
						</p>
					</CardBody>
				</Card>
			) }
		</WizardTaskItem>
	);
};

export default AddBusinessInfoTask;
