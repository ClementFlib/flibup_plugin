<?php
/**
 * Rendu public des pop-ups.
 *
 * @package FlibUp
 */

namespace FlibUp\Frontend;

use FlibUp\Popup;
use FlibUp\Settings;
use FlibUp\Targeting;
use FlibUp\Scheduler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collecte les pop-ups éligibles, charge les assets si nécessaire et injecte
 * le balisage dans le pied de page.
 */
class Frontend {

	/**
	 * Pop-ups à afficher sur la requête courante.
	 *
	 * @var Popup[]
	 */
	private $to_render = array();

	/**
	 * Mode prévisualisation (ID de la pop-up) ou 0.
	 *
	 * @var int
	 */
	private $preview_id = 0;

	/**
	 * Accroche les hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp', array( $this, 'prepare' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'wp_footer', array( $this, 'render' ), 99 );
	}

	/**
	 * Détermine les pop-ups à afficher (le plus tôt possible dans le rendu).
	 *
	 * @return void
	 */
	public function prepare() {
		// Prévisualisation admin.
		if ( isset( $_GET['flibup_preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->maybe_prepare_preview();
			if ( $this->preview_id ) {
				return;
			}
		}

		if ( is_admin() ) {
			return;
		}

		$ids = $this->get_eligible_popup_ids();
		if ( empty( $ids ) ) {
			return;
		}

		$matched = array();
		foreach ( $ids as $id ) {
			$popup = new Popup( $id );

			if ( ! $popup->is_enabled() ) {
				continue;
			}
			// On n'injecte pas une pop-up définitivement expirée.
			if ( Scheduler::is_expired( $popup ) ) {
				continue;
			}
			if ( ! Targeting::matches_current( $popup ) ) {
				continue;
			}

			$matched[] = $popup;
		}

		if ( empty( $matched ) ) {
			return;
		}

		// Tri par priorité décroissante.
		usort(
			$matched,
			static function ( Popup $a, Popup $b ) {
				return (int) $b->get( 'priority' ) <=> (int) $a->get( 'priority' );
			}
		);

		// Si plusieurs pop-ups non autorisées : on ne garde que la première.
		if ( ! Settings::allow_multiple() ) {
			$matched = array( $matched[0] );
		}

		$this->to_render = $matched;
	}

	/**
	 * Prépare la prévisualisation (utilisateur habilité uniquement).
	 *
	 * @return void
	 */
	private function maybe_prepare_preview() {
		$id = isset( $_GET['flibup_preview'] ) ? absint( $_GET['flibup_preview'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $id ) {
			return;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'flibup_preview_' . $id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $id ) ) {
			return;
		}

		if ( get_post_type( $id ) !== FLIBUP_POST_TYPE ) {
			return;
		}

		$this->preview_id = $id;
		$this->to_render  = array( new Popup( $id ) );
	}

	/**
	 * Liste des ID de pop-ups potentiellement actives (avec cache transient).
	 *
	 * @return array<int>
	 */
	private function get_eligible_popup_ids() {
		$cached = get_transient( FLIBUP_ACTIVE_CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => FLIBUP_POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => FLIBUP_META_PREFIX . 'enabled',
						'value' => '1',
					),
				),
			)
		);

		$ids = array_map( 'intval', $query->posts );

		set_transient( FLIBUP_ACTIVE_CACHE_KEY, $ids, 12 * HOUR_IN_SECONDS );

