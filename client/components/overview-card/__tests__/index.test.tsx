/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import OverviewCard from '..';

const LoadingState: React.FC = () => (
	<div data-testid="loading-state">loading</div>
);

describe( 'OverviewCard', () => {
	test( 'renders children (and not the LoadingState) when not loading, with title and forwarded class names', () => {
		const { container, getByText, queryByTestId } = render(
			<OverviewCard
				title="Payouts"
				isLoading={ false }
				LoadingState={ LoadingState }
				className="custom-card-class"
				headerClassName="custom-header-class"
			>
				<div>loaded content</div>
			</OverviewCard>
		);

		expect( getByText( 'Payouts' ) ).toBeInTheDocument();
		expect( getByText( 'loaded content' ) ).toBeInTheDocument();
		expect( queryByTestId( 'loading-state' ) ).not.toBeInTheDocument();
		expect(
			container.querySelector( '.custom-card-class' )
		).toBeInTheDocument();
		expect(
			container.querySelector( '.custom-header-class' )
		).toBeInTheDocument();
	} );

	test( 'renders the LoadingState (and not children) while loading', () => {
		const { getByTestId, queryByText } = render(
			<OverviewCard
				title="Payouts"
				isLoading={ true }
				LoadingState={ LoadingState }
			>
				<div>loaded content</div>
			</OverviewCard>
		);

		expect( getByTestId( 'loading-state' ) ).toBeInTheDocument();
		expect( queryByText( 'loaded content' ) ).not.toBeInTheDocument();
	} );
} );
