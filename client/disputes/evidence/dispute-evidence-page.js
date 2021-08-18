/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardBody,
	CardHeader,
	SelectControl,
} from '@wordpress/components';

/**
 * Internal dependencies.
 */
import Info from '../info';
import Page from 'components/page';
import Loadable, { LoadableBlock } from 'components/loadable';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import { DisputeEvidenceForm } from './dispute-evidence-form';

export const DisputeEvidencePage = ( props ) => {
	const {
		isLoading,
		dispute = {},
		productType,
		onChangeProductType,
		...evidenceFormProps
	} = props;
	const readOnly =
		dispute &&
		'needs_response' !== dispute.status &&
		'warning_needs_response' !== dispute.status;
	const disputeIsAvailable = ! isLoading && dispute.id;
	const testModeNotice = <TestModeNotice topic={ topics.disputeDetails } />;

	if ( ! isLoading && ! disputeIsAvailable ) {
		return (
			<Page isNarrow className="wcpay-dispute-details">
				{ testModeNotice }
				<div>
					{ __( 'Dispute not loaded', 'woocommerce-payments' ) }
				</div>
			</Page>
		);
	}

	return (
		<Page isNarrow className="wcpay-dispute-evidence">
			{ testModeNotice }
			<Card size="large">
				<CardHeader>
					{
						<Loadable
							isLoading={ isLoading }
							value={ __(
								'Challenge dispute',
								'woocommerce-payments'
							) }
						/>
					}
				</CardHeader>
				<CardBody>
					<Info dispute={ dispute } isLoading={ isLoading } />
				</CardBody>
			</Card>
			<Card size="large">
				<CardHeader>
					{
						<Loadable
							isLoading={ isLoading }
							value={ __(
								'Product type',
								'woocommerce-payments'
							) }
						/>
					}
				</CardHeader>
				<CardBody>
					<LoadableBlock isLoading={ isLoading } numLines={ 2 }>
						<SelectControl
							value={ productType }
							onChange={ onChangeProductType }
							options={ [
								{
									label: __(
										'Select one…',
										'woocommerce-payments'
									),
									disabled: true,
									value: '',
								},
								{
									label: __(
										'Physical product',
										'woocommerce-payments'
									),
									value: 'physical_product',
								},
								{
									label: __(
										'Digital product or service',
										'woocommerce-payments'
									),
									value: 'digital_product_or_service',
								},
								{
									label: __(
										'Offline service',
										'woocommerce-payments'
									),
									value: 'offline_service',
								},
								{
									label: __(
										'Multiple product types',
										'woocommerce-payments'
									),
									value: 'multiple',
								},
							] }
							disabled={ readOnly }
						/>
					</LoadableBlock>
				</CardBody>
			</Card>
			{
				// Don't render the form placeholder while the dispute is being loaded.
				// The form content depends on the selected product type, hence placeholder might disappear after loading.
				! isLoading && (
					<DisputeEvidenceForm
						{ ...evidenceFormProps }
						readOnly={ readOnly }
					/>
				)
			}
		</Page>
	);
};
