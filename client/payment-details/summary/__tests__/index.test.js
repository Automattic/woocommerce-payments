/** @format */
/* eslint-disable testing-library/no-unnecessary-act */
/**
 * External dependencies
 */
import { fireEvent, render, screen, within, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import moment from 'moment';

/**
 * Internal dependencies
 */
import PaymentDetailsSummary from '../';
import { useAuthorization } from 'wcpay/data/authorizations';
import { paymentIntentMock } from 'wcpay/data/payment-intents/__tests__/hooks.test';
import { recordEvent } from 'wcpay/tracks';
import { _resetOutcomeViewTrackingForTests } from '../../dispute-outcome/tracks';

// Mock dateI18n
jest.mock( '@wordpress/date', () => ( {
	dateI18n: jest.fn( ( format, date ) => {
		return jest
			.requireActual( '@wordpress/date' )
			.dateI18n( format, date, 'UTC' ); // Ensure UTC is used
	} ),
} ) );

const mockDisputeDoAccept = jest.fn();

jest.mock( 'wcpay/data/authorizations', () => ( {
	useAuthorization: jest.fn( () => ( {
		authorization: null,
	} ) ),
} ) );
jest.mock( 'wcpay/data/disputes', () => ( {
	useDisputeAccept: jest.fn( () => ( {
		doAccept: mockDisputeDoAccept,
		isLoading: false,
	} ) ),
} ) );

jest.mock( 'wcpay/tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	// Slice stores self-register on import; stub the registration APIs.
	createReduxStore: jest.fn(),
	register: jest.fn(),
	combineReducers: jest.fn(),
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

const mockUseAuthorization = useAuthorization;

const getBaseCharge = () => ( {
	id: 'ch_38jdHA39KKA',
	payment_intent: 'pi_abc',
	/* Stripe data comes in seconds, instead of the default Date milliseconds */
	created: 1568913840,
	amount: 2000,
	amount_refunded: 0,
	application_fee_amount: 70,
	disputed: false,
	dispute: null,
	currency: 'usd',
	type: 'charge',
	status: 'succeeded',
	paid: true,
	captured: true,
	balance_transaction: {
		amount: 2000,
		currency: 'usd',
		fee: 70,
	},
	refunds: {
		data: [],
	},
	order: {
		number: 45981,
		url: 'https://somerandomorderurl.com/?edit_order=45981',
	},
	billing_details: {
		name: 'Customer name',
		email: 'mock@example.com',
	},
	payment_method_details: {
		card: {
			brand: 'visa',
			last4: '4242',
		},
		type: 'card',
	},
	outcome: {
		risk_level: 'normal',
	},
} );

const getBaseDispute = () => ( {
	id: 'dp_1',
	amount: 2000,
	charge: 'ch_38jdHA39KKA',
	order: null,
	balance_transactions: [
		{
			amount: -2000,
			currency: 'usd',
			fee: 1500,
			reporting_category: 'dispute',
		},
	],
	created: 1693453017,
	currency: 'usd',
	evidence: {
		billing_address: '123 test address',
		customer_email_address: 'test@email.com',
		customer_name: 'Test customer',
		shipping_address: '123 test address',
	},
	evidence_details: {
		due_by: 1694303999,
		has_evidence: false,
		past_due: false,
		submission_count: 0,
	},
	issuer_evidence: null,
	metadata: {},
	payment_intent: 'pi_1',
	reason: 'fraudulent',
	status: 'needs_response',
} );

const createTapToPayMetadata = ( readerModel, platform ) => ( {
	platform,
	reader_id: 'APPLEBUILTINSIMULATOR-1',
	reader_model: readerModel,
} );

function renderCharge( charge, metadata = {}, isLoading = false, props = {} ) {
	const { container } = render(
		<PaymentDetailsSummary
			charge={ charge }
			metadata={ metadata }
			isLoading={ isLoading }
			{ ...props }
		/>
	);
	return container;
}

// Add this helper function to expand the accordion
const expandAccordion = ( title ) => {
	const accordionTitle = screen.getByText( title, {
		selector: '.wcpay-accordion__title-content',
	} );
	fireEvent.click( accordionTitle );
};

describe( 'PaymentDetailsSummary', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		global.wcpaySettings = {
			accountStatus: {
				country: 'US',
			},
			isSubscriptionsActive: false,
			shouldUseExplicitPrice: false,
			zeroDecimalCurrencies: [ 'jpy' ],
			connect: {
				country: 'US',
			},
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
				JP: {
					code: 'JPY',
					symbol: '¥',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 0,
				},
			},
			dateFormat: 'M j, Y',
			timeFormat: 'g:ia',
			featureFlags: {
				isDisputeIssuerEvidenceEnabled: false,
				isDisputeOutcomeViewEnabled: false,
			},
		};

		// mock Date.now that moment library uses to get current date for testing purposes
		Date.now = jest.fn( () =>
			new Date( '2023-09-08T12:33:37.000Z' ).getTime()
		);
	} );

	afterEach( () => {
		Date.now = () => new Date().getTime();
	} );

	test( 'correctly renders a charge', () => {
		expect( renderCharge( getBaseCharge() ) ).toMatchSnapshot();
		// eslint-disable-next-line @typescript-eslint/ban-ts-comment
		// @ts-ignore
		expect( console ).toHaveWarnedWith(
			// eslint-disable-next-line max-len
			'List with items prop is deprecated is deprecated and will be removed in version 9.0.0. Note: See ExperimentalList / ExperimentalListItem for the new API that will replace this component in future versions.'
		);
	} );

	test( 'correctly renders when payment intent is missing', () => {
		const baseCharge = getBaseCharge();
		baseCharge.payment_intent = null;
		expect( renderCharge( baseCharge ) ).toMatchSnapshot();
	} );

	test( 'renders partially refunded information for a charge', () => {
		const charge = getBaseCharge();
		charge.refunded = false;
		charge.amount_refunded = 1200;
		charge.refunds?.data.push( {
			balance_transaction: {
				amount: -charge.amount_refunded,
				currency: 'usd',
			},
		} );

		expect( renderCharge( charge ) ).toMatchSnapshot();
	} );

	test( 'renders fully refunded information for a charge', () => {
		const charge = getBaseCharge();
		charge.refunded = true;
		charge.amount_refunded = 2000;
		charge.refunds?.data.push( {
			balance_transaction: {
				amount: -charge.amount_refunded,
				currency: 'usd',
			},
		} );

		const container = renderCharge( charge );
		screen.getByText( /Refunded: -\$20.00/i );
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders the Tap to Pay channel from metadata with ios COTS_DEVICE', () => {
		const charge = getBaseCharge();
		const metadata = createTapToPayMetadata( 'COTS_DEVICE', 'ios' );

		expect( renderCharge( charge, metadata ) ).toMatchSnapshot();
	} );

	test( 'renders the Tap to Pay channel from metadata with android TAP_TO_PAY_DEVICE', () => {
		const charge = getBaseCharge();
		const metadata = createTapToPayMetadata(
			'TAP_TO_PAY_DEVICE',
			'android'
		);

		expect( renderCharge( charge, metadata ) ).toMatchSnapshot();
	} );

	test( 'renders a charge with subscriptions', () => {
		global.wcpaySettings.isSubscriptionsActive = true;

		const charge = getBaseCharge();
		if ( charge.order ) {
			charge.order.subscriptions = [
				{
					number: 'custom-246',
					url: 'https://example.com/subscription/246',
				},
			];
		}

		expect( renderCharge( charge ) ).toMatchSnapshot();
	} );

	test( 'renders loading state', () => {
		expect( renderCharge( {}, true ) ).toMatchSnapshot();
	} );

	describe( 'capture notification and fraud buttons', () => {
		beforeAll( () => {
			// Mock current date and time to fixed value in moment
			const fixedCurrentDate = new Date( '2023-01-01T01:00:00.000Z' );
			jest.spyOn( Date, 'now' ).mockImplementation( () =>
				fixedCurrentDate.getTime()
			);
		} );

		afterAll( () => {
			jest.spyOn( Date, 'now' ).mockRestore();
		} );

		test( 'renders capture section correctly', () => {
			mockUseAuthorization.mockReturnValueOnce( {
				authorization: {
					captured: false,
					charge_id: 'ch_mock',
					amount: 1000,
					currency: 'usd',
					created: moment.utc().format(),
					order_id: 123,
					risk_level: 1,
					customer_country: 'US',
					customer_email: 'test@example.com',
					customer_name: 'Test Customer',
					payment_intent_id: 'pi_mock',
				},
				isLoading: false,
				isRequesting: false,
				doCaptureAuthorization: jest.fn(),
				doCancelAuthorization: jest.fn(),
			} );
			const charge = getBaseCharge();
			charge.captured = false;

			const container = renderCharge( charge );

			expect(
				screen.getByRole( 'button', { name: /Capture/i } )
			).toBeInTheDocument();

			expect( container ).toMatchSnapshot();
		} );

		test( 'renders the fraud outcome buttons', () => {
			mockUseAuthorization.mockReturnValueOnce( {
				authorization: {
					captured: false,
					charge_id: 'ch_mock',
					amount: 1000,
					currency: 'usd',
					created: new Date( Date.now() ).toISOString(),
					order_id: 123,
					risk_level: 1,
					customer_country: 'US',
					customer_email: 'test@example.com',
					customer_name: 'Test Customer',
					payment_intent_id: 'pi_mock',
				},
				isLoading: false,
				isRequesting: false,
				doCaptureAuthorization: jest.fn(),
				doCancelAuthorization: jest.fn(),
			} );
			const charge = getBaseCharge();
			charge.captured = false;

			const container = renderCharge( charge, {}, false, {
				paymentIntent: paymentIntentMock,
			} );

			expect(
				screen.getByRole( 'button', { name: /Approve Transaction/i } )
			).toBeInTheDocument();

			expect(
				screen.getByRole( 'button', { name: /Block Transaction/i } )
			).toBeInTheDocument();

			expect(
				screen.queryByRole( 'button', { name: /Capture/i } )
			).not.toBeInTheDocument();

			expect(
				screen.getAllByText( ( content, element ) => {
					return (
						element.textContent.includes( 'You must' ) &&
						element.textContent.includes( 'capture' ) &&
						element.textContent.includes(
							'this charge within the next'
						)
					);
				} ).length
			).toBeGreaterThan( 0 );

			expect( container ).toMatchSnapshot();
		} );
	} );

	test( 'renders the information of a disputed charge', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'needs_response';

		renderCharge( charge );

		// Dispute Notice
		screen.getByText(
			/The cardholder claims this is an unauthorized transaction/,
			{ ignore: '.a11y-speak-region' }
		);

		// Don't render the staged evidence message
		expect(
			screen.queryByText( /You initiated a challenge to this dispute/, {
				ignore: '.a11y-speak-region',
			} )
		).toBeNull();

		// Expand the steps accordion
		expandAccordion( 'Steps you can take' );

		// Steps to resolve
		screen.getByText( /Steps you can take/i, {
			selector: '.wcpay-accordion__title-content',
		} );

		screen.getByText( /Contact your customer/i, {
			selector: '.dispute-step-item__name',
		} );
		screen.getByText( /Ask for the dispute to be withdrawn/i, {
			selector: '.dispute-step-item__name',
		} );
		screen.getByText( /Challenge or accept the dispute/i, {
			selector: '.dispute-step-item__name',
		} );

		screen.getByText(
			/Identify the issue and work towards a resolution where possible\./i,
			{ selector: '.dispute-step-item__description' }
		);
		screen.getByText(
			/If you've managed to resolve the issue with your customer, help them with the withdrawal of their dispute\./i,
			{ selector: '.dispute-step-item__description' }
		);
		screen.getByText(
			// eslint-disable-next-line max-len
			/Disagree with the dispute\? You can challenge it with the customer's bank\. Otherwise, accept it to close the case — the order amount and dispute fee won't be refunded\./i,
			{ selector: '.dispute-step-item__description' }
		);
		screen.getByRole( 'link', { name: /Email customer/i } );
		expect(
			screen.getAllByRole( 'link', { name: /Learn more/i } ).length
		).toBe( 2 );

		// Actions
		screen.getByRole( 'button', {
			name: /Challenge dispute/,
		} );
		screen.getByRole( 'button', {
			name: /Accept dispute/,
		} );

		// Refund menu is not rendered
		expect(
			screen.queryByRole( 'button', {
				name: /Transaction actions/i,
			} )
		).toBeNull();
	} );

	test( 'renders the information of a disputed charge when the store/charge currency differ', () => {
		// True when multi-currency is enabled.
		global.wcpaySettings.shouldUseExplicitPrice = true;

		// In this case, charge currency is JPY, but store currency is NOK.
		const charge = getBaseCharge();
		charge.currency = 'jpy';
		charge.amount = 10000;
		charge.balance_transaction = {
			amount: 72581,
			currency: 'nok',
			reporting_category: 'charge',
			fee: 4152,
		};
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'needs_response';
		charge.dispute.amount = 10000;
		charge.dispute.currency = 'jpy';
		charge.dispute.balance_transactions = [
			{
				amount: -72581,
				currency: 'nok',
				fee: 15000,
				reporting_category: 'dispute',
			},
		];
		renderCharge( charge );

		// Disputed amount should show the shopper/transaction currency.
		expect(
			screen.getByText( /Dispute Amount/i ).nextSibling
		).toHaveTextContent( /¥10,000 JPY/i );
	} );

	test( 'renders the information of a dispute-reversal charge', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'won';

		charge.dispute.balance_transactions = [
			{
				amount: -2000,
				fee: 1500,
				currency: 'usd',
				reporting_category: 'dispute',
			},
			{
				amount: 2000,
				fee: -1500,
				currency: 'usd',
				reporting_category: 'dispute_reversal',
			},
		];

		const container = renderCharge( charge );
		expect(
			screen.queryByText( /Deducted: \$-15.00/i )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', {
				name: /Fee breakdown/i,
			} )
		).not.toBeInTheDocument();
		expect( container ).toMatchSnapshot();
	} );

	test( 'renders the fee breakdown tooltip of a disputed charge', async () => {
		const charge = {
			...getBaseCharge(),
			currency: 'jpy',
			amount: 10000,
			balance_transaction: {
				amount: 2000,
				currency: 'usd',
				fee: 70,
			},
			disputed: true,
			dispute: {
				...getBaseDispute(),
				amount: 10000,
				status: 'under_review',
				balance_transactions: [
					{
						amount: -1500,
						fee: 1500,
						currency: 'usd',
						reporting_category: 'dispute',
					},
				],
			},
		};

		renderCharge( charge );

		// Open tooltip content
		const tooltipButton = screen.getByRole( 'button', {
			name: /Fee breakdown/i,
		} );
		await userEvent.click( tooltipButton );

		// Check fee breakdown calculated correctly
		const tooltipContent = screen.getByRole( 'tooltip' );
		expect(
			within( tooltipContent ).getByLabelText( /Transaction fee/ )
		).toHaveTextContent( /\$0.70/ );

		expect(
			within( tooltipContent ).getByLabelText( /Dispute fee/ )
		).toHaveTextContent( /\$15.00/ );

		expect(
			within( tooltipContent ).getByLabelText( /Total fees/ )
		).toHaveTextContent( /\$15.70/ );
	} );

	test( 'renders the information of an inquiry when the store/charge currency differ', () => {
		// True when multi-currency is enabled.
		global.wcpaySettings.shouldUseExplicitPrice = true;

		// In this case, charge currency is JPY, but store currency is NOK.
		const charge = getBaseCharge();
		charge.currency = 'jpy';
		charge.amount = 10000;
		charge.balance_transaction = {
			amount: 72581,
			currency: 'nok',
			reporting_category: 'charge',
			fee: 4152,
		};
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'warning_needs_response';
		charge.dispute.amount = 10000;
		charge.dispute.currency = 'jpy';
		// Inquiries don't have balance transactions.
		charge.dispute.balance_transactions = [];
		renderCharge( charge );

		// Disputed amount should show the shopper/transaction currency.
		expect(
			screen.getByText( /Dispute Amount/i ).nextSibling
		).toHaveTextContent( /¥10,000 JPY/i );
	} );

	test( 'correctly renders dispute details for a dispute with staged evidence', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'needs_response';
		charge.dispute.evidence_details = {
			has_evidence: true,
			due_by: 1694303999,
			past_due: false,
			submission_count: 0,
		};

		renderCharge( charge );

		screen.getByText(
			/The cardholder claims this is an unauthorized transaction/,
			{ ignore: '.a11y-speak-region' }
		);

		// Render the staged evidence message
		screen.getByText( /You initiated a challenge to this dispute/, {
			ignore: '.a11y-speak-region',
		} );

		screen.getByRole( 'button', {
			name: /Continue with challenge/,
		} );
	} );

	test( 'correctly renders the accept dispute modal and accepts', async () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'needs_response';

		renderCharge( charge );

		const openModalButton = screen.getByRole( 'button', {
			name: /Accept dispute/,
		} );

		// Open the modal
		await userEvent.click( openModalButton );

		screen.getByRole( 'heading', {
			name: /Accept the dispute?/,
		} );
		screen.getByText( /\$15.00 dispute fee/, {
			ignore: '.a11y-speak-region',
		} );

		screen.getByRole( 'button', {
			name: /Cancel/,
		} );
		const acceptButton = screen.getByRole( 'button', {
			name: /Accept dispute/,
		} );

		// Accept the dispute
		await userEvent.click( acceptButton );

		expect( mockDisputeDoAccept ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'navigates to the dispute challenge screen when the challenge button is clicked', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'needs_response';
		charge.dispute.id = 'dp_test123';

		renderCharge( charge );

		const challengeButton = screen.getByRole( 'button', {
			name: /Challenge dispute/,
		} );

		const challengeLink = challengeButton.closest( 'a' );
		expect( challengeLink ).toHaveAttribute(
			'href',
			`admin.php?page=wc-admin&path=%2Fpayments%2Fdisputes%2Fchallenge&id=${ charge.dispute.id }`
		);
	} );

	test( 'correctly renders dispute details for "won" disputes', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'won';
		charge.dispute.metadata.__evidence_submitted_at = '1693400000';
		renderCharge( charge );

		screen.getByText( /Good news — you've won this dispute/i, {
			ignore: '.a11y-speak-region',
		} );
		screen.getByRole( 'button', { name: /View dispute details/i } );

		// No actions or steps rendered
		expect( screen.queryByText( /Steps to resolve/i ) ).toBeNull();
		expect(
			screen.queryByRole( 'button', {
				name: /Challenge/i,
			} )
		).toBeNull();
		expect(
			screen.queryByRole( 'button', {
				name: /Accept/i,
			} )
		).toBeNull();

		// Refund menu is rendered
		screen.getByRole( 'button', {
			name: /Transaction actions/i,
		} );
	} );

	test( 'correctly renders dispute details for "under_review" disputes', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'under_review';
		charge.dispute.metadata.__evidence_submitted_at = '1693400000';

		renderCharge( charge );

		screen.getByText(
			/The customer's bank is currently reviewing the evidence you submitted/i,
			{
				ignore: '.a11y-speak-region',
			}
		);
		screen.getByRole( 'button', { name: /View submitted evidence/i } );

		// No actions or steps rendered
		expect( screen.queryByText( /Steps to resolve/i ) ).toBeNull();
		expect(
			screen.queryByRole( 'button', {
				name: /Challenge/i,
			} )
		).toBeNull();
		expect(
			screen.queryByRole( 'button', {
				name: /Accept/i,
			} )
		).toBeNull();

		// Refund menu is not rendered
		expect(
			screen.queryByRole( 'button', {
				name: /Transaction actions/i,
			} )
		).toBeNull();
	} );

	test( 'correctly renders dispute details for "accepted" disputes', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'lost';
		charge.dispute.metadata.__closed_by_merchant = '1';
		charge.dispute.metadata.__dispute_closed_at = '1693453017';

		renderCharge( charge );

		screen.getByText( /You accepted this dispute/i, {
			ignore: '.a11y-speak-region',
		} );
		// Check for the correct fee amount
		screen.getByText( /\$15.00 fee/i, {
			ignore: '.a11y-speak-region',
		} );

		// No actions or steps rendered
		expect( screen.queryByText( /Steps to resolve/i ) ).toBeNull();
		expect(
			screen.queryByRole( 'button', {
				name: /Challenge/i,
			} )
		).toBeNull();
		expect(
			screen.queryByRole( 'button', {
				name: /Accept/i,
			} )
		).toBeNull();

		// Refund menu is not rendered
		expect(
			screen.queryByRole( 'button', {
				name: /Transaction actions/i,
			} )
		).toBeNull();
	} );

	test( 'correctly renders dispute details for "lost" disputes', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'lost';
		charge.dispute.metadata.__evidence_submitted_at = '1693400000';
		charge.dispute.metadata.__dispute_closed_at = '1693453017';

		renderCharge( charge );

		screen.getByText( /Unfortunately, you've lost this dispute/i, {
			ignore: '.a11y-speak-region',
		} );
		// Check for the correct fee amount
		screen.getByText( /\$15.00 fee/i, {
			ignore: '.a11y-speak-region',
		} );
		screen.getByRole( 'button', { name: /View dispute details/i } );

		// No actions or steps rendered
		expect( screen.queryByText( /Steps to resolve/i ) ).toBeNull();
		expect(
			screen.queryByRole( 'button', {
				name: /Challenge/i,
			} )
		).toBeNull();
		expect(
			screen.queryByRole( 'button', {
				name: /Accept/i,
			} )
		).toBeNull();

		// Refund menu is not rendered
		expect(
			screen.queryByRole( 'button', {
				name: /Transaction actions/i,
			} )
		).toBeNull();
	} );

	test( 'correctly renders dispute details for "warning_needs_response" inquiry disputes', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'warning_needs_response';

		renderCharge( charge );

		// Dispute Notice
		screen.getByText(
			/The cardholder claims this is an unauthorized transaction/,
			{ ignore: '.a11y-speak-region' }
		);

		// Expand the steps accordion
		expandAccordion( 'Steps you can take' );

		// Steps to resolve
		screen.getByText( /Steps you can take/i, {
			selector: '.wcpay-accordion__title-content',
		} );
		screen.getByRole( 'link', {
			name: /Email customer/i,
		} );
		screen.getByText( /Submit evidence or issue a refund/i );

		// Actions
		screen.getByRole( 'button', {
			name: /Submit evidence$/i,
		} );
		screen.getByRole( 'button', {
			name: /Issue refund/i,
		} );

		// Refund menu is rendered
		screen.getByRole( 'button', {
			name: /Transaction actions/i,
		} );
	} );

	test( 'correctly renders dispute details for "warning_under_review" inquiry disputes', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'warning_under_review';
		charge.dispute.metadata.__evidence_submitted_at = '1693400000';

		renderCharge( charge );

		screen.getByText( /You submitted evidence for this inquiry/i, {
			ignore: '.a11y-speak-region',
		} );
		screen.getByRole( 'button', { name: /View submitted evidence/i } );

		// No actions rendered
		expect(
			screen.queryByRole( 'button', {
				name: /Challenge/i,
			} )
		).toBeNull();
		expect(
			screen.queryByRole( 'button', {
				name: /Accept/i,
			} )
		).toBeNull();

		// Refund menu is rendered
		screen.getByRole( 'button', {
			name: /Transaction actions/i,
		} );
	} );

	test( 'correctly renders dispute details for "warning_closed" inquiry disputes', () => {
		const charge = getBaseCharge();
		charge.disputed = true;
		charge.dispute = getBaseDispute();
		charge.dispute.status = 'warning_closed';
		charge.dispute.metadata.__evidence_submitted_at = '1693400000';
		charge.dispute.metadata.__dispute_closed_at = '1693453017';

		renderCharge( charge );

		screen.getByText( /This inquiry was closed/i, {
			ignore: '.a11y-speak-region',
		} );
		screen.getByRole( 'button', { name: /View submitted evidence/i } );

		// No actions rendered
		expect(
			screen.queryByRole( 'button', {
				name: /Challenge/i,
			} )
		).toBeNull();
		expect(
			screen.queryByRole( 'button', {
				name: /Accept/i,
			} )
		).toBeNull();

		// Refund menu is rendered
		screen.getByRole( 'button', {
			name: /Transaction actions/i,
		} );
	} );

	describe( 'order missing notice', () => {
		test( 'renders notice if order missing', () => {
			const charge = getBaseCharge();
			charge.order = null;

			const container = renderCharge( charge );

			expect(
				screen.getByRole( 'button', { name: /Refund/i } )
			).toBeInTheDocument();

			expect(
				screen.getByText(
					/This transaction is not connected to order. Investigate this purchase and refund the transaction as needed./
				)
			).toBeInTheDocument();

			expect( container ).toMatchSnapshot();
		} );

		test( 'does not render notice if order present', () => {
			const charge = getBaseCharge();

			const container = renderCharge( charge );

			expect(
				screen.queryByRole( 'button', { name: /Refund/i } )
			).not.toBeInTheDocument();

			expect(
				screen.queryByText(
					/This transaction is not connected to order. Investigate this purchase and refund the transaction as needed./
				)
			).not.toBeInTheDocument();

			expect( container ).toMatchSnapshot();
		} );
	} );

	describe( 'Refund actions menu', () => {
		test( 'Refund control menu is visible when conditions are met', async () => {
			await act( async () => {
				renderCharge( getBaseCharge() );
			} );
			expect(
				screen.getByLabelText( 'Transaction actions' )
			).toBeInTheDocument();
		} );

		test( 'Refund in full option is available when no amount has been refunded', async () => {
			await act( async () => {
				renderCharge( getBaseCharge() );
			} );
			await act( async () => {
				await userEvent.click(
					screen.getByLabelText( 'Transaction actions' )
				);
			} );
			expect( screen.getByText( 'Refund in full' ) ).toBeInTheDocument();
		} );

		test( 'Refund in full option is not available when an amount has been refunded', async () => {
			await act( async () => {
				renderCharge( { ...getBaseCharge(), amount_refunded: 42 } );
			} );
			await act( async () => {
				await userEvent.click(
					screen.getByLabelText( 'Transaction actions' )
				);
			} );
			expect(
				screen.queryByText( 'Refund in full' )
			).not.toBeInTheDocument();
		} );

		test( 'Partial refund option is available when charge is associated with an order', async () => {
			await act( async () => {
				renderCharge( getBaseCharge() );
			} );
			await act( async () => {
				await userEvent.click(
					screen.getByLabelText( 'Transaction actions' )
				);
			} );
			expect( screen.getByText( 'Partial refund' ) ).toBeInTheDocument();
		} );

		test( 'Refund control menu is not visible when charge is not captured', async () => {
			await act( async () => {
				renderCharge( { ...getBaseCharge(), captured: false } );
			} );
			expect(
				screen.queryByLabelText( 'Transaction actions' )
			).not.toBeInTheDocument();
		} );
	} );

	describe( 'Dispute outcome view feature flag', () => {
		const getResolvedCharge = ( status ) => {
			const charge = getBaseCharge();
			charge.disputed = true;
			charge.dispute = getBaseDispute();
			charge.dispute.status = status;
			charge.dispute.metadata = {
				__dispute_closed_at: '1693626817',
				// Set a real product type so `resolveProductType()` lands
				// on a real matrix cell. Drives the matrix-derived rows
				// (e.g., "Customer communication"), not just the universal
				// cover letter row, so the tests exercise the data path.
				__product_type: 'physical_product',
			};
			// Top up evidence with rows the matrix expects for
			// fraudulent × physical_product so we get at least one
			// matrix-driven "provided" row alongside the cover letter.
			charge.dispute.evidence = {
				...charge.dispute.evidence,
				shipping_date: '2026-01-01',
				customer_communication: 'Email thread with the customer',
			};
			return charge;
		};

		test( 'renders DisputeResolutionFooter for a resolved dispute when the flag is off', () => {
			renderCharge( getResolvedCharge( 'won' ) );

			expect(
				screen.getByText( /Good news/i, {
					ignore: '.a11y-speak-region',
				} )
			).toBeInTheDocument();
		} );

		// The Outcome View no longer replaces the resolution banner with an
		// Evidence Submitted list; design folded that section away (2026-05-26
		// review), so the banner renders for won/lost as it does with the flag
		// off, alongside the separate recommendations card.
		test( 'renders the resolution banner and no Evidence Submitted section for a won dispute when the flag is on', () => {
			global.wcpaySettings.featureFlags.isDisputeOutcomeViewEnabled = true;

			renderCharge( getResolvedCharge( 'won' ) );

			expect(
				screen.getByText( /Good news/i, {
					ignore: '.a11y-speak-region',
				} )
			).toBeInTheDocument();
			expect(
				screen.queryByRole( 'heading', { name: 'Evidence Submitted' } )
			).not.toBeInTheDocument();
		} );

		test( 'renders the resolution banner and no Evidence Submitted section for a lost dispute when the flag is on', () => {
			global.wcpaySettings.featureFlags.isDisputeOutcomeViewEnabled = true;

			renderCharge( getResolvedCharge( 'lost' ) );

			// The fixture submits no evidence, so the footer renders the
			// non-response copy; the point is that the banner is present.
			expect(
				screen.getByText( /This dispute was lost/i, {
					ignore: '.a11y-speak-region',
				} )
			).toBeInTheDocument();
			expect(
				screen.queryByRole( 'heading', { name: 'Evidence Submitted' } )
			).not.toBeInTheDocument();
		} );

		test( 'still renders DisputeResolutionFooter for an under_review dispute when the flag is on', () => {
			global.wcpaySettings.featureFlags.isDisputeOutcomeViewEnabled = true;

			renderCharge( getResolvedCharge( 'under_review' ) );

			expect(
				screen.getByText(
					/is currently reviewing the evidence you submitted/i,
					{ ignore: '.a11y-speak-region' }
				)
			).toBeInTheDocument();
		} );

		test( 'still renders DisputeAwaitingResponseDetails for an unresolved dispute when the flag is on', () => {
			global.wcpaySettings.featureFlags.isDisputeOutcomeViewEnabled = true;

			const charge = getBaseCharge();
			charge.disputed = true;
			charge.dispute = getBaseDispute();
			charge.dispute.status = 'needs_response';

			renderCharge( charge );

			expect(
				screen.getByText(
					/The cardholder claims this is an unauthorized transaction/,
					{ ignore: '.a11y-speak-region' }
				)
			).toBeInTheDocument();
		} );

		test( 'renders the recommendations card for a lost dispute with a matching reason × product type', () => {
			global.wcpaySettings.featureFlags.isDisputeOutcomeViewEnabled = true;

			const charge = getResolvedCharge( 'lost' );
			charge.dispute.reason = 'product_not_received';
			charge.dispute.metadata.__product_type = 'physical_product';
			charge.dispute.evidence = {}; // tracking missing → critical recommendation fires

			renderCharge( charge );

			expect(
				screen.getByRole( 'heading', {
					name: /what could help next time/i,
				} )
			).toBeInTheDocument();
		} );

		test( 'renders the recommendations card for a won dispute with a matching reason × product type', () => {
			global.wcpaySettings.featureFlags.isDisputeOutcomeViewEnabled = true;

			const charge = getResolvedCharge( 'won' );
			charge.dispute.reason = 'product_not_received';
			charge.dispute.metadata.__product_type = 'physical_product';
			charge.dispute.evidence = {
				shipping_tracking_number: '1Z999',
				shipping_carrier: 'UPS',
			};

			renderCharge( charge );

			expect(
				screen.getByRole( 'heading', { name: /what's working well/i } )
			).toBeInTheDocument();
		} );

		test( 'does not render the recommendations card for a warning_closed dispute', () => {
			global.wcpaySettings.featureFlags.isDisputeOutcomeViewEnabled = true;

			const charge = getResolvedCharge( 'warning_closed' );
			charge.dispute.reason = 'product_not_received';
			charge.dispute.metadata.__product_type = 'physical_product';

			renderCharge( charge );

			expect(
				screen.queryByRole( 'heading', {
					name: /what could help next time/i,
				} )
			).not.toBeInTheDocument();
			expect(
				screen.queryByRole( 'heading', {
					name: /what's working well/i,
				} )
			).not.toBeInTheDocument();
		} );

		test( 'does not render the recommendations card when the flag is off', () => {
			// Flag intentionally off; getResolvedCharge does not toggle it.
			const charge = getResolvedCharge( 'lost' );
			charge.dispute.reason = 'product_not_received';
			charge.dispute.metadata.__product_type = 'physical_product';

			renderCharge( charge );

			expect(
				screen.queryByRole( 'heading', {
					name: /what could help next time/i,
				} )
			).not.toBeInTheDocument();
		} );

		test( 'does not render the recommendations card on an accepted lost dispute', () => {
			// Accept-path: __closed_by_merchant === '1' means the merchant
			// chose not to challenge. Coaching them to "submit evidence next
			// time" misreads the choice, so the card suppresses. Per RiskOps
			// review.
			global.wcpaySettings.featureFlags.isDisputeOutcomeViewEnabled = true;

			const charge = getResolvedCharge( 'lost' );
			charge.dispute.reason = 'fraudulent';
			charge.dispute.metadata.__product_type = 'physical_product';
			charge.dispute.metadata.__closed_by_merchant = '1';

			renderCharge( charge );

			expect(
				screen.queryByRole( 'heading', {
					name: /what could help next time/i,
				} )
			).not.toBeInTheDocument();
			expect(
				screen.queryByRole( 'heading', {
					name: /what's working well/i,
				} )
			).not.toBeInTheDocument();
		} );

		// Wrapper-lifecycle coverage for the Tracks dedup. The function-level
		// guard is unit-tested in `dispute-outcome/__tests__/tracks.test.ts`;
		// these tests cover the remount path the dedup actually defends.
		describe( 'Tracks dedup across the wrapper lifecycle', () => {
			// The wrapper also renders the recommendations card, which fires its
			// own section-viewed events, so count only the viewed-event firings.
			const outcomeViewedCalls = () =>
				recordEvent.mock.calls.filter(
					( [ name ] ) => name === 'wcpay_dispute_outcome_viewed'
				);

			beforeEach( () => {
				recordEvent.mockClear();
				_resetOutcomeViewTrackingForTests();
				global.wcpaySettings.featureFlags.isDisputeOutcomeViewEnabled = true;
			} );

			test( 'rerendering with a fresh charge object but the same dispute id fires the event once', () => {
				const first = getResolvedCharge( 'won' );
				const { rerender } = render(
					<PaymentDetailsSummary charge={ first } />
				);

				expect( outcomeViewedCalls() ).toHaveLength( 1 );

				// Fresh object, same id: useEffect deps change; the Set catches it.
				const second = getResolvedCharge( 'won' );
				rerender( <PaymentDetailsSummary charge={ second } /> );

				expect( outcomeViewedCalls() ).toHaveLength( 1 );
			} );

			test( 'unmounting and remounting the wrapper with the same dispute id fires the event once', () => {
				const charge = getResolvedCharge( 'won' );
				const { unmount } = render(
					<PaymentDetailsSummary charge={ charge } />
				);

				expect( outcomeViewedCalls() ).toHaveLength( 1 );

				// Production regression: per-instance refs reset on unmount;
				// the module-scoped Set must survive it.
				unmount();
				render( <PaymentDetailsSummary charge={ charge } /> );

				expect( outcomeViewedCalls() ).toHaveLength( 1 );
			} );

			test( 'a different dispute id after the first fires its own event', () => {
				const first = getResolvedCharge( 'won' );
				const { unmount } = render(
					<PaymentDetailsSummary charge={ first } />
				);
				expect( outcomeViewedCalls() ).toHaveLength( 1 );

				// Dedup keyed by dispute id, not "have we ever fired".
				unmount();
				const second = getResolvedCharge( 'lost' );
				second.dispute.id = 'dp_2';
				render( <PaymentDetailsSummary charge={ second } /> );

				const viewed = outcomeViewedCalls();
				expect( viewed ).toHaveLength( 2 );
				expect( viewed[ 1 ][ 1 ] ).toEqual(
					expect.objectContaining( { dispute_id: 'dp_2' } )
				);
			} );
		} );

		// has_recommendations must mirror what the card actually renders, so it
		// is gated by the same conditions as the card (won/lost, matching
		// catalog entry, not merchant-accepted), not merely by the catalog match.
		describe( 'has_recommendations property', () => {
			beforeEach( () => {
				recordEvent.mockClear();
				_resetOutcomeViewTrackingForTests();
				global.wcpaySettings.featureFlags.isDisputeOutcomeViewEnabled = true;
			} );

			test( 'fires has_recommendations: true when the card has entries', () => {
				const charge = getResolvedCharge( 'lost' );
				charge.dispute.reason = 'product_not_received';
				charge.dispute.metadata.__product_type = 'physical_product';
				charge.dispute.evidence = {}; // tracking missing → critical fires

				render( <PaymentDetailsSummary charge={ charge } /> );

				expect( recordEvent ).toHaveBeenCalledWith(
					'wcpay_dispute_outcome_viewed',
					expect.objectContaining( { has_recommendations: true } )
				);
			} );

			test( 'fires has_recommendations: false when no catalog entry matches', () => {
				const charge = getResolvedCharge( 'won' );
				charge.dispute.reason = 'bank_cannot_process';
				charge.dispute.metadata.__product_type = 'physical_product';

				render( <PaymentDetailsSummary charge={ charge } /> );

				expect( recordEvent ).toHaveBeenCalledWith(
					'wcpay_dispute_outcome_viewed',
					expect.objectContaining( { has_recommendations: false } )
				);
			} );

			test( 'fires has_recommendations: false on an accepted dispute even when entries would match', () => {
				// The card suppresses on __closed_by_merchant, so the flag must
				// too: the merchant sees no card, so has_recommendations is false.
				const charge = getResolvedCharge( 'lost' );
				charge.dispute.reason = 'product_not_received';
				charge.dispute.metadata.__product_type = 'physical_product';
				charge.dispute.evidence = {};
				charge.dispute.metadata.__closed_by_merchant = '1';

				render( <PaymentDetailsSummary charge={ charge } /> );

				expect( recordEvent ).toHaveBeenCalledWith(
					'wcpay_dispute_outcome_viewed',
					expect.objectContaining( { has_recommendations: false } )
				);
			} );

			test( 'fires has_recommendations: false for a warning_closed inquiry', () => {
				const charge = getResolvedCharge( 'warning_closed' );
				charge.dispute.reason = 'product_not_received';
				charge.dispute.metadata.__product_type = 'physical_product';

				render( <PaymentDetailsSummary charge={ charge } /> );

				expect( recordEvent ).toHaveBeenCalledWith(
					'wcpay_dispute_outcome_viewed',
					expect.objectContaining( { has_recommendations: false } )
				);
			} );
		} );
	} );
} );
