<?php
/**
 * Interactive Geo Maps - Charities Map Addon
 *
 * @wordpress-plugin
 * Plugin Name:       Interactive Geo Maps - Charities Map Addon
 * Plugin URI:        https://interactivegeomaps.com
 * Description:       Custom plugin to add options to render custom taxonomy and custom post types in map
 * Version:           1.0.1
 * Author:            Carlos Moreira
 * Author URI:        https://cmoreira.net/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       igm-charities
 * Domain Path:       /languages
 */

// add pro meta options
add_filter( 'igm_model', 'igm_charities_model' );
add_filter( 'igm_add_meta', 'igm_charities_meta', 1 );

/**
 * Adds extra settings and options to the plugin
 *
 * @param array $model model for the map cpt
 * @return array modified $model
 */
function igm_charities_model( $model ) {

	$model['meta']['map_info']['sections']['general']['fields']['use_charities'] = [
		'type'    => 'switcher',
		'title'   => __( 'Auto Populate with Charities', 'igm-charities' ),
		'desc'    => __( 'Enable this to automatically populate map with information from the charity entries.', 'igm-charities' ),
		'default' => false,
	];

	$model['settings']['interactive-maps']['sections']['charities'] = [
		'title'  => __( 'Charities Addon', 'igm-charities' ),
		'icon'   => 'fa fa-handshake-o',
		'fields' => [
			'igmc_taxonomy'       => [
				'type'  => 'text',
				'title' => __( 'Taxonomy', 'igm-charities' ),
				'desc'  => __( 'Taxonomy to read the data from', 'igm-charities' ),
			],
			'igmc_cpt'            => [
				'type'  => 'text',
				'title' => __( 'Custom Post Type', 'igm-charities' ),
				'desc'  => __( 'CPT to read the data from', 'igm-charities' ),
			],
			'igmc_hide_empty'     => [
				'type'  => 'switcher',
				'title' => __( 'Hide Empty', 'igm-charities' ),
				'desc'  => __( 'Display empty terms', 'igm-charities' ),
			],
			'igmc_meta'           => [
				'type'  => 'text',
				'title' => __( 'Meta Field', 'igm-charities' ),
				'desc'  => __( 'Identifier for meta field with donated value to make calculations from.', 'igm-charities' ),
			],
			'igmc_meta_is_acf'    => [
				'type'  => 'switcher',
				'title' => __( 'Is it from ACF?', 'igm-charities' ),
				'desc'  => __( 'Enable this if the above meta field is added with ACF.', 'igm-charities' ),
			],
			'igmc_action_content' => [
				'type'    => 'select',
				'title'   => __( 'Action Content', 'igm-charities' ),
				'options' => [
					'open_url'     => __( 'URL to Term Archive', 'igm-charities' ),
					'charity_list' => __( 'HTML Entries List', 'igm-charities' ),
				],
				'desc'    => __( 'How to populate the action content for each country entry.', 'igm-charities' ),
			],
			'igmc_cache'          => [
				'type'  => 'switcher',
				'title' => __( 'Enable Cache', 'igm-charities' ),
				'desc'  => __( 'Once you have setup everything it might be good to enable some cache.', 'igm-charities' ),
			],
		],
	];

	return $model;
}

/**
 * Prepare meta information for map with entries from custom taxonomy and post type
 *
 * @param array $meta meta info for the map.
 * @return array modified $meta
 */
function igm_charities_meta( $meta ) {

	// check if this map has the option to use custom charities source.
	if ( isset( $meta['use_charities'] ) && $meta['use_charities'] ) {

		// get option values.
		$opts      = get_option( 'interactive-maps' );
		$tax       = isset( $opts['igmc_taxonomy'] ) ? $opts['igmc_taxonomy'] : '';
		$metaf     = isset( $opts['igmc_meta'] ) ? $opts['igmc_meta'] : '';
		$acf       = isset( $opts['igmc_meta_is_acf'] ) ? $opts['igmc_meta_is_acf'] : false;
		$action    = isset( $opts['igmc_action_content'] ) ? $opts['igmc_action_content'] : 'open_url';
		$empty     = isset( $opts['igmc_hide_empty'] ) ? $opts['igmc_hide_empty'] : false;
		$cache     = isset( $opts['igmc_cache'] ) ? $opts['igmc_cache'] : false;
		$post_type = isset( $opts['igmc_cpt'] ) ? $opts['igmc_cpt'] : 'any';

		// check for cache first.
		if ( $cache ) {
			$cached = get_transient( 'igm_cached_charity_info' );
			if ( $cached ) {
				$meta['regions'] = $cached;
				return $meta;
			}
		}

		// get taxonomy.
		if ( '' !== $tax && '' !== $metaf ) {

			$regions = isset( $meta['regions'] ) && is_array( $meta['regions'] ) ? $meta['regions'] : [];

			$terms = get_terms(
				array(
					'taxonomy'   => $tax,
					'hide_empty' => $empty,
				)
			);

			// loop through taxonomy to get posts associated.
			foreach ( $terms as $term ) {

				$entry = [
					'id'          => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'count'       => $term->count,
					'meta'        => $term->meta,
					'useDefaults' => '1',
				];

				$args = array(
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'post_type'      => $post_type,
					'tax_query'      => array(
						array(
							'taxonomy' => $tax,
							'field'    => 'slug',
							'terms'    => array( $term->slug ),
						),
					),
				);

				$posts = get_posts( $args );

				$html  = '';
				$total = 0;

				// loop posts.
				foreach ( $posts as $post ) {

					if ( $acf && function_exists( 'get_field' ) ) {
						$metaval = get_field( $metaf, $post->ID, true );
					} else {
						$metaval = get_post_meta( $post->ID, $metaf, true );
					}

					$posterms = get_the_terms( $post->ID, $tax );
					$totterms = count( $posterms );

					// convert to integer, divide by total of tax entries and add to total.
					$metaval = (float) $metaval;
					$metaval = $metaval / $totterms;
					$total   = $total + $metaval;

					$html .= sprintf( '<div>%1$s - %2$s</div>', $post->post_title, parseInt( $metaval ) );

				}

				if ( $html === '' ) {
					$html = 'No entries found';
				}

				$entry['value']          = parseInt( $total );
				$entry['tooltipContent'] = $html;

				if ( $action === 'open_url' ) {
					$entry['action']  = 'open_url';
					$entry['content'] = get_term_link( $term );
				} else {
					$entry['content'] = $html;
				}

				array_push( $regions, $entry );

			}

			$meta['regions'] = $regions;

			// cache data for 3 hours
			set_transient( 'igm_cached_charity_info', $regions, 3 * HOUR_IN_SECONDS );
		}
	}

	return $meta;

}



