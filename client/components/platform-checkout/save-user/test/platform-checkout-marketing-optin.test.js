/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import PlatformCheckoutMarketingOptIn from '../platform-checkout-marketing-optin';

describe( 'PlatformCheckoutMarketingOptIn', () => {
	it( 'should not render opt-in for marketing checkbox when the country does not require it', () => {
		render( <PlatformCheckoutMarketingOptIn country="US" /> );
		expect(
			screen.queryByLabelText( 'Opt-in for marketing messages' )
		).not.toBeInTheDocument();
	} );

	it( 'should render opt-in for marketing checkbox when the country does require it', () => {
		render( <PlatformCheckoutMarketingOptIn country="AT" /> );
		expect(
			screen.queryByLabelText( 'Opt-in for marketing messages' )
		).toBeInTheDocument();
	} );
} );
