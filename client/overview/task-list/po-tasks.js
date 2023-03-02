/** @format **/

/**
 * External dependencies
 */
import moment from 'moment';

/**
 * Internal dependencies.
 */
import { isInTestMode } from 'utils';
import strings from './strings';

const PROGRESSIVE_ONBOARDING_KYC_TPV_LIMIT = 5000;

export const getVerifyBankAccountTask = ( {
	poEnabled,
	tpv,
	firstPaymentDate,
} ) => {
	if ( ! poEnabled ) {
		return null;
	}
	if ( ! firstPaymentDate ) {
		return null;
	}
	let timefromFirstPayment;
	const dueDate = moment( firstPaymentDate )
		.add( 30, 'days' )
		.format( 'MMMM D, YYYY' );
	if ( isInTestMode() ) {
		// TODO GH-4748 maybe add check if it's proxied automattician or even delete it after testing
		timefromFirstPayment = moment().diff( firstPaymentDate, 'minutes' );
	} else {
		timefromFirstPayment = moment().diff( firstPaymentDate, 'days' );
	}
	let title = strings.po_tasks.after_payment.title;
	let level = 3;
	let description = strings.po_tasks.after_payment.description( dueDate );
	let actionLabelText = strings.po_tasks.after_payment.action_label;

	// balance is rising
	if (
		PROGRESSIVE_ONBOARDING_KYC_TPV_LIMIT * 0.2 <= tpv ||
		7 <= timefromFirstPayment
	) {
		title = strings.po_tasks.balance_rising.title;
		level = 2;
		description = strings.po_tasks.balance_rising.description( dueDate );
		actionLabelText = strings.po_tasks.balance_rising.action_label;
	}
	// near threshold
	if (
		PROGRESSIVE_ONBOARDING_KYC_TPV_LIMIT * 0.6 <= tpv ||
		21 <= timefromFirstPayment
	) {
		title = strings.po_tasks.near_threshold.title;
		level = 1;
		description = strings.po_tasks.near_threshold.description( dueDate );
		actionLabelText = strings.po_tasks.near_threshold.action_label;
	}
	// threshold reached
	if (
		PROGRESSIVE_ONBOARDING_KYC_TPV_LIMIT <= tpv ||
		30 <= timefromFirstPayment
	) {
		title = strings.po_tasks.threshold_reached.title;
		level = 1;
		description = strings.po_tasks.threshold_reached.description( dueDate );
		actionLabelText = strings.po_tasks.threshold_reached.action_label;
	}

	return {
		key: 'verify-bank-details-po',
		level: level,
		title: title,
		content: description,
		completed: false,
		onClick: () => {
			// TODO GH-4748 change the link to the one that leads to Stripe KYC with bank details.
			window.open( '#', '_blank' );
		},
		actionLabel: actionLabelText,
		visible: true,
		expandable: true,
		expanded: true,
		showActionButton: true,
		time: '2 minutes',
	};
};
