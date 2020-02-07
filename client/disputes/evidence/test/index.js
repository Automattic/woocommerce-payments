/** @format */
/**
 * External dependencies
 */
import { shallow, mount } from 'enzyme';

/**
 * Internal dependencies
 */
import { DisputeEvidenceForm } from '../';

describe( 'Dispute evidence form', () => {
	test( 'needing response, renders correctly', () => {
        const dispute = {
            id: 'dp_asdfghjkl',
            amount: 1000,
            created: 1572590800,
            evidence: {
                // eslint-disable-next-line camelcase
                customer_purchase_ip: '127.0.0.1',
                // eslint-disable-next-line camelcase
                uncategorized_text: '',
            },
            // eslint-disable-next-line camelcase
            evidence_details: {
                // eslint-disable-next-line camelcase
                due_by: 1573199200,
            },
            reason: 'fraudulent',
            status: 'needs_response',
        };

		const form = shallow(
			<DisputeEvidenceForm
                evidence={ dispute.evidence }
                showPlaceholder={ false }
                readOnly={ false }
			/>
		);
		expect( form ).toMatchSnapshot();
    } );

	test( 'not needing response, renders correctly', () => {
        const dispute = {
            id: 'dp_zxcvbnm',
            amount: 1050,
            created: 1572480800,
            evidence: {
                // eslint-disable-next-line camelcase
                customer_purchase_ip: '127.0.0.1',
                // eslint-disable-next-line camelcase
                uncategorized_text: 'winning_evidence',
            },
            // eslint-disable-next-line camelcase
            evidence_details: {
                // eslint-disable-next-line camelcase
                due_by: 1573099200,
            },
            reason: 'general',
            status: 'under_review',
        };

		const form = shallow(
			<DisputeEvidenceForm
                evidence={ dispute.evidence }
                showPlaceholder={ false }
                readOnly={ false }
			/>
		);
		expect( form ).toMatchSnapshot();
	} );

	test( 'confirmation requested on submit', () => {
		window.confirm = jest.fn();
		const dispute = {
			id: 'dp_asdfghjkl',
			amount: 1000,
			created: 1572590800,
			evidence: {
				// eslint-disable-next-line camelcase
				customer_purchase_ip: '127.0.0.1',
				// eslint-disable-next-line camelcase
				uncategorized_text: '',
			},
			// eslint-disable-next-line camelcase
			evidence_details: {
				// eslint-disable-next-line camelcase
				due_by: 1573199200,
			},
			reason: 'fraudulent',
			status: 'needs_response',
		};

		// We have to mount component to select button for click.
		const form = mount(
			<DisputeEvidenceForm
				evidence={ dispute.evidence }
				showPlaceholder={ false }
				readOnly={ false }
				onSave={ jest.fn() }
			/>
		);
		const submitButton = form.find( 'button.is-primary' );
		submitButton.simulate( 'click' );
		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect( window.confirm ).toHaveBeenCalledWith( "Are you sure you're ready to submit this evidence ?" );
	}
	);

	test( 'onSave called after confirmation only', () => {
		const dispute = {
			id: 'dp_asdfghjkl',
			amount: 1000,
			created: 1572590800,
			evidence: {
				// eslint-disable-next-line camelcase
				customer_purchase_ip: '127.0.0.1',
				// eslint-disable-next-line camelcase
				uncategorized_text: '',
			},
			// eslint-disable-next-line camelcase
			evidence_details: {
				// eslint-disable-next-line camelcase
				due_by: 1573199200,
			},
			reason: 'fraudulent',
			status: 'needs_response',
		};

		const onSave = jest.fn();

		// We have to mount component to select button for click.
		const form = mount(
			<DisputeEvidenceForm
				evidence={ dispute.evidence }
				showPlaceholder = { false }
				readOnly = { false }
				onSave = { onSave }
			/>
		);
		const submitButton = form.find( 'button.is-primary' );

		window.confirm = jest.fn();
		window.confirm
		.mockReturnValueOnce( true )
		.mockReturnValueOnce( false );

		// Test confirmed case.
		submitButton.simulate( 'click' );
		expect( onSave ).toHaveBeenCalledTimes( 1 );

		// Test cancelled case.
		submitButton.simulate( 'click' );
		expect( onSave ).toHaveBeenCalledTimes( 1 );
	} );
} );
