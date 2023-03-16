/**
 * External dependencies
 */
import React, { useState, useContext, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import WizardTaskItem from 'additional-methods-setup/wizard/task-item';
import WizardTaskContext from 'additional-methods-setup/wizard/task/context';
import CustomSelectControl from 'components/custom-select-control';
import { LoadableBlock } from 'components/loadable';
import { useBusinessTypes } from 'onboarding-experiment/hooks';
import RequiredVerificationInfo from './required-verification-info';
import strings from 'onboarding-experiment/strings';
import {
	Country,
	BusinessType,
	BusinessStructure,
	OnboardingProps,
} from 'onboarding-experiment/types';

interface TaskProps {
	onChange: ( data: Partial< OnboardingProps > ) => void;
}

const AddBusinessInfoTask = ( { onChange }: TaskProps ): JSX.Element => {
	const { isCompleted, setCompleted } = useContext( WizardTaskContext );
	const { countries, isLoading } = useBusinessTypes();

	const [ businessCountry, setBusinessCountry ] = useState< Country >();
	const [ businessType, setBusinessType ] = useState< BusinessType >();
	const [ businessStructure, setBusinessStructure ] = useState<
		BusinessStructure
	>();

	useEffect( () => {
		if ( ! businessCountry && countries.length ) {
			setBusinessCountry(
				countries.find(
					( country ) => country.key === wcpaySettings.connect.country
				)
			);
		}
	}, [ countries, businessCountry ] );

	useEffect( () => {
		onChange( {
			country: businessCountry?.key,
			type: businessType?.key,
			structure: businessStructure?.key,
		} );
	}, [ businessCountry, businessType, businessStructure, onChange ] );

	const handleBusinessCountryUpdate = ( country?: Country ) => {
		setBusinessCountry( country );
		setBusinessType( undefined );
		setBusinessStructure( undefined );
		setCompleted( false );
	};

	const handleBusinessTypeUpdate = ( type?: BusinessType ) => {
		setBusinessType( type );
		setBusinessStructure( undefined );
		setCompleted( 0 === type?.structures.length );
	};

	const handleBusinessStructureUpdate = ( structure?: BusinessStructure ) => {
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
					options={ countries }
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
				{ businessType && businessType.structures?.length > 0 && (
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
			{ businessCountry && businessType && isCompleted && (
				<RequiredVerificationInfo
					country={ businessCountry.key }
					type={ businessType.key }
					structure={ businessStructure?.key }
				/>
			) }
		</WizardTaskItem>
	);
};

export default AddBusinessInfoTask;
