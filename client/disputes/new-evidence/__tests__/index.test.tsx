/**
 * External dependencies
 */
import React from 'react';
import {
	render,
	screen,
	fireEvent,
	waitFor,
	cleanup,
} from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import NewEvidence from '../index';
import { isVisaComplianceDispute } from 'wcpay/disputes/utils';
import type { DisputeReason } from 'wcpay/types/disputes';
import {
	useGetSettings,
	useDisputeEvidence,
	WCPAY_STORE_NAME,
} from 'wcpay/data';

// Mock the API fetch calls
jest.mock( '@wordpress/api-fetch', () => jest.fn() );

// Mock the data hooks
jest.mock( 'wcpay/data', () => ( {
	useGetSettings: jest.fn(),
	useDisputeEvidence: jest.fn(),
} ) );

// Mock the dispatch hooks
jest.mock( '@wordpress/data', () => ( {
	createRegistryControl: jest.fn(),
	dispatch: jest.fn( () => ( {
		setIsMatching: jest.fn(),
		onLoad: jest.fn(),
		onHistoryChange: jest.fn(),
	} ) ),
	registerStore: jest.fn(),
	select: jest.fn(),
	useDispatch: jest.fn(),
	withDispatch: jest.fn( () => jest.fn() ),
	withSelect: jest.fn( () => jest.fn() ),
} ) );

const mockApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;
const mockUseGetSettings = useGetSettings as jest.MockedFunction<
	typeof useGetSettings
>;
const mockUseDisputeEvidence = useDisputeEvidence as jest.MockedFunction<
	typeof useDisputeEvidence
>;
const mockUseDispatch = useDispatch as jest.MockedFunction<
	typeof useDispatch
>;

// Mock wcpaySettings global
declare const global: {
	wcpaySettings: {
		zeroDecimalCurrencies: string[];
		connect: {
			country: string;
		};
		currencyData: {
			[ key: string ]: {
				code: string;
				symbol: string;
				symbolPosition: string;
				thousandSeparator: string;
				decimalSeparator: string;
				precision: number;
			};
		};
		featureFlags: {
			isDisputeAdditionalEvidenceTypesEnabled: boolean;
		};
	};
	confirm: jest.MockedFunction< typeof confirm >;
};

global.wcpaySettings = {
	zeroDecimalCurrencies: [],
	connect: {
		country: 'US',
	},
	currencyData: {
		USD: {
			code: 'USD',
			symbol: '$',
			symbolPosition: 'left',
			thousandSeparator: ',',
			decimalSeparator: '.',
			precision: 2,
		},
	},
	featureFlags: {
		isDisputeAdditionalEvidenceTypesEnabled: true,
	},
};

// Mock window.location.href assignments
delete ( window as any ).location;
( window as any ).location = { href: '' };

// Mock window.scrollTo
Object.defineProperty( window, 'scrollTo', {
	value: jest.fn(),
} );

// Mock window.confirm
global.confirm = jest.fn() as jest.MockedFunction< typeof confirm >;

