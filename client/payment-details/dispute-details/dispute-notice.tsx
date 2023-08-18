/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';
import NoticeOutlineIcon from 'gridicons/dist/notice-outline';
/**
 * Internal dependencies
 */
import './style.scss';
import BannerNotice from 'components/banner-notice';
import { reasons } from 'wcpay/disputes/strings';
import { Dispute } from 'wcpay/types/disputes';
import { isInquiry } from 'wcpay/disputes/utils';

interface DisputeNoticeProps {
	dispute: Dispute;
	urgent: boolean;
}

const DisputeNotice: React.FC< DisputeNoticeProps > = ( {
	dispute,
	urgent,
} ) => {
	const clientClaim =
		reasons[ dispute.reason ]?.claim ??
		__(
			'The cardholder claims this is an unrecognized charge.',
			'woocommerce-payments'
		);

	const noticeText = isInquiry( dispute )
		? /* translators: %s is the clients claim for the dispute, eg "The cardholder claims this is an unrecognized charge." */
		  __(
				// eslint-disable-next-line max-len
				'<strong>%s</strong> Challenge the inquiry if you believe the claim is invalid. Non-response will result in an automatic loss. <a>Learn more about responding to disputes and inquiries</a>',
				'woocommerce-payments'
		  )
		: /* translators: %s is the clients claim for the dispute, eg "The cardholder claims this is an unrecognized charge." */
		  __(
				// eslint-disable-next-line max-len
				'<strong>%s</strong> Challenge the dispute if you believe the claim is invalid, or accept to forfeit the funds and pay the dispute fee. Non-response will result in an automatic loss. <a>Learn more about responding to disputes</a>',
				'woocommerce-payments'
		  );

	return (
		<BannerNotice
			status={ urgent ? 'error' : 'warning' }
			icon={ <NoticeOutlineIcon /> }
			className="dispute-notice"
			isDismissible={ false }
		>
			{ createInterpolateElement( sprintf( noticeText, clientClaim ), {
				a: (
					// eslint-disable-next-line jsx-a11y/anchor-has-content
					<a
						target="_blank"
						rel="noopener noreferrer"
						href="https://woocommerce.com/document/woopayments/fraud-and-disputes/managing-disputes/#section-3"
					/>
				),
				strong: <strong />,
			} ) }
		</BannerNotice>
	);
};

export default DisputeNotice;
