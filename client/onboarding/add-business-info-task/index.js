/** @format */

/**
 * External dependencies
 */
import React, { useState, useContext, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CardBody, CustomSelectControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import WizardTaskItem from 'additional-methods-setup/wizard/task-item';
import WizardTaskContext from 'additional-methods-setup/wizard/task/context';
import OnboardingSelectControl from 'components/onboarding-select-control';
import { LoadableBlock } from 'components/loadable';
import { useBusinessTypes } from 'data/onboarding';
import strings from '../strings';

const AddBusinessInfo = () => {
	const { isCompleted } = useContext( WizardTaskContext );
	const { businessTypes, isLoading } = useBusinessTypes();

	const accountCountry = businessTypes.find(
		( b ) => b.key === wcpaySettings.connect.country
	);
	const [ businessCountry, setBusinessCountry ] = useState( null );
	const [ businessType, setBusinessType ] = useState( null );
	const [ businessStructure, setBusinessStructure ] = useState( null );

	useEffect( () => {
		setBusinessCountry( accountCountry );
	}, [ accountCountry ] );

	const handleBusinessCountryUpdate = ( country ) => {
		setBusinessCountry( country );
	};

	const handleBusinessTypeUpdate = ( type ) => {
		setBusinessType( type );
	};

	const handleBusinessStructureUpdate = ( structure ) => {
		setBusinessStructure( structure );
	};

	// if ( businessType ) console.log( businessType );
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
				<CustomSelectControl
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
				{ businessType && (
					<CustomSelectControl
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

export default AddBusinessInfo;
