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
import '../style.scss';
import { OnboardingSidebar } from './sidebar';
import { OnboardingContextProvider } from '../context';
import BusinessDetails from '../steps/business-details';

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
	const initialData = {
		business_name: wcSettings?.siteTitle,
		mcc: '12312',
		url: 'https://wcpay.test',
		country: 'US',
	};

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
										element={
											<div className="woocommerce-woopayments-onboarding-modal__content-wrapper">
												<OnboardingContextProvider
													initialData={ initialData }
												>
													<BusinessDetails />
												</OnboardingContextProvider>
											</div>
										}
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
