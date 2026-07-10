<?php
/**
 * Logique de ciblage des pages.
 *
 * Le ciblage est évalué côté serveur au moment du rendu. Cette décision est
 * donc « figée » par un éventuel cache de page : c'est acceptable car le
 * ciblage dépend de l'URL, qui est justement la clé du cache.
 *
 * @package FlibUp
 */

namespace FlibUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Décide si une pop-up doit apparaître sur la requête courante.
 */
class Targeting {

	/**
	 * La pop-up cible-t-elle la page courante ?
	 *
	 * @param Popup $popup Pop-up.
	 * @return bool
	 */
	public static function matches_current( Popup $popup ) {
		$mode           = $popup->get( 'targeting_mode' );
		$current_id     = self::current_object_id();
		$is_front       = is_front_page();
		$is_page        = is_page();
		$is_single_post = is_singular( 'post' );

		// Exclusions prioritaires (pages et articles).
		$exclude = array_merge(
			array_map( 'intval', (array) $popup->get( 'exclude_pages' ) ),
			array_map( 'intval', (array) $popup->get( 'exclude_posts' ) )
		);
		if ( $current_id && in_array( $current_id, $exclude, true ) ) {
			return false;
		}

		$match = false;

		switch ( $mode ) {
			case 'everywhere':
				$match = true;
				break;

			case 'front_page':
				$match = $is_front;
				break;

			case 'all_pages':
				$match = $is_page;
				break;

			case 'all_posts':
				$match = $is_single_post;
				break;

			case 'selected':
				$include_pages = array_map( 'intval', (array) $popup->get( 'include_pages' ) );
				$include_posts = array_map( 'intval', (array) $popup->get( 'include_posts' ) );
				$included      = array_merge( $include_pages, $include_posts );
				$match         = ( $current_id && in_array( $current_id, $included, true ) );
				break;
		}

		/**
		 * Permet d'étendre le ciblage (ex. produits WooCommerce, autres CPT)
		 * sans imposer de dépendance.
		 *
		 * @param bool  $match   Résultat courant.
		 * @param Popup $popup   Pop-up évaluée.
		 * @param int   $current_id ID de l'objet courant.
		 * @param string $mode   Mode de ciblage.
		 */
		return (bool) apply_filters( 'flibup_targeting_matches', $match, $popup, $current_id, $mode );
	}

	/**
	 * Renvoie l'ID de l'objet WordPress affiché (page ou article).
	 *
	 * @return int
	 */
	protected static function current_object_id() {
		if ( is_singular() ) {
			$id = get_queried_object_id();
			return $id ? (int) $id : 0;
		}
		return 0;
	}

	/**
	 * Description lisible du ciblage pour la liste d'administration.
	 *
	 * @param Popup $popup Pop-up.
	 * @return string
	 */
	public static function describe( Popup $popup ) {
		$mode = $popup->get( 'targeting_mode' );

		switch ( $mode ) {
			case 'everywhere':
				return __( 'Tout le site', 'flib-up' );
			case 'front_page':
				return __( "Page d'accueil", 'flib-up' );
			case 'all_pages':
				return __( 'Toutes les pages', 'flib-up' );
			case 'all_posts':
				return __( 'Tous les articles', 'flib-up' );
			case 'selected':
				$count = count( (array) $popup->get( 'include_pages' ) ) + count( (array) $popup->get( 'include_posts' ) );
				return sprintf(
					/* translators: %d: nombre de contenus ciblés */
					_n( '%d contenu ciblé', '%d contenus ciblés', $count, 'flib-up' ),
					$count
				);
			default:
				return '—';
		}
	}
}
