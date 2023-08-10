/** @format */
/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';
import React from 'react';

/**
 * Internal dependencies
 */
import PAYMENT_METHOD_IDS from 'wcpay/payment-methods/constants';
import PaymentMethodDisabledTooltip, {
	DocumentationUrlForDisabledPaymentMethod,
	getDocumentationUrlForDisabledPaymentMethod,
} from '../index';

describe( 'PaymentMethodDisabledTooltip', () => {
	test.each( [
		[
			PAYMENT_METHOD_IDS.AFTERPAY_CLEARPAY,
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
