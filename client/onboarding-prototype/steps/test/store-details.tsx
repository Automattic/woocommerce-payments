/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import StoreDetails from '../store-details';
import { OnboardingContextProvider } from '../../context';
import strings from '../../strings';

describe( 'StoreDetails', () => {
	it( 'renders and updates fields data when they are changed', () => {
		render(
			<OnboardingContextProvider>
				<StoreDetails />
			</OnboardingContextProvider>
		);
		const annualRevenueField = screen.getByText(
			strings.placeholders.annual_revenue
		);
		const goLiveTimeframeField = screen.getByText(
			strings.placeholders.go_live_timeframe
		);

		user.click( annualRevenueField );
		user.click(
			screen.getByText( strings.annualRevenues.from_250k_to_1m )
		);

		user.click( goLiveTimeframeField );
		user.click(
			screen.getByText( strings.goLiveTimeframes.within_1month )
		);

		expect( annualRevenueField ).toHaveTextContent(
			strings.annualRevenues.from_250k_to_1m
		);
		expect( goLiveTimeframeField ).toHaveTextContent(
			strings.goLiveTimeframes.within_1month
		);
	} );
} );
