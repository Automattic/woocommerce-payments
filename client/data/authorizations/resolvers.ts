/** @format */

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

/**
 * Internal dependencies
 */
import { updateAuthorizations, updateAuthorizationsSummary } from './actions';
import { Authorization } from './hooks';
import { Query } from '@woocommerce/navigation';

enum RiskLevel {
	HIGH = 'high',
	ELEVATED = 'elevated',
	NORMAL = 'normal',
}

/**
 * Retrieves the transactions summary from the summary API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getAuthorizations( query: Query ): any {
	const getMockedRows = () => {
		const randomAmount = () => {
			return 1400 + Math.floor( Math.random() * 5000 );
		};

		const randomDate = dateI18n(
			'M j, Y / g:iA',
			moment.utc( new Date() ).local().toISOString()
		);

		const randomCaptureDate = dateI18n(
			'M j, Y / g:iA',
			moment.utc( new Date() ).add( '7', 'days' ).local().toISOString()
		);

		const randomRisk = (): RiskLevel => {
			const risks: Array< RiskLevel > = [
				RiskLevel.ELEVATED,
				RiskLevel.NORMAL,
				RiskLevel.HIGH,
			];

			return risks[ Math.floor( Math.random() * risks.length ) ];
		};

		const data = Array( 10 ).fill( 0 );

		return data.map( () => {
			const dataAmount = randomAmount();
			const dataRisk = randomRisk();

			const authorization: Authorization = {
				authorization_id: '123',
				authorized_on: randomDate,
				captured_by: randomCaptureDate,
				order: {
					number: 24,
					customer_url: 'https://doggo.com',
					url: 'https://doggo.com',
				},
				risk_level: dataRisk,
				amount: dataAmount,
				customer_email: 'good_boy@doge.com',
				customer_country: 'Kingdom of Dogs',
				customer_name: 'Good boy',
			};

			return authorization;
		} );
	};

	yield updateAuthorizations( query, getMockedRows() );
}

export function* getAuthorizationsSummary( query: Query ): any {
	const data = {
		count: 10,
		total: 100,
		currency: 'USD',
		store_currencies: [ 'USD' ],
		customer_currencies: [ 'USD', 'EUR' ],
		totalAmount: 126790,
	};

	yield updateAuthorizationsSummary( query, data );
}
