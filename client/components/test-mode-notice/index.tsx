/**
 * External dependencies
 */
import React from 'react';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { getPaymentSettingsUrl, isInDevMode, isInTestMode } from 'utils';
import BannerNotice from '../banner-notice';
import interpolateComponents from '@automattic/interpolate-components';
import { ExternalLink } from '@wordpress/components';
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
	isTestModeOnboarding?: boolean;
}

// Plural forms, supplied per locale rather than derived by appending "s" to a
// translated singular — that only works in English.
const pluralNounToUse = {
	documents: __( 'documents', 'woocommerce-payments' ),
	deposits: __( 'payouts', 'woocommerce-payments' ),
	disputes: __( 'disputes', 'woocommerce-payments' ),
	loans: __( 'loans', 'woocommerce-payments' ),
	payments: __( 'orders', 'woocommerce-payments' ),
	transactions: __( 'orders', 'woocommerce-payments' ),
};

const getNoticeContent = (
	currentPage: CurrentPage,
	isDetailsView: boolean,
	isTestModeOnboarding: boolean,
	isDevMode: boolean
): JSX.Element => {
	switch ( currentPage ) {
		case 'overview':
			if ( isTestModeOnboarding ) {
				return (
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
				);
			}
			if ( isDevMode ) {
				return (
					<>
						{ interpolateComponents( {
							mixedString: sprintf(
								/* translators: %1$s: WooPayments */
								__(
									'{{strong}}%1$s is in test mode{{/strong}} because your store is running in a development or staging environment. ' +
										'To use live mode, switch to a production {{wpEnvLink}}WordPress environment{{/wpEnvLink}} or remove the WCPAY_DEV_MODE constant. ' +
										'{{learnMoreLink}}Learn more{{/learnMoreLink}}',
									'woocommerce-payments'
								),
								'WooPayments'
							),
							components: {
								strong: <strong />,
								wpEnvLink: (
									// @ts-expect-error: children is provided when interpolating the component
									<ExternalLink
										href={
											'https://make.wordpress.org/core/2020/08/27/wordpress-environment-types/'
										}
									/>
								),
								learnMoreLink: (
									// @ts-expect-error: children is provided when interpolating the component
									<ExternalLink
										href={
											'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/'
										}
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
			}
			return (
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
								// @ts-expect-error: children is provided when interpolating the component
								<ExternalLink
									href={
										'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/'
									}
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
			if ( isDevMode ) {
				return (
					<>
						{ interpolateComponents( {
							mixedString: sprintf(
								/* translators: %1$s: resource name (e.g. "transactions") */
								__(
									'Viewing test %1$s. Test mode is active because your store is in a development or staging environment. ' +
										'{{learnMoreLink}}Learn more{{/learnMoreLink}}',
									'woocommerce-payments'
								),
								currentPage === 'deposits'
									? 'payouts'
									: currentPage
							),
							components: {
								learnMoreLink: (
									// @ts-expect-error: children is provided when interpolating the component
									<ExternalLink
										href={
											'https://woocommerce.com/document/woopayments/testing-and-troubleshooting/test-accounts/'
										}
									/>
								),
							},
						} ) }
					</>
				);
			}
			if ( isDetailsView ) {
				return (
					<>
						{ interpolateComponents( {
							mixedString: sprintf(
								/* translators: %1$s: WooPayments, %2$s: plural record type, e.g. "orders" */
								__(
									'%1$s is in test mode, so only test %2$s are shown. To view live %2$s, disable test mode in {{settingsLink}}%1$s settings{{/settingsLink}}.',
									'woocommerce-payments'
								),
								'WooPayments',
								pluralNounToUse[ currentPage ]
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
			return (
				<>
					{ interpolateComponents( {
						mixedString: sprintf(
							/* translators: %1$s: WooPayments */
							__(
								'Viewing test %1$s. To view live %1s, disable test mode in {{settingsLink}}%2s settings{{/settingsLink}}.',
								'woocommerce-payments'
							),
							currentPage === 'deposits'
								? 'payouts'
								: currentPage,
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
	isTestModeOnboarding = false,
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
				isTestModeOnboarding,
				isInDevMode()
			) }
		</BannerNotice>
	);
};
