/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import RequiredVerificationInfo from '../required-verification-info';
import { useRequiredVerificationInfo } from 'wcpay/data/onboarding';

jest.mock( 'wcpay/data/onboarding', () => ( {
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

describe( 'RequiredVerificationInfoTask', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'shows a loadable block', () => {
		useRequiredVerificationInfo.mockReturnValue( {
			requiredFields: requirements,
			isLoading: true,
			getRequiredVerificationInfo: () => requirements,
		} );

		const { container: task } = render( <RequiredVerificationInfo /> );
		expect( task ).toMatchSnapshot();
	} );

	it( 'shows the requirements', () => {
		useRequiredVerificationInfo.mockReturnValue( {
			requiredFields: requirements,
			isLoading: false,
			getRequiredVerificationInfo: () => requirements,
		} );

		const { container: task } = render( <RequiredVerificationInfo /> );
		expect( task ).toMatchSnapshot();
	} );
} );
