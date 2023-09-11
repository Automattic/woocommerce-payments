/** @format **/

/**
 * External dependencies
 */
import moment from 'moment';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies.
 */
import strings from '../strings';

const tpvLimit = 5000;

// TODO: We can update this to return TaskItem component, but currently there are some type inconsistencies to work out.
export const getVerifyBankAccountTask = (): any => {
	const {
		status,
		progressiveOnboarding: {
			isEnabled: poEnabled,
			isComplete: poComplete,
			tpv,
			firstTransactionDate: firstPaymentDate,
		},
		created: createdDate,
	} = wcpaySettings.accountStatus;

	const handleClick = () => {
		window.location.href = addQueryArgs( wcpaySettings.connectUrl, {
			collect_payout_requirements: true,
		} );
	};

	if ( ! poEnabled || poComplete || ! createdDate ) {
		return null;
	}

	if (
		status === 'pending' ||
		status === 'complete' ||
		status === 'enabled'
	) {
		return null;
	}

	let title,
		level = 3, // 3 is the default level for tasks.
		description,
		actionLabelText,
		verifyDetailsDueDate;

	if ( ! firstPaymentDate ) {
		verifyDetailsDueDate = moment( createdDate )
			.add( 30, 'days' )
			.format( 'MMMM D, YYYY' );
		const daysFromAccountCreation = moment().diff( createdDate, 'days' );

		// When account is created less than 14 days ago, we also show a notice but it's just info.
		if ( 14 > daysFromAccountCreation ) {
			title = strings.tasks.after_payment.title;
			level = 3;
			description = strings.tasks.after_payment.description(
				verifyDetailsDueDate
			);
			actionLabelText = strings.tasks.after_payment.action_label;
		}

		if ( 14 <= daysFromAccountCreation ) {
			title = strings.tasks.no_payment_14_days.title;
			level = 2;
			description = strings.tasks.no_payment_14_days.description(
				verifyDetailsDueDate
			);
			actionLabelText = strings.tasks.no_payment_14_days.action_label;
		}
		if ( 30 <= daysFromAccountCreation ) {
			title = strings.tasks.no_payment_30_days.title;
			level = 1;
			description = strings.tasks.no_payment_30_days.description;
			actionLabelText = strings.tasks.no_payment_30_days.action_label;
		}
	} else {
		const tpvInUsd = tpv / 100;
		verifyDetailsDueDate = moment( firstPaymentDate )
			.add( 30, 'days' )
			.format( 'MMMM D, YYYY' );
		const daysFromFirstPayment = moment().diff( firstPaymentDate, 'days' );

		title = strings.tasks.after_payment.title;
		level = 3;
		description = strings.tasks.after_payment.description(
			verifyDetailsDueDate
		);
		actionLabelText = strings.tasks.after_payment.action_label;

		// Balance is rising.
		if ( tpvLimit * 0.2 <= tpvInUsd || 7 <= daysFromFirstPayment ) {
			title = strings.tasks.balance_rising.title;
			level = 2;
			description = strings.tasks.balance_rising.description(
				verifyDetailsDueDate
			);
			actionLabelText = strings.tasks.balance_rising.action_label;
		}
		// Near threshold.
		if ( tpvLimit * 0.6 <= tpvInUsd || 21 <= daysFromFirstPayment ) {
			title = strings.tasks.near_threshold.title;
			level = 1;
			description = strings.tasks.near_threshold.description(
				verifyDetailsDueDate
			);
			actionLabelText = strings.tasks.near_threshold.action_label;
		}
		// Threshold reached.
		if ( tpvLimit <= tpvInUsd || 30 <= daysFromFirstPayment ) {
			title = strings.tasks.threshold_reached.title;
			level = 1;
			description = strings.tasks.threshold_reached.description(
				verifyDetailsDueDate
			);
			actionLabelText = strings.tasks.threshold_reached.action_label;
		}
	}

	return {
		key: 'verify-bank-details-po',
		title: title,
		content: description,
		level: level,
		completed: false,
		expanded: true,
		expandable: true,
		isDismissable: false,
		showActionButton: true,
		action: handleClick,
		onClick: handleClick,
		actionLabel: actionLabelText,
		visible: true,
		time: '2 minutes',
	};
};
