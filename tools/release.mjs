#!/usr/bin/env node
/**
 * Release de l'extension : npm run release -- <patch|minor|major|x.y.z> [--dry-run]
 *
 * Contrôles (dépôt propre, branche main, numéro supérieur, entrée du
 * changelog), mise à jour de la version partout où elle figure, compilation,
 * tests, commit « Release x.y.z » et tag vx.y.z. Rien n'est poussé : la
 * commande à lancer est affichée. Le push du tag déclenche le workflow
 * .github/workflows/release.yml, qui publie la release GitHub et son zip.
 *
 * Semver strict : patch = correction, minor = fonctionnalité compatible,
 * major = rupture (attribut renommé, balisage incompatible…).
 */
import { execSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const FILES = {
	main: 'snap-carousel-block.php',
	readme: 'readme.txt',
	package: 'package.json',
	lock: 'package-lock.json',
	blocks: [ 'src/carousel/block.json', 'src/slide/block.json' ],
};

const args = process.argv.slice( 2 );
const dryRun = args.includes( '--dry-run' );
const bump = args.find( ( arg ) => ! arg.startsWith( '--' ) );

const read = ( file ) => readFileSync( join( ROOT, file ), 'utf8' );
const write = ( file, content ) =>
	! dryRun && writeFileSync( join( ROOT, file ), content );
const run = ( command ) =>
	execSync( command, { cwd: ROOT, stdio: 'pipe', encoding: 'utf8' } ).trim();
const fail = ( message ) => {
	console.error( `\nRelease interrompue : ${ message }` );
	process.exit( 1 );
};
const step = ( message ) => console.log( `\n> ${ message }` );

const parse = ( version ) => {
	const match = /^(\d+)\.(\d+)\.(\d+)$/.exec( version || '' );
	return match ? match.slice( 1 ).map( Number ) : null;
};
const next = ( current, kind ) => {
	const [ major, minor, patch ] = parse( current );
	return (
		{
			major: `${ major + 1 }.0.0`,
			minor: `${ major }.${ minor + 1 }.0`,
			patch: `${ major }.${ minor }.${ patch + 1 }`,
		}[ kind ] || kind
	);
};
const greater = ( a, b ) => {
	const [ pa, pb ] = [ parse( a ), parse( b ) ];
	for ( let i = 0; i < 3; i++ ) {
		if ( pa[ i ] !== pb[ i ] ) {
			return pa[ i ] > pb[ i ];
		}
	}
	return false;
};

/* 1. Contrôles ------------------------------------------------------------ */

step( 'Contrôles' );

if ( ! bump ) {
	fail( 'indiquer patch, minor, major ou un numéro x.y.z.' );
}
if ( run( 'git status --porcelain' ) && ! dryRun ) {
	fail( 'le dépôt contient des modifications non commitées.' );
}
if ( run( 'git rev-parse --abbrev-ref HEAD' ) !== 'main' ) {
	fail( 'la release se fait depuis la branche main.' );
}

const current = /^\s*\*\s*Version:\s*(\S+)/m.exec( read( FILES.main ) )?.[ 1 ];
const version = next( current, bump );
if ( ! parse( version ) ) {
	fail(
		`numéro invalide « ${ version } » (x.y.z, sans zéro de remplissage).`
	);
}
if ( ! greater( version, current ) ) {
	fail(
		`${ version } doit être supérieur à la version actuelle ${ current }.`
	);
}
if ( run( `git tag --list v${ version }` ) ) {
	fail( `le tag v${ version } existe déjà.` );
}

const readme = read( FILES.readme );
const changelog = readme.slice( readme.indexOf( '== Changelog ==' ) );
if ( ! changelog.includes( `= ${ version } =` ) ) {
	fail(
		`ajouter l'entrée « = ${ version } = » en tête de la section Changelog de readme.txt.`
	);
}

console.log(
	`  ${ current } -> ${ version }${ dryRun ? ' [simulation]' : '' }`
);

/* 2. Version ---------------------------------------------------------------- */

step( 'Mise à jour de la version' );

write(
	FILES.main,
	read( FILES.main )
		.replace( /^(\s*\*\s*Version:\s*)\S+/m, `$1${ version }` )
		.replace(
			/(define\(\s*'WEAREWP_SNAPCAROUSELBLOCK_VERSION',\s*')[^']+(')/,
			`$1${ version }$2`
		)
);
write(
	FILES.readme,
	readme.replace( /^Stable tag:.*$/m, `Stable tag: ${ version }` )
);
for ( const file of [ FILES.package, FILES.lock ] ) {
	const json = JSON.parse( read( file ) );
	json.version = version;
	if ( json.packages?.[ '' ] ) {
		json.packages[ '' ].version = version;
	}
	write( file, `${ JSON.stringify( json, null, 2 ) }\n` );
}
// block.json : seule la ligne de version change, la mise en forme est gardée.
for ( const file of FILES.blocks ) {
	write(
		file,
		read( file ).replace( /("version":\s*")[^"]+(")/, `$1${ version }$2` )
	);
}
console.log(
	'  en-tête, constante, Stable tag, package.json, package-lock.json, block.json'
);

/* 3. Compilation et tests -------------------------------------------------- */

step( 'Compilation et tests' );

if ( ! dryRun ) {
	run( 'npm run build' );
}
try {
	run( 'npm test' );
} catch ( error ) {
	fail( `tests en échec.\n${ error.stdout || error.message }` );
}
console.log(
	`  ${ dryRun ? 'compilation non lancée (simulation), ' : 'build/ recompilé, ' }tests réussis`
);

if ( dryRun ) {
	console.log(
		'\nSimulation terminée : aucun fichier modifié, aucun commit, aucun tag.'
	);
	process.exit( 0 );
}

/* 4. Commit et tag ------------------------------------------------------------ */

step( 'Commit et tag' );

run( 'git add -A' );
run( `git commit -m "Release ${ version }"` );
run( `git tag -a v${ version } -m "Version ${ version }"` );
console.log( `  commit « Release ${ version } » et tag v${ version }` );

console.log(
	`\nRelease ${ version } prête. Pour la publier (le workflow GitHub crée la release et son zip) :\n  git push origin main --follow-tags\n`
);
