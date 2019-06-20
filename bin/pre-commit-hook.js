'use-strict';

/**
 * External dependencies
 */
const execSync = require( 'child_process' ).execSync;

/**
 * Parses the output of a git diff command into javascript file paths.
 *
 * @param   {String} command Command to run. Expects output like `git diff --name-only […]`
 * @returns {Array}          Paths output from git command
 */
function parseGitDiffToPathArray( command ) {
	return execSync( command )
		.toString()
		.split( '\n' )
		.map( name => name.trim() )
		.filter(
			name => name.endsWith( '.js' ) || name.endsWith( '.jsx' ) || name.endsWith( '.scss' ) || name.endsWith( '.php' )
		);
}

const files = parseGitDiffToPathArray( 'git diff --cached --name-only --diff-filter=ACM' );

try {
	// Check if PHP_CodeSniffer is installed.
	execSync( `./vendor/bin/phpcs -h` );
} catch( e ) {
	console.log(
		'PHP_CodeSniffer is not installed. ' +
		'Please, run `composer install` ' +
		'and run the command again.' );
	process.exit( 1 );
}

let foundErrors = false;

files.forEach( file => {
	if ( ! file.endsWith( '.php' ) ) {
		return;
	}

	try {
		execSync( `./vendor/bin/phpcs --standard=phpcs.xml.dist --colors ${ file }` );
	} catch( err ) {
		console.log( err.stdout.toString( 'utf8' ) );
		foundErrors = true;
	}
} );

if ( foundErrors ) {
	process.exit( 1 );
}