describe( 'NewEvidence - Visa Compliance Flow', () => {
	const baseDispute = {
		id: 'dp_test_123',
		amount: 1000,
		currency: 'usd',
		created: 1609459200,
		reason: 'noncompliant',
		status: 'needs_response',
		evidence_details: {
			due_by: 1610064000,
		},
		evidence: {},
		metadata: {},
		order: {
			id: 123,
			number: '123',
			ip_address: '192.168.1.1',
		},
		charge: {
			id: 'ch_test_123',
			payment_method_details: {
				type: 'card',
				card: {
					issuer: 'Test Bank',
				},
			},
		},
		enhanced_eligibility_types: [],
		balance_transactions: [],
		payment_intent: 'pi_test_123',
	};

	const mockSettings = {
		account: {
			business_details: {
				name: 'Test Business',
				address: {
					line1: '123 Test St',
					city: 'Test City',
					state: 'TS',
					postal_code: '12345',
					country: 'US',
				},
			},
		},
	};

	beforeEach( () => {
		jest.clearAllMocks();
		mockApiFetch.mockResolvedValue( baseDispute );
		mockUseGetSettings.mockReturnValue( mockSettings );
		mockUseDisputeEvidence.mockReturnValue( { updateDispute: jest.fn() } );
		( mockUseDispatch as jest.Mock ).mockReturnValue( {
			createSuccessNotice: jest.fn(),
			createErrorNotice: jest.fn(),
			createInfoNotice: jest.fn(),
		} );
		( window as any ).location.href = '';
		( global.confirm as jest.Mock ).mockReturnValue( true );
	} );

	describe( 'Visa Compliance dispute detection', () => {
		it( 'should detect Visa Compliance dispute by noncompliant reason', () => {
			const dispute = {
				reason: 'noncompliant' as DisputeReason,
				enhanced_eligibility_types: [],
			};
			expect( isVisaComplianceDispute( dispute ) ).toBe( true );
		} );

		it( 'should detect Visa Compliance dispute by enhanced_eligibility_types', () => {
			const dispute = {
				reason: 'fraudulent' as DisputeReason,
				enhanced_eligibility_types: [ 'visa_compliance' ],
			};
			expect( isVisaComplianceDispute( dispute ) ).toBe( true );
		} );

		it( 'should not detect Visa Compliance for regular disputes', () => {
			const dispute = {
				reason: 'fraudulent' as DisputeReason,
				enhanced_eligibility_types: [],
			};
			expect( isVisaComplianceDispute( dispute ) ).toBe( false );
		} );
	} );

	describe( 'Visa Compliance UI rendering', () => {
		it( 'should render Visa Compliance specific title and description', async () => {
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor(
				() => {
					expect(
						screen.getByText( 'Tell us about the dispute' )
					).toBeInTheDocument();
				},
				{ timeout: 3000 }
			);

			await waitFor(
				() => {
					expect(
						screen.getByText( /This is a compliance case/i )
					).toBeInTheDocument();
				},
				{ timeout: 3000 }
			);
		} );

		it( 'should render Visa Compliance specific textarea', async () => {
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				expect(
					screen.getByLabelText(
						'Why do you disagree with this dispute?'
					)
				).toBeInTheDocument();
				expect(
					screen.getByText(
						'Please enter any relevant details here.'
					)
				).toBeInTheDocument();
			} );
		} );

		it( 'should render Visa Compliance specific inline notice', async () => {
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				expect(
					screen.getByText(
						'The outcome of this dispute will be determined by Visa.'
					)
				).toBeInTheDocument();
			} );
		} );

		it( 'should not render the accordion for Visa Compliance disputes', async () => {
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				expect(
					screen.queryByText( 'Challenge dispute' )
				).not.toBeInTheDocument();
			} );
		} );

		it( 'should not render the stepper for Visa Compliance disputes', async () => {
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				expect(
					screen.queryByText( "Let's gather the basics" )
				).not.toBeInTheDocument();
				expect(
					screen.queryByText( 'Add your shipping details' )
				).not.toBeInTheDocument();
				expect(
					screen.queryByText( 'Review your cover letter' )
				).not.toBeInTheDocument();
			} );
		} );

		it( 'should render Visa Compliance specific buttons', async () => {
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				expect(
					screen.getByRole( 'button', { name: 'Cancel' } )
				).toBeInTheDocument();
				expect(
					screen.getByRole( 'button', { name: 'Save for later' } )
				).toBeInTheDocument();
				expect(
					screen.getByRole( 'button', { name: 'Submit' } )
				).toBeInTheDocument();
				expect(
					screen.queryByRole( 'button', { name: 'Next' } )
				).not.toBeInTheDocument();
				expect(
					screen.queryByRole( 'button', { name: 'Back' } )
				).not.toBeInTheDocument();
			} );
		} );
	} );

	describe( 'Visa Compliance form behavior', () => {
		it( 'should not allow editing in read-only mode', async () => {
			const readOnlyDispute = { ...baseDispute, status: 'under_review' };
			mockApiFetch.mockResolvedValue( readOnlyDispute );

			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				const textarea = screen.getByLabelText(
					'Why do you disagree with this dispute?'
				);
				expect( textarea ).toBeDisabled();
			} );
		} );

		it( 'should show confirmation dialog when submitting', async () => {
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				const submitButton = screen.getByRole( 'button', {
					name: 'Submit',
				} );
				fireEvent.click( submitButton );
				expect( global.confirm ).toHaveBeenCalledWith(
					"Are you sure you're ready to submit this evidence? Evidence submissions are final."
				);
			} );
		} );

		it( 'should not submit if confirmation is cancelled', async () => {
			( global.confirm as jest.Mock ).mockReturnValue( false );
			mockApiFetch.mockResolvedValue( {
				...baseDispute,
				status: 'needs_response',
			} );

			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				const submitButton = screen.getByRole( 'button', {
					name: 'Submit',
				} );
				fireEvent.click( submitButton );
				expect( mockApiFetch ).not.toHaveBeenCalledWith(
					expect.objectContaining( {
						data: expect.objectContaining( { submit: true } ),
					} )
				);
			} );
		} );
	} );

	describe( 'Visa Compliance document handling', () => {
		it( 'should show only Visa Compliance recommended documents', async () => {
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				expect(
					screen.getByText( 'Upload evidence' )
				).toBeInTheDocument();
				expect(
					screen.getByText(
						'Submit any files you find relevant to this dispute.'
					)
				).toBeInTheDocument();
				expect(
					screen.getByText( 'Other documents' )
				).toBeInTheDocument();
				expect(
					screen.getByText(
						'Any other relevant documents that will support your case.'
					)
				).toBeInTheDocument();
			} );
		} );

		it( 'should not show regular dispute documents for Visa Compliance', async () => {
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				expect(
					screen.queryByText( 'Order receipt' )
				).not.toBeInTheDocument();
				expect(
					screen.queryByText( 'Customer communication' )
				).not.toBeInTheDocument();
				expect(
					screen.queryByText( 'Store refund policy' )
				).not.toBeInTheDocument();
			} );
		} );
	} );

	describe( 'Visa Compliance save functionality', () => {
		it( 'should save evidence when Save for later is clicked', async () => {
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				const saveButton = screen.getByRole( 'button', {
					name: 'Save for later',
				} );
				fireEvent.click( saveButton );

				expect( mockApiFetch ).toHaveBeenCalledWith(
					expect.objectContaining( {
						path: '/wc/v3/payments/disputes/dp_test_123',
						method: 'post',
						data: expect.objectContaining( {
							submit: false,
						} ),
					} )
				);
			} );
		} );

		it( 'should submit evidence when Submit is clicked and confirmed', async () => {
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				const submitButton = screen.getByRole( 'button', {
					name: 'Submit',
				} );
				fireEvent.click( submitButton );

				expect( global.confirm ).toHaveBeenCalled();
				expect( mockApiFetch ).toHaveBeenCalledWith(
					expect.objectContaining( {
						path: '/wc/v3/payments/disputes/dp_test_123',
						method: 'post',
						data: expect.objectContaining( {
							submit: true,
						} ),
					} )
				);
			} );
		} );
	} );

	describe( 'Visa Compliance vs Regular dispute comparison', () => {
		it( 'should render different UI for Visa Compliance vs regular disputes', async () => {
			// Test Visa Compliance dispute
			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor(
				() => {
					expect(
						screen.getByText( 'Tell us about the dispute' )
					).toBeInTheDocument();
				},
				{ timeout: 3000 }
			);

			await waitFor(
				() => {
					expect(
						screen.getByText( /This is a compliance case/i )
					).toBeInTheDocument();
				},
				{ timeout: 3000 }
			);

			// Clean up the first render
			cleanup();

			// Test regular dispute
			const regularDispute = {
				...baseDispute,
				reason: 'fraudulent' as DisputeReason,
			};
			mockApiFetch.mockResolvedValue( regularDispute );
			render( <NewEvidence query={ { id: 'dp_test_456' } } /> );

			await waitFor(
				() => {
					expect(
						screen.getByText( "Let's gather the basics" )
					).toBeInTheDocument();
					expect(
						screen.getByText( 'Challenge dispute' )
					).toBeInTheDocument();
					expect(
						screen.queryByText( /This is a compliance case/i )
					).not.toBeInTheDocument();
				},
				{ timeout: 3000 }
			);

			// The WooCommerce List component shows a deprecation warning
			expect( console ).toHaveWarned();
		} );

		it( 'should handle enhanced_eligibility_types Visa Compliance disputes', async () => {
			const enhancedDispute = {
				...baseDispute,
				reason: 'fraudulent' as DisputeReason,
				enhanced_eligibility_types: [ 'visa_compliance' ],
			};
			mockApiFetch.mockResolvedValue( enhancedDispute );

			render( <NewEvidence query={ { id: 'dp_test_789' } } /> );

			await waitFor(
				() => {
					expect(
						screen.getByText( 'Tell us about the dispute' )
					).toBeInTheDocument();
				},
				{ timeout: 3000 }
			);

			await waitFor(
				() => {
					expect(
						screen.getByText( /This is a compliance case/i )
					).toBeInTheDocument();
				},
				{ timeout: 3000 }
			);
		} );
	} );

	describe( 'Error handling', () => {
		it( 'should show error notice when API fetch fails', async () => {
			const mockCreateErrorNotice = jest.fn();
			( mockUseDispatch as jest.Mock ).mockReturnValue( {
				createSuccessNotice: jest.fn(),
				createErrorNotice: mockCreateErrorNotice,
				createInfoNotice: jest.fn(),
			} );

			const error = new Error( 'API error' );
			mockApiFetch.mockRejectedValue( error );

			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
					'Error: API error'
				);
			} );
		} );

		it( 'should show error notice when save fails', async () => {
			const mockCreateErrorNotice = jest.fn();
			( mockUseDispatch as jest.Mock ).mockReturnValue( {
				createSuccessNotice: jest.fn(),
				createErrorNotice: mockCreateErrorNotice,
				createInfoNotice: jest.fn(),
			} );

			const error = new Error( 'Save error' );
			mockApiFetch
				.mockResolvedValueOnce( baseDispute )
				.mockRejectedValueOnce( error );

			render( <NewEvidence query={ { id: 'dp_test_123' } } /> );

			await waitFor( () => {
				const saveButton = screen.getByRole( 'button', {
					name: 'Save for later',
				} );
				fireEvent.click( saveButton );

				expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
					expect.stringContaining( 'Save error' )
				);
			} );
		} );
	} );
} );

