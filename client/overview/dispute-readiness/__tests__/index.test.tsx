/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { CollapsibleList, TaskItem } from '@woocommerce/experimental';

/**
 * Internal dependencies
 */
import DisputeReadinessCard from '..';
import {
	useDisputeReadiness,
	useDisputeReadinessActions,
} from 'wcpay/data/dispute-readiness';

jest.mock( 'wcpay/data/dispute-readiness', () => ( {
	useDisputeReadiness: jest.fn(),
	useDisputeReadinessActions: jest.fn(),
} ) );

jest.mock( '@woocommerce/experimental', () => ( {
	CollapsibleList: jest.fn(),
	TaskItem: jest.fn(),
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
				description:
					'Make sure your business name appears clearly on customer bank statements to prevent confusion.',
			},
			{
				id: 'refund_policy',
				status: 'complete',
				label: 'Refund policy page published',
				description:
					'Publish a refund policy so customers can resolve issues with you before filing a dispute.',
			},
			{
				id: 'support_contact',
				status: 'complete',
				label: 'Customer support contact linked in order emails',
				description:
					'Give customers a direct way to reach you from their order emails to handle issues quickly.',
			},
			{
				id: 'terms_and_conditions',
				status: 'incomplete',
				label: 'Terms & conditions linked at checkout',
				description:
					'Add a T&C link at checkout so customers acknowledge your policies before completing a purchase.',
				actionLabel: 'Fix it',
				actionUrl:
					'https://example.test/wp-admin/admin.php?page=wc-settings&tab=advanced',
			},
		],
	},
};

const mockUseDisputeReadiness = useDisputeReadiness as jest.Mock;
const mockUseDisputeReadinessActions = useDisputeReadinessActions as jest.Mock;
const mockCollapsibleList = CollapsibleList as jest.Mock;
const mockTaskItem = TaskItem as jest.Mock;

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
		mockCollapsibleList.mockImplementation( ( { children } ) => (
			<div>{ children }</div>
		) );
		mockTaskItem.mockImplementation(
			( { title, content, showActionButton, action, actionLabel } ) => (
				<div>
					<div>{ title }</div>
					<div>{ content }</div>
					{ showActionButton && (
						<button onClick={ action }>{ actionLabel }</button>
					) }
				</div>
			)
		);
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

	it( 'renders design copy and task rows', () => {
		renderCard();

		expect( screen.getByText( 'Dispute readiness' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				'These 4 steps help customers recognize charges, understand your policies, and contact you before opening a dispute.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Recognizable statement descriptor' )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Make sure your business name appears clearly on customer bank statements to prevent confusion.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Terms & conditions linked at checkout' )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Add a T&C link at checkout so customers acknowledge your policies before completing a purchase.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Fix it' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', {
				name: 'Dispute readiness actions',
			} )
		).toBeInTheDocument();
	} );

	it( 'dismisses the card from the actions menu', async () => {
		renderCard();

		await userEvent.click(
			screen.getByRole( 'button', {
				name: 'Dispute readiness actions',
			} )
		);
		await userEvent.click(
			screen.getByRole( 'menuitem', { name: 'Dismiss' } )
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
			screen.getAllByRole( 'button', { name: 'Fix it' } )[ 0 ]
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
