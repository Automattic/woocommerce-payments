/**
 * External dependencies
 */
import React from 'react';
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import InfoNoticeModal from '../info-notice-modal';

describe( 'Connect Account Page – Info Notice Modal', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'renders correctly when opened', () => {
		const { container } = render( <InfoNoticeModal /> );

		expect( container ).toMatchSnapshot();
	} );
} );
