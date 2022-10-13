/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { Button, Modal, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import './style.scss';
import strings from '../strings';

const OnboardingMoreInfoModal = (): JSX.Element => {
	// Declare state attributes
	const [ isModalOpen, setModalOpen ] = useState( true );
	const [ isProcessingContinue ] = useState( false );

	// Declare hooks to handle button clicks
	const handleModalClose = () => {
		setModalOpen( false );
	};

	const renderList = ( list: Array< string > ) =>
		list.map( ( item: string ) => {
			return <li key={ item }>{ item }</li>;
		} );

	const title = __(
		'Verifying your information with WooCommerce Payments',
		'woocommerce-payments'
	);

	return (
		<Modal
			title={ title }
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
				<div className="woocommerce-payments__onboarding_more_info-footer">
					<Button
						isPrimary
						onClick={ handleModalClose }
						isBusy={ isProcessingContinue }
					>
						{ __( 'Got it', 'woocommerce-payments' ) }
					</Button>
				</div>
			</div>
		</Modal>
	);
};

export default OnboardingMoreInfoModal;
