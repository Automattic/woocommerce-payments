/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import PaymentMethod from '../payment-method';
import DuplicatedPaymentMethodsContext from '../../settings-manager/duplicated-payment-methods-context';
import WCPaySettingsContext from '../../wcpay-settings-context';
import {
	useEnabledPaymentMethodIds,
	useGetPaymentMethodStatuses,
	useManualCapture,
	usePmPromotions,
} from 'wcpay/data';

jest.mock( 'wcpay/data', () => ( {
	useEnabledPaymentMethodIds: jest.fn(),
	useGetPaymentMethodStatuses: jest.fn(),
	useManualCapture: jest.fn(),
	usePmPromotions: jest.fn(),
} ) );

describe( 'PaymentMethod', () => {
	beforeEach( () => {
		useEnabledPaymentMethodIds.mockReturnValue( [
			[ 'card', 'bancontact' ],
		] );
		useGetPaymentMethodStatuses.mockReturnValue( {} );
		useManualCapture.mockReturnValue( [ false ] );
		usePmPromotions.mockReturnValue( {
			pmPromotions: [],
			isLoading: false,
		} );

		// Set up wcpaySettings with required properties for discount badge tests.
		global.wcpaySettings = {
			...global.wcpaySettings,
			zeroDecimalCurrencies: [ 'jpy', 'krw', 'vnd' ],
			currencyData: {
				US: {
					code: 'USD',
					symbol: '$',
					symbolPosition: 'left',
					thousandSeparator: ',',
					decimalSeparator: '.',
					precision: 2,
				},
			},
			connect: { country: 'US' },
		};
	} );

	// Clear the mocks (including the mock call count) after each test.
	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'renders label and description', () => {
		render(
			<PaymentMethod
				id="bancontact"
				description="Bar"
				label="Bancontact"
			/>
		);

		expect( screen.queryByLabelText( 'Bancontact' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'Bar' ) ).toBeInTheDocument();
	} );

	it( 'calls onCheckClick when clicking its checkbox', async () => {
		const handleCheckClickMock = jest.fn();
		const handleUnCheckClickMock = jest.fn();
		useEnabledPaymentMethodIds.mockReturnValue( [ [ 'card' ] ] );

		render(
			<PaymentMethod
				id="bancontact"
				description="Bar"
				label="Bancontact"
				onCheckClick={ handleCheckClickMock }
				onUncheckClick={ handleUnCheckClickMock }
			/>
		);

		await user.click( screen.getByLabelText( 'Bancontact' ) );

		await waitFor( () =>
			expect( handleCheckClickMock ).toHaveBeenCalledTimes( 1 )
		);
		expect( handleCheckClickMock ).toHaveBeenCalledWith( 'bancontact' );
		expect( handleUnCheckClickMock ).not.toHaveBeenCalled();
	} );

	test( 'calls onUnCheckClick when clicking its checkbox', async () => {
		const handleCheckClickMock = jest.fn();
		const handleUnCheckClickMock = jest.fn();

		render(
			<PaymentMethod
				id="bancontact"
				description="Bar"
				label="Bancontact"
				onCheckClick={ handleCheckClickMock }
				onUncheckClick={ handleUnCheckClickMock }
			/>
		);

		await user.click( screen.getByLabelText( 'Bancontact' ) );

		await waitFor( () =>
			expect( handleUnCheckClickMock ).toHaveBeenCalledTimes( 1 )
		);
		expect( handleUnCheckClickMock ).toHaveBeenCalledWith( 'bancontact' );
		expect( handleCheckClickMock ).not.toHaveBeenCalled();
	} );

	it( 'shows the required label on payment methods which are required', () => {
		render(
			<PaymentMethod
				id="card"
				label="Card"
				description="Bar"
				required={ true }
				locked={ false }
			/>
		);

		expect( screen.getAllByText( '(Required)' ) ).toHaveLength( 2 );
	} );

	it( 'does not call onCheckClick or onUnCheckClick when clicking a locked checkbox', async () => {
		const handleCheckClickMock = jest.fn();
		const handleUnCheckClickMock = jest.fn();
		render(
			<PaymentMethod
				label="Bancontact"
				id="bancontact"
				onCheckClick={ handleCheckClickMock }
				onUncheckClick={ handleUnCheckClickMock }
				description="Locked payment method"
				locked={ true }
			/>
		);

		await user.click( screen.getByLabelText( 'Bancontact' ) );

		expect( handleCheckClickMock ).not.toHaveBeenCalled();
		expect( handleUnCheckClickMock ).not.toHaveBeenCalled();
	} );

	it( 'does not render DuplicateNotice if the payment method is not in duplicated', () => {
		render(
			<DuplicatedPaymentMethodsContext.Provider
				value={ {
					duplicates: { ideal: [ 'woocommerce_payments' ] },
					dismissedDuplicateNotices: {},
					setDismissedDuplicateNotices: () => null,
				} }
			>
				<PaymentMethod
					label="Test Method"
					id="card"
					description="Test Description"
				/>
			</DuplicatedPaymentMethodsContext.Provider>
		);

		expect(
			screen.queryByText(
				'This payment method is enabled by other extensions. Review extensions to improve the shopper experience.'
			)
		).not.toBeInTheDocument();
	} );

	it( 'render DuplicateNotice if payment method is in duplicates', () => {
		render(
			<DuplicatedPaymentMethodsContext.Provider
				value={ {
					duplicates: { card: [ 'woocommerce_payments' ] },
					dismissedDuplicateNotices: {},
					setDismissedDuplicateNotices: () => null,
				} }
			>
				<PaymentMethod
					label="Test Method"
					id="card"
					description="Test Description"
				/>
			</DuplicatedPaymentMethodsContext.Provider>
		);

		expect(
			screen.queryByText(
				'This payment method is enabled by other extensions. Review extensions to improve the shopper experience.'
			)
		).toBeInTheDocument();
	} );

	describe( 'Promotional discount badge', () => {
		// Helper to create a complete FeeStructure for tests.
		const createFeeStructure = ( overrides = {} ) => ( {
			base: {
				currency: 'USD',
				percentage_rate: 0.029,
				fixed_rate: 30,
			},
			additional: {
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			},
			fx: {
				currency: 'USD',
				percentage_rate: 0,
				fixed_rate: 0,
			},
			discount: [],
			...overrides,
		} );

		it( 'renders promotional badge when payment method has active discount', () => {
			const accountFeesWithDiscount = {
				klarna: createFeeStructure( {
					discount: [
						{
							currency: 'USD',
							percentage_rate: 0,
							fixed_rate: 0,
							discount: 0.5, // 50% - formatFee multiplies by 100.
							end_time: '2026-12-31',
							volume_allowance: null,
							volume_currency: null,
							current_volume: null,
						},
					],
				} ),
			};

			render(
				<WCPaySettingsContext.Provider
					value={ { accountFees: accountFeesWithDiscount } }
				>
					<PaymentMethod
						id="klarna"
						label="Klarna"
						description="Buy now, pay later"
					/>
				</WCPaySettingsContext.Provider>
			);

			// The badge should show the discount text (desktop + mobile labels).
			expect(
				screen.getAllByText( /50% off fees through/i )
			).toHaveLength( 2 );
		} );

		it( 'does not render promotional badge when payment method has no discount', () => {
			const accountFeesNoDiscount = {
				klarna: createFeeStructure( {
					discount: [],
				} ),
			};

			render(
				<WCPaySettingsContext.Provider
					value={ { accountFees: accountFeesNoDiscount } }
				>
					<PaymentMethod
						id="klarna"
						label="Klarna"
						description="Buy now, pay later"
					/>
				</WCPaySettingsContext.Provider>
			);

			// No discount badge should be present.
			expect( screen.queryByText( /off fees/i ) ).not.toBeInTheDocument();
		} );

		it( 'does not render promotional badge when first discount entry has no discount value', () => {
			// Tests edge case where discount array has multiple entries but
			// the first entry (which is checked) has no discount value.
			const accountFeesMultipleDiscounts = {
				klarna: createFeeStructure( {
					discount: [
						{
							currency: 'USD',
							percentage_rate: 0,
							fixed_rate: 0,
							// First entry has no discount value.
							end_time: '2026-12-31',
							volume_allowance: null,
							volume_currency: null,
							current_volume: null,
						},
						{
							currency: 'USD',
							percentage_rate: 0,
							fixed_rate: 0,
							discount: 0.5, // Second entry has discount but is not checked.
							end_time: '2026-12-31',
							volume_allowance: null,
							volume_currency: null,
							current_volume: null,
						},
					],
				} ),
			};

			render(
				<WCPaySettingsContext.Provider
					value={ { accountFees: accountFeesMultipleDiscounts } }
				>
					<PaymentMethod
						id="klarna"
						label="Klarna"
						description="Buy now, pay later"
					/>
				</WCPaySettingsContext.Provider>
			);

			// No discount badge should be present since only first entry is checked.
			expect( screen.queryByText( /off fees/i ) ).not.toBeInTheDocument();
		} );

		it( 'does not render promotional badge when discount has no discount value', () => {
			const accountFeesNoDiscountValue = {
				klarna: createFeeStructure( {
					discount: [
						{
							currency: 'USD',
							percentage_rate: 0,
							fixed_rate: 0,
							// No discount value.
							end_time: null,
							volume_allowance: null,
							volume_currency: null,
							current_volume: null,
						},
					],
				} ),
			};

			render(
				<WCPaySettingsContext.Provider
					value={ { accountFees: accountFeesNoDiscountValue } }
				>
					<PaymentMethod
						id="klarna"
						label="Klarna"
						description="Buy now, pay later"
					/>
				</WCPaySettingsContext.Provider>
			);

			// No discount badge should be present.
			expect( screen.queryByText( /off fees/i ) ).not.toBeInTheDocument();
		} );

		it( 'does not render promotional badge when accountFees is not provided', () => {
			render(
				<WCPaySettingsContext.Provider value={ {} }>
					<PaymentMethod
						id="klarna"
						label="Klarna"
						description="Buy now, pay later"
					/>
				</WCPaySettingsContext.Provider>
			);

			// No discount badge should be present.
			expect( screen.queryByText( /off fees/i ) ).not.toBeInTheDocument();
		} );

		it( 'renders promotional badge with volume-based discount', () => {
			const accountFeesWithVolumeDiscount = {
				affirm: createFeeStructure( {
					discount: [
						{
							currency: 'USD',
							percentage_rate: 0,
							fixed_rate: 0,
							discount: 1, // 100% - formatFee multiplies by 100.
							end_time: '2026-06-30',
							volume_allowance: 50000,
							volume_currency: 'USD',
							current_volume: 0,
						},
					],
				} ),
			};

			render(
				<WCPaySettingsContext.Provider
					value={ { accountFees: accountFeesWithVolumeDiscount } }
				>
					<PaymentMethod
						id="affirm"
						label="Affirm"
						description="Pay over time"
					/>
				</WCPaySettingsContext.Provider>
			);

			// The badge should show the discount text (100% = full discount, desktop + mobile labels).
			expect(
				screen.getAllByText( /100% off fees through/i )
			).toHaveLength( 2 );
		} );

		it( 'renders promotional badge without end date when end_time is not provided', () => {
			const accountFeesNoEndTime = {
				klarna: createFeeStructure( {
					discount: [
						{
							currency: 'USD',
							percentage_rate: 0,
							fixed_rate: 0,
							discount: 0.25, // 25% - formatFee multiplies by 100.
							end_time: null,
							volume_allowance: null,
							volume_currency: null,
							current_volume: null,
						},
					],
				} ),
			};

			render(
				<WCPaySettingsContext.Provider
					value={ { accountFees: accountFeesNoEndTime } }
				>
					<PaymentMethod
						id="klarna"
						label="Klarna"
						description="Buy now, pay later"
					/>
				</WCPaySettingsContext.Provider>
			);

			// The badge should show the discount text without date (desktop + mobile labels).
			expect( screen.getAllByText( /25% off fees/i ) ).toHaveLength( 2 );
		} );
	} );

	describe( 'PM Promotion badge', () => {
		// Helper to create a mock badge promotion.
		const createBadgePromotion = ( overrides = {} ) => ( {
			id: 'klarna-promo__badge',
			promo_id: 'klarna-promo',
			payment_method: 'klarna',
			payment_method_title: 'Klarna',
			type: 'badge',
			title: 'Zero fees for 90 days',
			description:
				'Enable Klarna and pay zero processing fees for 90 days.',
			cta_label: 'Enable Klarna',
			tc_url: 'https://example.com/terms',
			tc_label: 'See terms',
			...overrides,
		} );

		it( 'renders promotional badge when payment method has badge promotion', () => {
			const badgePromotion = createBadgePromotion();
			usePmPromotions.mockReturnValue( {
				pmPromotions: [ badgePromotion ],
				isLoading: false,
			} );

			render(
				<PaymentMethod
					id="klarna"
					label="Klarna"
					description="Buy now, pay later"
				/>
			);

			// The badge should show the promotion title (desktop + mobile labels).
			expect(
				screen.getAllByText( 'Zero fees for 90 days' )
			).toHaveLength( 2 );
		} );

		it( 'does not render promotional badge for non-matching payment method', () => {
			const badgePromotion = createBadgePromotion( {
				payment_method: 'affirm',
			} );
			usePmPromotions.mockReturnValue( {
				pmPromotions: [ badgePromotion ],
				isLoading: false,
			} );

			render(
				<PaymentMethod
					id="klarna"
					label="Klarna"
					description="Buy now, pay later"
				/>
			);

			// No badge should be present since promotion is for affirm.
			expect(
				screen.queryByText( 'Zero fees for 90 days' )
			).not.toBeInTheDocument();
		} );

		it( 'does not render promotional badge for spotlight-type promotion', () => {
			const spotlightPromotion = createBadgePromotion( {
				id: 'klarna-promo__spotlight',
				type: 'spotlight',
			} );
			usePmPromotions.mockReturnValue( {
				pmPromotions: [ spotlightPromotion ],
				isLoading: false,
			} );

			render(
				<PaymentMethod
					id="klarna"
					label="Klarna"
					description="Buy now, pay later"
				/>
			);

			// No badge should be present since promotion is spotlight type.
			expect(
				screen.queryByText( 'Zero fees for 90 days' )
			).not.toBeInTheDocument();
		} );

		it( 'prefers discount fee over badge promotion when both exist', () => {
			// Create accountFees with discount.
			const accountFeesWithDiscount = {
				klarna: {
					base: {
						currency: 'USD',
						percentage_rate: 0.029,
						fixed_rate: 30,
					},
					additional: {
						currency: 'USD',
						percentage_rate: 0,
						fixed_rate: 0,
					},
					fx: {
						currency: 'USD',
						percentage_rate: 0,
						fixed_rate: 0,
					},
					discount: [
						{
							currency: 'USD',
							percentage_rate: 0,
							fixed_rate: 0,
							discount: 0.5, // 50%.
							end_time: '2026-12-31',
							volume_allowance: null,
							volume_currency: null,
							current_volume: null,
						},
					],
				},
			};

			// Also have a badge promotion.
			const badgePromotion = createBadgePromotion();
			usePmPromotions.mockReturnValue( {
				pmPromotions: [ badgePromotion ],
				isLoading: false,
			} );

			render(
				<WCPaySettingsContext.Provider
					value={ { accountFees: accountFeesWithDiscount } }
				>
					<PaymentMethod
						id="klarna"
						label="Klarna"
						description="Buy now, pay later"
					/>
				</WCPaySettingsContext.Provider>
			);

			// Should show discount fee text, not promotion title.
			expect(
				screen.getAllByText( /50% off fees through/i )
			).toHaveLength( 2 );
			expect(
				screen.queryByText( 'Zero fees for 90 days' )
			).not.toBeInTheDocument();
		} );

		it( 'renders badge promotion with correct tooltip label', () => {
			const badgePromotion = createBadgePromotion();
			usePmPromotions.mockReturnValue( {
				pmPromotions: [ badgePromotion ],
				isLoading: false,
			} );

			render(
				<PaymentMethod
					id="klarna"
					label="Klarna"
					description="Buy now, pay later"
				/>
			);

			// The tooltip button should have the correct accessible label.
			const tooltipButtons = screen.getAllByRole( 'button', {
				name: /promotion details/i,
			} );
			expect( tooltipButtons ).toHaveLength( 2 ); // Desktop + mobile.
		} );

		it( 'does not render promotional badge when promotions array is empty', () => {
			usePmPromotions.mockReturnValue( {
				pmPromotions: [],
				isLoading: false,
			} );

			render(
				<PaymentMethod
					id="klarna"
					label="Klarna"
					description="Buy now, pay later"
				/>
			);

			// No badge should be present.
			expect(
				screen.queryByText( 'Zero fees for 90 days' )
			).not.toBeInTheDocument();
		} );

		it( 'does not render promotional badge while promotions are loading', () => {
			usePmPromotions.mockReturnValue( {
				pmPromotions: [],
				isLoading: true,
			} );

			render(
				<PaymentMethod
					id="klarna"
					label="Klarna"
					description="Buy now, pay later"
				/>
			);

			// No badge should be present while loading.
			expect(
				screen.queryByText( 'Zero fees for 90 days' )
			).not.toBeInTheDocument();
		} );
	} );
} );
