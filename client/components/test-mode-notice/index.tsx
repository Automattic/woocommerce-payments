/**
 * External dependencies
 */
import React from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getPaymentSettingsUrl, isInTestMode } from 'utils';
import BannerNotice from '../banner-notice';
import interpolateComponents from '@automattic/interpolate-components';
import { Link } from '@woocommerce/components';
import { recordEvent } from 'wcpay/tracks';

type CurrentPage =
	| 'overview'
	| 'documents'
	| 'deposits'
	| 'disputes'
	| 'loans'
	| 'payments'
	| 'transactions';

interface Props {
	currentPage: CurrentPage;
	actions?: React.ComponentProps< typeof BannerNotice >[ 'actions' ];
	isDetailsView?: boolean;
	isOnboardingTestMode?: boolean;
}

const nounToUse = {
	documents: __( 'document', 'woocommerce-payments' ),
	deposits: __( 'deposit', 'woocommerce-payments' ),
	disputes: __( 'dispute', 'woocommerce-payments' ),
	loans: __( 'loan', 'woocommerce-payments' ),
	payments: __( 'order', 'woocommerce-payments' ),
	transactions: __( 'order', 'woocommerce-payments' ),
};

const verbToUse = {
	documents: __( 'created', 'woocommerce-payments' ),
	deposits: __( 'created', 'woocommerce-payments' ),
	disputes: __( 'created', 'woocommerce-payments' ),
	loans: __( 'created', 'woocommerce-payments' ),
	payments: __( 'placed', 'woocommerce-payments' ),
	transactions: __( 'placed', 'woocommerce-payments' ),
};

const getNoticeContent = (
	currentPage: CurrentPage,
	isDetailsView: boolean,
	isOnboardingTestMode: boolean
): JSX.Element => {
	switch ( currentPage ) {
		case 'overview':
			return isOnboardingTestMode ? (
				<>
					{ interpolateComponents( {
						mixedString: sprintf(
							/* translators: %1$s: WooPayments */
							__(
								'{{strong}}%1$s is in sandbox mode.{{/strong}} You need to set up a live %1$s account before you can accept real transactions.',
								'woocommerce-payments'
							),
							'WooPayments'
						),
						components: {
							strong: <strong />,
						},
					} ) }
				</>
			) : (
				<>
					{ interpolateComponents( {
						mixedString: sprintf(
							/* translators: %1$s: WooPayments */
							__(
								'{{strong}}%1$s is in test mode.{{/strong}} All transactions will be simulated. {{learnMoreLink}}Learn more{{/learnMoreLink}}',
								'woocommerce-payments'
							),
							'WooPayments'
						),
						components: {
							strong: <strong />,
							learnMoreLink: (
								// Link content is in the format string above. Consider disabling jsx-a11y/anchor-has-content.
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<Link
									href={
										'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/sandbox-mode/'
									}
									target="_blank"
									rel="noreferrer"
									type="external"
									onClick={ () =>
										recordEvent(
											'wcpay_overview_test_mode_learn_more_clicked'
										)
									}
								/>
							),
						},
					} ) }
				</>
			);
		case 'documents':
		case 'deposits':
		case 'disputes':
		case 'payments':
		case 'loans':
		case 'transactions':
			return isDetailsView ? (
				<>
					{ interpolateComponents( {
						mixedString: sprintf(
							/* translators: %1$s: WooPayments */
							_n(
								'%1$s was in test mode when this %2$s was %3$s. To view live %2$ss, disable test mode in {{settingsLink}}%1$s settings{{/settingsLink}}.',
								'%1$s was in test mode when these %2$ss were %3$s. To view live %2$ss, disable test mode in {{settingsLink}}%1$s settings{{/settingsLink}}.',
								'deposits' === currentPage ? 2 : 1,
								'woocommerce-payments'
							),
							'WooPayments',
							nounToUse[ currentPage ],
							verbToUse[ currentPage ]
						),
						components: {
							settingsLink: (
								// Link content is in the format string above. Consider disabling jsx-a11y/anchor-has-content.
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a href={ getPaymentSettingsUrl() } />
							),
						},
					} ) }
				</>
			) : (
				<>
					{ interpolateComponents( {
						mixedString: sprintf(
							/* translators: %1$s: WooPayments */
							__(
								'Viewing test %1$s. To view live %1s, disable test mode in {{settingsLink}}%2s settings{{/settingsLink}}.',
								'woocommerce-payments'
							),
							currentPage,
							'WooPayments'
						),
						components: {
							settingsLink: (
								// Link content is in the format string above. Consider disabling jsx-a11y/anchor-has-content.
								// eslint-disable-next-line jsx-a11y/anchor-has-content
								<a href={ getPaymentSettingsUrl() } />
							),
						},
					} ) }
				</>
			);
	}
};

export const TestModeNotice: React.FC< Props > = ( {
	currentPage,
	actions,
	isDetailsView = false,
	isOnboardingTestMode = false,
} ) => {
	if ( ! isInTestMode() ) return null;

	return (
		<BannerNotice
			status="warning"
			isDismissible={ false }
			actions={ actions }
		>
			{ getNoticeContent(
				currentPage,
				isDetailsView,
				isOnboardingTestMode
			) }
		</BannerNotice>
	);
};
