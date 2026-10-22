/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { FraudRules, getRulesError } from '../fraud-rules';

const avsRule = {
	key: 'avs_verification',
	outcome: 'block',
	check: { key: 'avs_mismatch', operator: 'equals', value: true },
};

describe( 'FraudRules', () => {
	beforeEach( () => {
		( global as Record< string, unknown > ).wcSettings = {
			admin: {
				preloadSettings: {
					general: {
						woocommerce_allowed_countries: 'specific',
						woocommerce_all_except_countries: [],
						woocommerce_specific_allowed_countries: [ 'CA', 'US' ],
					},
				},
			},
			countries: { CA: 'Canada', US: 'United States' },
		};
		( global as Record< string, unknown > ).wcpaySettings = {
			storeCurrency: 'USD',
			isMultiCurrencyEnabled: '0',
			featureFlags: { isFRTReviewFeatureActive: false },
			accountStatus: {
				fraudProtection: { declineOnAVSFailure: false },
			},
		};
	} );

	it( 'saves an enabled rule and switches to advanced protection', async () => {
		const onChange = jest.fn();
		render(
			<FraudRules
				data={ {
					advanced_fraud_protection_settings: [],
					current_protection_level: 'basic',
				} }
				onChange={ onChange }
			/>
		);

		await userEvent.click(
			screen.getByLabelText( 'Enable AVS Mismatch filter' )
		);

		expect( onChange ).toHaveBeenLastCalledWith( {
			advanced_fraud_protection_settings: [
				expect.objectContaining( { key: 'avs_verification' } ),
			],
			current_protection_level: 'advanced',
		} );
	} );

	it( 'switches to basic protection when every rule is off', async () => {
		const onChange = jest.fn();
		render(
			<FraudRules
				data={ {
					advanced_fraud_protection_settings: [ avsRule ],
					current_protection_level: 'advanced',
				} }
				onChange={ onChange }
			/>
		);

		await userEvent.click(
			screen.getByLabelText( 'Enable AVS Mismatch filter' )
		);

		expect( onChange ).toHaveBeenLastCalledWith( {
			advanced_fraud_protection_settings: [],
			current_protection_level: 'basic',
		} );
	} );

	it( 'shows the load error instead of the rules', () => {
		render(
			<FraudRules
				data={ { advanced_fraud_protection_settings: 'error' } }
				onChange={ jest.fn() }
			/>
		);

		expect(
			screen.getByText( /error retrieving your fraud protection/, {
				selector: '.wcpay-inline-notice__content',
			} )
		).toBeInTheDocument();
		expect(
			screen.queryByLabelText( 'Enable AVS Mismatch filter' )
		).not.toBeInTheDocument();
	} );
} );

describe( 'getRulesError', () => {
	beforeEach( () => {
		( global as Record< string, unknown > ).wcpaySettings = {
			featureFlags: { isFRTReviewFeatureActive: false },
		};
	} );

	it( 'blocks saving a threshold rule without limits', () => {
		expect(
			getRulesError( {
				advanced_fraud_protection_settings: [
					{
						key: 'order_items_threshold',
						outcome: 'block',
						check: null,
					},
				],
			} )
		).toBe(
			'An item range must be set for the "Order Item Threshold" filter.'
		);
	} );

	it( 'allows valid rules', () => {
		expect(
			getRulesError( { advanced_fraud_protection_settings: [ avsRule ] } )
		).toBeNull();
	} );
} );
