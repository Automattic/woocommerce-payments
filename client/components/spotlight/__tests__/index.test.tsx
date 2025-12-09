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

	it( 'renders footnote when provided', () => {
		const propsWithFootnote = {
			...defaultProps,
			footnote: '*Terms and conditions apply',
		};
		render( <Spotlight { ...propsWithFootnote } /> );

		expect(
			screen.getByText( '*Terms and conditions apply' )
		).toBeInTheDocument();
	} );

	it( 'renders footnote with React component content', () => {
		const propsWithReactFootnote = {
			...defaultProps,
			footnote: (
				<>
					*Terms and <em>conditions</em> apply
				</>
			),
		};
		render( <Spotlight { ...propsWithReactFootnote } /> );

		expect( screen.getByText( /Terms and/i ) ).toBeInTheDocument();
		expect( screen.getByText( 'conditions' ) ).toBeInTheDocument();
	} );

	it( 'renders image when provided as string', () => {
		const propsWithImage = {
			...defaultProps,
			image: 'https://example.com/image.png',
		};
		const { container } = render( <Spotlight { ...propsWithImage } /> );

		// Image is decorative (role="presentation", aria-hidden="true"), so query by tag directly
		const image = container.querySelector( 'img' );
		expect( image ).toBeInTheDocument();
		expect( image ).toHaveAttribute(
			'src',
			'https://example.com/image.png'
		);
		expect( image ).toHaveAttribute( 'alt', '' );
		expect( image ).toHaveAttribute( 'role', 'presentation' );
		expect( image ).toHaveAttribute( 'aria-hidden', 'true' );
	} );

	it( 'renders image when provided as React element', () => {
		const propsWithImage = {
			...defaultProps,
			image: <div data-testid="custom-image">Custom Image</div>,
		};
		render( <Spotlight { ...propsWithImage } /> );

		expect( screen.getByTestId( 'custom-image' ) ).toBeInTheDocument();
	} );

	it( 'calls onPrimaryClick but not onDismiss when primary button is clicked', async () => {
		jest.useFakeTimers();
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

		// Fast forward past the animation timeout
		act( () => {
			jest.advanceTimersByTime( 500 );
		} );

		// onDismiss should NOT be called - backend handles dismissal on activation
		expect( onDismiss ).not.toHaveBeenCalled();

		jest.useRealTimers();
	} );

	it( 'calls onSecondaryClick when secondary button is clicked', async () => {
		const onSecondaryClick = jest.fn();

		render(
			<Spotlight
				{ ...defaultProps }
				secondaryButtonLabel="Learn more"
				onSecondaryClick={ onSecondaryClick }
			/>
		);

		const secondaryButton = screen.getByText( 'Learn more' );
		await userEvent.click( secondaryButton );

		expect( onSecondaryClick ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'calls onDismiss when close button is clicked', async () => {
		const onDismiss = jest.fn();

		render( <Spotlight { ...defaultProps } onDismiss={ onDismiss } /> );

		const closeButton = screen.getByLabelText( 'Close' );
		await userEvent.click( closeButton );

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

	it( 'calls onView when spotlight becomes visible with showImmediately', () => {
		const onView = jest.fn();

		render( <Spotlight { ...defaultProps } onView={ onView } /> );

		expect( onView ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'calls onView after delay when showImmediately is false', async () => {
		jest.useFakeTimers();
		const onView = jest.fn();

		render(
			<Spotlight
				{ ...defaultProps }
				showImmediately={ false }
				onView={ onView }
			/>
		);

		// onView should not be called initially
		expect( onView ).not.toHaveBeenCalled();

		// Fast forward time by 4 seconds
		act( () => {
			jest.advanceTimersByTime( 4000 );
		} );

		// Flush requestAnimationFrame calls
		await act( async () => {
			await Promise.resolve();
		} );

		// onView should now be called
		await waitFor( () => {
			expect( onView ).toHaveBeenCalledTimes( 1 );
		} );

		jest.useRealTimers();
	} );

	describe( 'Accessibility', () => {
		it( 'has correct dialog ARIA attributes', () => {
			render( <Spotlight { ...defaultProps } /> );

			const dialog = screen.getByRole( 'dialog' );
			expect( dialog ).toHaveAttribute( 'aria-modal', 'true' );
			expect( dialog ).toHaveAttribute(
				'aria-labelledby',
				'spotlight-heading'
			);
		} );

		it( 'heading has correct id for aria-labelledby', () => {
			render( <Spotlight { ...defaultProps } /> );

			const heading = screen.getByRole( 'heading', {
				name: 'Test Heading',
			} );
			expect( heading ).toHaveAttribute( 'id', 'spotlight-heading' );
		} );

		it( 'closes spotlight when Escape key is pressed', async () => {
			const onDismiss = jest.fn();

			render( <Spotlight { ...defaultProps } onDismiss={ onDismiss } /> );

			// Press Escape key
			await userEvent.keyboard( '{Escape}' );

			// onDismiss is called after animation timeout
			await waitFor(
				() => {
					expect( onDismiss ).toHaveBeenCalledTimes( 1 );
				},
				{ timeout: 500 }
			);
		} );

		it( 'traps focus within the dialog on Tab', async () => {
			const propsWithSecondary = {
				...defaultProps,
				secondaryButtonLabel: 'Learn more',
				onSecondaryClick: jest.fn(),
			};

			render( <Spotlight { ...propsWithSecondary } /> );

			const closeButton = screen.getByLabelText( 'Close' );
			const primaryButton = screen.getByText( 'Activate' );

			// Focus the last element (primary button)
			primaryButton.focus();
			expect( primaryButton.ownerDocument.activeElement ).toBe(
				primaryButton
			);

			// Tab should wrap to the first focusable element (close button)
			await userEvent.tab();
			expect( primaryButton.ownerDocument.activeElement ).toBe(
				closeButton
			);
		} );

		it( 'traps focus within the dialog on Shift+Tab', async () => {
			const propsWithSecondary = {
				...defaultProps,
				secondaryButtonLabel: 'Learn more',
				onSecondaryClick: jest.fn(),
			};

			render( <Spotlight { ...propsWithSecondary } /> );

			const closeButton = screen.getByLabelText( 'Close' );
			const primaryButton = screen.getByText( 'Activate' );

			// Focus the first element (close button)
			closeButton.focus();
			expect( closeButton.ownerDocument.activeElement ).toBe(
				closeButton
			);

			// Shift+Tab should wrap to the last focusable element (primary button)
			await userEvent.tab( { shift: true } );
			expect( closeButton.ownerDocument.activeElement ).toBe(
				primaryButton
			);
		} );

		it( 'focuses the dialog when it becomes visible', () => {
			render( <Spotlight { ...defaultProps } /> );

			const dialog = screen.getByRole( 'dialog' );
			expect( dialog.ownerDocument.activeElement ).toBe( dialog );
		} );
	} );

	describe( 'Badge type variations', () => {
		it( 'renders badge with default success type when badgeType is not provided', () => {
			const { container } = render( <Spotlight { ...defaultProps } /> );

			const badge = container.querySelector( '.chip-success' );
			expect( badge ).toBeInTheDocument();
		} );

		it( 'renders badge with specified badgeType', () => {
			const propsWithBadgeType = {
				...defaultProps,
				badgeType: 'warning' as const,
			};
			const { container } = render(
				<Spotlight { ...propsWithBadgeType } />
			);

			const badge = container.querySelector( '.chip-warning' );
			expect( badge ).toBeInTheDocument();
		} );

		it( 'renders badge with alert type', () => {
			const propsWithAlertType = {
				...defaultProps,
				badgeType: 'alert' as const,
			};
			const { container } = render(
				<Spotlight { ...propsWithAlertType } />
			);

			const badge = container.querySelector( '.chip-alert' );
			expect( badge ).toBeInTheDocument();
		} );

		it( 'defaults to success type when invalid badgeType is provided', () => {
			const propsWithInvalidType = {
				...defaultProps,
				badgeType: 'invalid-type' as any,
			};
			const { container } = render(
				<Spotlight { ...propsWithInvalidType } />
			);

			// Should fall back to success type
			const badge = container.querySelector( '.chip-success' );
			expect( badge ).toBeInTheDocument();
		} );

		it( 'defaults to success type when badgeType is undefined', () => {
			const propsWithUndefinedType = {
				...defaultProps,
				badgeType: undefined,
			};
			const { container } = render(
				<Spotlight { ...propsWithUndefinedType } />
			);

			const badge = container.querySelector( '.chip-success' );
			expect( badge ).toBeInTheDocument();
		} );
	} );

	describe( 'CSS class variations', () => {
		it( 'applies has-image class when image is provided', () => {
			const propsWithImage = {
				...defaultProps,
				image: 'https://example.com/image.png',
			};
			const { container } = render( <Spotlight { ...propsWithImage } /> );

			expect(
				container.querySelector( '.wcpay-spotlight__card.has-image' )
			).toBeInTheDocument();
		} );

		it( 'does not apply has-image class when no image provided', () => {
			const { container } = render( <Spotlight { ...defaultProps } /> );

			const card = container.querySelector( '.wcpay-spotlight__card' );
			expect( card ).toBeInTheDocument();
			expect( card ).not.toHaveClass( 'has-image' );
		} );

		it( 'removes visible class during close animation', async () => {
			const onDismiss = jest.fn();
			const { container } = render(
				<Spotlight { ...defaultProps } onDismiss={ onDismiss } />
			);

			// Initially visible
			expect(
				container.querySelector( '.wcpay-spotlight--visible' )
			).toBeInTheDocument();

			// Click close
			const closeButton = screen.getByLabelText( 'Close' );
			await userEvent.click( closeButton );

			// Class should be removed immediately (before timeout completes)
			expect(
				container.querySelector( '.wcpay-spotlight--visible' )
			).not.toBeInTheDocument();
		} );
	} );
} );