describe( 'NewEvidence - Regular Dispute Flow', () => {
	const regularDispute = {
		id: 'dp_test_456',
		amount: 1000,
		currency: 'usd',
		created: 1609459200,
		reason: 'fraudulent',
		status: 'needs_response',
		evidence_details: {
			due_by: 1610064000,
		},
		evidence: {},
		metadata: {},
		order: {
			id: 123,
			number: '123',
			ip_address: '192.168.1.1',
		},
		charge: {
			id: 'ch_test_123',
			payment_method_details: {
				type: 'card',
				card: {
					issuer: 'Test Bank',
				},
			},
		},
		enhanced_eligibility_types: [],
		balance_transactions: [],
		payment_intent: 'pi_test_456',
	};

	beforeEach( () => {
		jest.clearAllMocks();
		mockApiFetch.mockResolvedValue( regularDispute );
		( window as any ).location.href = '';
	} );

	it( 'should render regular dispute UI with stepper', async () => {
		render( <NewEvidence query={ { id: 'dp_test_456' } } /> );

		await waitFor( () => {
			expect(
				screen.getByText( "Let's gather the basics" )
			).toBeInTheDocument();
			expect(
				screen.getByText( 'Challenge dispute' )
			).toBeInTheDocument();
			expect( screen.getByText( 'Purchase info' ) ).toBeInTheDocument();
			expect( screen.getByText( 'Review' ) ).toBeInTheDocument();
		} );
	} );

	it( 'should render regular dispute documents', async () => {
		render( <NewEvidence query={ { id: 'dp_test_456' } } /> );

		await waitFor( () => {
			expect( screen.getByText( 'Order receipt' ) ).toBeInTheDocument();
			expect(
				screen.getByText( 'Customer communication' )
			).toBeInTheDocument();
		} );
	} );

	it( 'should not render Visa Compliance specific elements', async () => {
		render( <NewEvidence query={ { id: 'dp_test_456' } } /> );

		await waitFor( () => {
			expect(
				screen.queryByText( 'Tell us about the dispute' )
			).not.toBeInTheDocument();
			expect(
				screen.queryByText( 'This is a compliance case' )
			).not.toBeInTheDocument();
		} );
	} );
} );

