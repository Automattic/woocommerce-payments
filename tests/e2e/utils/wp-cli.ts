/**
 * External dependencies
 */
import { exec as _exec } from 'child_process';
import { promisify } from 'util';

const exec = promisify( _exec );

/**
 * Options for runWp command
 */
interface RunWpCliOptions {
	/** Name of the Docker container running WordPress */
	container?: string;
	/** WP user (ID, slug, or email) to run the command as */
	user?: string;
	/** Working directory inside the container (optional) */
	cwd?: string;
}

/**
 * Run a WP-CLI command inside a Dockerized WordPress container.
 *
 * @param cliCmd - The WP-CLI command string (without "wp ")
 * @param options - Optional settings for container name, user, and cwd
 * @return A promise resolving to stdout and stderr from the command
 */
export async function runWpCli(
	cliCmd: string,
	options: RunWpCliOptions = {}
): Promise< { stdout: string; stderr: string } > {
	const container = options.container ?? 'wcp_e2e_wordpress';
	// Build the base docker exec command
	let cmd = `docker exec -T ${ container } ${ cliCmd }`;

	// Append user flag if provided
	if ( options.user ) {
		cmd += ` --user=${ options.user }`;
	}

	// If a cwd is specified, wrap the command to cd inside the container
	if ( options.cwd ) {
		// Rebuild command to change directory first
		const userFlag = options.user ? ` --user=${ options.user }` : '';
		cmd = `docker exec -T ${ container } sh -c "cd ${ options.cwd } && ${ cliCmd }${ userFlag }"`;
	}

	// Execute and return the result
	const { stdout, stderr } = await exec( cmd );
	return { stdout, stderr };
}
