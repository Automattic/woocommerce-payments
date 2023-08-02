/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import InfoNoticeModal from '../info-notice-modal';

declare const global: {
	wcpaySettings: {
		isWooPayStoreCountryAvailable?: boolean;
	};
};

describe( 'Connect Account Page – Info Notice Modal', () => {
	test( 'renders correctly when opened', () => {
		global.wcpaySettings = {
			isWooPayStoreCountryAvailable: true,
		};

		render( <InfoNoticeModal /> );

		const learnMore = screen.getByRole( 'button', { name: /learn more/i } );
		userEvent.click( learnMore );

		const modalContent = document.querySelector(
			'.components-modal__content'
		);

		expect( modalContent ).toMatchSnapshot();
	} );
} );
