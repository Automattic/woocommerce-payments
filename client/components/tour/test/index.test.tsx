/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import Tour from '..';
import {
	TourContent,
	TourFooter,
	TourImage,
	TourNextButton,
	TourPreviousButton,
	TourStep,
} from '../components';

const MockTour = ( { onTourEnd }: { onTourEnd: jest.Mock } ) => (
	<>
		<div id="first-selector">First target element</div>
		<div id="second-selector">Second target element</div>
		<Tour onTourEnd={ onTourEnd }>
			<TourStep
				selector="#first-selector"
				position={ { bottom: 20, left: 20 } }
			>
				<TourImage src="image.png" alt="Example title" />
				<TourContent
					title="Example title"
					description="Example description"
				/>
				<TourFooter showCounter={ false }>
					<TourNextButton />
				</TourFooter>
			</TourStep>

			<TourStep selector="#second-selector" position="bottom">
				<TourImage src="image.png" alt="Example title (second)" />
				<TourContent
					title="Example title (second)"
					description="Example description (second)"
				/>
				<TourFooter>
					<TourPreviousButton />
					<TourNextButton>Close</TourNextButton>
				</TourFooter>
			</TourStep>
		</Tour>
	</>
);

describe( 'Tour component', () => {
	window.scrollTo = jest.fn();

	window.ResizeObserver =
		window.ResizeObserver ||
		jest.fn().mockImplementation( () => ( {
			disconnect: jest.fn(),
			observe: jest.fn(),
			unobserve: jest.fn(),
		} ) );

	afterAll( () => {
		jest.clearAllMocks();
	} );

	it( 'should render correctly', () => {
		const { baseElement } = render( <MockTour onTourEnd={ jest.fn() } /> );

		expect( baseElement ).toMatchSnapshot();
	} );

	it( 'should render the second step after clicking on the action button', () => {
		const { getByText } = render( <MockTour onTourEnd={ jest.fn() } /> );

		expect( getByText( 'Example title' ) ).toBeTruthy();
		expect( getByText( 'Example description' ) ).toBeTruthy();

		userEvent.click( getByText( 'Next' ) );

		expect( getByText( 'Example title (second)' ) ).toBeTruthy();
		expect( getByText( 'Example description (second)' ) ).toBeTruthy();
	} );

	it( 'should render the first step after clicking on the previous button', () => {
		const { getByText } = render( <MockTour onTourEnd={ jest.fn() } /> );

		expect( getByText( 'Example title' ) ).toBeTruthy();
		expect( getByText( 'Example description' ) ).toBeTruthy();

		userEvent.click( getByText( 'Next' ) );

		expect( getByText( 'Example title (second)' ) ).toBeTruthy();
		expect( getByText( 'Example description (second)' ) ).toBeTruthy();

		userEvent.click( getByText( 'Previous' ) );

		expect( getByText( 'Example title' ) ).toBeTruthy();
		expect( getByText( 'Example description' ) ).toBeTruthy();
	} );

	it( 'should call the onTourEnd prop after finishing the tour', () => {
		const handleTourEnd = jest.fn();

		const { getByText } = render(
			<MockTour onTourEnd={ handleTourEnd } />
		);

		userEvent.click( getByText( 'Next' ) );

		expect( handleTourEnd ).not.toHaveBeenCalled();

		userEvent.click( getByText( 'Close' ) );

		expect( handleTourEnd ).toHaveBeenCalled();
	} );

	it( 'should call the onTourEnd prop after clicking on the exit button', () => {
		const handleTourEnd = jest.fn();

		const { getByLabelText } = render(
			<MockTour onTourEnd={ handleTourEnd } />
		);

		userEvent.click( getByLabelText( 'Close tour modal' ) );

		expect( handleTourEnd ).toHaveBeenCalled();
	} );

	it( 'should render the counter when the prop is true', () => {
		const { getByText, queryByText } = render(
			<MockTour onTourEnd={ jest.fn() } />
		);

		expect( queryByText( '1 of 2' ) ).toBeFalsy();

		userEvent.click( getByText( 'Next' ) );

		expect( queryByText( '2 of 2' ) ).toBeTruthy();
	} );

	it( 'should do nothing when clicking on the previous button in the first step', () => {
		const { getByText } = render(
			<>
				<div id="first-selector">First target element</div>
				<Tour onTourEnd={ jest.fn() }>
					<TourStep selector="#first-selector" position="bottom">
						<TourImage src="image.png" alt="Example title" />
						<TourContent
							title="Example title"
							description="Example description"
						/>
						<TourFooter showCounter={ false } />
					</TourStep>
				</Tour>
			</>
		);

		expect( getByText( 'Example title' ) ).toBeTruthy();
		expect( getByText( 'Example description' ) ).toBeTruthy();

		userEvent.click( getByText( 'Previous' ) );

		expect( getByText( 'Example title' ) ).toBeTruthy();
		expect( getByText( 'Example description' ) ).toBeTruthy();
	} );

	it( 'should render in sticky mode if the given selector is not found', () => {
		const { baseElement } = render(
			<>
				<div id="first-selector">First target element</div>
				<Tour onTourEnd={ jest.fn() }>
					<TourStep selector="#wrong-selector" position="bottom">
						<TourImage src="image.png" alt="Example title" />
						<TourContent
							title="Example title"
							description="Example description"
						/>
						<TourFooter showCounter={ false }>
							<TourNextButton />
						</TourFooter>
					</TourStep>
				</Tour>
			</>
		);

		expect( baseElement ).toMatchSnapshot();
	} );

	it( 'should render in sticky mode if the given selector is empty', () => {
		const { baseElement } = render(
			<>
				<div id="first-selector">First target element</div>
				<Tour onTourEnd={ jest.fn() }>
					<TourStep selector="" position="bottom">
						<TourImage src="image.png" alt="Example title" />
						<TourContent
							title="Example title"
							description="Example description"
						/>
						<TourFooter showCounter={ false }>
							<TourNextButton />
						</TourFooter>
					</TourStep>
				</Tour>
			</>
		);

		expect( baseElement ).toMatchSnapshot();
	} );

	it( 'should reset the scrollRestoration', () => {
		history.scrollRestoration = 'auto';

		expect( history.scrollRestoration ).toBe( 'auto' );

		const { unmount } = render( <MockTour onTourEnd={ jest.fn() } /> );

		expect( history.scrollRestoration ).toBe( 'manual' );

		unmount();

		expect( history.scrollRestoration ).toBe( 'auto' );
	} );

	it( 'should not render the tour modal if no content is passed as props', () => {
		const { baseElement } = render(
			<>
				<div id="first-selector">First target element</div>
				<Tour onTourEnd={ jest.fn() } />
			</>
		);

		expect( baseElement ).toMatchSnapshot();
	} );
} );
