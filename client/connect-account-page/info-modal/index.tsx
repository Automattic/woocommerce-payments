/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { Button, Modal, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './style.scss';
import strings from '../strings';

interface Props {
	handleModalClose: () => void;
}

const OnboardingMoreInfoModal: React.FunctionComponent< Props > = ( {
	handleModalClose,
} ) => {
	const renderList = ( list: Array< string > ) =>
		list.map( ( item: string ) => {
			return <li key={ item }>{ item }</li>;
		} );

	return (
		<Modal
			title={ strings.infoModal.title }
			isDismissible={ true }
			shouldCloseOnClickOutside={ true }
			shouldCloseOnEsc={ true }
			onRequestClose={ handleModalClose }
			className="woocommerce-payments__onboarding_more_info-modal"
		>
			<div className="woocommerce-payments__onboarding_more_info-wrapper">
				<div className="woocommerce-payments__onboarding_more_info-message">
					<h4>{ strings.infoModal.whyWeAsk.heading } </h4>
					<p>{ strings.infoModal.whyWeAsk.description }</p>
					<h4>{ strings.infoModal.whatIsKyc.heading } </h4>
					<p>{ strings.infoModal.whatIsKyc.description }</p>
					<h4>{ strings.infoModal.whyShareInfo.heading } </h4>
					<p>{ strings.infoModal.whyShareInfo.description }</p>
					<p>{ strings.infoModal.whyShareInfo.description2 }</p>
					<h4>{ strings.infoModal.whatElse.heading } </h4>
					<p>{ strings.infoModal.whatElse.description }</p>
					<h4>{ strings.infoModal.isMyDataSafe.heading } </h4>
					<p>{ strings.infoModal.isMyDataSafe.description }</p>
					<h4>{ strings.infoModal.howQuickly.heading } </h4>
					<p>{ strings.infoModal.howQuickly.description }</p>
					<h4>{ strings.infoModal.whatInformation.heading } </h4>
					<p>{ strings.infoModal.whatInformation.description }</p>
					<Notice
						className="wcpay-connect-warning-notice"
						status="warning"
						isDismissible={ false }
					>
						<span>
							<b>
								{ strings.infoModal.businessOwnerInfo.heading }
							</b>
						</span>
						<ul>
							{ renderList(
								strings.infoModal.businessOwnerInfo.fields
							) }
						</ul>
					</Notice>

					<Notice
						className="wcpay-connect-warning-notice"
						status="warning"
						isDismissible={ false }
					>
						<span>
							<b>{ strings.infoModal.businessInfo.heading }</b>
						</span>
						<ul>
							{ renderList(
								strings.infoModal.businessInfo.fields
							) }
						</ul>
					</Notice>
				</div>
				<hr />
				<div className="woocommerce-payments__onboarding_more_info-footer">
					<Button isPrimary onClick={ handleModalClose }>
						{ __( 'Got it', 'woocommerce-payments' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
};

export default OnboardingMoreInfoModal;
