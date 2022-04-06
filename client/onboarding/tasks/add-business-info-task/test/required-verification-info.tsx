/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import { mocked } from 'ts-jest/utils';

/**
 * Internal dependencies
 */
import RequiredVerificationInfo from '../required-verification-info';
import { useRequiredVerificationInfo } from 'onboarding/hooks';
import { BusinessType } from 'onboarding/types';

jest.mock( 'onboarding/hooks', () => ( {
	useRequiredVerificationInfo: jest.fn(),
} ) );

const requirements = [
	'business_profile.url',
	'business_profile.mcc',
	'representative.first_name',
	'representative.last_name',
	'representative.dob.day',
	'representative.dob.month',
	'representative.dob.year',
	'representative.phone',
	'representative.email',
	'representative.address.line1',
	'representative.address.postal_code',
	'representative.address.city',
	'representative.address.state',
	'representative.ssn_last_4',
	'company.name',
	'company.tax_id',
	'tos_acceptance.ip',
	'tos_acceptance.date',
	'external_account',
];

const props = {
	country: 'US',
	type: 'company' as BusinessType[ 'key' ],
	structure: 'private_comporation',
};

describe( 'RequiredVerificationInfoTask', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'shows a loadable block', () => {
		mocked( useRequiredVerificationInfo ).mockReturnValue( {
			requiredInfo: requirements,
			isLoading: true,
		} );

		const { container: task } = render(
			<RequiredVerificationInfo { ...props } />
		);
		expect( task ).toMatchSnapshot();
	} );

	it( 'shows the requirements', () => {
		mocked( useRequiredVerificationInfo ).mockReturnValue( {
			requiredInfo: requirements,
			isLoading: false,
		} );

		const { container: task } = render(
			<RequiredVerificationInfo { ...props } />
		);
		expect( task ).toMatchSnapshot();
	} );
} );
