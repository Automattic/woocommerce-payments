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
		isJetpackConnected: boolean;
	};
};

describe( 'Connect Account Page – Info Notice Modal', () => {
	test( 'renders correctly when opened', () => {
		global.wcpaySettings = {
			isWooPayStoreCountryAvailable: true,
			isJetpackConnected: true,
		};

		render( <InfoNoticeModal /> );

		const enableDeposits = screen.getByRole( 'button', {
			name: /enable deposits./i,
		} );
		userEvent.click( enableDeposits );

		const modalContent = document.querySelector(
			'.components-modal__content'
		);

		expect( modalContent ).toMatchSnapshot();
	} );
} );
