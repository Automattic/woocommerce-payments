/** @format **/

/**
 * External dependencies
 */
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { renderUpdateBusinessDetailsModal } from './update-business-details-modal-loader';
import { recordTaskEvent } from './record-task-event';

const getAdminUrl = ( args ) => addQueryArgs( 'admin.php', args );

const runUpdateBusinessDetailsTaskAction = ( {
	status,
	hasMultipleErrors,
	detailsSubmitted,
	accountLink,
	requirementErrors,
	currentDeadline,
} ) => {
	if ( status === 'complete' || status === 'enabled' ) {
		return;
	}

	if ( hasMultipleErrors ) {
		renderUpdateBusinessDetailsModal( {
			requirementErrors,
			status,
			accountLink,
			currentDeadline,
		} );
		return;
	}

	let source = 'wcpay-update-business-details-task';
	if ( ! detailsSubmitted ) {
		source = 'wcpay-finish-setup-task';
	}
	recordTaskEvent( 'wcpay_account_details_link_clicked', {
		source,
	} );

	// If the onboarding isn't complete redirect to the NOX onboarding page.
	if ( ! detailsSubmitted ) {
		window.location.href = getAdminUrl( {
			page: 'wc-settings',
			tab: 'checkout',
			path: '/woopayments/onboarding',
			source: 'wcpay-finish-setup-task',
			from: 'WCPAY_OVERVIEW',
		} );
	} else {
		window.open(
			addQueryArgs( accountLink, {
				from: 'WCPAY_OVERVIEW',
				source: 'wcpay-update-business-details-task',
			} ),
			'_blank'
		);
	}
};

export default runUpdateBusinessDetailsTaskAction;
