/**
 * External dependencies
 */
import React from 'react';
import { Flex, FlexBlock, TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import strings from '../strings';
import { useOnboardingContext } from '../context';
import { OnboardingFields } from '../types';

const PersonalDetails: React.FC = () => {
	const { data, setData } = useOnboardingContext();

	const getFieldProps = ( name: keyof OnboardingFields ) => ( {
		label: strings.fields[ name ],
		value: data[ name ] || '',
		onChange: ( value: string ) => setData( { [ name ]: value } ),
	} );

	return (
		<>
			<Flex>
				<FlexBlock>
					<TextControl
						{ ...getFieldProps( 'individual.first_name' ) }
					/>
				</FlexBlock>
				<FlexBlock>
					<TextControl
						{ ...getFieldProps( 'individual.last_name' ) }
					/>
				</FlexBlock>
			</Flex>
			<TextControl { ...getFieldProps( 'email' ) } />
			<div>
				{
					// TODO: Create a notice component
					strings.steps.personal.notice
				}
			</div>
			<TextControl { ...getFieldProps( 'phone' ) } />
		</>
	);
};

export default PersonalDetails;
