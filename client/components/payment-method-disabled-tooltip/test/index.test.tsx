/** @format */
/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import PaymentMethodDisabledTooltip, {
	DocumentationUrlForDisabledPaymentMethod,
	getDocumentationUrlForDisabledPaymentMethod,
} from '../index';

const mockWooPaymentsPaymentMethodsConfig: typeof window.wooPaymentsPaymentMethodsConfig = {
	mock_payment_method_id: {
		isBnpl: true, // This is the only property that matters for these tests.
		title: 'Mock Payment Method',
		icon: '',
		darkIcon: '',
		countries: [],
		testingInstructions: '',
		isReusable: false,
		showSaveOption: false,
		forceNetworkSavedCards: false,
	},
};

// Set up the global configuration before tests
beforeAll( () => {
	window.wooPaymentsPaymentMethodsConfig = mockWooPaymentsPaymentMethodsConfig;
} );

// Clean up after tests
afterAll( () => {
	delete window.wooPaymentsPaymentMethodsConfig;
} );

describe( 'PaymentMethodDisabledTooltip', () => {
	test.each( [
		[
			'mock_payment_method_id',
			DocumentationUrlForDisabledPaymentMethod.BNPLS,
		],
		[ 'default-method', DocumentationUrlForDisabledPaymentMethod.DEFAULT ],
	] )(
		'renders tooltip with correct learn more link for %s',
		( tooltipId, expectedUrl ) => {
			render(
				<PaymentMethodDisabledTooltip id={ tooltipId }>
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
			expect( learnMoreLink ).toHaveAttribute(
				'href',
				getDocumentationUrlForDisabledPaymentMethod( tooltipId )
			);
			expect( learnMoreLink.getAttribute( 'href' ) ).toEqual(
				expectedUrl
			);
			expect( learnMoreLink ).toHaveAttribute( 'target', '_blank' );
			expect( learnMoreLink ).toHaveAttribute( 'rel', 'noreferrer' );
		}
	);
} );
