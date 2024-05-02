/**
 * External dependencies
 */
import React, { useState, useEffect, useCallback } from 'react';
import { useDispatch } from '@wordpress/data';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies
 */
import PluginDisableSurvey from './deactivation-survey';

const PluginsPage = () => {
	const { updateOptions } = useDispatch( 'wc/admin/options' );
	const [ modalOpen, setModalOpen ] = useState( false );
	const [ isModalDismissed, setIsModalDismissed ] = useState(
		window.wcpayPluginsSettings?.isExitSurveyModalDimissed ?? false
	);

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

	const closeModal = useCallback( () => {
		setModalOpen( false );
		setIsModalDismissed( true );

		// Update modal dismissed option.
		updateOptions( {
			wcpay_exit_survey_dismissed: true,
		} );

		window.wcpayPluginsSettings.isExitSurveyModalDimissed = true;

		// Deactivate plugin
		deactivatePlugin();
	}, [ setModalOpen, updateOptions, deactivatePlugin ] );

	const handleLinkClick = useCallback(
		( e ) => {
			e.preventDefault();
			showModal();
		},
		[ showModal ]
	);

	useEffect( () => {
		// If the survey is dismissed skip event listeners.
		if ( isModalDismissed ) {
			return null;
		}

		// Abort if the deactivation link is not present.
		if ( deactivationLink === null ) {
			return null;
		}

		// Handle click event
		deactivationLink.addEventListener( 'click', handleLinkClick );

		return () => {
			deactivationLink.removeEventListener( 'click', handleLinkClick );
		};
	}, [ isModalDismissed, deactivationLink, handleLinkClick ] );

	return (
		<>
			{ ! isModalDismissed && modalOpen && (
				<PluginDisableSurvey onRequestClose={ closeModal } />
			) }
		</>
	);
};

ReactDOM.render(
	<PluginsPage />,
	document.querySelector( '#woopayments-plugins-page-app' )
);
