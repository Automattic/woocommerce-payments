/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import Spotlight from '../index';
import { SpotlightProps } from '../types';

// Mock the style import
jest.mock( '../style.scss', () => ( {} ) );

describe( 'Spotlight Component', () => {
	const defaultProps: SpotlightProps = {
		badge: 'Limited time offer',
		heading: 'Test Heading',
		description: 'Test description text',
		primaryButtonLabel: 'Activate',
		onPrimaryClick: jest.fn(),
		onDismiss: jest.fn(),
		showImmediately: true, // Show immediately for tests
	};

	beforeEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders the spotlight with all basic elements', () => {
		render( <Spotlight { ...defaultProps } /> );

		expect( screen.getByText( 'Limited time offer' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Test Heading' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Test description text' )
		).toBeInTheDocument();
		expect( screen.getByText( 'Activate' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Close' ) ).toBeInTheDocument();
	} );

	it( 'renders without badge when not provided', () => {
		const propsWithoutBadge = { ...defaultProps, badge: undefined };
		render( <Spotlight { ...propsWithoutBadge } /> );

		expect(
			screen.queryByText( 'Limited time offer' )
		).not.toBeInTheDocument();
		expect( screen.getByText( 'Test Heading' ) ).toBeInTheDocument();
	} );

	it( 'renders secondary button when provided', () => {
		const propsWithSecondary = {
			...defaultProps,
			secondaryButtonLabel: 'Learn more',
			onSecondaryClick: jest.fn(),
		};
		render( <Spotlight { ...propsWithSecondary } /> );

		expect( screen.getByText( 'Learn more' ) ).toBeInTheDocument();
	} );

	it( 'renders disclaimer when provided', () => {
		const propsWithDisclaimer = {
			...defaultProps,
			disclaimer: '*Terms and conditions apply',
		};
		render( <Spotlight { ...propsWithDisclaimer } /> );

		expect(
			screen.getByText( '*Terms and conditions apply' )
		).toBeInTheDocument();
	} );

	it( 'renders disclaimer with React component content', () => {
		const propsWithReactDisclaimer = {
			...defaultProps,
			disclaimer: (
				<>
					*Terms and <em>conditions</em> apply
				</>
			),
		};
		render( <Spotlight { ...propsWithReactDisclaimer } /> );

		expect( screen.getByText( /Terms and/i ) ).toBeInTheDocument();
		expect( screen.getByText( 'conditions' ) ).toBeInTheDocument();
	} );

	it( 'renders image when provided as string', () => {
		const propsWithImage = {
			...defaultProps,
			image: 'https://example.com/image.png',
		};
		render( <Spotlight { ...propsWithImage } /> );

		const image = screen.getByAltText( 'Spotlight image' );
		expect( image ).toBeInTheDocument();
		expect( image ).toHaveAttribute(
			'src',
			'https://example.com/image.png'
		);
	} );

	it( 'renders image when provided as React element', () => {
		const propsWithImage = {
			...defaultProps,
			image: <div data-testid="custom-image">Custom Image</div>,
		};
		render( <Spotlight { ...propsWithImage } /> );

		expect( screen.getByTestId( 'custom-image' ) ).toBeInTheDocument();
	} );

	it( 'calls onPrimaryClick and onDismiss when primary button is clicked', async () => {
		const onPrimaryClick = jest.fn();
		const onDismiss = jest.fn();

		render(
			<Spotlight
				{ ...defaultProps }
				onPrimaryClick={ onPrimaryClick }
				onDismiss={ onDismiss }
			/>
		);

		const primaryButton = screen.getByText( 'Activate' );
		await userEvent.click( primaryButton );

		expect( onPrimaryClick ).toHaveBeenCalledTimes( 1 );

		// onDismiss is called after animation timeout (300ms)
		await waitFor(
			() => {
				expect( onDismiss ).toHaveBeenCalledTimes( 1 );
			},
			{ timeout: 500 }
		);
	} );

	it( 'calls onSecondaryClick when secondary button is clicked', () => {
		const onSecondaryClick = jest.fn();

		render(
			<Spotlight
				{ ...defaultProps }
				secondaryButtonLabel="Learn more"
				onSecondaryClick={ onSecondaryClick }
			/>
		);

		const secondaryButton = screen.getByText( 'Learn more' );
		userEvent.click( secondaryButton );

		expect( onSecondaryClick ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'calls onDismiss when close button is clicked', async () => {
		const onDismiss = jest.fn();

		render( <Spotlight { ...defaultProps } onDismiss={ onDismiss } /> );

		const closeButton = screen.getByLabelText( 'Close' );
		userEvent.click( closeButton );

		// onDismiss is called after animation timeout
		await waitFor(
			() => {
				expect( onDismiss ).toHaveBeenCalledTimes( 1 );
			},
			{ timeout: 500 }
		);
	} );

	it( 'does not render when showImmediately is false initially', () => {
		const propsWithDelay = {
			...defaultProps,
			showImmediately: false,
		};
		render( <Spotlight { ...propsWithDelay } /> );

		// Component should not be visible initially
		expect( screen.queryByText( 'Test Heading' ) ).not.toBeInTheDocument();
	} );

	it( 'renders after delay when showImmediately is false', async () => {
		jest.useFakeTimers();
		const propsWithDelay = {
			...defaultProps,
			showImmediately: false,
		};
		render( <Spotlight { ...propsWithDelay } /> );

		// Component should not be visible initially
		expect( screen.queryByText( 'Test Heading' ) ).not.toBeInTheDocument();

		// Fast forward time by 4 seconds
		act( () => {
			jest.advanceTimersByTime( 4000 );
		} );

		// Component should now be visible
		await waitFor( () => {
			expect( screen.getByText( 'Test Heading' ) ).toBeInTheDocument();
		} );

		jest.useRealTimers();
	} );

	it( 'renders description with React component content', () => {
		const propsWithReactContent = {
			...defaultProps,
			description: (
				<>
					Test with <strong>bold</strong> text
				</>
			),
		};
		render( <Spotlight { ...propsWithReactContent } /> );

		expect( screen.getByText( /Test with/i ) ).toBeInTheDocument();
		expect( screen.getByText( 'bold' ) ).toBeInTheDocument();
	} );

	it( 'applies correct CSS classes', () => {
		const { container } = render( <Spotlight { ...defaultProps } /> );

		expect(
			container.querySelector( '.wcpay-spotlight' )
		).toBeInTheDocument();
		expect(
			container.querySelector( '.wcpay-spotlight--visible' )
		).toBeInTheDocument();
		expect(
			container.querySelector( '.wcpay-spotlight__card' )
		).toBeInTheDocument();
	} );
} );
