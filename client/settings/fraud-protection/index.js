/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, Modal } from '@wordpress/components';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import { useCurrentProtectionLevel, useCurrencies } from '../../data';
import './style.scss';
// import ExceedsDollarAmountRule from './components/exceeds-dollar-amount-rule';
// import ProtectionLevelModalNotice from './components/protection-level-modal-notice';
import {
	ExceedsDollarAmountRule,
	ProtectionLevelModalNotice,
} from './components';

const ProtectionLevels = () => {
	const [ isStandardModalOpen, setStandardModalOpen ] = useState( false );
	const [ isHighModalOpen, setHighModalOpen ] = useState( false );
	const { currencies } = useCurrencies();
	const storeCurrency = currencies.default ? currencies.default : {};

	const [
		currentProtectionLevel,
		updateProtectionLevel,
	] = useCurrentProtectionLevel();

	const handleLevelChange = ( level ) => {
		updateProtectionLevel( level );
	};

	return (
		<fieldset>
			<ul>
				<li>
					<label htmlFor="fraud-protection__standard-level">
						<div className="fraud-protection-single-radio-wrapper">
							<input
								name={ 'fraud-protection-level-select' }
								id={
									'fraud-protection-level-select_standard-level'
								}
								value={ 'standard' }
								type={ 'radio' }
								checked={
									'standard' === currentProtectionLevel
								}
								onChange={ () =>
									handleLevelChange( 'standard' )
								}
							/>
							<p className="fraud-protection-single-radio-wrapper__item">
								{ __( 'Standard', 'woocommerce-payments' ) }
							</p>
							<p
								className={
									'fraud-protection-single-radio-wrapper__item--recommended'
								}
							>
								{ __(
									'(Recommended)',
									'woocommerce-payments'
								) }
							</p>
							<HelpOutlineIcon
								size={ 18 }
								title="Standard level help icon"
								onClick={ () => setStandardModalOpen( true ) }
							/>
							{ isStandardModalOpen && (
								<Modal
									title={ __(
										'Standard filter level',
										'woocommerce-payments'
									) }
									isDismissible={ true }
									shouldCloseOnClickOutside={ true }
									shouldCloseOnEsc={ true }
									onRequestClose={ () =>
										setStandardModalOpen( false )
									}
									className="fraud-protection-level-modal"
								>
									<div className="components-modal__body--fraud-protection">
										<ProtectionLevelModalNotice level="standard" />
										<p>
											{ __(
												'Payments will be ',
												'woocommerce-payments'
											) }
											<span className="component-modal__text--blocked">
												{ __(
													'blocked ',
													'woocommerce-payments'
												) }
											</span>
											{ __(
												'if: ',
												'woocommerce-payments'
											) }
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
											{ __(
												'if:',
												'woocommerce-payments'
											) }
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
												level="standard"
												storeCurrency={ storeCurrency }
											/>
											<li>
												{ __(
													'The same card or IP address submits',
													'woocommerce-payments'
												) }{ ' ' }
												<strong>
													{ __( '5 orders' ) }
												</strong>{ ' ' }
												{ __( 'within' ) }{ ' ' }
												<strong>
													{ __( '72 hours.' ) }
												</strong>
											</li>
										</ul>
										<Button
											className="component-modal__button--confirm"
											onClick={ () =>
												setStandardModalOpen( false )
											}
											isTertiary
										>
											{ __(
												'Got it',
												'woocommerce-payments'
											) }
										</Button>
									</div>
								</Modal>
							) }
						</div>
						<p className={ 'fraud-protection__text--help-text' }>
							{ __(
								"Standard protection: Provides a standard level of filtering that's suitable for most businesses.",
								'woocommerce-payments'
							) }
						</p>
					</label>
				</li>
				<li>
					<label htmlFor="fraud-protection__high-level">
						<div className="fraud-protection-single-radio-wrapper">
							<input
								name={ 'fraud-protection-level-select' }
								id={
									'fraud-protection-level-select_high-level'
								}
								value={ 'high' }
								type={ 'radio' }
								checked={ 'high' === currentProtectionLevel }
								onChange={ () => handleLevelChange( 'high' ) }
							/>
							<p className="fraud-protection-single-radio-wrapper__item">
								{ __( 'High', 'woocommerce-payments' ) }
							</p>
							<HelpOutlineIcon
								size={ 18 }
								title="High level help icon"
								onClick={ () => setHighModalOpen( true ) }
							/>
							{ isHighModalOpen && (
								<Modal
									title={ __(
										'High filter level',
										'woocommerce-payments'
									) }
									isDismissible={ true }
									shouldCloseOnClickOutside={ true }
									shouldCloseOnEsc={ true }
									onRequestClose={ () =>
										setHighModalOpen( false )
									}
									className="fraud-protection-level-modal"
								>
									<div className="components-modal__body--fraud-protection">
										<ProtectionLevelModalNotice level="high" />
										<p>
											{ __(
												'Payments will be ',
												'woocommerce-payments'
											) }
											<span className="component-modal__text--blocked">
												{ __(
													'blocked ',
													'woocommerce-payments'
												) }
											</span>
											{ __(
												'if: ',
												'woocommerce-payments'
											) }
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
												level="high"
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
												{ __(
													'within ',
													'woocommerce-payments'
												) }{ ' ' }
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
											{ __(
												'if:',
												'woocommerce-payments'
											) }
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
													{ __(
														'2 items ',
														'woocommerce-payments'
													) }
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
											onClick={ () =>
												setHighModalOpen( false )
											}
											isTertiary
										>
											{ __(
												'Got it',
												'woocommerce-payments'
											) }
										</Button>
									</div>
								</Modal>
							) }
						</div>
						<p className={ 'fraud-protection__text--help-text' }>
							{ __(
								'High protection: Offers the highest level of filtering for stores, ' +
									'but may catch some legitimate transactions.',
								'woocommerce-payments'
							) }
						</p>
					</label>
				</li>
			</ul>
		</fieldset>
	);
};

const FraudProtection = () => {
	return (
		<Card className="fraud-protection">
			<CardBody>
				<h4>{ __( 'Payment risk level', 'woocommerce-payments' ) }</h4>
				<ProtectionLevels />
			</CardBody>
		</Card>
	);
};

export default FraudProtection;
