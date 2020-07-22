/**
 * External dependencies
 */
import { Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { getPaymentSettingsUrl, isInTestMode } from '../../../util';
import {
	topics,
	getPaymentsSettingsUrlComponent,
	getTopicDetails,
	getNoticeMessage,
	getNotice,
	withTestNotice,
} from '../index';

jest.mock( '../../../util', () => ( {
	isInTestMode: jest.fn(),
	getPaymentSettingsUrl: jest.fn().mockReturnValue( 'https://example.com/' ),
} ) );

describe( 'Test mode notification', () => {
	// Set up easy to use lists containing test inputs.
	const listTopics = [
		topics.transactions,
		topics.deposits,
		topics.disputes,
	];
	const detailsTopics = [
		topics.depositDetails,
		topics.disputeDetails,
		topics.paymentDetails,
	];
	const allTopics = [ ...listTopics, ...detailsTopics ];

	const topicsWithTestMode = [
		...allTopics.map( ( topic ) => [ topic, true ] ),
		...allTopics.map( ( topic ) => [ topic, false ] ),
	];

	test( 'Returns correct URL component', () => {
		const expected = (
			<a href={ getPaymentSettingsUrl() }>
				{ 'View WooCommerce Payments settings.' }
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
			'WooCommerce Payments was in test mode when these orders were placed.'
		);

		expect( getTopicDetails( topics.disputeDetails ) ).toBe(
			'WooCommerce Payments was in test mode when this order was placed.'
		);

		expect( getTopicDetails( topics.paymentDetails ) ).toBe(
			'WooCommerce Payments was in test mode when this order was placed.'
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

	test.each( allTopics )( 'Returns correct notice', ( topic ) => {
		const expected = (
			<Notice
				className="wcpay-test-mode-notice"
				status="warning"
				isDismissible={ false }
			>
				{ getNoticeMessage( topic ) }
			</Notice>
		);

		expect( getNotice( topic ) ).toStrictEqual( expected );
	} );

	test.each( topicsWithTestMode )(
		'Simple component is wrapped correctly',
		( topic, isTestMode ) => {
			isInTestMode.mockReturnValue( isTestMode );

			const wrappedComponent = () => (
				<p>This component will be wrapped.</p>
			);
			const expected = (
				<div>
					{ isTestMode ? getNotice( topic ) : null }
					{ wrappedComponent() }
				</div>
			);

			expect( withTestNotice( wrappedComponent, topic )() ).toStrictEqual(
				expected
			);
		}
	);

	test.each( topicsWithTestMode )(
		'Component with props is wrapped correctly',
		( topic, isTestMode ) => {
			isInTestMode.mockReturnValue( isTestMode );

			const propToPass = { text: 'test prop' };
			const wrappedComponent = ( props ) => (
				<p>This component with { props.text } will be wrapped.</p>
			);
			const expected = (
				<div>
					{ isTestMode ? getNotice( topic ) : null }
					{ wrappedComponent( propToPass ) }
				</div>
			);

			expect(
				withTestNotice( wrappedComponent, topic )( propToPass )
			).toStrictEqual( expected );
		}
	);

	test.each( topicsWithTestMode )(
		'Component with props and ownProps is wrapped correctly',
		( topic, isTestMode ) => {
			isInTestMode.mockReturnValue( isTestMode );

			const mockProps = { text: 'test prop' };
			const mockOwnProps = { text: 'test ownProp' };
			const wrappedComponent = ( props, ownProps ) => (
				<p>
					This component with { props.text } and { ownProps.text }{ ' ' }
					will be wrapped.
				</p>
			);
			const expected = (
				<div>
					{ isTestMode ? getNotice( topic ) : null }
					{ wrappedComponent( mockProps, mockOwnProps ) }
				</div>
			);

			expect(
				withTestNotice( wrappedComponent, topic )(
					mockProps,
					mockOwnProps
				)
			).toStrictEqual( expected );
		}
	);
} );
