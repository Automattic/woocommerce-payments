/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import CardBody from '../card-body';
import { ExportLanguage } from './components';
import './style.scss';

const Reporting: React.FC = () => {
	return (
		<>
			<Card className="reporting-settings">
				<CardBody>
					<h4>
						<span id="reporting-card-title">
							{ __(
								'Report exporting default language',
								'woocommerce-payments'
							) }
						</span>
					</h4>
					<ExportLanguage />
				</CardBody>
			</Card>
		</>
	);
};

export default Reporting;
