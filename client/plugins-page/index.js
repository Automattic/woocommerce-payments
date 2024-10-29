/**
 * External dependencies
 */
import React, { useState, useEffect, useCallback } from 'react';
import { useDispatch } from '@wordpress/data';
import ReactDOM from 'react-dom';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';

/**
 * Internal dependencies
 */
import PluginDisableSurvey from './deactivation-survey';

const PluginsPage = () => {
	const { updateOptions } = useDispatch( OPTIONS_STORE_NAME );
	const [ modalOpen, setModalOpen ] = useState( false );
	const surveyModalTimestamp =
		window.wcpayPluginsSettings?.exitSurveyLastShown ?? null;

	const deactivationLink = document.querySelector(
		'#deactivate-woocommerce-payments, #deactivate-woocommerce-payments-dev'
	); // ID set by WP on the deactivation link.

	const deactivatePlugin = useCallback( () => {
		// Abort if the deactivation link is not present.
		if ( deactivationLink === null ) {
			return null;
		}

		// Deactivate plugin
		window.location.href = deactivationLink.getAttribute( 'href' );
	}, [ deactivationLink ] );

	const showModal = useCallback( () => {
		setModalOpen( true );
	}, [ setModalOpen ] );

	const closeModal = async () => {
		setModalOpen( false );

		const currentDate = new Date();

		// Update modal dismissed option.
		await updateOptions( {
			wcpay_exit_survey_last_shown: currentDate,
		} );

		window.wcpayPluginsSettings.exitSurveyLastShown = currentDate;

		// Deactivate plugin
		deactivatePlugin();
	};

	const isModalDismissed = useCallback( () => {
		if ( surveyModalTimestamp ) {
			const date1 = new Date( surveyModalTimestamp );
			const date2 = new Date();
			const diffTime = Math.abs( date2 - date1 );
			const diffDays = Math.ceil( diffTime / ( 1000 * 60 * 60 * 24 ) );

			if ( diffDays < 7 ) {
				return true;
			}
		}

		return false;
	}, [ surveyModalTimestamp ] );

	const handleLinkClick = useCallback(
		( e ) => {
			e.preventDefault();
			showModal();
		},
		[ showModal ]
	);

	useEffect( () => {
		// If the survey is dismissed skip event listeners.
		if ( isModalDismissed() ) {
			return null;
		}

		// Abort if the deactivation link is not present.
		if ( deactivationLink === null ) {
			return null;
		}

		// Handle click event.
		deactivationLink.addEventListener( 'click', handleLinkClick );

		return () => {
			deactivationLink.removeEventListener( 'click', handleLinkClick );
		};
	}, [ isModalDismissed, deactivationLink, handleLinkClick ] );

	return (
		<>
			{ ! isModalDismissed() && modalOpen && (
				<PluginDisableSurvey onRequestClose={ closeModal } />
			) }
		</>
	);
};

ReactDOM.render(
	<PluginsPage />,
	document.querySelector( '#woopayments-plugins-page-app' )
);
