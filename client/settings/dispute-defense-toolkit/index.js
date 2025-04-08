/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card, CheckboxControl } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';

const DisputeDefenseToolkitSettings = () => {
	// TODO: Add hooks for settings state management
	const [ isEnabled, setIsEnabled ] = React.useState( false );
	const [ autoSaveTracking, setAutoSaveTracking ] = React.useState( false );
	const [ logSupport, setLogSupport ] = React.useState( false );
	const [ saveDeliveryPhotos, setSaveDeliveryPhotos ] = React.useState(
		false
	);
	const [ captureTosAcceptance, setCaptureTosAcceptance ] = React.useState(
		false
	);

	return (
		<Card>
			<CardBody>
				<CheckboxControl
					checked={ isEnabled }
					onChange={ setIsEnabled }
					label={ __(
						'Enable Dispute Defense Toolkit',
						'woocommerce-payments'
					) }
					help={ interpolateComponents( {
						mixedString: __(
							'Automatically collect and store evidence that can help defend against disputes. ' +
								'{{learnMoreLink}}Learn more{{/learnMoreLink}}',
							'woocommerce-payments'
						),
						components: {
							learnMoreLink: (
								<a
									target="_blank"
									rel="noreferrer"
									href="https://woocommerce.com/document/woopayments/disputes/"
								>
									{ __(
										'Learn more',
										'woocommerce-payments'
									) }
								</a>
							),
						},
					} ) }
				/>

				<div
					className="dispute-defense-toolkit__settings"
					style={ { marginLeft: '24px' } }
				>
					<CheckboxControl
						disabled={ ! isEnabled }
						checked={ autoSaveTracking }
						onChange={ setAutoSaveTracking }
						label={ __(
							'Auto-save tracking numbers',
							'woocommerce-payments'
						) }
						help={ __(
							'Automatically save shipping tracking numbers to order metadata.',
							'woocommerce-payments'
						) }
					/>

					<CheckboxControl
						disabled={ ! isEnabled }
						checked={ logSupport }
						onChange={ setLogSupport }
						label={ __(
							'Log support communications',
							'woocommerce-payments'
						) }
						help={ __(
							'Save customer support interactions to help with dispute evidence.',
							'woocommerce-payments'
						) }
					/>

					<CheckboxControl
						disabled={ ! isEnabled }
						checked={ saveDeliveryPhotos }
						onChange={ setSaveDeliveryPhotos }
						label={ __(
							'Save delivery confirmation photos',
							'woocommerce-payments'
						) }
						help={ __(
							'Store delivery confirmation photos with order metadata.',
							'woocommerce-payments'
						) }
					/>

					<CheckboxControl
						disabled={ ! isEnabled }
						checked={ captureTosAcceptance }
						onChange={ setCaptureTosAcceptance }
						label={ __(
							'Capture Terms of Service acceptance',
							'woocommerce-payments'
						) }
						help={ __(
							'Record when customers accept the Terms of Service during checkout.',
							'woocommerce-payments'
						) }
					/>
				</div>
			</CardBody>
		</Card>
	);
};

export default DisputeDefenseToolkitSettings;
