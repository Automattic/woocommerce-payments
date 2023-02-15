/**
 * External dependencies
 */
import React, { useCallback, useContext } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, ExternalLink } from '@wordpress/components';
import interpolateComponents from 'interpolate-components';
import { Icon, store, people } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import WizardTaskContext from '../wizard/task/context';
import CollapsibleBody from '../wizard/collapsible-body';
import WizardTaskItem from '../wizard/task-item';
import WcPayUpeContext from '../../settings/wcpay-upe-toggle/context';
import Pill from '../../components/pill';
import './enable-upe-preview-task.scss';

const EnableUpePreviewTask = () => {
	const { setIsUpeEnabled, status, upeType } = useContext( WcPayUpeContext );

	const { setCompleted } = useContext( WizardTaskContext );

	const handleContinueClick = useCallback( () => {
		setIsUpeEnabled( true ).then( () => {
			setCompleted( true, 'add-payment-methods' );
		} );
	}, [ setIsUpeEnabled, setCompleted ] );

	return (
		<WizardTaskItem
			title={
				'split' === upeType
					? interpolateComponents( {
							mixedString: __(
								'{{wrapper}}Enable the improved WooCommerce Payments checkout experience{{/wrapper}}',
								'woocommerce-payments'
							),
							components: {
								wrapper: <span />,
							},
					  } )
					: interpolateComponents( {
							mixedString: __(
								'{{wrapper}}Enable the new WooCommerce Payments checkout experience{{/wrapper}} ' +
									'{{earlyAccessWrapper}}Early access{{/earlyAccessWrapper}}',
								'woocommerce-payments'
							),
							components: {
								wrapper: <span />,
								earlyAccessWrapper: <Pill />,
							},
					  } )
			}
			index={ 1 }
		>
			<CollapsibleBody className="enable-upe-preview__body">
				<p className="wcpay-wizard-task__description-element is-muted-color">
					{ 'split' === upeType
						? interpolateComponents( {
								mixedString: __(
									'Get access to additional payment methods and an improved checkout experience. {{learnMoreLink /}}',
									'woocommerce-payments'
								),
								components: {
									learnMoreLink: (
										// eslint-disable-next-line max-len
										<ExternalLink href="https://woocommerce.com/document/payments/additional-payment-methods/#introduction">
											{ __(
												'Learn more',
												'woocommerce-payments'
											) }
										</ExternalLink>
									),
								},
						  } )
						: interpolateComponents( {
								mixedString: __(
									'Get early access to additional payment methods and an improved checkout experience, ' +
										'coming soon to WooCommerce payments. {{learnMoreLink /}}',
									'woocommerce-payments'
								),
								components: {
									learnMoreLink: (
										// eslint-disable-next-line max-len
										<ExternalLink href="https://woocommerce.com/document/payments/additional-payment-methods/#introduction">
											{ __(
												'Learn more',
												'woocommerce-payments'
											) }
										</ExternalLink>
									),
								},
						  } ) }
				</p>
				<div className="enable-upe-preview__advantages-wrapper">
					<Card className="enable-upe-preview__advantage">
						<div className="enable-upe-preview__advantage-color enable-upe-preview__advantage-color--for-you" />
						<CardBody>
							<Icon icon={ store } />
							<h3>For you</h3>
							<ul className="enable-upe-preview__advantage-features-list">
								<li>
									{ __(
										'Easily add new payment methods used by customers from all over the world.',
										'woocommerce-payments'
									) }
								</li>
								<li>
									{ __(
										'FREE upgrade exclusive to WooCommerce Payments users.',
										'woocommerce-payments'
									) }
								</li>
								<li>
									{ __(
										'No hidden costs or setup fees.',
										'woocommerce-payments'
									) }
								</li>
							</ul>
						</CardBody>
					</Card>
					<Card className="enable-upe-preview__advantage">
						<div className="enable-upe-preview__advantage-color enable-upe-preview__advantage-color--for-customers" />
						<CardBody>
							<Icon icon={ people } />
							<h3>For customers</h3>
							<ul className="enable-upe-preview__advantage-features-list">
								<li>
									{ __(
										'Checkout is completed without leaving your store.',
										'woocommerce-payments'
									) }
								</li>
								<li>
									{ __(
										'Customers see only payment methods most relevent for them.',
										'woocommerce-payments'
									) }
								</li>
								<li>
									{ __(
										'Refined checkout experience designed for desktop and mobile.',
										'woocommerce-payments'
									) }
								</li>
							</ul>
						</CardBody>
					</Card>
				</div>
				<Button
					isBusy={ 'pending' === status }
					disabled={ 'pending' === status }
					onClick={ handleContinueClick }
					isPrimary
				>
					{ __( 'Enable', 'woocommerce-payments' ) }
				</Button>
			</CollapsibleBody>
		</WizardTaskItem>
	);
};

export default EnableUpePreviewTask;
