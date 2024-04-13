<?php
/**
 * Plugin uninstaller logic.
 *
 * @package xo-event-calendar
 * @since 1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

$options = get_option( 'xo_event_calendar_options' );
if ( false !== $options && isset( $options['delete_data'] ) && $options['delete_data'] ) {
	/**
	 * Uninstall palugin.
	 *
	 * @param string $post_type     Post type.
	 * @param string $taxonomy_type Taxonomy type.
	 */
	function xo_event_calendar_uninstall( $post_type, $taxonomy_type ) {
		global $wpdb, $wp_taxonomies;

		register_taxonomy( $taxonomy_type, $post_type );
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy_type,
				'hide_empty' => false,
			)
		);
		if ( $terms ) {
			foreach ( $terms as $term ) {
				$term_id = (int) $term->term_id;
				delete_option( 'xo_event_calendar_cat_' . $term_id );
				wp_delete_term( $term_id, $term->taxonomy );
			}
		}
		unset( $wp_taxonomies[ $taxonomy_type ] );

		$posts = get_posts(
			array(
				'numberposts' => -1,
				'post_type'   => $post_type,
				'post_status' => 'any',
			)
		);
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		delete_option( 'xo_event_calendar_options' );
		delete_option( 'xo_event_calendar_holiday_settings' );
	}

	$uninstall_options = get_site_transient( 'xo_event_calendar_uninstall_options' );
	if ( $uninstall_options ) {
		$_post_type     = $uninstall_options[0];
		$_taxonomy_type = $uninstall_options[1];

		if ( is_multisite() ) {
			$site_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				xo_event_calendar_uninstall( $post_type, $taxonomy_type );
			}
			restore_current_blog();
		} else {
			xo_event_calendar_uninstall( $_post_type, $_taxonomy_type );
		}

		delete_site_transient( 'xo_event_calendar_uninstall_options' );
	}
}
