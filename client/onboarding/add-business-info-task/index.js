/** @format */

/**
 * External dependencies
 */
import React, { useState, useContext } from 'react';
import { __ } from '@wordpress/i18n';
import { SelectControl, Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import WizardTaskItem from 'wcpay/additional-methods-setup/wizard/task-item';
import WizardTaskContext from 'wcpay/additional-methods-setup/wizard/task/context';
import OnboardingSelectControl from 'wcpay/components/onboarding-select-control';
import { LoadableBlock } from 'wcpay/components/loadable';
import { useBusinessTypes } from 'wcpay/data/onboarding';
import strings from '../strings';

const AddBusinessInfo = () => {
	const { setCompleted } = useContext( WizardTaskContext );
	const { businessTypes, isLoading } = useBusinessTypes();
	const {
		connect: { availableCountries, country },
	} = wcpaySettings;

	const countries = Object.keys( availableCountries ).map(
		( countryCode ) => {
			return {
				label: availableCountries[ countryCode ],
				value: countryCode,
			};
		}
	);

	const [ businessCountry, setBusinessCountry ] = useState( country );
	const [ businessType, setBusinessType ] = useState( '' );
	const [ businessStructure, setBusinessStructure ] = useState();
	const [ displayStructures, setDisplayStructures ] = useState( false );
	const [ displayVerificationCard, setDisplayVerificationCard ] = useState(
		false
	);

	const getBusinessTypesForCountry = ( countryCode ) => {
		if ( isLoading || ! businessTypes.hasOwnProperty( 'data' ) ) {
			return false;
		}

		return businessTypes.data.hasOwnProperty( countryCode )
			? businessTypes.data[ countryCode ]
			: false;
	};

	const formatBusinessTypes = ( countryCode ) => {
		const businessTypeData = getBusinessTypesForCountry( countryCode );

		if ( isLoading || ! businessTypeData ) {
			return [];
		}

		return Object.keys( businessTypeData ).map( ( key ) => ( {
			name: strings.businessTypes[ key ],
			description: 'Test',
			key: key,
		} ) );
	};

	const shouldDisplayStructures = ( countryCode, type ) => {
		const businessTypeData = getBusinessTypesForCountry( countryCode );

		if ( isLoading || ! businessTypeData ) {
			return false;
		}

		return businessTypeData.hasOwnProperty( type )
			? 0 < businessTypeData[ type ].length
			: false;
	};

	const formatBusinessStructures = ( countryCode, type ) => {
		const businessTypeData = getBusinessTypesForCountry( countryCode );

		if ( isLoading || ! businessTypeData ) {
			return false;
		}

		return businessTypeData.hasOwnProperty( type )
			? businessTypeData[ type ]
			: [];
	};

	const handleBusinessTypeUpdate = ( type ) => {
		setBusinessType( type );

		if ( shouldDisplayStructures( businessCountry, type.key ) ) {
			formatBusinessStructures( businessCountry, type.key );
			setDisplayStructures( true );
			setCompleted( false );
		} else {
			setDisplayStructures( false );
			setDisplayVerificationCard( true );
			setCompleted( true );
		}
	};

	const handleBusinessStructureUpdate = ( structure ) => {
		setBusinessStructure( structure );
		setCompleted( true );
	};

	const handleBusinessCountryUpdate = ( countryCode ) => {
		setBusinessCountry( countryCode );
		formatBusinessTypes( countryCode );
		setCompleted( false );
		// TODO: put these calls in some resetState function or something similar.
		setDisplayStructures( false );
		setDisplayVerificationCard( false );
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
				<SelectControl
					label={ __( 'Country', 'woocommerce-payments' ) }
					value={ businessCountry }
					onChange={ ( value ) =>
						handleBusinessCountryUpdate( value )
					}
					options={ countries }
				/>

				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ strings.onboarding.countryDescription }
				</p>

				<OnboardingSelectControl
					className="wcpay-onboarding-select-business-types"
					label={ __( 'Business type', 'woocommerce-payments' ) }
					value={ businessType }
					onChange={ ( { selectedItem } ) =>
						handleBusinessTypeUpdate( selectedItem )
					}
					options={ formatBusinessTypes( businessCountry ) }
				/>

				{ displayStructures && (
					<SelectControl
						label={ __(
							'Business structure',
							'woocommerce-payments'
						) }
						value={ businessStructure }
						onChange={ ( value ) =>
							handleBusinessStructureUpdate( value )
						}
						options={ formatBusinessStructures( businessCountry ) }
					/>
				) }
			</LoadableBlock>

			{ displayVerificationCard && (
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
