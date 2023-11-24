/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { getPaymentSettingsUrl, isInTestMode } from 'utils';
import {
	topics,
	getPaymentsSettingsUrlComponent,
	getTopicDetails,
	getNoticeMessage,
	TestModeNotice,
} from '../index';

jest.mock( 'utils', () => ( {
	isInTestMode: jest.fn(),
	getPaymentSettingsUrl: jest.fn().mockReturnValue( 'https://example.com/' ),
} ) );

describe( 'Test mode notification', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			accountStatus: {
				detailsSubmitted: true,
			},
		};
	} );
	// Set up easy to use lists containing test inputs.
	const listTopics = [
		topics.transactions,
		topics.deposits,
		topics.disputes,
		topics.documents,
	];
	const detailsTopics = [
		topics.depositDetails,
		topics.disputeDetails,
		topics.paymentDetails,
		topics.documentDetails,
	];
	const allTopics = [ ...listTopics, ...detailsTopics ];

	const topicsWithTestMode = [
		...allTopics.map( ( topic ) => [ topic, true ] ),
		...allTopics.map( ( topic ) => [ topic, false ] ),
	];

	test( 'Returns correct URL component', () => {
		const expected = (
			<a href={ getPaymentSettingsUrl() }>
				{ 'View WooPayments settings' }
			</a>
		);

		expect( getPaymentsSettingsUrlComponent() ).toStrictEqual( expected );
	} );

	test.each( listTopics )(
		'Returns right notice message for list topics',
		( topic ) => {
			const expected = (
				<span>
					{ topic } { getPaymentsSettingsUrlComponent( topic ) }
				</span>
			);

			expect( getNoticeMessage( topic ) ).toStrictEqual( expected );
		}
	);

	test( 'Notice details are correct for details topics', () => {
		expect( getTopicDetails( topics.depositDetails ) ).toBe(
			'WooPayments was in test mode when these orders were placed.'
		);

		expect( getTopicDetails( topics.disputeDetails ) ).toBe(
			'WooPayments was in test mode when this order was placed.'
		);

		expect( getTopicDetails( topics.paymentDetails ) ).toBe(
			'WooPayments was in test mode when this order was placed.'
		);
	} );

	test.each( detailsTopics )(
		'Returns right notice message for details topics',
		( topic ) => {
			const topicDetails = getTopicDetails( topic );
			const urlComponent = getPaymentsSettingsUrlComponent( topic );
			const expected = (
				<span>
					<b>{ topic }</b> { topicDetails } { urlComponent }
				</span>
			);

			expect( getNoticeMessage( topic ) ).toStrictEqual( expected );
		}
	);

	test( 'Returns right notice message without URL component', () => {
		global.wcpaySettings.accountStatus.detailsSubmitted = false;
		const topic = topics.overview;
		isInTestMode.mockReturnValue( true );
		const { container: testModeNotice } = render(
			<TestModeNotice topic={ topic } />
		);

		expect( testModeNotice ).toMatchSnapshot();
	} );

	test.each( topicsWithTestMode )(
		'Component is rendered correctly',
		( topic, isTestMode ) => {
			isInTestMode.mockReturnValue( isTestMode );
			const { container: testModeNotice } = render(
				<TestModeNotice topic={ topic } />
			);

			expect( testModeNotice ).toMatchSnapshot();
		}
	);
} );
