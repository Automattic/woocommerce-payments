/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { SelectControl, ExternalLink } from '@wordpress/components';

/**
 * Internal dependencies
 */
import interpolateComponents from '@automattic/interpolate-components';
import { ReportingExportLanguageHook } from '../../interfaces';
import { useReportingExportLanguage } from 'wcpay/data';
import { getExportLanguageOptions } from 'wcpay/utils';

const ExportLanguage: React.FC = () => {
	const [
		exportLanguage,
		updateExportLanguage,
	] = useReportingExportLanguage() as ReportingExportLanguageHook;

	const handleExportLanguageChange = ( language: string ) => {
		updateExportLanguage( language );
	};

	return (
		<div className="reporting-export-language">
			<SelectControl
				label={ __( 'Language', 'woocommerce-payments' ) }
				value={ exportLanguage }
				onChange={ handleExportLanguageChange }
				options={ getExportLanguageOptions() }
			/>
			<p className="reporting-settings__text--help-text">
				{ interpolateComponents( {
					mixedString: __(
						'You can change your global site language preferences in {{learnMoreLink}}General Settings{{/learnMoreLink}}.',
						'woocommerce-payments'
					),
					components: {
						learnMoreLink: (
							// eslint-disable-next-line max-len
							<ExternalLink
								href={ addQueryArgs( 'options-general.php' ) }
							/>
						),
					},
				} ) }
			</p>
		</div>
	);
};

export default ExportLanguage;
