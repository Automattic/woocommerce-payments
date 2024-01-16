/** @format */
/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';
import { Button, SelectControl } from '@wordpress/components';
import interpolateComponents from '@automattic/interpolate-components';

/**
 * Internal dependencies
 */
import { ReportingExportLanguageHook } from 'wcpay/settings/reporting-settings/interfaces';
import { useReportingExportLanguage } from 'wcpay/data';
import ConfirmationModal from 'wcpay/components/confirmation-modal';
import './styles.scss';

interface CSVExportModalProps {
	totalItems: number;
	exportType: string;
	onClose: () => void;
	onSubmit: () => void;
}

const CVSExportModal: React.FunctionComponent< CSVExportModalProps > = ( {
	totalItems,
	exportType,
	onClose,
	onSubmit,
} ) => {
	const [
		exportLanguage,
		updateExportLanguage,
	] = useReportingExportLanguage() as ReportingExportLanguageHook;

	const buttonContent = (
		<>
			<Button isSecondary onClick={ onClose }>
				{ __( 'Cancel', 'woocommerce-payments' ) }
			</Button>
			<Button isPrimary onClick={ onSubmit }>
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

	const exportLanguageOptions = [
		{ label: __( 'English', 'woocommerce-payments' ), value: 'en' },
		{
			label: __( 'Site Language', 'woocommerce-payments' ),
			value: 'default',
		},
	];

	const handleExportLanguageChange = ( language: string ) => {
		updateExportLanguage( language );
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

				<div className="reporting-export-modal__settings-language">
					<SelectControl
						label={ __( 'Language', 'woocommerce-payments' ) }
						value={ exportLanguage }
						onChange={ handleExportLanguageChange }
						options={ exportLanguageOptions }
					/>
				</div>
			</div>
		</ConfirmationModal>
	);
};

export default CVSExportModal;
