/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, Modal, Notice } from '@wordpress/components';
import HelpOutlineIcon from 'gridicons/dist/help-outline';
import { useState } from '@wordpress/element';
import { SVG, Path } from '@wordpress/primitives';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import { useCurrentProtectionLevel } from '../../data';
import './style.scss';

const TipIcon = ( props ) => {
	const svgPathParts = [
		'M12 15.8c-3.7 0-6.8-3-6.8-6.8s3-6.8 6.8-6.8c3.7 0 6.8 3 6.8 6.8s-3.1 6.8-6.8 6.8zm0-12C9.1 3.8 ',
		'6.8 6.1 6.8 9s2.4 5.2 5.2 5.2c2.9 0 5.2-2.4 5.2-5.2S14.9 3.8 12 3.8zM8 17.5h8V19H8zM10 20.5h4V22h-4z',
	];

	return (
		<SVG width="24" height="24" viewBox="0 0 24 24" { ...props }>
			<Path d={ svgPathParts.join( ' ' ) } />
		</SVG>
	);
};

const StandardNotice = () => {
	return (
		<Notice
			className="component-notice--is-info"
			status="info"
			isDismissible={ false }
		>
			<div className="component-notice__content--flex">
				<TipIcon className="component-notice__icon" />
				<p>
					{ __(
						"Provides a standard level of filtering that's suitable for most business.",
						'woocommerce-payments'
					) }
				</p>
			</div>
		</Notice>
	);
};

const HighNotice = () => {
	return (
		<Notice
			className="component-notice--is-info"
			status="info"
			isDismissible={ false }
		>
			<div className="component-notice__content--flex">
				<TipIcon className="component-notice__icon" />
				<p>
					{ __(
						'Offers the highest level of filtering for stores, but may catch some legitimate transactions',
						'woocommerce-payments'
					) }
				</p>
			</div>
		</Notice>
	);
};

const ProtectionLevels = () => {
	const [ isStandardModalOpen, setStandardModalOpen ] = useState( false );
	const [ isHighModalOpen, setHighModalOpen ] = useState( false );

	const [
		currentProtectionLevel,
		updateProtectionLevel,
	] = useCurrentProtectionLevel();

	const handleLevelChange = ( level ) => {
		updateProtectionLevel( level );
	};

	return (
		<>
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
									onClick={ () =>
										setStandardModalOpen( true )
									}
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
											<StandardNotice />
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
												<li>
													{ __(
														'An order exceeds',
														'woocommerce-payments'
													) }{ ' ' }
													<strong>
														{ __(
															'$1,000',
															'woocommerce-payments'
														) }{ ' ' }
													</strong>
													{ __(
														'or',
														'woocommerce-payments'
													) }{ ' ' }
													<strong>
														{ __( '10 items.' ) }
													</strong>
												</li>
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
													setStandardModalOpen(
														false
													)
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
							<p
								className={
									'fraud-protection__text--help-text'
								}
							>
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
									checked={
										'high' === currentProtectionLevel
									}
									onChange={ () =>
										handleLevelChange( 'high' )
									}
								/>
								<p className="fraud-protection-single-radio-wrapper__item">
									{ __( 'High', 'woocommerce-payments' ) }
								</p>
								<HelpOutlineIcon
									size={ 18 }
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
											<HighNotice />
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
												<li>
													{ __(
														'An order exceeds ',
														'woocommerce-payments'
													) }{ ' ' }
													<strong>
														{ __(
															'$1,000.00.',
															'woocommerce-payments'
														) }
													</strong>
												</li>
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
							<p
								className={
									'fraud-protection__text--help-text'
								}
							>
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
		</>
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
