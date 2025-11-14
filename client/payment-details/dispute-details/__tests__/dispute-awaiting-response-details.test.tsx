/** @format */
/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';

/**
 * Internal dependencies
 */
import DisputeAwaitingResponseDetails from '../dispute-awaiting-response-details';
import { useDisputeAccept } from 'wcpay/data';
import type { Dispute } from 'wcpay/types/disputes';
import type { ChargeBillingDetails } from 'wcpay/types/charges';
import WCPaySettingsContext from 'wcpay/settings/wcpay-settings-context';

const mockDisputeDoAccept = jest.fn();

jest.mock( 'wcpay/data', () => ( {
	useDisputeAccept: jest.fn( () => ( {
		doAccept: mockDisputeDoAccept,
		isLoading: false,
	} ) ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( {
		setIsMatching: jest.fn(),
		onLoad: jest.fn(),
	} ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	useDispatch: jest.fn( () => ( {
		createErrorNotice: jest.fn(),
	} ) ),
	useSelect: jest.fn( () => ( { getNotices: jest.fn() } ) ),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
} ) );

jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		connect: {
			country: string;
		};
	};
};

const mockUseDisputeAccept = useDisputeAccept as jest.MockedFunction<
	typeof useDisputeAccept
>;

const getBaseDispute = (): Dispute => ( {
	id: 'dp_visa_compliance_1',
	amount: 5000,
	charge: 'ch_mock',
	order: null,
	balance_transactions: [
		{
			amount: -5000,
			currency: 'usd',
			fee: 1500,
			reporting_category: 'dispute',
		},
	],
	created: 1693453017,
	currency: 'usd',
	evidence: {},
	evidence_details: {
		due_by: 1694303999,
		has_evidence: false,
		past_due: false,
		submission_count: 0,
	},
	issuer_evidence: null,
	metadata: {},
	payment_intent: 'pi_1',
	reason: 'noncompliant',
	status: 'needs_response',
	enhanced_eligibility_types: [ 'visa_compliance' ],
} );

const getBaseBillingDetails = (): ChargeBillingDetails => ( {
	name: 'Test Customer',
	email: 'test@example.com',
	phone: null,
	address: {
		city: 'Test City',
		country: 'US',
		line1: '123 Test St',
		line2: '',
		postal_code: '12345',
		state: 'CA',
	},
} );

const renderWithContext = ( component: React.ReactElement ) => {
	const settingsContext = {
		accountStatus: {
			country: 'US',
		},
		storeName: 'Test Store',
		featureFlags: {
			isDisputeIssuerEvidenceEnabled: false,
		},
	};
	return render(
		// @ts-expect-error: Only need a part of the settings for the test
		<WCPaySettingsContext.Provider value={ settingsContext }>
			{ component }
		</WCPaySettingsContext.Provider>
	);
};

