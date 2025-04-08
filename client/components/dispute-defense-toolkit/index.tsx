/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	CheckboxControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';

const DisputeDefenseToolkit: React.FC = () => {
	const noop = ( isChecked: boolean ) => undefined;

	return (
		<Card className="dispute-defense-toolkit">
			<CardHeader>
				<h2>
					{ __( 'Dispute Defense Toolkit', 'woocommerce-payments' ) }
				</h2>
			</CardHeader>
			<CardBody>
				<div className="toolkit-section">
					<h3>
						{ __(
							'Automatic Evidence Collection',
							'woocommerce-payments'
						) }
					</h3>
					<ul className="toolkit-features">
						<li>
							<CheckboxControl
								label={ __(
									'Auto-save tracking numbers',
									'woocommerce-payments'
								) }
								checked={ true }
								disabled={ true }
								onChange={ noop }
							/>
							<p className="description">
								{ __(
									'Automatically saves tracking numbers from supported shipping providers',
									'woocommerce-payments'
								) }
							</p>
						</li>
						<li>
							<CheckboxControl
								label={ __(
									'Log support communications',
									'woocommerce-payments'
								) }
								checked={ true }
								disabled={ true }
								onChange={ noop }
							/>
							<p className="description">
								{ __(
									'Automatically logs customer support conversations for dispute evidence',
									'woocommerce-payments'
								) }
							</p>
						</li>
						<li>
							<CheckboxControl
								label={ __(
									'Save delivery confirmation photos',
									'woocommerce-payments'
								) }
								checked={ true }
								disabled={ true }
								onChange={ noop }
							/>
							<p className="description">
								{ __(
									'Automatically saves delivery confirmation photos to order metadata',
									'woocommerce-payments'
								) }
							</p>
						</li>
					</ul>
				</div>
			</CardBody>
		</Card>
	);
};

export default DisputeDefenseToolkit;
