/** @format **/

/**
 * External dependencies
 */
import React, { useState } from 'react';
import { Button, Modal, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import wcpayTracks from 'tracks';
import TipBox from 'components/tip-box';
import strings from './strings';
import './style.scss';

const renderList = ( list: Array< string > ) =>
	list.map( ( item: string ) => {
		return <li key={ item }>{ item }</li>;
	} );

const InfoNoticeModal: React.FC = () => {
	const [ isModalOpen, setModalOpen ] = useState( false );

	const handleModalClose = () => setModalOpen( false );

	return (
		<>
			<TipBox color="gray">
				{ strings.infoNotice.description }{ ' ' }
				<Button
					variant="link"
					onClick={ () => {
						wcpayTracks.recordEvent(
							wcpayTracks.events.CONNECT_ACCOUNT_KYC_MODAL_OPENED
						);
						setModalOpen( true );
					} }
				>
					{ strings.infoNotice.button }
				</Button>
			</TipBox>
			{ isModalOpen && (
				<Modal
					title={ strings.infoModal.title }
					isDismissible={ true }
					shouldCloseOnClickOutside={ true }
					shouldCloseOnEsc={ true }
					onRequestClose={ handleModalClose }
					className="connect-account-page__info-modal"
				>
					<div>
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
						<Notice status="warning" isDismissible={ false }>
							<b>
								{ strings.infoModal.businessOwnerInfo.heading }
							</b>
							<ul>
								{ renderList(
									strings.infoModal.businessOwnerInfo.fields
								) }
							</ul>
						</Notice>

						<Notice status="warning" isDismissible={ false }>
							<b>{ strings.infoModal.businessInfo.heading }</b>
							<ul>
								{ renderList(
									strings.infoModal.businessInfo.fields
								) }
							</ul>
						</Notice>
					</div>
					<hr />
					<div className="connect-account-page__info-modal__footer">
						<Button variant="primary" onClick={ handleModalClose }>
							{ __( 'Got it', 'woocommerce-payments' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default InfoNoticeModal;
