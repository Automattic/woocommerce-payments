/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';

/**
 * Internal dependencies
 */
import ExceedsDollarAmountRule from '../exceeds-dollar-amount-rule';
import ProtectionLevelModalNotice from '../protection-level-modal-notice';
import interpolateComponents from '@automattic/interpolate-components';

export const HighFraudProtectionModal = ( {
	level,
	isHighModalOpen,
	setHighModalOpen,
	storeCurrency,
} ) => {
	const { declineOnAVSFailure, declineOnCVCFailure } = window.wcpaySettings
		?.accountStatus?.fraudProtection ?? {
		declineOnAVSFailure: true,
		declineOnCVCFailure: true,
	};

	return (
		<>
			{ isHighModalOpen && (
				<Modal
					title={ __( 'High filter level', 'woocommerce-payments' ) }
					isDismissible={ true }
					shouldCloseOnClickOutside={ true }
					shouldCloseOnEsc={ true }
					onRequestClose={ () => setHighModalOpen( false ) }
					className="fraud-protection-level-modal"
				>
					<div className="components-modal__body--fraud-protection">
						<ProtectionLevelModalNotice level={ level } />
						<p>
							{ interpolateComponents( {
								mixedString: __(
									'Payments will be {{blocked}}blocked{{/blocked}} if:',
									'woocommerce-payments'
								),
								components: {
									blocked: (
										<span className="component-modal__text--blocked" />
									),
								},
							} ) }
						</p>
						<ul>
							{ declineOnAVSFailure && (
								<li>
									{ __(
										'The billing address does not match what is on file with the card issuer.',
										'woocommerce-payments'
									) }
								</li>
							) }
							{ declineOnCVCFailure && (
								<li>
									{ __(
										"The card's issuing bank cannot verify the CVV.",
										'woocommerce-payments'
									) }
								</li>
							) }
							<li>
								{ __(
									'An order originates from an IP address outside your country',
									'woocommerce-payments'
								) }
							</li>
							<ExceedsDollarAmountRule
								level={ level }
								storeCurrency={ storeCurrency }
							/>
						</ul>
						<p>
							{ interpolateComponents( {
								mixedString: __(
									'Payments will be {{review}}authorized and held for review{{/review}} if:',
									'woocommerce-payments'
								),
								components: {
									review: (
										<span className="component-modal__text--review" />
									),
								},
							} ) }
						</p>
						<ul>
							<li>
								{ interpolateComponents( {
									mixedString: __(
										'An order has less than {{strong}}2 items{{/strong}} or more than {{strong}}10 items.{{/strong}}',
										'woocommerce-payments'
									),
									components: { strong: <strong /> },
								} ) }
							</li>
							<li>
								{ __(
									"The shipping and billing addresses don't match.",
									'woocommerce-payments'
								) }
							</li>
							<li>
								{ __(
									"The billing address country doesn't match the country resolved from the IP address.",
									'woocommerce-payments'
								) }
							</li>
						</ul>
						<Button
							className="component-modal__button--confirm"
							onClick={ () => setHighModalOpen( false ) }
							isTertiary
						>
							{ __( 'Got it', 'woocommerce-payments' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export const StandardFraudProtectionModal = ( {
	level,
	isStandardModalOpen,
	setStandardModalOpen,
	storeCurrency,
} ) => {
	const { declineOnAVSFailure, declineOnCVCFailure } = window.wcpaySettings
		?.accountStatus?.fraudProtection ?? {
		declineOnAVSFailure: true,
		declineOnCVCFailure: true,
	};

	const hasActivePlatformChecks = declineOnAVSFailure || declineOnCVCFailure;
	return (
		<>
			{ isStandardModalOpen && (
				<Modal
					title={ __(
						'Standard filter level',
						'woocommerce-payments'
					) }
					isDismissible={ true }
					shouldCloseOnClickOutside={ true }
					shouldCloseOnEsc={ true }
					onRequestClose={ () => setStandardModalOpen( false ) }
					className="fraud-protection-level-modal"
				>
					<div className="components-modal__body--fraud-protection">
						<ProtectionLevelModalNotice level={ level } />
						{ hasActivePlatformChecks && (
							<>
								<p>
									{ interpolateComponents( {
										mixedString: __(
											'Payments will be {{blocked}}blocked{{/blocked}} if:',
											'woocommerce-payments'
										),
										components: {
											blocked: (
												<span className="component-modal__text--blocked" />
											),
										},
									} ) }
								</p>
								<ul>
									{ declineOnAVSFailure && (
										<li>
											{ __(
												'The billing address does not match what is on file with the card issuer.',
												'woocommerce-payments'
											) }
										</li>
									) }
									{ declineOnCVCFailure && (
										<li>
											{ __(
												"The card's issuing bank cannot verify the CVV.",
												'woocommerce-payments'
											) }
										</li>
									) }
								</ul>
							</>
						) }
						<p>
							{ interpolateComponents( {
								mixedString: __(
									'Payments will be {{review}}authorized and held for review{{/review}} if:',
									'woocommerce-payments'
								),
								components: {
									review: (
										<span className="component-modal__text--review" />
									),
								},
							} ) }
						</p>
						<ul>
							<li>
								{ __(
									'An order originates from an IP address outside your country.',
									'woocommerce-payments'
								) }
							</li>
							<li>
								{ __(
									"The billing address country doesn't match the country resolved from the IP address.",
									'woocommerce-payments'
								) }
							</li>
							<ExceedsDollarAmountRule
								level={ level }
								storeCurrency={ storeCurrency }
							/>
						</ul>
						<Button
							className="component-modal__button--confirm"
							onClick={ () => setStandardModalOpen( false ) }
							isTertiary
						>
							{ __( 'Got it', 'woocommerce-payments' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};

export const BasicFraudProtectionModal = ( {
	level,
	isBasicModalOpen,
	setBasicModalOpen,
} ) => {
	const { declineOnAVSFailure, declineOnCVCFailure } = window.wcpaySettings
		?.accountStatus?.fraudProtection ?? {
		declineOnAVSFailure: true,
		declineOnCVCFailure: true,
	};

	const hasActivePlatformChecks = declineOnAVSFailure || declineOnCVCFailure;
	return (
		<>
			{ isBasicModalOpen && (
				<Modal
					title={ __(
						'Standard filter level',
						'woocommerce-payments'
					) }
					isDismissible={ true }
					shouldCloseOnClickOutside={ true }
					shouldCloseOnEsc={ true }
					onRequestClose={ () => setBasicModalOpen( false ) }
					className="fraud-protection-level-modal"
				>
					<div className="components-modal__body--fraud-protection">
						<ProtectionLevelModalNotice level={ level } />
						{ hasActivePlatformChecks && (
							<>
								<p>
									{ interpolateComponents( {
										mixedString: __(
											'Payments will be {{blocked}}blocked{{/blocked}} if:',
											'woocommerce-payments'
										),
										components: {
											blocked: (
												<span className="component-modal__text--blocked" />
											),
										},
									} ) }
								</p>
								<ul>
									{ declineOnAVSFailure && (
										<li>
											{ __(
												'The billing address does not match what is on file with the card issuer.',
												'woocommerce-payments'
											) }
										</li>
									) }
									{ declineOnCVCFailure && (
										<li>
											{ __(
												"The card's issuing bank cannot verify the CVV.",
												'woocommerce-payments'
											) }
										</li>
									) }
								</ul>
							</>
						) }
						<Button
							className="component-modal__button--confirm"
							onClick={ () => setBasicModalOpen( false ) }
							isTertiary
						>
							{ __( 'Got it', 'woocommerce-payments' ) }
						</Button>
					</div>
				</Modal>
			) }
		</>
	);
};
