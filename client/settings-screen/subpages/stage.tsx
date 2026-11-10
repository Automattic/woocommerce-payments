/**
 * External dependencies
 */
import React, { useEffect, useMemo, useState } from 'react';
import { Breadcrumbs, Page } from '@wordpress/admin-ui';
import { Spinner } from '@wordpress/components';
import { useViewportMatch } from '@wordpress/compose';
import { useEntityRecord } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { useParams } from '@wordpress/route';
import { Button, Notice, Stack } from '@wordpress/ui';
import { useViewConfig } from '@wordpress/views';
import { DataForm, useFormValidity } from 'dataviews-settings-screen';
import type { Field, Form } from 'dataviews-settings-screen';

/**
 * Internal dependencies
 */
import { getCrumbs, getFields, getSubPage, SubPage } from './pages';

type Settings = Record< string, unknown >;

const emptyForm: Form = { fields: [] };

/**
 * Use REST error messages without assuming requests reject with Error instances.
 */
const getErrorMessage = ( error: unknown, fallback: string ): string =>
	typeof error === 'object' &&
	error !== null &&
	'message' in error &&
	typeof error.message === 'string' &&
	error.message !== ''
		? error.message
		: fallback;

/**
 * Render and save one sub-page, the same way WooCommerce renders the main page.
 *
 * Each page is its own entity, so Save and Discard only cover this page's fields. Every page reads
 * the same settings record, so this page reloads it when it opens again and has the main page
 * reload it after a save.
 */
function SubPageForm( { page }: { page: SubPage } ) {
	const { kind, recordId } = wcpaySettingsScreenConfig;
	const { name, label } = page;
	const wasLoaded = useSelect(
		( select ) =>
			select( 'core' ).hasFinishedResolution( 'getEntityRecord', [
				kind,
				name,
				recordId,
			] ),
		// Only whether it was loaded when the page opens.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[]
	);
	const { invalidateResolution } = useDispatch( 'core' );
	useEffect( () => {
		if ( wasLoaded ) {
			invalidateResolution( 'getEntityRecord', [ kind, name, recordId ] );
		}
		// Only when the page opens.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const { record, editedRecord, hasEdits, edit, save } =
		useEntityRecord< Settings >( kind, name, recordId );
	const isNarrow = useViewportMatch( 'medium', '<' );
	const { form } = useViewConfig( { kind, name } );
	const fields = useMemo(
		() =>
			getFields(
				page,
				window.wcpaySettingsScreen?.fieldParts
			) as Field< Settings >[],
		[ page ]
	);
	const [ notice, setNotice ] = useState< {
		status: 'success' | 'error';
		message: string;
	} >();
	const { isSaving, loadError } = useSelect(
		( select ) => ( {
			isSaving: select( 'core' ).isSavingEntityRecord(
				kind,
				name,
				recordId
			),
			loadError: select( 'core' ).getResolutionError( 'getEntityRecord', [
				kind,
				name,
				recordId,
			] ),
		} ),
		[ kind, name, recordId ]
	);
	const data = editedRecord as Settings | undefined;
	const { validity, isValid } = useFormValidity(
		data ?? {},
		fields,
		form ?? emptyForm
	);
	const saveSettings = async () => {
		if ( isSaving || ! hasEdits || ! isValid ) {
			return;
		}
		setNotice( undefined );
		try {
			await save();
			invalidateResolution( 'getEntityRecord', [
				kind,
				recordId,
				recordId,
			] );
			setNotice( {
				status: 'success',
				message: __( 'Settings saved.', 'woocommerce-payments' ),
			} );
		} catch ( error ) {
			setNotice( {
				status: 'error',
				message: getErrorMessage(
					error,
					__( 'Unable to save settings.', 'woocommerce-payments' )
				),
			} );
		}
	};
	const hasLoadError = !! loadError;
	const isReady = ! hasLoadError && !! data && !! form;
	const formActions = (
		<Stack direction="row" gap="sm" justify="end">
			<Button
				variant="outline"
				size="compact"
				disabled={ isSaving || ! hasEdits }
				onClick={ () => {
					if ( record ) {
						edit( record );
						setNotice( undefined );
					}
				} }
			>
				{ __( 'Discard changes', 'woocommerce-payments' ) }
			</Button>
			<Button
				variant="solid"
				size="compact"
				loading={ isSaving }
				disabled={ isSaving || ! hasEdits || ! isValid }
				onClick={ () => void saveSettings() }
			>
				{ __( 'Save changes', 'woocommerce-payments' ) }
			</Button>
		</Stack>
	);
	return (
		<Page
			ariaLabel={ label }
			breadcrumbs={
				<Breadcrumbs
					items={ getCrumbs( page, window.location.href ) }
				/>
			}
			showSidebarToggle={ false }
			hasPadding
			// The Page header doesn't wrap its actions, so they move below the form on small screens.
			actions={ isReady && ! isNarrow ? formActions : undefined }
		>
			<Stack
				direction="column"
				gap="lg"
				className="wcpay-settings-screen__subpage"
			>
				{ hasLoadError && (
					<Notice.Root intent="error">
						<Notice.Description>
							{ getErrorMessage(
								loadError,
								__(
									'Unable to load settings.',
									'woocommerce-payments'
								)
							) }
						</Notice.Description>
					</Notice.Root>
				) }
				{ ! hasLoadError && ! isReady && (
					<Stack direction="row" justify="center">
						<Spinner />
					</Stack>
				) }
				{ notice && (
					<Notice.Root intent={ notice.status }>
						<Notice.Description>
							{ notice.message }
						</Notice.Description>
						<Notice.CloseIcon
							onClick={ () => {
								setNotice( undefined );
							} }
						/>
					</Notice.Root>
				) }
				{ isReady && (
					<DataForm
						data={ data }
						fields={ fields }
						form={ form }
						validity={ validity }
						onChange={ ( edits ) => {
							if ( ! isSaving ) {
								setNotice( undefined );
								edit( edits );
							}
						} }
					/>
				) }
				{ isReady && isNarrow && formActions }
			</Stack>
		</Page>
	);
}

/**
 * Show the WooPayments sub-page whose path matches the route.
 */
function SubPageStage() {
	const { subpage }: { subpage?: string } = useParams( { strict: false } );
	const page = getSubPage( subpage );
	return page ? <SubPageForm key={ page.name } page={ page } /> : null;
}

export const stage = SubPageStage;
