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

export const HighFraudProtectionModal = ( {
	level,
	isHighModalOpen,
	setHighModalOpen,
	storeCurrency,
} ) => {
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
							{ __(
								'Payments will be ',
								'woocommerce-payments'
							) }
							<span className="component-modal__text--blocked">
								{ __( 'blocked ', 'woocommerce-payments' ) }
							</span>
							{ __( 'if: ', 'woocommerce-payments' ) }
						</p>
						<ul>
							<li>
								{ __(
									'The billing address does not match what is on file with the card issuer.',
									'woocommerce-payments'
								) }
							</li>
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
							<li>
								{ __(
									'The same card or IP address submits ',
									'woocommerce-payments'
								) }{ ' ' }
								<strong>
									{ __(
										'5 orders ',
										'woocommerce-payments'
									) }
								</strong>{ ' ' }
								{ __( 'within ', 'woocommerce-payments' ) }{ ' ' }
								<strong>
									{ __(
										'72 hours.',
										'woocommerce-payments'
									) }
								</strong>
							</li>
						</ul>
						<p>
							{ __(
								'Payments will be ',
								'woocommerce-payments'
							) }
							<span className="component-modal__text--review">
								{ __(
									'authorized and held for review ',
									'woocommerce-payments'
								) }
							</span>
							{ __( 'if:', 'woocommerce-payments' ) }
						</p>
						<ul>
							<li>
								{ __(
									"The card's issuing bank cannot verify the CVV.",
									'woocommerce-payments'
								) }
							</li>
							<li>
								{ __(
									'An order has less than ',
									'woocommerce-payments'
								) }{ ' ' }
								<strong>
									{ __( '2 items ', 'woocommerce-payments' ) }
								</strong>{ ' ' }
								{ __(
									'or more than ',
									'woocommerce-payments'
								) }{ ' ' }
								<strong>
									{ __(
										'10 items.',
										'woocommerce-payments'
									) }
								</strong>
							</li>
							<li>
								{ __(
									"The shipping and billing addresses don't match.",
									'woocommerce-payments'
								) }
							</li>
							<li>
								{ __(
									'An order is shipping or billing to a non-domestic address.',
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
						<p>
							{ __(
								'Payments will be ',
								'woocommerce-payments'
							) }
							<span className="component-modal__text--blocked">
								{ __( 'blocked ', 'woocommerce-payments' ) }
							</span>
							{ __( 'if: ', 'woocommerce-payments' ) }
						</p>
						<ul>
							<li>
								{ __(
									'The billing address does not match what is on file with the card issuer.',
									'woocommerce-payments'
								) }
							</li>
						</ul>
						<p>
							{ __(
								'Payments will be ',
								'woocommerce-payments'
							) }
							<span className="component-modal__text--review">
								{ __(
									'authorized and held for review ',
									'woocommerce-payments'
								) }
							</span>
							{ __( 'if:', 'woocommerce-payments' ) }
						</p>
						<ul>
							<li>
								{ __(
									"The card's issuing bank cannot verify the CVV.",
									'woocommerce-payments'
								) }
							</li>
							<li>
								{ __(
									'An order originates from an IP address outside your country.',
									'woocommerce-payments'
								) }
							</li>
							<ExceedsDollarAmountRule
								level={ level }
								storeCurrency={ storeCurrency }
							/>
							<li>
								{ __(
									'The same card or IP address submits',
									'woocommerce-payments'
								) }{ ' ' }
								<strong>{ __( '5 orders' ) }</strong>{ ' ' }
								{ __( 'within' ) }{ ' ' }
								<strong>{ __( '72 hours.' ) }</strong>
							</li>
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
