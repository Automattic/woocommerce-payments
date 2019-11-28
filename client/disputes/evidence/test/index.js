/** @format */
/**
 * External dependencies
 */
import { shallow } from 'enzyme';

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
                customer_purchase_ip: '127.0.0.1',
                uncategorized_text: '',
            },
            evidence_details: {
                due_by: 1573199200,
            },
            reason: 'fraudulent',
            status: 'needs_response',
        };

		const form = shallow(
			<DisputeEvidenceForm
                evidence={ dispute.evidence }
                showPlaceholder={ false }
                // onChange
                // onSave
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
                customer_purchase_ip: '127.0.0.1',
                uncategorized_text: 'winning_evidence',
            },
            evidence_details: {
                due_by: 1573099200,
            },
            reason: 'general',
            status: 'under_review',
        };

		const form = shallow(
			<DisputeEvidenceForm
                evidence={ dispute.evidence }
                showPlaceholder={ false }
                // onChange
                // onSave
                readOnly={ false }
			/>
		);
		expect( form ).toMatchSnapshot();
	} );
} );
