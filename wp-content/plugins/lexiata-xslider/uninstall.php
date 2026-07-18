<?php
/**
 * Lexiata XSlider — Uninstall
 *
 * Runs when the user deletes the plugin via the Plugins screen.
 * Removes all slider projects, slide data, and transients.
 *
 * @package Lexiata_XSlider
 */

// Exit if uninstall is not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all plugin data for the current site (blog).
 *
 * Wrapping cleanup in a function keeps every variable in function scope,
 * so we never leak un-prefixed globals into WordPress.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- function IS prefixed with lexiata_xslider_; this is a Plugin Check false positive.
function lexiata_xslider_cleanup_current_site() {
	global $wpdb;

	$cpt = 'lex_xslider';

	// 1) Delete every slider project (CPT post). Their post-meta is auto-deleted too.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall context, no caching needed
	$post_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
		$cpt
	) );
	// phpcs:enable

	if ( ! empty( $post_ids ) ) {
		foreach ( $post_ids as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}

	// 2) Catch any orphaned post-meta (in case posts were deleted manually but meta lingered).
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall context, no caching needed
	$wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ( %s, %s )",
		'_lex_xslider_settings',
		'_lex_xslider_slides'
	) );
	// phpcs:enable

	// 3) Drop transients we created.
	delete_transient( 'lex_xslider_notice' );
	delete_site_transient( 'lex_xslider_notice' );
}

// Run cleanup for the current site.
lexiata_xslider_cleanup_current_site();

// Multisite: repeat per blog.
if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	foreach ( $site_ids as $site_id ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		switch_to_blog( (int) $site_id );
		lexiata_xslider_cleanup_current_site();
		restore_current_blog();
	}
	unset( $site_ids, $site_id );
}
