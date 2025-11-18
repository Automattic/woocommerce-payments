/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import Badge from '../index';

// Mock the style import
jest.mock( '../style.scss', () => ( {} ) );

describe( 'Badge Component', () => {
	it( 'renders with default info variant', () => {
		const { container } = render( <Badge>Test Badge</Badge> );

		expect( screen.getByText( 'Test Badge' ) ).toBeInTheDocument();
		expect(
			container.querySelector( '.wcpay-badge--info' )
		).toBeInTheDocument();
	} );

	it( 'renders with success variant', () => {
		const { container } = render(
			<Badge variant="success">Success Badge</Badge>
		);

		expect( screen.getByText( 'Success Badge' ) ).toBeInTheDocument();
		expect(
			container.querySelector( '.wcpay-badge--success' )
		).toBeInTheDocument();
	} );

	it( 'renders with warning variant', () => {
		const { container } = render(
			<Badge variant="warning">Warning Badge</Badge>
		);

		expect( screen.getByText( 'Warning Badge' ) ).toBeInTheDocument();
		expect(
			container.querySelector( '.wcpay-badge--warning' )
		).toBeInTheDocument();
	} );

	it( 'renders with error variant', () => {
		const { container } = render(
			<Badge variant="error">Error Badge</Badge>
		);

		expect( screen.getByText( 'Error Badge' ) ).toBeInTheDocument();
		expect(
			container.querySelector( '.wcpay-badge--error' )
		).toBeInTheDocument();
	} );

	it( 'applies custom className', () => {
		const { container } = render(
			<Badge className="custom-class">Custom Badge</Badge>
		);

		expect( screen.getByText( 'Custom Badge' ) ).toBeInTheDocument();
		expect(
			container.querySelector( '.wcpay-badge.custom-class' )
		).toBeInTheDocument();
	} );

	it( 'applies base wcpay-badge class to all variants', () => {
		const { container } = render( <Badge>Test</Badge> );

		expect( container.querySelector( '.wcpay-badge' ) ).toBeInTheDocument();
	} );
} );
