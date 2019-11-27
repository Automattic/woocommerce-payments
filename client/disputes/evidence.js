/** @format **/

/**
 * External dependencies
 */
import { Button } from '@wordpress/components';
import { Card } from '@woocommerce/components';

export const DisputeEvidenceForm = props => {
    return (
        <div>
            <Card>
                <Button isPrimary>Submit Evidence</Button>
                <Button isDefault>Save For Later</Button>
            </Card>
        </div>
    );
};

export default DisputeEvidenceForm;