describe( 'NewEvidence - Payment Intent Cache Invalidation', () => {
	const mockInvalidateResolutionForStoreSelector = jest.fn();
	const mockCreateSuccessNotice = jest.fn();
	const mockCreateErrorNotice = jest.fn();
	const mockCreateInfoNotice = jest.fn();

	const createDisputeWithPaymentIntent = ( hasPaymentIntent: boolean ) => ( {
		id: 'dp_test_cache',
		amount: 1000,
		currency: 'usd',
		created: 1609459200,
		reason: 'fraudulent',
		status: 'needs_response',
		evidence_details: {
			due_by: 1610064000,
		},
		evidence: {},
		metadata: {
			__product_type: 'digital_product_or_service',
		},
		order: {
			id: 123,
			number: '123',
			ip_address: '192.168.1.1',
			suggested_product_type: 'digital_product_or_service',
		},
		charge: {
			id: 'ch_test_123',
			payment_method_details: {
				type: 'card',
				card: {
					issuer: 'Test Bank',
				},
			},
			...( hasPaymentIntent && { payment_intent: 'pi_test_123' } ),
		},
		enhanced_eligibility_types: [],
		balance_transactions: [],
	} );

	beforeEach( () => {
		jest.clearAllMocks();
		mockUseGetSettings.mockReturnValue( {
			account: {
				business_details: {
					name: 'Test Business',
				},
			},
		} );
		mockUseDisputeEvidence.mockReturnValue( { updateDispute: jest.fn() } );
		( mockUseDispatch as jest.Mock ).mockImplementation(
			( storeName: string ) => {
				if ( storeName === WCPAY_STORE_NAME ) {
					return {
						invalidateResolutionForStoreSelector: mockInvalidateResolutionForStoreSelector,
					};
				}
				if ( storeName === 'core/notices' ) {
					return {
						createSuccessNotice: mockCreateSuccessNotice,
						createErrorNotice: mockCreateErrorNotice,
						createInfoNotice: mockCreateInfoNotice,
					};
				}
				return {};
			}
		);
		( window as any ).location.href = '';
		( global.confirm as jest.Mock ).mockReturnValue( true );
	} );

	it( 'invalidates payment intent cache when dispute is saved and has payment_intent', async () => {
		const disputeWithPaymentIntent = createDisputeWithPaymentIntent( true );
		mockApiFetch.mockResolvedValue( disputeWithPaymentIntent );

		render( <NewEvidence query={ { id: 'dp_test_cache' } } /> );

		// Wait for loading to complete
		await waitFor( () => {
			expect(
				screen.queryByText( 'Loading dispute…' )
			).not.toBeInTheDocument();
		} );

		// Find and click the "Save for later" button
		const saveButton = await screen.findByRole( 'button', {
			name: 'Save for later',
		} );
		fireEvent.click( saveButton );

		// Wait for the save to complete and verify cache invalidation was called
		await waitFor( () => {
			expect(
				mockInvalidateResolutionForStoreSelector
			).toHaveBeenCalledWith( 'getPaymentIntent' );
		} );
	} );

	it( 'does not invalidate payment intent cache when dispute has no payment_intent', async () => {
		const disputeWithoutPaymentIntent = createDisputeWithPaymentIntent(
			false
		);
		mockApiFetch.mockResolvedValue( disputeWithoutPaymentIntent );

		render( <NewEvidence query={ { id: 'dp_test_cache' } } /> );

		// Wait for loading to complete
		await waitFor( () => {
			expect(
				screen.queryByText( 'Loading dispute…' )
			).not.toBeInTheDocument();
		} );

		// Find and click the "Save for later" button
		const saveButton = await screen.findByRole( 'button', {
			name: 'Save for later',
		} );
		fireEvent.click( saveButton );

		// Wait for the POST request to be made (save operation)
		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					method: 'post',
				} )
			);
		} );

		// Verify cache invalidation was NOT called
		expect(
			mockInvalidateResolutionForStoreSelector
		).not.toHaveBeenCalled();
	} );

	it( 'invalidates payment intent cache when evidence is submitted', async () => {
		const disputeWithPaymentIntent = createDisputeWithPaymentIntent( true );
		mockApiFetch.mockResolvedValue( disputeWithPaymentIntent );

		render( <NewEvidence query={ { id: 'dp_test_cache' } } /> );

		// Wait for loading to complete
		await waitFor( () => {
			expect(
				screen.queryByText( 'Loading dispute…' )
			).not.toBeInTheDocument();
		} );

		// Navigate to the review step (for digital products - no shipping step)
		const nextButton = await screen.findByRole( 'button', {
			name: /Next/i,
		} );
		fireEvent.click( nextButton );

		// Wait for the review step to render
		await waitFor( () => {
			expect(
				screen.getByText( 'Review your cover letter' )
			).toBeInTheDocument();
		} );

		// Find and click submit button
		const submitButton = await screen.findByRole( 'button', {
			name: /Submit/i,
		} );
		fireEvent.click( submitButton );

		// Wait for submit to complete and verify cache invalidation was called
		await waitFor( () => {
			expect(
				mockInvalidateResolutionForStoreSelector
			).toHaveBeenCalledWith( 'getPaymentIntent' );
		} );
	} );
} );
