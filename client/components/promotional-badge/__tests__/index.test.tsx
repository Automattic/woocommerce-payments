/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PromotionalBadge from '../';

describe( 'PromotionalBadge', () => {
	test( 'renders the badge with message', () => {
		render(
			<PromotionalBadge
				message="50% off fees"
				tooltip="You are getting 50% off on processing fees."
			/>
		);

		expect( screen.getByText( '50% off fees' ) ).toBeInTheDocument();
	} );

	test( 'renders with success type by default', () => {
		const { container } = render(
			<PromotionalBadge
				message="25% off fees"
				tooltip="Discount details"
			/>
		);

		const badge = container.querySelector( '.wcpay-promotional-badge' );
		expect( badge ).toHaveClass( 'chip-success' );
	} );

	test( 'renders with custom chip type', () => {
		const { container } = render(
			<PromotionalBadge
				message="Limited offer"
				tooltip="Discount details"
				type="warning"
			/>
		);

		const badge = container.querySelector( '.wcpay-promotional-badge' );
		expect( badge ).toHaveClass( 'chip-warning' );
	} );

	test( 'renders the info icon button', () => {
		render(
			<PromotionalBadge
				message="50% off fees"
				tooltip="Tooltip content"
				tooltipLabel="Discount details"
			/>
		);

		const tooltipButton = screen.getByRole( 'button', {
			name: /discount details/i,
		} );
		expect( tooltipButton ).toBeInTheDocument();
	} );

	test( 'shows tooltip content when clicking the info icon', () => {
		render(
			<PromotionalBadge
				message="50% off fees"
				tooltip="You are getting 50% off on processing fees."
				tooltipLabel="Discount details"
			/>
		);

		const tooltipButton = screen.getByRole( 'button', {
			name: /discount details/i,
		} );
		fireEvent.click( tooltipButton );

		expect(
			screen.getByText( 'You are getting 50% off on processing fees.' )
		).toBeInTheDocument();
	} );

	test( 'uses default tooltip label when not provided', () => {
		render(
			<PromotionalBadge
				message="50% off fees"
				tooltip="Tooltip content"
			/>
		);

		const tooltipButton = screen.getByRole( 'button', {
			name: /more information/i,
		} );
		expect( tooltipButton ).toBeInTheDocument();
	} );

	test( 'applies chip base class', () => {
		const { container } = render(
			<PromotionalBadge message="Test badge" tooltip="Test tooltip" />
		);

		const badge = container.querySelector( '.wcpay-promotional-badge' );
		expect( badge ).toHaveClass( 'chip' );
	} );

	test( 'renders correctly with all props', () => {
		const { container } = render(
			<PromotionalBadge
				message="30% off fees through Dec 31, 2026"
				tooltip="You are getting 30% off on processing fees for the first $1,000 of total payment volume or through Dec 31, 2026."
				type="primary"
				tooltipLabel="View promotion details"
			/>
		);

		expect( container ).toMatchSnapshot();
	} );

	test( 'shows tooltip with T&C link when tcUrl is provided', () => {
		render(
			<PromotionalBadge
				message="50% off fees"
				tooltip="You are getting 50% off on processing fees."
				tooltipLabel="Discount details"
				tcUrl="https://example.com/terms"
				tcLabel="See terms"
			/>
		);

		const tooltipButton = screen.getByRole( 'button', {
			name: /discount details/i,
		} );
		fireEvent.click( tooltipButton );

		expect(
			screen.getByText( 'You are getting 50% off on processing fees.' )
		).toBeInTheDocument();

		const tcLink = screen.getByRole( 'link', { name: /see terms/i } );
		expect( tcLink ).toBeInTheDocument();
		expect( tcLink ).toHaveAttribute( 'href', 'https://example.com/terms' );
		expect( tcLink ).toHaveAttribute( 'target', '_blank' );
		expect( tcLink ).toHaveAttribute( 'rel', 'noopener noreferrer' );
	} );

	test( 'uses backend-provided tcLabel when available', () => {
		render(
			<PromotionalBadge
				message="50% off fees"
				tooltip="Promotion description."
				tcUrl="https://example.com/terms"
				tcLabel="View full terms and conditions"
			/>
		);

		const tooltipButton = screen.getByRole( 'button', {
			name: /more information/i,
		} );
		fireEvent.click( tooltipButton );

		const tcLink = screen.getByRole( 'link', {
			name: /view full terms and conditions/i,
		} );
		expect( tcLink ).toBeInTheDocument();
		// Ensure the default is NOT used.
		expect(
			screen.queryByRole( 'link', {
				name: /^see terms and conditions$/i,
			} )
		).not.toBeInTheDocument();
	} );

	test( 'uses default T&C label when tcUrl is provided but tcLabel is not', () => {
		render(
			<PromotionalBadge
				message="50% off fees"
				tooltip="Promotion description."
				tcUrl="https://example.com/terms"
			/>
		);

		const tooltipButton = screen.getByRole( 'button', {
			name: /more information/i,
		} );
		fireEvent.click( tooltipButton );

		const tcLink = screen.getByRole( 'link', {
			name: /see terms and conditions/i,
		} );
		expect( tcLink ).toBeInTheDocument();
	} );

	test( 'uses default T&C label when tcLabel is empty string', () => {
		render(
			<PromotionalBadge
				message="50% off fees"
				tooltip="Promotion description."
				tcUrl="https://example.com/terms"
				tcLabel=""
			/>
		);

		const tooltipButton = screen.getByRole( 'button', {
			name: /more information/i,
		} );
		fireEvent.click( tooltipButton );

		const tcLink = screen.getByRole( 'link', {
			name: /see terms and conditions/i,
		} );
		expect( tcLink ).toBeInTheDocument();
	} );

	test( 'does not show T&C link when tcUrl is not provided', () => {
		render(
			<PromotionalBadge
				message="50% off fees"
				tooltip="You are getting 50% off on processing fees."
				tooltipLabel="Discount details"
			/>
		);

		const tooltipButton = screen.getByRole( 'button', {
			name: /discount details/i,
		} );
		fireEvent.click( tooltipButton );

		expect( screen.queryByRole( 'link' ) ).not.toBeInTheDocument();
	} );

	test( 'renders correctly with all props including T&C', () => {
		const { container } = render(
			<PromotionalBadge
				message="30% off fees through Dec 31, 2026"
				tooltip="You are getting 30% off on processing fees."
				type="primary"
				tooltipLabel="View promotion details"
				tcUrl="https://example.com/terms"
				tcLabel="View terms and conditions"
			/>
		);

		expect( container ).toMatchSnapshot();
	} );
} );
