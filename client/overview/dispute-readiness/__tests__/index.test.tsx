/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import DisputeReadinessCard from '..';
import { useDisputeReadiness, useDisputeReadinessActions } from 'data';

jest.mock( 'data', () => ( {
	useDisputeReadiness: jest.fn(),
	useDisputeReadinessActions: jest.fn(),
} ) );

jest.mock( 'wcpay/tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

const dismissDisputeReadinessCard = jest.fn();
const confirmStatementDescriptor = jest.fn();
const refreshDisputeReadiness = jest.fn();

const readinessPayload = {
	overview: {
		enabled: true,
		hidden: false,
		score: 3,
		total: 4,
		state: 'incomplete',
		isDismissed: false,
		completeSignalIds: [
			'statement_descriptor',
			'refund_policy',
			'support_contact',
		],
		incompleteSignalIds: [ 'terms_and_conditions' ],
		signals: [
			{
				id: 'statement_descriptor',
				status: 'complete',
				label: 'Recognizable statement descriptor',
			},
			{
				id: 'refund_policy',
				status: 'complete',
				label: 'Refund policy page published',
			},
			{
				id: 'support_contact',
				status: 'complete',
				label: 'Customer support contact linked in order emails',
			},
			{
				id: 'terms_and_conditions',
				status: 'incomplete',
				label: 'Terms & conditions linked at checkout',
				actionLabel: 'Fix',
				actionUrl:
					'https://example.test/wp-admin/admin.php?page=wc-settings&tab=advanced',
			},
		],
	},
};

const mockUseDisputeReadiness = useDisputeReadiness as jest.Mock;
const mockUseDisputeReadinessActions = useDisputeReadinessActions as jest.Mock;

const renderCard = ( overrides = {} ) => {
	mockUseDisputeReadiness.mockReturnValue( {
		disputeReadiness: readinessPayload,
		disputeReadinessError: undefined,
		isLoading: false,
		...overrides,
	} );

	return render( <DisputeReadinessCard /> );
};

describe( 'DisputeReadinessCard', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockUseDisputeReadinessActions.mockReturnValue( {
			dismissDisputeReadinessCard,
			confirmStatementDescriptor,
			refreshDisputeReadiness,
		} );
	} );

	it( 'renders nothing when disabled', () => {
		const { container } = renderCard( {
			disputeReadiness: {
				overview: {
					...readinessPayload.overview,
					enabled: false,
				},
			},
		} );

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders nothing when dismissed', () => {
		const { container } = renderCard( {
			disputeReadiness: {
				overview: {
					...readinessPayload.overview,
					isDismissed: true,
				},
			},
		} );

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders score and checklist rows', () => {
		renderCard();

		expect( screen.getByText( 'Dispute Readiness' ) ).toBeInTheDocument();
		expect( screen.getByText( '3' ) ).toBeInTheDocument();
		expect( screen.getByText( 'of 4' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Recognizable statement descriptor' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Terms & conditions linked at checkout' )
		).toBeInTheDocument();
		expect( screen.getByRole( 'link', { name: 'Fix →' } ) ).toHaveAttribute(
			'href',
			'https://example.test/wp-admin/admin.php?page=wc-settings&tab=advanced'
		);
	} );

	it( 'dismisses the card when clicking dismiss', async () => {
		renderCard();

		await userEvent.click(
			screen.getByRole( 'button', {
				name: 'Dismiss dispute readiness card',
			} )
		);

		expect( dismissDisputeReadinessCard ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'opens and confirms the statement descriptor review modal', async () => {
		renderCard( {
			disputeReadiness: {
				overview: {
					...readinessPayload.overview,
					score: 2,
					completeSignalIds: [ 'refund_policy', 'support_contact' ],
					incompleteSignalIds: [
						'statement_descriptor',
						'terms_and_conditions',
					],
					signals: [
						{
							id: 'statement_descriptor',
							status: 'incomplete',
							label: 'Recognizable statement descriptor',
							actionUrl:
								'https://example.test/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments',
							reviewPrompt: {
								text: "Your statement descriptor will show up on your customers' bank statements. Does it clearly identify your store?",
								currentDescriptor: 'MY STORE',
								confirmLabel: 'Looks good',
								updateLabel: 'Update',
							},
						},
						...readinessPayload.overview.signals.slice( 1 ),
					],
				},
			},
		} );

		expect(
			screen.queryByText(
				"Your statement descriptor will show up on your customers' bank statements. Does it clearly identify your store?"
			)
		).not.toBeInTheDocument();

		await userEvent.click(
			screen.getAllByRole( 'link', { name: 'Fix →' } )[ 0 ]
		);

		expect(
			screen.getByText(
				"Your statement descriptor will show up on your customers' bank statements. Does it clearly identify your store?"
			)
		).toBeInTheDocument();
		expect( screen.getByText( 'MY STORE' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Update' } )
		).toHaveAttribute(
			'href',
			'https://example.test/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments'
		);

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Looks good' } )
		);

		expect( confirmStatementDescriptor ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'refreshes dispute readiness when mounted', () => {
		renderCard();

		expect( refreshDisputeReadiness ).toHaveBeenCalledTimes( 1 );
	} );
} );
