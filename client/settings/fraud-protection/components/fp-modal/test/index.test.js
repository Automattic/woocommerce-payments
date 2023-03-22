/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import {
	HighFraudProtectionModal,
	StandardFraudProtectionModal,
} from '../index';

describe( 'HighFraudProtectionModal', () => {
	const setHighModalOpen = jest.fn();

	beforeEach( () => {
		global.wcpaySettings = {
			accountStatus: {
				fraudProtection: {
					declineOnAVSFailure: true,
					declineOnCVCFailure: true,
				},
			},
		};
	} );

	it( 'renders the high protection level content with USD store currency in the modal body', () => {
		const isHighModalOpen = true;
		const storeCurrency = { name: 'US Dollar', symbol: '$', code: 'USD' };

		render(
			<HighFraudProtectionModal
				level="high"
				isHighModalOpen={ isHighModalOpen }
				setHighModalOpen={ setHighModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const highUSDContentModal = screen.getByRole( 'document' );

		expect( highUSDContentModal ).toMatchSnapshot();
		expect( highUSDContentModal ).toHaveTextContent(
			/The billing address does not match what is on file with the card issuer/i
		);
		expect( highUSDContentModal ).toHaveTextContent(
			/The card's issuing bank cannot verify the CVV/i
		);
	} );

	it( 'renders the high protection level content with non-USD store currency in the modal body', () => {
		const isHighModalOpen = true;
		const storeCurrency = { name: 'Euro', symbol: '€', code: 'EUR' };

		render(
			<HighFraudProtectionModal
				level="high"
				isHighModalOpen={ isHighModalOpen }
				setHighModalOpen={ setHighModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const highEURContentModal = screen.getByRole( 'document' );
		expect( highEURContentModal ).toMatchSnapshot();
	} );

	it( "doesn't render the AVS failure text when AVS check is disabled on the platform", () => {
		const isHighModalOpen = true;
		const storeCurrency = { name: 'Euro', symbol: '€', code: 'EUR' };
		global.wcpaySettings.accountStatus.fraudProtection.declineOnAVSFailure = false;

		render(
			<HighFraudProtectionModal
				level="high"
				isHighModalOpen={ isHighModalOpen }
				setHighModalOpen={ setHighModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);
		const highUSDContentModal = screen.getByRole( 'document' );
		expect( highUSDContentModal ).not.toHaveTextContent(
			/The billing address does not match what is on file with the card issuer/i
		);
	} );

	it( "doesn't render the CVC failure text when CVC check is disabled on the platform", () => {
		const isHighModalOpen = true;
		const storeCurrency = { name: 'Euro', symbol: '€', code: 'EUR' };
		global.wcpaySettings.accountStatus.fraudProtection.declineOnCVCFailure = false;

		render(
			<HighFraudProtectionModal
				level="high"
				isHighModalOpen={ isHighModalOpen }
				setHighModalOpen={ setHighModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const highUSDContentModal = screen.getByRole( 'document' );

		expect( highUSDContentModal ).not.toHaveTextContent(
			/The card's issuing bank cannot verify the CVV/i
		);
	} );

	it( "doesn't render both the AVS and CVC failure texts when both checks are disabled on the platform", () => {
		const isHighModalOpen = true;
		const storeCurrency = { name: 'Euro', symbol: '€', code: 'EUR' };
		global.wcpaySettings.accountStatus.fraudProtection.declineOnAVSFailure = false;
		global.wcpaySettings.accountStatus.fraudProtection.declineOnCVCFailure = false;

		render(
			<HighFraudProtectionModal
				level="high"
				isHighModalOpen={ isHighModalOpen }
				setHighModalOpen={ setHighModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const highUSDContentModal = screen.getByRole( 'document' );

		expect( highUSDContentModal ).not.toHaveTextContent(
			/The billing address does not match what is on file with the card issuer/i
		);
		expect( highUSDContentModal ).not.toHaveTextContent(
			/The card's issuing bank cannot verify the CVV/i
		);
	} );
} );

describe( 'StandardFraudProtectionModal', () => {
	const setStandardModalOpen = jest.fn();

	beforeEach( () => {
		global.wcpaySettings = {
			accountStatus: {
				fraudProtection: {
					declineOnAVSFailure: true,
					declineOnCVCFailure: true,
				},
			},
		};
	} );

	it( 'renders the standard protection level content with USD store currency in the modal body', () => {
		const isStandardModalOpen = true;
		const storeCurrency = { name: 'US Dollar', symbol: '$', code: 'USD' };

		render(
			<StandardFraudProtectionModal
				level="standard"
				isStandardModalOpen={ isStandardModalOpen }
				setStandardModalOpen={ setStandardModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const standardUSDContentModal = screen.getByRole( 'document' );

		expect( standardUSDContentModal ).toMatchSnapshot();
		expect( standardUSDContentModal ).toHaveTextContent(
			/The billing address does not match what is on file with the card issuer/i
		);
		expect( standardUSDContentModal ).toHaveTextContent(
			/The card's issuing bank cannot verify the CVV/i
		);
	} );

	it( 'renders the standard protection level content with non-USD store currency in the modal body', () => {
		const isStandardModalOpen = true;
		const storeCurrency = { name: 'Euro', symbol: '€', code: 'EUR' };

		render(
			<StandardFraudProtectionModal
				level="standard"
				isStandardModalOpen={ isStandardModalOpen }
				setStandardModalOpen={ setStandardModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const standardEURContentModal = screen.getByRole( 'document' );

		expect( standardEURContentModal ).toMatchSnapshot();
	} );

	it( "doesn't render the AVS check failure block text when AVS check is disabled for the account", () => {
		const isStandardModalOpen = true;
		const storeCurrency = { name: 'US Dollar', symbol: '$', code: 'USD' };
		global.wcpaySettings.accountStatus.fraudProtection.declineOnAVSFailure = false;

		render(
			<StandardFraudProtectionModal
				level="standard"
				isStandardModalOpen={ isStandardModalOpen }
				setStandardModalOpen={ setStandardModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const standardUSDContentModal = screen.getByRole( 'document' );

		expect( standardUSDContentModal ).not.toHaveTextContent(
			/The billing address does not match what is on file with the card issuer/i
		);
		expect( standardUSDContentModal ).toHaveTextContent(
			/The card's issuing bank cannot verify the CVV/i
		);
	} );

	it( "doesn't render the CVC check failure block text when CVC check is disabled for the account", () => {
		const isStandardModalOpen = true;
		const storeCurrency = { name: 'US Dollar', symbol: '$', code: 'USD' };
		global.wcpaySettings.accountStatus.fraudProtection.declineOnCVCFailure = false;

		render(
			<StandardFraudProtectionModal
				level="standard"
				isStandardModalOpen={ isStandardModalOpen }
				setStandardModalOpen={ setStandardModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const standardUSDContentModal = screen.getByRole( 'document' );

		expect( standardUSDContentModal ).toHaveTextContent(
			/The billing address does not match what is on file with the card issuer/i
		);
		expect( standardUSDContentModal ).not.toHaveTextContent(
			/The card's issuing bank cannot verify the CVV/i
		);
	} );

	it( "doesn't render the block list complelely when both checks are disabled for the account", () => {
		const isStandardModalOpen = true;
		const storeCurrency = { name: 'US Dollar', symbol: '$', code: 'USD' };
		global.wcpaySettings.accountStatus.fraudProtection.declineOnAVSFailure = false;
		global.wcpaySettings.accountStatus.fraudProtection.declineOnCVCFailure = false;

		render(
			<StandardFraudProtectionModal
				level="standard"
				isStandardModalOpen={ isStandardModalOpen }
				setStandardModalOpen={ setStandardModalOpen }
				storeCurrency={ storeCurrency }
			/>
		);

		const standardUSDContentModal = screen.getByRole( 'document' );

		expect( standardUSDContentModal ).not.toHaveTextContent(
			/The billing address does not match what is on file with the card issuer/i
		);
		expect( standardUSDContentModal ).not.toHaveTextContent(
			/The card's issuing bank cannot verify the CVV/i
		);
		expect( standardUSDContentModal ).not.toHaveTextContent(
			/Payments will be blocked if:/i
		);
	} );
} );
