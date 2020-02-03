/* eslint-disable no-console */
/* eslint no-process-exit: 0, no-undef: 0, strict: 0 */
'use strict';
require( 'shelljs/global' );
const colors = require( 'colors' );
const confirm = require( 'confirm-simple' );
const archiver = require( 'archiver' );
const fs = require( 'fs' );

// some config
const releaseFolder = 'release';
const targetFolder = 'release/woocommerce-payments';
const dirsToCopy = [
	'assets',
	'dist',
	'includes',
];

confirm( colors.cyan( 'Howdy! This script is going to create a release folder with a compiled ' +
	'zipped up plugin ready for release. This script assumes you\'ve already checked out the correct branch, ' +
	'are running the latest code, and have run tests as needed. Sound good?' ), ( ok ) => {
	if ( ! ok ) {
		console.log( 'Okay. Abandoning ship'.magenta );
		process.exit( 0 );
	}

	// run npm dist
	rm( '-rf', 'dist' );
	exec( 'npm run build' );

	// start with a clean release folder
	rm( '-rf', releaseFolder );
	mkdir( releaseFolder );
	mkdir( targetFolder );

	// copy the main php file and readme.txt
	cp( 'woocommerce-payments.php', targetFolder );
	cp( 'readme.txt', targetFolder );

	// copy the directories to the release folder
	cp( '-Rf', dirsToCopy, targetFolder );

	const output = fs.createWriteStream( releaseFolder + '/woocommerce-payments.zip' );
	const archive = archiver( 'zip' );

	output.on( 'close', () => {
		console.log( colors.green( 'All done: Release is built in the ' + releaseFolder + ' folder.' ) );
	} );

	archive.on( 'error', ( err ) => {
		console.error( colors.red( 'An error occured while creating the zip: ' + err +
			'\nYou can still probably create the zip manually from the ' + targetFolder + ' folder.' ) );
	} );

	archive.pipe( output );

	archive.directory( targetFolder, '' );

	archive.finalize();
} );
