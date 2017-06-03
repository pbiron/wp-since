<?php
/*
 * Plugin Name: WP Since
 * Description: List changes in a WP version
 * Plugin URI:  https://github.com/pbiron/wp-since
 * Version: 0.1.0
 * Author: Paul 'Sparrow Hawk' Biron
 * Author URI:  http://SparrowHawkComputing.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wporg
 * Domain Path: /languages
 * GitHub Plugin URI:  https://github.com/pbiron/wp-since
 *
 * @copyright 2017 Paul V. Biron/Sparrow Hawk Computing
 * @package wp-since
 */

defined( 'ABSPATH' ) ||	die;

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

// add our WP_CLI command
add_action( 'init', function() {
	WP_CLI::add_command( 'since', WP_Since_CLI ) ;

	return;
} ) ;

/**
 * WP_CLI version of https://developer.wordpress.org/reference/since/version
 *
 * @since 0.1.0
 */
class
WP_Since_CLI
	extends WP_CLI_Command
{
	/**
	 * List changes in a WP version.
	 *
	 * For this to work properly, not only must the wporg-developer theme be active,
	 * but the patch in https://meta.trac.wordpress.org/ticket/2847 (or it's
	 * equivalent) must be applied to that theme.
	 *
	 * ## Options
	 *
	 * [<version>]
	 * : version to list changes for.  Default: the current version.
	 *
	 * [--change_type=<change_type>]
	 * : type of change to list changes for.
	 * ---
	 * default: any
	 * options:
	 *   - any
	 *   - introduced
	 *   - modified
	 * ---
	 *
	 * [--post_type=<type>]
	 * : post_type to list changes for.
	 * ---
	 * default: any
	 * options:
	 *   - any
	 *   - wp-parser-class
	 *   - wp-parser-method
	 *   - wp-parser-function
	 *   - wp-parser-hook
	 * ---
	 *
	 * ## Examples
	 *
	 * # List all changes in current version.
	 * $ wp since
	 *
	 * 	   # List all changes in version x.y.z.
	 * 	   $ wp since x.y.z
	 *
	 * 	   # List changes introduced in version x.y.z.
	 * 	   $ wp since x.y.z --change_type=introduced
	 *
	 * 	   # List changes modified in current version.
	 * 	   $ wp since --change_type=modified
	 *
	 * 	   # List all changes to hooks in version x.y.z.
	 * 	   $ wp since x.y.z --post_type=wp-parser-hook
	 *
	 * @since 0.1.0
	 *
	 * @todo For some reason the "$ wp help since" isn't formatting correctly,
	 * 		 must be a typo in there somewhere that I just can't see
	 */
	function
	__invoke( $args, $assoc_args )
	{
		global $post;

		$_change_types = array(
			'introduced' => __( 'Introduced', 'wporg' ),
			'modified' => __( 'Modified', 'wporg' ),
		);
		$version = $this->get_version( array_shift( $args ) );
		$change_types = 'any' === $assoc_args['change_type'] ? array_keys( $_change_types ) : array( $assoc_args['change_type'] );
		$post_types = 'any' === $assoc_args['post_type'] ? DevHub\get_parsed_post_types() : $assoc_args['post_type']; ;

		/* translators: %s: Version number */
		WP_CLI::log( sprintf( __( "Changes in %s\n", 'wporg' ), $version) ) ;

		foreach ( $change_types as $change_type ) {
			$args = array(
				'post_type'      => $post_types,
				'tax_query'      =>
					array(
						array(
							'taxonomy' => 'wp-parser-since',
							'field'    => 'name',
							'terms'    => $version,
							),
					),
				'wp-parser-since' => $version, // simulate the query_vars when term archive is accessed interactively
				'change_type' => $change_type,
				'posts_per_page' => -1,
				'orderby'        => 'post_type title',
				'order'          => 'ASC',
			);

			$changes = new WP_Query( $args );

			if ( 'any' === $assoc_args['change_type'] ) {
				// if more than 1 change_type requested, output the current one
				// in this iteration of the foreach()
				WP_CLI::log( "\n{$_change_types[$change_type]}\n" );
			}

			if ( $changes->have_posts() ) {
				$_post_type = '';
				$type_indent = $assoc_args['change_type'] === 'any' ? "\t" : '';
				$title_indent = $type_indent . ( $assoc_args['post_type'] === 'any' ? "\t" : '' );

				while ( $changes->have_posts() ) {
					$changes->the_post();

					$post_type = get_post_type();
					if ( is_array( $post_types ) && $_post_type != $post_type ) {
						// 1st post of a given post type...output it's label
						$_post_type = $post_type;
						$post_type_obj = get_post_type_object( $post_type );

						WP_CLI::log( "\n{$type_indent}{$post_type_obj->label}\n" );
					}

					// output the post's title
					WP_CLI::log( sprintf( '%s%s', $title_indent, get_the_title() ) );

					// output the trac ticket
					if ( $ticket = $this->get_trac_ticket( $post ) ) {
						/* translators: 1: one or more tab characters 2: a trac ticket number */
						WP_CLI::log( sprintf( __( '%1$strac ticket: https://core.trac.wordpress.org/ticket/%2$s', 'wporg' ), "\t{$title_indent}", $ticket) );
					}

					// output the post's package
					/* translators: 1: one or more tab characters 2: comma-separated list of @\package's */
					WP_CLI::log( sprintf( '%1$spackage: %2$s', "\t{$title_indent}", $this->get_package( $post ) ) );

					// output the modification made
					if ( 'modified' === $change_type && $modification = $this->get_modification( $post, $version ) ) {
						/* translators: 1: one or more tab characters 2: the description of the modification */
						WP_CLI::log( sprintf( __( '%1$smodification: %2$s', 'wporg' ), "\t{$title_indent}", $modification ) ) ;
					}
				}
			}
			else {
				/* translators: %s: one or more tab characters */
				WP_CLI::log( sprintf( __( '%sNo changes.', 'wporg' ), $type_indent ) );
			}
		}

		return;
	}

	/**
	 * Get the version
	 *
	 * If no version is passed on command-line, attempt to get the current version
	 *
	 * @since 0.1.0
	 *
	 * @param string $version Version passed on command-line.
	 *
	 * @return string|exit Version on success.  On error, output error message(s) and exit.
	 */
	function
	get_version( $version = '' )
	{
		if ( ! empty( $version ) ) {
			return $version;
		}

		$current_version = DevHub\get_current_version_term();
		if ( is_wp_error( $current_version ) ) {
			WP_CLI::error( __( 'Couldn\'t get current version', 'wporg' ), false );
			foreach ( $current_version->get_error_messages() as $error ) {
				WP_CLI::error( $error, false );
			}

			die;
		}

		return $current_version->name;
	}

	/**
	 * Get the package for a post
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Post $post The post to get the package for.
	 *
	 * @return string
	 */
	function
	get_package( $post )
	{
		/* translators: package not specified in the sources */
		$package = __( 'unspecified', 'wporg') ;
		$_package = get_the_terms( $post, 'wp-parser-package' );
		if ( ! is_wp_error( $_package ) && ! empty( $_package ) ) {
			$package = implode( ', ', wp_list_pluck( $_package, 'name' ) );
		}

		return $package;
	}

	/**
	 * Get the trac ticket for a post
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Post $post The post to get the ticket for.
	 *
	 * @return string Trac ticket number.
	 *
	 * @todo With my current understanding of how data is stored, the association
	 * 		 of a ticket # and a @\since is very muddy...and this is probably useless.
	 */
	function
	get_trac_ticket( $post )
	{
		// get the ticket # from post_meta
		$ticket = get_post_meta( $post->ID, 'wporg_ticket_number', true ) ;
		if ( empty( $ticket ) ) {
			// @todo although some sources include @\link tags to trac tickets
			//		 there doesn't seem to be a reliable method of associating those
			//		 @\link's to specific @\since's
		}

		return $ticket;
	}

	/**
	 * Get the modification made in the specified version.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Post $post The post to get the modification for.
	 * @param string $version The version to get the modification for.
	 *
	 * @return string|null
	 */
	function
	get_modification( $post, $version )
	{
		$changelog = DevHub\get_changelog_data( $post->ID );

		// strip the span.since-description markup
		return preg_replace( '/<span class="since-description">(.*)<\/span>/', '$1',
			empty( $changelog[$version]['description'] ) ? '' : $changelog[$version]['description'] )  ;
	}
}