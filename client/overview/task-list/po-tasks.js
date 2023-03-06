/** @format **/

/**
 * External dependencies
 */
import moment from 'moment';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies.
 */
import strings from './strings';

const PROGRESSIVE_ONBOARDING_KYC_TPV_LIMIT = 5000;

export const getVerifyBankAccountTask = ( {
	poEnabled,
	poComplete,
	tpv,
	firstPaymentDate,
	createdDate,
} ) => {
	if ( ! poEnabled || poComplete || ! createdDate ) {
		return null;
	}

	let title, level, description, actionLabelText, verifyDetailsDueDate;

	if ( ! firstPaymentDate ) {
		verifyDetailsDueDate = moment( createdDate )
			.add( 30, 'days' )
			.format( 'MMMM D, YYYY' );
		const daysFromAccountCreation = moment().diff( createdDate, 'days' );

		// when account is created less than 14 days ago, no need to show any notice
		if ( 14 > daysFromAccountCreation ) {
			return null;
		}

		if ( 14 <= daysFromAccountCreation ) {
			title = strings.po_tasks.no_payment_14_days.title;
			level = 2;
			description = strings.po_tasks.no_payment_14_days.description(
				verifyDetailsDueDate
			);
			actionLabelText = strings.po_tasks.no_payment_14_days.action_label;
		}
		if ( 30 <= daysFromAccountCreation ) {
			title = strings.po_tasks.no_payment_30_days.title;
			level = 1;
			description = strings.po_tasks.no_payment_30_days.description;
			actionLabelText = strings.po_tasks.no_payment_30_days.action_label;
		}
	} else {
		verifyDetailsDueDate = moment( firstPaymentDate )
			.add( 30, 'days' )
			.format( 'MMMM D, YYYY' );
		const daysFromFirstPayment = moment().diff( firstPaymentDate, 'days' );

		title = strings.po_tasks.after_payment.title;
		level = 3;
		description = strings.po_tasks.after_payment.description(
			verifyDetailsDueDate
		);
		actionLabelText = strings.po_tasks.after_payment.action_label;

		// balance is rising
		if (
			PROGRESSIVE_ONBOARDING_KYC_TPV_LIMIT * 0.2 <= tpv ||
			7 <= daysFromFirstPayment
		) {
			title = strings.po_tasks.balance_rising.title;
			level = 2;
			description = strings.po_tasks.balance_rising.description(
				verifyDetailsDueDate
			);
			actionLabelText = strings.po_tasks.balance_rising.action_label;
		}
		// near threshold
		if (
			PROGRESSIVE_ONBOARDING_KYC_TPV_LIMIT * 0.6 <= tpv ||
			21 <= daysFromFirstPayment
		) {
			title = strings.po_tasks.near_threshold.title;
			level = 1;
			description = strings.po_tasks.near_threshold.description(
				verifyDetailsDueDate
			);
			actionLabelText = strings.po_tasks.near_threshold.action_label;
		}
		// threshold reached
		if (
			PROGRESSIVE_ONBOARDING_KYC_TPV_LIMIT <= tpv ||
			30 <= daysFromFirstPayment
		) {
			title = strings.po_tasks.threshold_reached.title;
			level = 1;
			description = strings.po_tasks.threshold_reached.description(
				verifyDetailsDueDate
			);
			actionLabelText = strings.po_tasks.threshold_reached.action_label;
		}
	}

	return {
		key: 'verify-bank-details-po',
		level: level,
		title: title,
		content: description,
		completed: false,
		onClick: () => {
			window.location = addQueryArgs( wcpaySettings.connectUrl, {
				collect_payout_requirements: true,
			} );
		},
		actionLabel: actionLabelText,
		visible: true,
		expandable: true,
		expanded: true,
		showActionButton: true,
		time: '2 minutes',
	};
};
