/** @format */

/**
 * External dependencies
 */
import { render, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { DisputeEvidenceForm, DisputeEvidencePage } from '../';

/* eslint-disable camelcase */
const disputeNeedsResponse = {
	id: 'dp_asdfghjkl',
	amount: 1000,
	created: 1572590800,
	evidence: {
		customer_purchase_ip: '127.0.0.1',
		uncategorized_text: '',
	},
	evidence_details: {
		due_by: 1573199200,
	},
	reason: 'fraudulent',
	status: 'needs_response',
};

const disputeNoNeedForResponse = {
	id: 'dp_zxcvbnm',
	amount: 1050,
	created: 1572480800,
	evidence: {
		customer_purchase_ip: '127.0.0.1',
		uncategorized_text: 'winning_evidence',
	},
	evidence_details: {
		due_by: 1573099200,
	},
	reason: 'general',
	status: 'under_review',
};
/* eslint-enable camelcase */

const fields = [
	{
		key: 'general',
		title: 'General evidence',
		fields: [
			{
				key: 'product_description',
				label: 'Product description',
				type: 'textarea',
			},
			{
				key: 'customer_name',
				label: 'Customer name',
				type: 'text',
			},
			{
				key: 'customer_signature',
				label: 'Customer signature',
				type: 'file',
			},
		],
	},
];

describe( 'Dispute evidence form', () => {
	test( 'needing response, renders correctly', () => {
		const { container: form } = render(
			<DisputeEvidenceForm
				fields={ fields }
				evidence={ disputeNeedsResponse.evidence }
				readOnly={ false }
			/>
		);
		expect( form ).toMatchSnapshot();
	} );

	test( 'not needing response, renders correctly', () => {
		const { container: form } = render(
			<DisputeEvidenceForm
				fields={ fields }
				evidence={ disputeNoNeedForResponse.evidence }
				readOnly={ true }
			/>
		);
		expect( form ).toMatchSnapshot();
	} );

	test( 'confirmation requested on submit', () => {
		window.confirm = jest.fn();

		// We have to mount component to select button for click.
		const { getAllByText } = render(
			<DisputeEvidenceForm
				fields={ fields }
				evidence={ disputeNeedsResponse.evidence }
				readOnly={ false }
				onSave={ jest.fn() }
			/>
		);

		// There are multiple submit buttons in the form. Use the last one.
		const submitButton = getAllByText( /submit.*/i ).pop();
		fireEvent.click( submitButton );
		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect( window.confirm ).toHaveBeenCalledWith(
			"Are you sure you're ready to submit this evidence? Evidence submissions are final."
		);
	}
	);

	test( 'onSave called after confirmation only', () => {
		const onSave = jest.fn();

		// We have to mount component to select button for click.
		const { getAllByText } = render(
			<DisputeEvidenceForm
				fields={ fields }
				evidence={ disputeNeedsResponse.evidence }
				readOnly={ false }
				onSave={ onSave }
			/>
		);

		// There are multiple submit buttons in the form. Use the last one.
		const submitButton = getAllByText( /submit.*/i ).pop();

		window.confirm = jest.fn();
		window.confirm
		.mockReturnValueOnce( false )
		.mockReturnValueOnce( true );

		// Test not confirmed case.
		fireEvent.click( submitButton );
		expect( onSave ).toHaveBeenCalledTimes( 0 );

		// Test confirmed case.
		fireEvent.click( submitButton );
		expect( onSave ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'Dispute evidence page', () => {
	test( 'renders correctly', () => {
		const { container: form } = render(
			<DisputeEvidencePage
				showPlaceholder={ false }
				dispute={ disputeNeedsResponse }
				evidence={ disputeNeedsResponse.evidence }
			/>
		);
		expect( form ).toMatchSnapshot();
	} );
} );
