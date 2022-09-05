/** @format */

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

/**
 * Internal dependencies
 */
import {
	updateAuthorizations,
	updateAuthorization,
	updateAuthorizationsSummary,
} from './actions';
import { Authorization, RiskLevel } from 'wcpay/types/authorizations';
import { Query } from '@woocommerce/navigation';

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
			const risks: Array< RiskLevel > = [ 'elevated', 'normal', 'high' ];

			return risks[ Math.floor( Math.random() * risks.length ) ];
		};

		const data = Array( 10 ).fill( 0 );

		return data.map( () => {
			const dataAmount = randomAmount();
			const dataRisk = randomRisk();

			const authorization: Authorization = {
				authorization_id: `id_${ Date.now() }_${ Math.floor(
					Math.random() * 1000
				) }`,
				authorized_on: randomDate,
				capture_by: randomCaptureDate,
				captured: false,
				order: {
					number: 254,
					customer_url: 'https://doggo.com',
					url: 'https://doggo.com',
				},
				risk_level: dataRisk,
				amount: dataAmount,
				customer_email: 'good_boy@doge.com',
				customer_country: 'Kingdom of Dogs',
				customer_name: 'Good boy',
				payment_intent_id: 'pi_3Lcm2iQsDOQXPzI102uKS0FD',
			};

			return authorization;
		} );
	};

	yield updateAuthorizations( query, getMockedRows() );
}

export function* getAuthorization( id: string ): Generator< unknown > {
	// TODO replace mocked implementation when server is ready
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
		const risks: Array< RiskLevel > = [ 'elevated', 'normal', 'high' ];

		return risks[ Math.floor( Math.random() * risks.length ) ];
	};

	const authorization = {
		authorization_id: id,
		authorized_on: randomDate,
		capture_by: randomCaptureDate,
		captured: false,
		order: {
			number: 254,
			customer_url: 'https://doggo.com',
			url: 'https://doggo.com',
		},
		risk_level: randomRisk(),
		amount: randomAmount(),
		customer_email: 'good_boy@doge.com',
		customer_country: 'Kingdom of Dogs',
		customer_name: 'Good boy',
		payment_intent_id: 'pi_3Lcm2iQsDOQXPzI102uKS0FD',
	};

	yield updateAuthorization( authorization );
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
