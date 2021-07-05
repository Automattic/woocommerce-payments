/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../../wizard/task/context';
import { useSettings } from '../../../data';

import WcpaySettingsFetcher from '../wcpay-settings-fetcher';

jest.mock( '../../../data', () => ( {
	useSettings: jest.fn().mockReturnValue( {} ),
} ) );

describe( 'WcpaySettingsFetcher', () => {
	it( 'should not fetch the settings when the task to enable UPE is not completed', () => {
		render(
			<WizardTaskContext.Provider value={ { isCompleted: false } }>
				<WcpaySettingsFetcher />
			</WizardTaskContext.Provider>
		);

		expect( useSettings ).not.toHaveBeenCalled();
	} );

	it( 'should fetch the settings when the task to enable UPE is completed', () => {
		render(
			<WizardTaskContext.Provider value={ { isCompleted: true } }>
				<WcpaySettingsFetcher />
			</WizardTaskContext.Provider>
		);

		expect( useSettings ).toHaveBeenCalled();
	} );
} );