describe( 'DisputeAwaitingResponseDetails - Visa Compliance', () => {
	// eslint-disable-next-line
	const originalWarn = console.warn;

	beforeEach( () => {
		jest.clearAllMocks();

		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			connect: {
				country: 'US',
			},
		};

		// Suppress the List component deprecation warning
		// eslint-disable-next-line
		console.warn = ( ...args ) => {
			const warningMessage = args[ 0 ];
			if (
				typeof warningMessage === 'string' &&
				warningMessage.includes( 'List with items prop is deprecated' )
			) {
				return; // Suppress this specific warning
			}
			originalWarn( ...args ); // Pass through other warnings
		};
	} );

	afterEach( () => {
		// Restore original console.warn after each test
		// eslint-disable-next-line
		console.warn = originalWarn;
	} );

	test( 'renders Visa compliance dispute with NonCompliantDisputeSteps', () => {
		const dispute = getBaseDispute();
		const customer = getBaseBillingDetails();

		const { container } = renderWithContext(
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				orderUrl="https://example.com/order/123"
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		// Check for Visa compliance specific content
		expect( screen.getByText( /Steps you can take/i ) ).toBeInTheDocument();

		// Check for the two steps: Accept and Challenge
		expect(
			screen.getByText( /Accepting the dispute/i, {
				selector: '.dispute-steps__item-name',
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( /Challenge the dispute/i, {
				selector: '.dispute-steps__item-name',
			} )
		).toBeInTheDocument();

		expect( container ).toMatchSnapshot();
	} );

	test( 'renders Visa compliance checkbox', () => {
		const dispute = getBaseDispute();
		const customer = getBaseBillingDetails();

		renderWithContext(
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				orderUrl="https://example.com/order/123"
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		// Check for the checkbox with the $500 fee acknowledgment
		const checkbox = screen.getByRole( 'checkbox', {
			name: /By checking this box, you acknowledge that challenging this Visa compliance dispute incurs a \$500 USD fee/i,
		} );

		expect( checkbox ).toBeInTheDocument();
		expect( checkbox ).not.toBeChecked();
	} );

	test( 'Challenge button is disabled when checkbox is not checked and no staged evidence', () => {
		const dispute = getBaseDispute();
		const customer = getBaseBillingDetails();

		renderWithContext(
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				orderUrl="https://example.com/order/123"
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		const challengeButton = screen.getByRole( 'button', {
			name: /Challenge dispute/i,
		} );

		expect( challengeButton ).toBeDisabled();
	} );

	test( 'Challenge button is enabled when checkbox is checked', async () => {
		const dispute = getBaseDispute();
		const customer = getBaseBillingDetails();

		renderWithContext(
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				orderUrl="https://example.com/order/123"
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		const checkbox = screen.getByRole( 'checkbox', {
			name: /By checking this box, you acknowledge that challenging this Visa compliance dispute incurs a \$500 USD fee/i,
		} );

		// Check the checkbox
		await userEvent.click( checkbox );

		expect( checkbox ).toBeChecked();

		const challengeButton = screen.getByRole( 'button', {
			name: /Challenge dispute/i,
		} );

		expect( challengeButton ).not.toBeDisabled();
	} );

	test( 'Challenge button is enabled when there is staged evidence (regardless of checkbox)', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			evidence_details: {
				due_by: 1694303999,
				has_evidence: true, // Has staged evidence
				past_due: false,
				submission_count: 0,
			},
		};
		const customer = getBaseBillingDetails();

		renderWithContext(
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				orderUrl="https://example.com/order/123"
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		const challengeButton = screen.getByRole( 'button', {
			name: /Continue with challenge/i,
		} );

		// Button should be enabled even without checking the checkbox
		expect( challengeButton ).not.toBeDisabled();
	} );

	test( 'renders correct help link for Visa compliance disputes', () => {
		const dispute = getBaseDispute();
		const customer = getBaseBillingDetails();

		renderWithContext(
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				orderUrl="https://example.com/order/123"
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		const helpLink = screen.getByRole( 'link', {
			name: /Learn more about Visa compliance disputes/i,
		} );

		expect( helpLink ).toBeInTheDocument();
		expect( helpLink ).toHaveAttribute(
			'href',
			'https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#visa-compliance-disputes'
		);
	} );

	test( 'Accept dispute button works correctly for Visa compliance disputes', async () => {
		const dispute = getBaseDispute();
		const customer = getBaseBillingDetails();

		renderWithContext(
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				orderUrl="https://example.com/order/123"
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		const acceptButton = screen.getByRole( 'button', {
			name: /Accept dispute/i,
		} );

		// Accept button should be enabled
		expect( acceptButton ).not.toBeDisabled();

		// Click to open modal
		await userEvent.click( acceptButton );

		// Check modal is displayed
		expect(
			screen.getByRole( 'heading', { name: /Accept the dispute\?/i } )
		).toBeInTheDocument();
	} );

	test( 'does not render checkbox for non-Visa compliance disputes', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			reason: 'fraudulent', // Different reason
			enhanced_eligibility_types: [],
		};
		const customer = getBaseBillingDetails();

		renderWithContext(
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				orderUrl="https://example.com/order/123"
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		// Checkbox should not be present
		expect(
			screen.queryByRole( 'checkbox', {
				name: /By checking this box, you acknowledge that challenging this Visa compliance dispute/i,
			} )
		).not.toBeInTheDocument();
	} );

	test( 'render checkbox when reason is noncompliant but missing visa_compliance eligibility type', () => {
		const dispute: Dispute = {
			...getBaseDispute(),
			enhanced_eligibility_types: [], // Missing visa_compliance
		};
		const customer = getBaseBillingDetails();

		renderWithContext(
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				orderUrl="https://example.com/order/123"
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		// Checkbox should not be present
		expect(
			screen.queryByRole( 'checkbox', {
				name: /By checking this box, you acknowledge that challenging this Visa compliance dispute/i,
			} )
		).toBeInTheDocument();
	} );

	test( 'Challenge button remains disabled during accept request', () => {
		mockUseDisputeAccept.mockReturnValueOnce( {
			doAccept: mockDisputeDoAccept,
			isLoading: true, // Request in progress
		} );

		const dispute = getBaseDispute();
		const customer = getBaseBillingDetails();

		renderWithContext(
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				orderUrl="https://example.com/order/123"
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		const challengeButton = screen.getByRole( 'button', {
			name: /Challenge dispute/i,
		} );

		expect( challengeButton ).toBeDisabled();
	} );

	test( 'renders Visa-specific notice text in dispute notice', () => {
		const dispute = getBaseDispute();
		const customer = getBaseBillingDetails();

		renderWithContext(
			<DisputeAwaitingResponseDetails
				dispute={ dispute }
				customer={ customer }
				chargeCreated={ 1693453017 }
				orderUrl="https://example.com/order/123"
				paymentMethod="card"
				bankName="Chase Bank"
			/>
		);

		// Check for Visa-specific text in the notice
		expect(
			screen.getByText(
				/Your customer’s bank, Chase Bank, claims this payment violates Visa’s rules/i,
				{ exact: false }
			)
		).toBeInTheDocument();

		expect(
			screen.getByText( /Challenging adds an additional \$500 USD/i, {
				exact: false,
			} )
		).toBeInTheDocument();
	} );
} );
