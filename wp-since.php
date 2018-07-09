<?php
/*
 * Plugin Name: WP Since
 * Description: List changes in a WP version
 * Plugin URI:  https://github.com/pbiron/wp-since
 * Version: 0.2
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

/*
 * @todo can a given function/method/class/hook have more than 1 @deprecated tag?
 * 		 if so, this (and the accompanying mods to phpdoc-parser & wporg-developer)
 * 		 will need to account for that
 */

defined( 'ABSPATH' ) ||	die;

if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	return;
}

// add our WP_CLI command
add_action( 'init', function() {
	if ( 'wporg-developer' !== get_template() ) {
		WP_CLI::error( __( "'wprog-developer', or a child theme thereof, must be active.", 'wporg' ) );

		return;
	}

	WP_CLI::add_command( 'since', 'WP_Since_CLI' ) ;

	return;
} );

// hook into actions used by 'wp parser create'.  See @\todo's in the DocBlocks for these
// two methods for details.
add_action( 'wp_parser_import_item', array( 'WP_Since_CLI', 'add_deprecated_since' ), 10, 2 );
add_action( 'wp_parser_ending_import', array( 'WP_Since_CLI', 'add_since_termmetas' ), 11 );

/**
 * WP_CLI version of https://developer.wordpress.org/reference/since/<version>
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
	 * For this to work properly, not only must the wporg-developer theme (or a
	 * child theme there of) be active, but the patch in
	 * https://meta.trac.wordpress.org/ticket/2847 (or it's equivalent) must
	 * be applied to that theme.
	 *
	 * ## Options
	 *
	 * [<version>]
	 * : version to list changes for.  Default: the lastest MAJOR version.
	 *
	 * [--change_type=<change_type>]
	 * : type of change to list.
	 * ---
	 * default: any
	 * options:
	 *   - any
	 *   - introduced
	 *   - modified
	 *   - deprecated
	 * ---
	 *
	 * [--post_type=<type>]
	 * : post_type to list changes for.
	 * ---
	 * default: any
	 * options:
	 *   - any
	 *   - class
	 *   - method
	 *   - function
	 *   - hook
	 * ---
	 *
	 * ## Examples
	 *
	 * # List all changes in current version.
	 * $ wp since
	 *
	 * # List all changes in version x.y.z.
	 * $ wp since x.y.z
	 * Success: Changes in x.y.z...
	 *
	 * # List changes introduced in version x.y.z.
	 * $ wp since x.y.z --change_type=introduced
	 * Success: Introduced in x.y.z...
	 *
	 * # List changes modified in current version.
	 * $ wp since --change_type=modified
	 * Success: Modified in x.y.z...
	 *
	 * # List all changes to hooks in version x.y.z.
	 * $ wp since x.y.z --post_type=wp-parser-hook
	 * Success: Changes in x.y.z...
	 *
	 * # List all deprecated functions in version x.y.z.
	 * $ wp since x.y.z --change_type=deprecated --post_type=wp-parser-function
	 * Success: Deprecated in x.y.z...
	 *
	 * @since 0.1.0
	 * @since 0.2 Added 'Deprecated' change_type.
	 *
	 * @global WP_Post $post The current post.
	 *
	 * @todo For some reason the "$ wp help since" isn't formatting correctly,
	 * 		 must be a typo in there somewhere that I just can't see
	 */
	function __invoke( $args, $assoc_args ) {
		global $post;

		$this->maybe_augment_db();

		$version = $this->get_version( array_shift( $args ) );
		$version_term = get_term_by( 'name', $version, 'wp-parser-since' );
		if ( ! $version_term ) {
			/* translaters: %s version argument */
			WP_CLI::error( sprintf( __( 'Unknown version: %s', 'wporg' ), $version ) );

			die;
		}

		$_change_types = \DevHub\get_change_types( 'labels' );
		$doing_all_change_types = true;
		if ( 'any' === $assoc_args['change_type'] ) {
			/* translators: %s: Version number */
			WP_CLI::log( sprintf( __( 'Changes in %s', 'wporg' ), $version) );

			$change_types = array_keys( $_change_types );
		}
		else {
			/* translators: 1: Change type 2: Version number */
			WP_CLI::log( sprintf( __( '%1$s in %2$s', 'wporg' ), $_change_types[ $assoc_args['change_type'] ], $version) );

			$change_types = array( $assoc_args['change_type'] );
			$doing_all_change_types = false;
		}

		$doing_all_post_types = true;
		if ( 'any' !== $assoc_args['post_type'] ) {
			$doing_all_post_types = false;
		}

		// simulate the query_vars when @since archive is accessed interactively
		// except for the orderby/order & posts_per_page
		$args = array(
			'post_type' => ! $doing_all_post_types ? 'wp-parser-' . $assoc_args['post_type'] : array(),
			'wp-parser-since' => $version,
			'posts_per_page'  => -1,
			'orderby'         => 'post_type title',
			'order'           => 'ASC',
		);

		foreach ( $change_types as $change_type ) {
			if ( $doing_all_change_types ) {
				// if more than 1 change_type requested, output the current one
				// in this iteration of the foreach()
				WP_CLI::log( "\n{$_change_types[$change_type]}\n" );
			}

			$args['change_type'] = $change_type;
			$changes = new WP_Query( $args );

			if ( $changes->have_posts() ) {
				$_post_type = '';
				$type_indent = $doing_all_change_types ? "\t" : '';
				$title_indent = $type_indent . ( $doing_all_post_types ? "\t" : '' );

				while ( $changes->have_posts() ) {
					$changes->the_post();
					$post_type = get_post_type();

 					if ( $doing_all_post_types && $_post_type !== $post_type ) {
						// 1st post of a given post type...output it's label
						$_post_type = $post_type;
						$post_type_obj = get_post_type_object( $post_type );

						WP_CLI::log( "\n{$type_indent}{$post_type_obj->label}\n" );
					}
					else {
						WP_CLI::line();
					}

					// output the post's title
					WP_CLI::log( $title_indent . get_the_title() );

					// output the trac ticket
					// see the @\todo in the DocBlock for WP_Since_CLI::get_trac_ticket()
					if ( $ticket = $this->get_trac_ticket( $post ) ) {
						/* translators: %s: a trac ticket number */
						WP_CLI::log( "\t{$title_indent}" . sprintf( __( 'trac ticket: https://core.trac.wordpress.org/ticket/%s', 'wporg' ), $ticket) );
					}

					// output the modification made
					if ( 'modified' === $change_type && $modification = $this->get_modification( $post, $version ) ) {
						/* translators: %s: the description of the modification */
						WP_CLI::log( "\t{$title_indent}" . sprintf( __( 'modification: %s', 'wporg' ), $modification ) ) ;
					}
					// output the alternaitive (if any) for deprecated items
					elseif ( 'deprecated' === $change_type && $alternative = $this->get_alternative( $post ) ) {
						/* translators: %s: the description of the modification */
						WP_CLI::log( "\t{$title_indent}" . sprintf( __( 'alternative: %s', 'wporg' ), $alternative ) ) ;
					}

					// output the post's source file
					$file_term = get_the_terms( $post->ID, 'wp-parser-source-file' );
					if ( $file_term && ! is_wp_error( $file_term ) ) {
						$file_term = array_shift( $file_term );
						/* translators: %s: the source file containing the item */
						WP_CLI::log( "\t{$title_indent}" . sprintf( __( 'source file: %s', 'wporg' ), $file_term->name ) ) ;
					}

					// output the post's package
					/* translators: %s: comma-separated list of @\package's */
					WP_CLI::log( "\t{$title_indent}" . sprintf( __( 'package: %s', 'wpord' ), $this->get_package( $post ) ) );
				}
			}
			else {
				/* translators: %s: one or more tab characters */
				WP_CLI::log( "\n$type_indent" . __( 'No changes.', 'wporg' ) );
			}
		}

		return;
	}

	/**
	 * Get the version.
	 *
	 * If no version is passed on command-line, attempt to get the current version
	 *
	 * @since 0.1.0
	 *
	 * @param string $version Version passed on command-line.
	 * @return string|exit Version on success.  On error, output error message(s) and exit.
	 */
	protected function get_version( $version = '' ) {
		if ( ! empty( $version ) ) {
			return $version;
		}

		$current_version = \DevHub\get_current_version_term();
		if ( is_wp_error( $current_version ) ) {
			WP_CLI::error( __( 'Couldn\'t get current version.', 'wporg' ), false );
			foreach ( $current_version->get_error_messages() as $error ) {
				WP_CLI::error( $error, false );
			}

			die;
		}

		return $current_version->name;
	}

	/**
	 * Get the package for a post.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Post $post The post to get the package for.
	 * @return string Comma-separated list of packages.
	 */
	protected function get_package( $post ) {
		/* translators: package not specified in the sources */
		$package = __( 'unspecified', 'wporg') ;
		$_package = get_the_terms( $post, 'wp-parser-package' );
		if ( ! is_wp_error( $_package ) && ! empty( $_package ) ) {
			$package = implode( ', ', wp_list_pluck( $_package, 'name' ) );
		}

		return $package;
	}

	/**
	 * Get the trac ticket for a post.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_Post $post The post to get the ticket for.
	 * @return string Trac ticket number.
	 *
	 * @todo With my current understanding of how data is stored, the association
	 * 		 of a ticket # and a @\since is very muddy...and this is probably useless.
	 */
	protected function get_trac_ticket( $post ) {
		// get the ticket # from post_meta
		$ticket = get_post_meta( $post->ID, 'wporg_ticket_number', true );
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
	 * @return string
	 */
	protected function get_modification( $post, $version ) {
		$changelog = DevHub\get_changelog_data( $post->ID );

		if ( empty( $changelog[ $version ]['description'] ) ) {
			return '';
		}

		// strip the span.since-description markup
		return preg_replace( '/<span class="since-description">(.*)<\/span>/', '$1', $changelog[ $version ]['description'] );
	}

	/**
	 * Get the preferred alternative for a deprecation.
	 *
	 * @since 0.2
	 *
	 * @param WP_Post $post The post to get the alternative for.
	 * @return string
	 */
	protected function get_alternative( $post ) {
		$deprecated = \DevHub\get_deprecated( $post->ID, false );

		$types = explode( '-', get_post_type( $post->ID ) );
		$type = array_pop( $types );

		// strip the "This has been deprecated.", leaving only the preferred alternative
		/* translators: %s: parsed post post */
		return ltrim( str_replace( sprintf( __( 'This %s has been deprecated.', 'wporg' ), $type ), '', $deprecated ) );
	}

	/**
	 * Add the version an item was deprecated in to the @since tags, so that
	 * it will display in the @since archive for that version.
	 *
	 * @since 0.2
	 *
	 * @param int   $post_id post ID of the inserted or updated item.
	 * @param array $data PHPDoc data for the item we just imported.
	 * @return void
	 *
	 * @todo Ideally, this functionality should be added to the phpdoc-parser plugin,
	 * 		 in \WP_Parser\Importer::import_item() just before the line with the
	 * 		 comment that reads "If the item has @since markup, assign the taxonomy".
	 * 		 But for now I felt it was easier to include it in this plugin.
	 */
	static function add_deprecated_since( $post_id, $data ) {
		$deprecated_version = wp_list_filter( $data['doc']['tags'], array( 'name' => 'deprecated' ) );
		if ( ! empty( $deprecated_version ) ) {
			$deprecated_version = array_shift( $deprecated_version );
			$data['doc']['tags'][] = array(
				'name' => 'since',
				'content' => $deprecated_version['content'],
			);

			// @todo when/if this functionality gets merged into phpdoc-parser
			// 		 we can return at this point, since \WP_Parser\Importer::import_item()
			//		 will take are of the rest
			update_post_meta( $post_id, '_wp_parser_tags', $data['doc']['tags'] );
			$since_term = get_term_by( 'name', $deprecated_version['content'], 'wp-parser-since', 'ARRAY_A' );
			if ( $since_term ) {
				wp_set_object_terms( $post_id, (int) $since_term['term_id'], 'wp-parser-since', true );
			}
		}

		return;
	}

	/**
	 * Add term metas to facilitate filtering by type of change on @since archive
	 *
	 * @since 0.2
	 *
	 * @global wpdb $wpdb Global wpdb object.
	 *
	 * @return void
	 *
	 * @todo Ideally, this functionality should be incorporated into phpdoc-parser
	 * 		 (hooked into `wp_parser_ending_import`).  But for now I felt it was
	 * 		 easier to include it in this plugin.
	 */
	static function add_since_termmetas() {
		global $wpdb;

		// remove current relationships
		$wpdb->delete( $wpdb->termmeta, array( 'meta_key' => '_wp_parser_changes' ) );

		// add term metas for each term in the @since tax
		foreach ( get_terms( array( 'taxonomy' => 'wp-parser-since' ) ) as $version ) {
			$changes = array();

			$args = array(
				'post_type' => array(),
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'fields' => 'ids',
				'wp-parser-since' => $version->name,
			);
			$posts = get_posts( $args );

			foreach ( $posts as $post_id ) {
				// @todo remove reliance on \DevHub\get_changelog_data()
				$changelog_data = \DevHub\get_changelog_data( $post_id );
				if ( empty( $changelog_data[ $version->name ] ) ) {
					// should never happen...
					continue;
				}

 				$post_type = get_post_type( $post_id );

				// determine the type of change
				$introduced_version = array_shift( array_keys( $changelog_data ) );
				$tags = get_post_meta( $post_id, '_wp-parser_tags', true );
				$deprecated_version = wp_list_filter( $tags, array( 'name' => 'deprecated' ) );
				$deprecated_version = array_shift( $deprecated_version );
				if ( $introduced_version === $version->name ) {
					$changes['introduced'][ $post_type ][] = $post_id;
				}
				elseif ( $deprecated_version['content'] === $version->name ) {
					$changes['deprecated'][ $post_type ][] = $post_id;
				}
				else {
					$changes['modified'][ $post_type ][] = $post_id;
				}
			}

			add_term_meta( $version->term_id, '_wp_parser_changes', $changes );
		}

		return;
	}

	/**
	 * Augment results of using `wp parser create` if the sources were slurped
	 * before this plugin was active.
	 *
	 * @since 0.2
	 *
	 * @global wpdb $wpdb Global wpdb object.
	 *
	 * @return void
	 */
	protected function maybe_augment_db() {
		global $wpdb;

 		$changes = $wpdb->get_col( "SELECT meta_id FROM $wpdb->termmeta WHERE meta_key = '_wp_parser_changes' LIMIT 1" );
 		if ( ! empty( $changes ) ) {
 			// db already augmented, nothing to do
 			return;
 		}

		WP_CLI::log( __( 'It appears that when `wp parser create` slurped the source wp-since was not active.', 'wporg' ) );
		WP_CLI::log( __( 'Now doing the one-time database augmentation...', 'wporg' ) );

		WP_CLI::log( __( 'Adding deprecated version for posts to @since...', 'wporg' ) );
		$args = array(
			'post_type' => array(),
			'post_status' => 'publish',
			'tax_query' => array(
				array(
					'taxonomy' => 'wp-parser-since',
					'operator' => 'EXISTS',
				),
			),
			'posts_per_page' => -1,
			'fields' => 'ids',
		);
		$posts = get_posts( $args );
		foreach ( $posts as $post_id ) {
			$data = array(
				'doc' => array(
					'tags' => get_post_meta( $post_id, '_wp-parser_tags', true ),
				),
			);
			$this->add_deprecated_since( $post_id, $data );
		}

		WP_CLI::log( __( 'Updating changes for each version...', 'wporg' ) );
		$this->add_since_termmetas();

		WP_CLI::log( __( 'Database augmented.', 'wporg' ) );

		return;
	}
}
