/** @format */
/**
 * External dependencies
 */
import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	CheckboxControl,
	ExternalLink,
} from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';
import { useDispatch } from '@wordpress/data';
import DomainsIcon from 'gridicons/dist/domains';

/**
 * Internal dependencies
 */
import { ReportingExportLanguageHook } from 'wcpay/settings/reporting-settings/interfaces';
import { useReportingExportLanguage, useSettings } from 'wcpay/data';
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import { getAdminUrl, getExportLanguageOptions } from 'wcpay/utils';
import './styles.scss';

interface CSVExportModalProps {
	totalItems: number;
	exportType: 'transactions' | 'deposits' | 'disputes';
	onClose: () => void;
	onSubmit: ( language: string ) => void;
}

interface SettingsHook {
	isSaving: boolean;
	isLoading: boolean;
	saveSettings: () => void;
}

const CVSExportModal: React.FunctionComponent< CSVExportModalProps > = ( {
	totalItems,
	exportType,
	onClose,
	onSubmit,
} ) => {
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const { saveSettings } = useSettings() as SettingsHook;

	const [
		exportLanguage,
		updateExportLanguage,
	] = useReportingExportLanguage() as ReportingExportLanguageHook;

	const [ modalLanguage, setModalLanguage ] = useState( exportLanguage );
	const [ modalRemember, setModalRemember ] = useState( true );

	const onDownload = async () => {
		onSubmit( modalLanguage );

		// If the Remember checkbox is checked, dismiss the modal.
		if ( modalRemember ) {
			await updateOptions( {
				wcpay_reporting_export_modal_dismissed: modalRemember,
			} );

			updateExportLanguage( modalLanguage );
			saveSettings();

			wcpaySettings.reporting.exportModalDismissed = true;
		}
	};

	const buttonContent = (
		<>
			<Button isSecondary onClick={ onClose }>
				{ __( 'Cancel', 'woocommerce-payments' ) }
			</Button>
			<Button isPrimary onClick={ onDownload }>
				{ __( 'Download', 'woocommerce-payments' ) }
			</Button>
		</>
	);

	const getModalTitle = ( type: string ): string => {
		switch ( type ) {
			case 'transactions':
				return __(
					'Export transactions report',
					'woocommerce-payments'
				);
			case 'deposits':
				return __( 'Export deposits report', 'woocommerce-payments' );
			case 'disputes':
				return __( 'Export disputes report', 'woocommerce-payments' );
			default:
				return __( 'Export report', 'woocommerce-payments' );
		}
	};

	const getExportNumberText = ( type: string ): string => {
		switch ( type ) {
			case 'transactions':
				return __(
					'Exporting {{total/}} transactions…',
					'woocommerce-payments'
				);
			case 'deposits':
				return __(
					'Exporting {{total/}} deposits…',
					'woocommerce-payments'
				);
			case 'disputes':
				return __(
					'Exporting {{total/}} disputes…',
					'woocommerce-payments'
				);
			default:
				return __(
					'Exporting {{total/}} rows…',
					'woocommerce-payments'
				);
		}
	};

	const getExportLabel = ( type: string ): string => {
		switch ( type ) {
			case 'transactions':
				return __(
					'Export transactions report in',
					'woocommerce-payments'
				);
			case 'deposits':
				return __(
					'Export deposits report in',
					'woocommerce-payments'
				);
			case 'disputes':
				return __(
					'Export disputes report in',
					'woocommerce-payments'
				);
			default:
				return __( 'Export report in', 'woocommerce-payments' );
		}
	};

	const handleExportLanguageChange = ( language: string ) => {
		setModalLanguage( language );
	};

	const handleExportLanguageRememberChange = ( value: boolean ) => {
		setModalRemember( value );
	};

	return (
		<ConfirmationModal
			title={ getModalTitle( exportType ) }
			isDismissible={ false }
			className="reporting-export-modal"
			actions={ buttonContent }
			onRequestClose={ () => {
				return false;
			} }
		>
			<div className="reporting-export-modal__items-number">
				{ interpolateComponents( {
					mixedString: getExportNumberText( exportType ),
					components: {
						total: <strong>{ totalItems }</strong>,
					},
				} ) }
			</div>

			<div className="reporting-export-modal__settings">
				<h4>Settings</h4>

				<div className="reporting-export-modal__settings--language">
					<div className="reporting-export-modal__settings--language-label">
						<DomainsIcon className="domains-icon" />
						<span className="export-label">
							{ getExportLabel( exportType ) }
						</span>
					</div>
					<div className="reporting-export-modal__settings--language-select">
						<SelectControl
							label={ '' }
							value={ modalLanguage }
							onChange={ handleExportLanguageChange }
							options={ getExportLanguageOptions() }
						/>
					</div>
				</div>

				<div className="reporting-export-modal__settings--remember">
					<CheckboxControl
						label={ __(
							'Remember the language settings.',
							'woocommerce-payments'
						) }
						help={ interpolateComponents( {
							mixedString: __(
								"Don't worry, you can always change this later in the {{learnMoreLink}}Payment Settings{{/learnMoreLink}}",
								'woocommerce-payments'
							),
							components: {
								learnMoreLink: (
									// eslint-disable-next-line max-len
									<ExternalLink
										href={ getAdminUrl( {
											page: 'wc-settings',
											tab: 'checkout',
											section: 'woocommerce_payments',
										} ) }
									/>
								),
							},
						} ) }
						checked={ modalRemember }
						onChange={ handleExportLanguageRememberChange }
						data-testid="export-modal-remember"
					/>
				</div>
			</div>
		</ConfirmationModal>
	);
};

export default CVSExportModal;