		return $ids;
	}

	/**
	 * Charge les assets publics uniquement si au moins une pop-up est à rendre.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( empty( $this->to_render ) ) {
			return;
		}

		wp_enqueue_style(
			'flibup-public',
			FLIBUP_URL . 'assets/css/public.css',
			array(),
			FLIBUP_VERSION
		);

		wp_enqueue_script(
			'flibup-public',
			FLIBUP_URL . 'assets/js/public.js',
			array(),
			FLIBUP_VERSION,
			true
		);

		wp_localize_script(
			'flibup-public',
			'flibupPublic',
			array(
				'allowMultiple' => Settings::allow_multiple(),
				'preview'       => (bool) $this->preview_id,
				'now'           => time(),
			)
		);
	}

	/**
	 * Injecte le balisage des pop-ups dans le pied de page.
	 *
	 * @return void
	 */
	public function render() {
		if ( empty( $this->to_render ) ) {
			return;
		}

		echo "\n<!-- Flib'Up -->\n";
		foreach ( $this->to_render as $popup ) {
			$this->render_popup( $popup );
		}
		echo "<!-- /Flib'Up -->\n";
	}

	/**
	 * Balisage d'une pop-up.
	 *
	 * @param Popup $popup Pop-up.
	 * @return void
	 */
	private function render_popup( Popup $popup ) {
		$id            = $popup->get_id();
		$config        = $popup->to_frontend_config();
		$config['preview'] = (bool) $this->preview_id;

		$visible_title = (string) $popup->get( 'visible_title' );
		$content       = (string) $popup->get( 'content' );
		$button_text   = (string) $popup->get( 'button_text' );
		$button_url    = (string) $popup->get( 'button_url' );
		$button_target = ( '_blank' === $popup->get( 'button_target' ) ) ? '_blank' : '_self';

		$css_vars    = $popup->css_vars();
		$style       = '';
		foreach ( $css_vars as $prop => $val ) {
			$style .= $prop . ':' . $val . ';';
		}

		$dialog_id  = 'flibup-dialog-' . $id;
		$title_id   = 'flibup-title-' . $id;
		$has_title  = ( '' !== trim( $visible_title ) );
		$position   = (string) $popup->get( 'close_position' );

		$overlay_classes = array( 'flibup-overlay' );
		if ( (int) $popup->get( 'overlay_blur' ) === 1 ) {
			$overlay_classes[] = 'flibup-has-blur';
		}
		$dialog_classes = array( 'flibup-dialog', 'flibup-close-' . $position );

		$json = wp_json_encode( $config );
		?>
		<div class="<?php echo esc_attr( implode( ' ', $overlay_classes ) ); ?>"
			id="flibup-overlay-<?php echo esc_attr( $id ); ?>"
			data-flibup="<?php echo esc_attr( $json ); ?>"
			style="<?php echo esc_attr( $style ); ?>"
			hidden>
			<div class="<?php echo esc_attr( implode( ' ', $dialog_classes ) ); ?>"
				id="<?php echo esc_attr( $dialog_id ); ?>"
				role="dialog"
				aria-modal="true"
				<?php if ( $has_title ) : ?>
					aria-labelledby="<?php echo esc_attr( $title_id ); ?>"
				<?php else : ?>
					aria-label="<?php esc_attr_e( 'Fenêtre d\'information', 'flib-up' ); ?>"
				<?php endif; ?>
				tabindex="-1">

				<button type="button" class="flibup-close" aria-label="<?php esc_attr_e( 'Fermer', 'flib-up' ); ?>">
					<?php echo $this->close_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG statique interne. ?>
				</button>

				<?php if ( $has_title ) : ?>
					<h2 class="flibup-title" id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $visible_title ); ?></h2>
				<?php endif; ?>

				<div class="flibup-content">
					<?php echo wp_kses_post( $content ); ?>
				</div>

				<?php if ( '' !== trim( $button_text ) && '' !== trim( $button_url ) ) : ?>
					<div class="flibup-actions">
						<a class="flibup-button"
							href="<?php echo esc_url( $button_url ); ?>"
							<?php if ( '_blank' === $button_target ) : ?>
								target="_blank" rel="noopener noreferrer"
							<?php endif; ?>>
							<?php echo esc_html( $button_text ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Icône SVG de fermeture (légère, intégrée).
	 *
	 * @return string
	 */
	private function close_svg() {
		return '<svg class="flibup-close-icon" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>';
	}
}
