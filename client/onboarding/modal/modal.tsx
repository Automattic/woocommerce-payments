/**
 * External dependencies
 */
import React from 'react';
import { Card, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { BrowserRouter, Route, Routes } from 'react-router-dom';

/**
 * Internal dependencies
 */
import './modal.scss';
import { OnboardingSidebar } from './sidebar';
import OnboardingKycPage from '../kyc';

interface OnboardingModalProps {
	/**
	 * Indicates if the modal is currently open.
	 */
	isOpen: boolean;
	/**
	 * Callback function to handle modal closure.
	 */
	onClose: () => void;
}

export const OnboardingModal = ( {
	isOpen,
	onClose,
}: OnboardingModalProps ) => {
	return (
		<>
			{ isOpen && (
				<Modal
					title=""
					className="woocommerce-woopayments-onboarding-modal"
					onRequestClose={ onClose }
				>
					<div className="woocommerce-woopayments-onboarding-modal__wrapper">
						<BrowserRouter basename={ window.location.pathname }>
							<OnboardingSidebar />
							<Card
								className={
									'woocommerce-woopayments-onboarding-modal__content'
								}
							>
								<Routes>
									<Route
										path="/"
										element={ <OnboardingKycPage /> }
									/>
									<Route
										path="/payment-methods"
										element={ <>Payment Methods</> }
									/>
								</Routes>
							</Card>
						</BrowserRouter>
					</div>
				</Modal>
			) }
		</>
	);
};
