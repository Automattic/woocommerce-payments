/** @format */
/**
 * External dependencies
 */
import { shallow, mount } from 'enzyme';

/**
 * Internal dependencies
 */
import { DisputeEvidenceForm } from '../';

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

describe( 'Dispute evidence form', () => {
	test( 'needing response, renders correctly', () => {
		const form = shallow(
			<DisputeEvidenceForm
                evidence={ disputeNeedsResponse.evidence }
                showPlaceholder={ false }
                readOnly={ false }
			/>
		);
		expect( form ).toMatchSnapshot();
    } );

	test( 'not needing response, renders correctly', () => {
        const form = shallow(
			<DisputeEvidenceForm
				evidence={ disputeNoNeedForResponse.evidence }
				showPlaceholder={ false }
				readOnly={ false }
			/>
		);
		expect( form ).toMatchSnapshot();
	} );

	test( 'confirmation requested on submit', () => {
		window.confirm = jest.fn();

		// We have to mount component to select button for click.
		const form = mount(
			<DisputeEvidenceForm
				evidence={ disputeNeedsResponse.evidence }
				showPlaceholder={ false }
				readOnly={ false }
				onSave={ jest.fn() }
			/>
		);

		const submitButton = form.find( 'button.is-primary' ).last();
		submitButton.simulate( 'click' );
		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect( window.confirm ).toHaveBeenCalledWith(
			"Are you sure you're ready to submit this evidence? Evidence submissions are final."
		);
	}
	);

	test( 'onSave called after confirmation only', () => {
		const onSave = jest.fn();

		// We have to mount component to select button for click.
		const form = mount(
			<DisputeEvidenceForm
				evidence={ disputeNeedsResponse.evidence }
				showPlaceholder={ false }
				readOnly={ false }
				onSave={ onSave }
			/>
		);
		const submitButton = form.find( 'button.is-primary' ).last();

		window.confirm = jest.fn();
		window.confirm
		.mockReturnValueOnce( false )
		.mockReturnValueOnce( true );

		// Test not confirmed case.
		submitButton.simulate( 'click' );
		expect( onSave ).toHaveBeenCalledTimes( 0 );

		// Test confirmed case.
		submitButton.simulate( 'click' );
		expect( onSave ).toHaveBeenCalledTimes( 1 );
	} );
} );
