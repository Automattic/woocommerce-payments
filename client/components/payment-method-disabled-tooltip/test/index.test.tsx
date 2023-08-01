/** @format */
/**
 * External dependencies
 */
import { render, screen, fireEvent } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import PaymentMethodDisabledTooltip from '../index';
import { getDocumentationUrlForDisabledPaymentMethod } from '../utils';

// Mock the utils function
jest.mock( '../utils', () => ( {
	getDocumentationUrlForDisabledPaymentMethod: jest.fn(),
} ) );

describe( 'PaymentMethodDisabledTooltip', () => {
	const mockMethodId = 'testId';
	const mockUrl = 'http://test.url';

	const mockGetDocumentationUrlForDisabledPaymentMethod = getDocumentationUrlForDisabledPaymentMethod as jest.MockedFunction<
		typeof getDocumentationUrlForDisabledPaymentMethod
	>;

	beforeAll( () => {
		mockGetDocumentationUrlForDisabledPaymentMethod.mockImplementation(
			() => mockUrl
		);
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders with correct text and link', () => {
		render(
			<PaymentMethodDisabledTooltip id={ mockMethodId }>
				Test children
			</PaymentMethodDisabledTooltip>
		);

		const element = screen.getByText( 'Test children' );
		expect( element ).toBeInTheDocument();

		fireEvent.mouseOver( element );

		const tooltip = screen.getByRole( 'tooltip' );
		expect( tooltip ).toBeInTheDocument();
		expect( tooltip ).toHaveTextContent(
			'We need more information from you to enable this method. Learn more.'
		);

		const learnMoreLink = screen.getByRole( 'link', {
			name: 'Learn more.',
		} );
		expect( learnMoreLink ).toHaveAttribute( 'href', mockUrl );
		expect( learnMoreLink ).toHaveAttribute( 'target', '_blank' );
		expect( learnMoreLink ).toHaveAttribute( 'rel', 'noreferrer' );
	} );

	test( 'calls getDocumentationUrlForDisabledPaymentMethod with correct id', () => {
		render(
			<PaymentMethodDisabledTooltip id={ mockMethodId }>
				Test children
			</PaymentMethodDisabledTooltip>
		);

		expect(
			mockGetDocumentationUrlForDisabledPaymentMethod
		).toHaveBeenCalledWith( mockMethodId );
	} );
} );
