/** @format **/

/**
 * External dependencies
 */
import { addQueryArgs } from '@wordpress/url';
import { Link } from '@woocommerce/components';

/**
 * Internal dependencies.
 */
import Chip from '../chip';
import displayStatus from './mappings';
import { formatStringValue } from '../../util';

const DisputeStatusChip = ( { dispute: { status, id: disputeId } } ) => {
	const mapping = displayStatus[ status ] || {};
	const message = mapping.message || formatStringValue( status );
	const type    = mapping.type || 'light';

	const chip = (
		<Chip message={ message } type={ type } />
	);

	// Have the status link to the evidence form, if actionable.
	// TODO this can be removed when the dispute details button is added to the row.
	if ( status.indexOf( 'needs_response' ) >= 0 ) {
		const evidenceUrl = addQueryArgs(
			'admin.php',
			{
				page: 'wc-admin',
				path: '/payments/disputes/evidence',
				id: disputeId,
			}
		);
		return (
			<Link href={ evidenceUrl }>{ chip }</Link>
		);
	}

	return chip;
};

export default DisputeStatusChip;
