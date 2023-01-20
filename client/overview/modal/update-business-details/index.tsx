/**
 * External dependencies
 */
import React from 'react';
import { Button, Modal, Notice } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './index.scss';
import strings from './strings';
import { useState } from '@wordpress/element';
import { dateI18n } from '@wordpress/date';
import { sprintf } from '@wordpress/i18n';
import moment from 'moment/moment';

interface Props {
	errorMessages: Array< string >;
	accountStatus: string;
	accountLink: string;
	currentDeadline?: number | null;
}

const UpdateBusinessDetailsModal = ( {
	errorMessages,
	accountStatus,
	accountLink,
	currentDeadline,
}: Props ): any => {
	const [ isModalOpen, setModalOpen ] = useState( true );

	const closeModal = () => {
		setModalOpen( false );
	};

	const openAccountLink = () => {
		window.open( accountLink, '_blank' );
	};

	return (
		<>
			{ isModalOpen && (
				<Modal
					title={ strings.updateBusinessDetails }
					isDismissible={ true }
					className="wcpay-update-business-details-modal"
					shouldCloseOnClickOutside={ false }
					onRequestClose={ closeModal }
				>
					<div className="wcpay-update-business-details-modal__wrapper">
						<div className="wcpay-update-business-details-modal__body">
							<p>
								{ accountStatus === 'restricted_soon' &&
								currentDeadline ? (
									<>
										<p>
											{
												strings.restrictedSoonDescription
											}
										</p>
										<p>
											{ sprintf(
												strings.restrictedSoonUpdateBy,
												dateI18n(
													'ga M j, Y',
													moment(
														currentDeadline * 1000
													).toISOString()
												)
											) }
										</p>
									</>
								) : (
									<p>{ strings.restrictedDescription }</p>
								) }
							</p>

							{ errorMessages.map( ( errorMessage, index ) => (
								<Notice
									key={ index }
									status="warning"
									isDismissible={ false }
								>
									{ errorMessage }
								</Notice>
							) ) }
						</div>
					</div>
					<hr />
					<div className="wcpay-update-business-details-modal__footer">
						<Button isSecondary onClick={ closeModal }>
							{ strings.cancel }
						</Button>

						<Button isPrimary onClick={ openAccountLink }>
							{ strings.updateBusinessDetails }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export default UpdateBusinessDetailsModal;
