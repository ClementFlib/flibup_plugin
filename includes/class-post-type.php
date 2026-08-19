<?php
/**
 * Enregistrement du Custom Post Type des pop-ups.
 *
 * @package FlibUp
 */

namespace FlibUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Le CPT est privé : non public, non indexé, non interrogeable côté front.
 * Les pop-ups sont uniquement des enregistrements de configuration.
 */
class Post_Type {

	/**
	 * Accroche les hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );

		// Invalidation du cache lors des changements.
		add_action( 'save_post_' . FLIBUP_POST_TYPE, array( $this, 'on_change' ) );
		add_action( 'deleted_post', array( $this, 'on_delete' ) );
		add_action( 'trashed_post', array( $this, 'on_change_id' ) );
		add_action( 'untrashed_post', array( $this, 'on_change_id' ) );

		// Colonnes de la liste d'administration.
		add_filter( 'manage_' . FLIBUP_POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . FLIBUP_POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Déclare le type de contenu.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( "Pop-ups", 'Nom général du type', 'flib-up' ),
			'singular_name'      => _x( 'Pop-up', 'Nom singulier du type', 'flib-up' ),
			'add_new'            => __( 'Ajouter', 'flib-up' ),
			'add_new_item'       => __( 'Ajouter une pop-up', 'flib-up' ),
			'edit_item'          => __( 'Modifier la pop-up', 'flib-up' ),
			'new_item'           => __( 'Nouvelle pop-up', 'flib-up' ),
			'view_item'          => __( 'Voir la pop-up', 'flib-up' ),
			'search_items'       => __( 'Rechercher une pop-up', 'flib-up' ),
			'not_found'          => __( 'Aucune pop-up trouvée', 'flib-up' ),
			'not_found_in_trash' => __( 'Aucune pop-up dans la corbeille', 'flib-up' ),
			'all_items'          => __( 'Toutes les pop-ups', 'flib-up' ),
			'menu_name'          => "Flib'Up",
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'show_ui'             => true,
			'show_in_menu'        => false, // Affiché sous notre propre menu.
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'hierarchical'        => false,
			'menu_icon'           => 'dashicons-external',
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		);

		register_post_type( FLIBUP_POST_TYPE, $args );
	}

	/**
	 * Invalidation du cache après enregistrement.
	 *
	 * @return void
	 */
	public function on_change() {
		flibup_clear_active_cache();
	}

	/**
	 * Invalidation du cache après suppression, si c'est bien notre CPT.
	 *
	 * @param int $post_id ID du post.
	 * @return void
	 */
	public function on_delete( $post_id ) {
		if ( get_post_type( $post_id ) === FLIBUP_POST_TYPE ) {
			flibup_clear_active_cache();
		}
	}

	/**
	 * Invalidation générique par ID.
	 *
	 * @param int $post_id ID du post.
	 * @return void
	 */
	public function on_change_id( $post_id ) {
		if ( get_post_type( $post_id ) === FLIBUP_POST_TYPE ) {
			flibup_clear_active_cache();
		}
	}

	/**
	 * Colonnes de la liste.
	 *
	 * @param array $columns Colonnes existantes.
	 * @return array
	 */
	public function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['flibup_status']    = __( 'Statut', 'flib-up' );
				$new['flibup_dates']     = __( 'Diffusion', 'flib-up' );
				$new['flibup_trigger']   = __( 'Déclenchement', 'flib-up' );
				$new['flibup_targeting'] = __( 'Pages', 'flib-up' );
			}
		}
		return $new;
	}

	/**
	 * Rendu d'une colonne personnalisée.
	 *
	 * @param string $column  Nom de la colonne.
	 * @param int    $post_id ID du post.
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		$popup = new Popup( $post_id );

		switch ( $column ) {
			case 'flibup_status':
				$status = Scheduler::get_status( $popup );
				$map    = array(
					'inactive'  => array( __( 'Inactive', 'flib-up' ), '#b32d2e' ),
					'scheduled' => array( __( 'Programmée', 'flib-up' ), '#996800' ),
					'active'    => array( __( 'Active', 'flib-up' ), '#1a7d33' ),
					'expired'   => array( __( 'Expirée', 'flib-up' ), '#787c82' ),
				);
				$info = isset( $map[ $status ] ) ? $map[ $status ] : $map['inactive'];
				printf(
					'<span style="display:inline-block;padding:2px 8px;border-radius:3px;color:#fff;background:%1$s;font-size:12px;">%2$s</span>',
					esc_attr( $info[1] ),
					esc_html( $info[0] )
				);
				break;

			case 'flibup_dates':
				$start = $popup->get( 'start_datetime' );
				$end   = $popup->get( 'end_datetime' );
				if ( ! $start && ! $end ) {
					echo esc_html__( 'En continu', 'flib-up' );
				} else {
					echo esc_html(
						sprintf(
							/* translators: 1: date de début, 2: date de fin */
							__( 'Du %1$s au %2$s', 'flib-up' ),
							$start ? $start : __( 'immédiat', 'flib-up' ),
							$end ? $end : __( 'illimité', 'flib-up' )
						)
					);
				}
				break;

			case 'flibup_trigger':
				$mode = $popup->get( 'trigger_mode' );
				if ( 'click' === $mode ) {
					$selector = trim( (string) $popup->get( 'trigger_selector' ) );
					echo esc_html__( 'Au clic', 'flib-up' );
					if ( '' !== $selector ) {
						echo ' <code>' . esc_html( $selector ) . '</code>';
					}
				} elseif ( 'delay' === $mode ) {
					$delay = (int) $popup->get( 'trigger_delay' );
					$unit  = $popup->get( 'trigger_delay_unit' );
					echo esc_html(
						sprintf(
							/* translators: 1: valeur du délai, 2: unité */
							__( 'Après %1$s %2$s', 'flib-up' ),
							$delay,
							'ms' === $unit ? __( 'ms', 'flib-up' ) : __( 's', 'flib-up' )
						)
					);
				} else {
					echo esc_html__( 'Immédiat', 'flib-up' );
				}
				break;

			case 'flibup_targeting':
				echo esc_html( Targeting::describe( $popup ) );
				break;
		}
	}
}
