<?php
/**
 * Gestion AJAX (recherche de contenus pour le ciblage).
 *
 * @package FlibUp
 */

namespace FlibUp\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recherche paginée de pages/articles pour le sélecteur de ciblage.
 */
class Ajax {

	/**
	 * Accroche les hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_flibup_search_content', array( $this, 'search_content' ) );
	}

	/**
	 * Répond à la recherche AJAX.
	 *
	 * @return void
	 */
	public function search_content() {
		check_ajax_referer( 'flibup_search', 'nonce' );

		if ( ! current_user_can( Admin::capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Accès refusé.', 'flib-up' ) ), 403 );
		}

		$term  = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$ptype = isset( $_GET['ptype'] ) ? sanitize_key( wp_unslash( $_GET['ptype'] ) ) : 'page';

		$allowed_ptypes = array( 'page', 'post' );
		/**
		 * Permet d'ajouter des types de contenu au sélecteur de ciblage.
		 *
		 * @param array $allowed_ptypes Types autorisés.
		 */
		$allowed_ptypes = (array) apply_filters( 'flibup_searchable_post_types', $allowed_ptypes );

		if ( ! in_array( $ptype, $allowed_ptypes, true ) ) {
			$ptype = 'page';
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $ptype,
				'post_status'            => 'publish',
				's'                      => $term,
				'posts_per_page'         => 20,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$results = array();
		foreach ( $query->posts as $p ) {
			$results[] = array(
				'id'   => $p->ID,
				'text' => get_the_title( $p ) ? get_the_title( $p ) : sprintf( '#%d', $p->ID ),
			);
		}

		wp_send_json_success( array( 'results' => $results ) );
	}
}
