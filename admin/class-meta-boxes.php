<?php
/**
 * Rendu de la méta-boîte de configuration (interface à onglets).
 *
 * @package FlibUp
 */

namespace FlibUp\Admin;

use FlibUp\Popup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Affiche tous les champs de configuration d'une pop-up.
 */
class Meta_Boxes {

	/**
	 * Rendu principal.
	 *
	 * @param \WP_Post $post Post en cours d'édition.
	 * @return void
	 */
	public function render( $post ) {
		$popup = new Popup( $post->ID );
		$v     = $popup->all();

		wp_nonce_field( Admin::NONCE_ACTION, Admin::NONCE_FIELD );

		$preview_url = wp_nonce_url(
			add_query_arg(
				array(
					'flibup_preview' => $post->ID,
				),
				home_url( '/' )
			),
			'flibup_preview_' . $post->ID
		);
		?>
		<div class="flibup-admin">
			<div class="flibup-topbar">
				<label class="flibup-enabled">
					<input type="checkbox" name="flibup_enabled" value="1" <?php checked( 1, (int) $v['enabled'] ); ?> />
					<strong><?php esc_html_e( 'Pop-up activée', 'flib-up' ); ?></strong>
				</label>
				<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener" class="button button-secondary flibup-preview-btn">
					<?php esc_html_e( 'Prévisualiser', 'flib-up' ); ?>
				</a>
				<p class="description">
					<?php esc_html_e( 'La prévisualisation ouvre le site avec les réglages enregistrés (pensez à enregistrer avant).', 'flib-up' ); ?>
				</p>
			</div>

			<h2 class="nav-tab-wrapper flibup-tabs">
				<a href="#flibup-tab-content" class="nav-tab nav-tab-active" data-tab="content"><?php esc_html_e( 'Contenu', 'flib-up' ); ?></a>
				<a href="#flibup-tab-display" class="nav-tab" data-tab="display"><?php esc_html_e( 'Affichage', 'flib-up' ); ?></a>
				<a href="#flibup-tab-targeting" class="nav-tab" data-tab="targeting"><?php esc_html_e( 'Ciblage', 'flib-up' ); ?></a>
				<a href="#flibup-tab-trigger" class="nav-tab" data-tab="trigger"><?php esc_html_e( 'Déclenchement et fréquence', 'flib-up' ); ?></a>
				<a href="#flibup-tab-schedule" class="nav-tab" data-tab="schedule"><?php esc_html_e( 'Programmation', 'flib-up' ); ?></a>
				<a href="#flibup-tab-overlay" class="nav-tab" data-tab="overlay"><?php esc_html_e( 'Masque', 'flib-up' ); ?></a>
				<a href="#flibup-tab-close" class="nav-tab" data-tab="close"><?php esc_html_e( 'Fermeture', 'flib-up' ); ?></a>
				<a href="#flibup-tab-advanced" class="nav-tab" data-tab="advanced"><?php esc_html_e( 'Avancé', 'flib-up' ); ?></a>
			</h2>

			<?php
			$this->tab_content( $v, $post->ID );
			$this->tab_display( $v );
			$this->tab_targeting( $v );
			$this->tab_trigger( $v );
			$this->tab_schedule( $v );
			$this->tab_overlay( $v );
			$this->tab_close( $v );
			$this->tab_advanced( $v );
			?>
		</div>
		<?php
	}

	/**
	 * Sépare une longueur « 90vw » en [valeur, unité].
	 *
	 * @param string $length Longueur.
	 * @param string $default_unit Unité par défaut.
	 * @return array{0:string,1:string}
	 */
	private function split_length( $length, $default_unit = 'px' ) {
		$length = (string) $length;
		if ( preg_match( '/^(\d+(?:\.\d+)?)(px|%|vw|vh)$/', $length, $m ) ) {
			return array( $m[1], $m[2] );
		}
		if ( preg_match( '/^(\d+(?:\.\d+)?)$/', $length ) ) {
			return array( $length, $default_unit );
		}
		return array( '', $default_unit );
	}

	/**
	 * Champ « valeur + unité ».
	 *
	 * @param string $name_base Base du nom (sans _val/_unit).
	 * @param string $length    Longueur actuelle.
	 * @param string $default_unit Unité par défaut.
	 * @return void
	 */
	private function length_field( $name_base, $length, $default_unit = 'px' ) {
		list( $val, $unit ) = $this->split_length( $length, $default_unit );
		$units = array( 'px', '%', 'vw', 'vh' );
		?>
		<input type="number" step="0.1" min="0" class="small-text" name="<?php echo esc_attr( $name_base . '_val' ); ?>" value="<?php echo esc_attr( $val ); ?>" />
		<select name="<?php echo esc_attr( $name_base . '_unit' ); ?>">
			<?php foreach ( $units as $u ) : ?>
				<option value="<?php echo esc_attr( $u ); ?>" <?php selected( $u, $unit ); ?>><?php echo esc_html( $u ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Champ couleur (wp-color-picker).
	 *
	 * @param string $name  Nom.
	 * @param string $value Valeur.
	 * @return void
	 */
	private function color_field( $name, $value ) {
		printf(
			'<input type="text" class="flibup-color" name="%1$s" value="%2$s" data-default-color="%2$s" />',
			esc_attr( $name ),
			esc_attr( $value )
		);
	}

	/**
	 * Onglet Contenu.
	 *
	 * @param array $v       Valeurs.
	 * @param int   $post_id ID du post.
	 * @return void
	 */
	private function tab_content( $v, $post_id ) {
		?>
		<div id="flibup-tab-content" class="flibup-tab flibup-tab-active">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="flibup_visible_title"><?php esc_html_e( 'Titre affiché dans la pop-up', 'flib-up' ); ?></label></th>
					<td>
						<input type="text" id="flibup_visible_title" class="large-text" name="flibup_visible_title" value="<?php echo esc_attr( $v['visible_title'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Le titre du post (en haut de l\'écran) sert de nom interne.', 'flib-up' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Contenu', 'flib-up' ); ?></th>
					<td>
						<?php
						wp_editor(
							$v['content'],
							'flibupcontent',
							array(
								'textarea_name' => 'flibup_content',
								'textarea_rows' => 8,
								'media_buttons' => false,
								'teeny'         => true,
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th><label for="flibup_button_text"><?php esc_html_e( 'Texte du bouton', 'flib-up' ); ?></label></th>
					<td>
						<input type="text" id="flibup_button_text" class="regular-text" name="flibup_button_text" value="<?php echo esc_attr( $v['button_text'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Le bouton est masqué si le texte ou l\'URL est vide.', 'flib-up' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="flibup_button_url"><?php esc_html_e( 'URL du bouton', 'flib-up' ); ?></label></th>
					<td>
						<input type="url" id="flibup_button_url" class="regular-text" name="flibup_button_url" value="<?php echo esc_attr( $v['button_url'] ); ?>" placeholder="https://" />
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Ouverture du lien', 'flib-up' ); ?></th>
					<td>
						<label><input type="radio" name="flibup_button_target" value="_self" <?php checked( '_self', $v['button_target'] ); ?> /> <?php esc_html_e( 'Même onglet', 'flib-up' ); ?></label>
						&nbsp;&nbsp;
						<label><input type="radio" name="flibup_button_target" value="_blank" <?php checked( '_blank', $v['button_target'] ); ?> /> <?php esc_html_e( 'Nouvel onglet', 'flib-up' ); ?></label>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Onglet Affichage (dimensions et style).
	 *
	 * @param array $v Valeurs.
	 * @return void
	 */
	private function tab_display( $v ) {
		?>
		<div id="flibup-tab-display" class="flibup-tab">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Largeur', 'flib-up' ); ?></th>
					<td><?php $this->length_field( 'flibup_width', $v['width'], 'px' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Largeur maximale', 'flib-up' ); ?></th>
					<td><?php $this->length_field( 'flibup_max_width', $v['max_width'], 'vw' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Hauteur minimale', 'flib-up' ); ?></th>
					<td><?php $this->length_field( 'flibup_min_height', $v['min_height'], 'px' ); ?> <span class="description"><?php esc_html_e( '(facultatif)', 'flib-up' ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Hauteur maximale', 'flib-up' ); ?></th>
					<td><?php $this->length_field( 'flibup_max_height', $v['max_height'], 'vh' ); ?> <span class="description"><?php esc_html_e( 'Au-delà, le contenu défile.', 'flib-up' ); ?></span></td>
				</tr>
				<tr>
					<th><label for="flibup_padding"><?php esc_html_e( 'Espacement interne', 'flib-up' ); ?></label></th>
					<td><input type="text" id="flibup_padding" name="flibup_padding" value="<?php echo esc_attr( $v['padding'] ); ?>" placeholder="32px" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Alignement du texte', 'flib-up' ); ?></th>
					<td>
						<select name="flibup_text_align">
							<option value="left" <?php selected( 'left', $v['text_align'] ); ?>><?php esc_html_e( 'Gauche', 'flib-up' ); ?></option>
							<option value="center" <?php selected( 'center', $v['text_align'] ); ?>><?php esc_html_e( 'Centré', 'flib-up' ); ?></option>
							<option value="right" <?php selected( 'right', $v['text_align'] ); ?>><?php esc_html_e( 'Droite', 'flib-up' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="flibup_title_size"><?php esc_html_e( 'Taille du titre', 'flib-up' ); ?></label></th>
					<td><input type="text" id="flibup_title_size" name="flibup_title_size" value="<?php echo esc_attr( $v['title_size'] ); ?>" placeholder="24px" /></td>
				</tr>
				<tr>
					<th><label for="flibup_content_size"><?php esc_html_e( 'Taille du contenu', 'flib-up' ); ?></label></th>
					<td><input type="text" id="flibup_content_size" name="flibup_content_size" value="<?php echo esc_attr( $v['content_size'] ); ?>" placeholder="16px" /></td>
				</tr>
				<tr>
					<th><label for="flibup_button_text_size"><?php esc_html_e( 'Taille du texte du bouton', 'flib-up' ); ?></label></th>
					<td><input type="text" id="flibup_button_text_size" name="flibup_button_text_size" value="<?php echo esc_attr( $v['button_text_size'] ); ?>" placeholder="16px" /></td>
				</tr>
				<tr>
					<th><label for="flibup_button_width"><?php esc_html_e( 'Largeur du bouton', 'flib-up' ); ?></label></th>
					<td><input type="text" id="flibup_button_width" name="flibup_button_width" value="<?php echo esc_attr( $v['button_width'] ); ?>" placeholder="auto" /> <span class="description"><?php esc_html_e( 'auto, 100% ou une longueur.', 'flib-up' ); ?></span></td>
				</tr>
				<tr>
					<th><label for="flibup_button_padding"><?php esc_html_e( 'Espacements du bouton', 'flib-up' ); ?></label></th>
					<td><input type="text" id="flibup_button_padding" name="flibup_button_padding" value="<?php echo esc_attr( $v['button_padding'] ); ?>" placeholder="12px 20px" /></td>
				</tr>
				<tr>
					<th><label for="flibup_radius"><?php esc_html_e( 'Rayon des angles', 'flib-up' ); ?></label></th>
					<td><input type="text" id="flibup_radius" name="flibup_radius" value="<?php echo esc_attr( $v['radius'] ); ?>" placeholder="8px" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Couleur de fond', 'flib-up' ); ?></th>
					<td><?php $this->color_field( 'flibup_bg_color', $v['bg_color'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Couleur du titre', 'flib-up' ); ?></th>
					<td><?php $this->color_field( 'flibup_title_color', $v['title_color'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Couleur du texte', 'flib-up' ); ?></th>
					<td><?php $this->color_field( 'flibup_text_color', $v['text_color'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Couleur du bouton', 'flib-up' ); ?></th>
					<td><?php $this->color_field( 'flibup_button_color', $v['button_color'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Couleur du texte du bouton', 'flib-up' ); ?></th>
					<td><?php $this->color_field( 'flibup_button_text_color', $v['button_text_color'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Couleur du bouton au survol', 'flib-up' ); ?></th>
					<td><?php $this->color_field( 'flibup_button_hover_color', $v['button_hover_color'] ); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Onglet Ciblage.
	 *
	 * @param array $v Valeurs.
	 * @return void
	 */
	private function tab_targeting( $v ) {
		$modes = array(
			'everywhere' => __( 'Partout sur le site', 'flib-up' ),
			'front_page' => __( "Uniquement la page d'accueil", 'flib-up' ),
			'all_pages'  => __( 'Toutes les pages', 'flib-up' ),
			'all_posts'  => __( 'Tous les articles', 'flib-up' ),
			'selected'   => __( 'Sélection précise', 'flib-up' ),
		);
		?>
		<div id="flibup-tab-targeting" class="flibup-tab">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( "Où afficher la pop-up", 'flib-up' ); ?></th>
					<td>
						<select name="flibup_targeting_mode" id="flibup_targeting_mode">
							<?php foreach ( $modes as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $v['targeting_mode'] ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<div class="flibup-selected-wrap" data-show-when="selected">
				<h4><?php esc_html_e( 'Sélection des contenus ciblés', 'flib-up' ); ?></h4>
				<?php
				$this->id_selector( 'flibup_include_pages', $v['include_pages'], 'page', __( 'Pages ciblées', 'flib-up' ) );
				$this->id_selector( 'flibup_include_posts', $v['include_posts'], 'post', __( 'Articles ciblés', 'flib-up' ) );
				?>
			</div>

			<h4><?php esc_html_e( 'Exclusions', 'flib-up' ); ?></h4>
			<p class="description"><?php esc_html_e( 'Les contenus exclus ne verront jamais cette pop-up, quel que soit le mode de ciblage.', 'flib-up' ); ?></p>
			<?php
			$this->id_selector( 'flibup_exclude_pages', $v['exclude_pages'], 'page', __( 'Pages exclues', 'flib-up' ) );
			$this->id_selector( 'flibup_exclude_posts', $v['exclude_posts'], 'post', __( 'Articles exclus', 'flib-up' ) );
			?>
		</div>
		<?php
	}

	/**
	 * Sélecteur d'ID avec recherche AJAX.
	 *
	 * @param string $name     Nom du champ.
	 * @param array  $ids      IDs déjà sélectionnés.
	 * @param string $ptype    Type de contenu (page/post).
	 * @param string $label    Libellé.
	 * @return void
	 */
	private function id_selector( $name, $ids, $ptype, $label ) {
		$ids = array_map( 'absint', (array) $ids );
		?>
		<div class="flibup-id-selector" data-name="<?php echo esc_attr( $name ); ?>" data-ptype="<?php echo esc_attr( $ptype ); ?>">
			<label class="flibup-id-label"><strong><?php echo esc_html( $label ); ?></strong></label>
			<div class="flibup-chips">
				<?php foreach ( $ids as $id ) : ?>
					<span class="flibup-chip" data-id="<?php echo esc_attr( $id ); ?>">
						<span class="flibup-chip-label"><?php echo esc_html( get_the_title( $id ) ? get_the_title( $id ) : ( '#' . $id ) ); ?></span>
						<button type="button" class="flibup-chip-remove" aria-label="<?php esc_attr_e( 'Retirer', 'flib-up' ); ?>">&times;</button>
						<input type="hidden" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $id ); ?>" />
					</span>
				<?php endforeach; ?>
			</div>
			<input type="text" class="flibup-id-search regular-text" placeholder="<?php esc_attr_e( 'Rechercher…', 'flib-up' ); ?>" autocomplete="off" />
			<ul class="flibup-id-results" hidden></ul>
		</div>
		<?php
	}

	/**
	 * Onglet Déclenchement et fréquence.
	 *
	 * @param array $v Valeurs.
	 * @return void
	 */
	private function tab_trigger( $v ) {
		?>
		<div id="flibup-tab-trigger" class="flibup-tab">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Déclenchement', 'flib-up' ); ?></th>
					<td>
						<label><input type="radio" name="flibup_trigger_mode" value="immediate" <?php checked( 'immediate', $v['trigger_mode'] ); ?> /> <?php esc_html_e( 'Immédiat au chargement', 'flib-up' ); ?></label><br />
						<label><input type="radio" name="flibup_trigger_mode" value="delay" <?php checked( 'delay', $v['trigger_mode'] ); ?> /> <?php esc_html_e( 'Après un délai', 'flib-up' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><label for="flibup_trigger_delay"><?php esc_html_e( 'Délai', 'flib-up' ); ?></label></th>
					<td>
						<input type="number" id="flibup_trigger_delay" class="small-text" min="0" name="flibup_trigger_delay" value="<?php echo esc_attr( $v['trigger_delay'] ); ?>" />
						<select name="flibup_trigger_delay_unit">
							<option value="s" <?php selected( 's', $v['trigger_delay_unit'] ); ?>><?php esc_html_e( 'secondes', 'flib-up' ); ?></option>
							<option value="ms" <?php selected( 'ms', $v['trigger_delay_unit'] ); ?>><?php esc_html_e( 'millisecondes', 'flib-up' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Fréquence d\'affichage', 'flib-up' ); ?></th>
					<td>
						<select name="flibup_frequency_mode" id="flibup_frequency_mode">
							<option value="always" <?php selected( 'always', $v['frequency_mode'] ); ?>><?php esc_html_e( 'À chaque chargement de page', 'flib-up' ); ?></option>
							<option value="session" <?php selected( 'session', $v['frequency_mode'] ); ?>><?php esc_html_e( 'Une fois par session', 'flib-up' ); ?></option>
							<option value="visitor" <?php selected( 'visitor', $v['frequency_mode'] ); ?>><?php esc_html_e( 'Une fois par visiteur', 'flib-up' ); ?></option>
							<option value="days" <?php selected( 'days', $v['frequency_mode'] ); ?>><?php esc_html_e( 'Réafficher après X jours', 'flib-up' ); ?></option>
						</select>
						<p class="description">
							<?php esc_html_e( 'La pop-up est comptée comme « vue » dès son ouverture (comportement le plus robuste : un visiteur qui l\'ignore ne la reverra pas à chaque page).', 'flib-up' ); ?>
						</p>
					</td>
				</tr>
				<tr class="flibup-freq-days">
					<th><label for="flibup_frequency_days"><?php esc_html_e( 'Nombre de jours avant réaffichage', 'flib-up' ); ?></label></th>
					<td><input type="number" id="flibup_frequency_days" class="small-text" min="1" name="flibup_frequency_days" value="<?php echo esc_attr( $v['frequency_days'] ); ?>" /></td>
				</tr>
				<tr class="flibup-freq-cookie">
					<th><label for="flibup_cookie_days"><?php esc_html_e( 'Durée de mémorisation (jours)', 'flib-up' ); ?></label></th>
					<td>
						<input type="number" id="flibup_cookie_days" class="small-text" min="1" name="flibup_cookie_days" value="<?php echo esc_attr( $v['cookie_days'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Utilisé pour le mode « une fois par visiteur ».', 'flib-up' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="flibup_campaign_version"><?php esc_html_e( 'Version de campagne', 'flib-up' ); ?></label></th>
					<td>
						<input type="text" id="flibup_campaign_version" class="small-text" name="flibup_campaign_version" value="<?php echo esc_attr( $v['campaign_version'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Changez cette valeur pour forcer une nouvelle apparition auprès des visiteurs qui ont déjà vu la pop-up.', 'flib-up' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Onglet Programmation.
	 *
	 * @param array $v Valeurs.
	 * @return void
	 */
	private function tab_schedule( $v ) {
		$start = $v['start_datetime'] ? str_replace( ' ', 'T', $v['start_datetime'] ) : '';
		$end   = $v['end_datetime'] ? str_replace( ' ', 'T', $v['end_datetime'] ) : '';
		?>
		<div id="flibup-tab-schedule" class="flibup-tab">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="flibup_start_datetime"><?php esc_html_e( 'Début de diffusion', 'flib-up' ); ?></label></th>
					<td>
						<input type="datetime-local" id="flibup_start_datetime" name="flibup_start_datetime" value="<?php echo esc_attr( $start ); ?>" />
						<p class="description"><?php esc_html_e( 'Vide = immédiat. Les comparaisons utilisent le fuseau horaire de WordPress.', 'flib-up' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="flibup_end_datetime"><?php esc_html_e( 'Fin de diffusion', 'flib-up' ); ?></label></th>
					<td>
						<input type="datetime-local" id="flibup_end_datetime" name="flibup_end_datetime" value="<?php echo esc_attr( $end ); ?>" />
						<p class="description"><?php esc_html_e( 'Vide = pas de fin (jusqu\'à désactivation manuelle).', 'flib-up' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Onglet Masque.
	 *
	 * @param array $v Valeurs.
	 * @return void
	 */
	private function tab_overlay( $v ) {
		?>
		<div id="flibup-tab-overlay" class="flibup-tab">
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Couleur du masque', 'flib-up' ); ?></th>
					<td><?php $this->color_field( 'flibup_overlay_color', $v['overlay_color'] ); ?></td>
				</tr>
				<tr>
					<th><label for="flibup_overlay_opacity"><?php esc_html_e( 'Opacité', 'flib-up' ); ?></label></th>
					<td><input type="number" id="flibup_overlay_opacity" class="small-text" step="0.05" min="0" max="1" name="flibup_overlay_opacity" value="<?php echo esc_attr( $v['overlay_opacity'] ); ?>" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Fond transparent', 'flib-up' ); ?></th>
					<td><label><input type="checkbox" name="flibup_overlay_transparent" value="1" <?php checked( 1, (int) $v['overlay_transparent'] ); ?> /> <?php esc_html_e( 'Ignorer la couleur et l\'opacité (masque invisible).', 'flib-up' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Flou d\'arrière-plan', 'flib-up' ); ?></th>
					<td>
						<label><input type="checkbox" name="flibup_overlay_blur" value="1" <?php checked( 1, (int) $v['overlay_blur'] ); ?> /> <?php esc_html_e( 'Activer', 'flib-up' ); ?></label>
						&nbsp; <?php esc_html_e( 'Intensité :', 'flib-up' ); ?>
						<input type="number" class="small-text" min="0" max="50" name="flibup_overlay_blur_px" value="<?php echo esc_attr( $v['overlay_blur_px'] ); ?>" /> px
					</td>
				</tr>
				<tr>
					<th><label for="flibup_anim_speed"><?php esc_html_e( 'Vitesse d\'animation (ms)', 'flib-up' ); ?></label></th>
					<td>
						<input type="number" id="flibup_anim_speed" class="small-text" min="0" max="5000" name="flibup_anim_speed" value="<?php echo esc_attr( $v['anim_speed'] ); ?>" />
						<label style="margin-left:10px;"><input type="checkbox" name="flibup_anim_disabled" value="1" <?php checked( 1, (int) $v['anim_disabled'] ); ?> /> <?php esc_html_e( 'Désactiver l\'animation', 'flib-up' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Blocage du défilement', 'flib-up' ); ?></th>
					<td><label><input type="checkbox" name="flibup_block_scroll" value="1" <?php checked( 1, (int) $v['block_scroll'] ); ?> /> <?php esc_html_e( 'Bloquer le défilement de la page lorsque la pop-up est ouverte.', 'flib-up' ); ?></label></td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Onglet Fermeture.
	 *
	 * @param array $v Valeurs.
	 * @return void
	 */
	private function tab_close( $v ) {
		$positions = array(
			'inside-tr'  => __( 'Intérieur, haut droite', 'flib-up' ),
			'inside-tl'  => __( 'Intérieur, haut gauche', 'flib-up' ),
			'outside-tr' => __( 'Extérieur, haut droite', 'flib-up' ),
			'outside-tl' => __( 'Extérieur, haut gauche', 'flib-up' ),
		);
		?>
		<div id="flibup-tab-close" class="flibup-tab">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="flibup_close_size"><?php esc_html_e( 'Taille de l\'icône (px)', 'flib-up' ); ?></label></th>
					<td><input type="number" id="flibup_close_size" class="small-text" min="8" max="100" name="flibup_close_size" value="<?php echo esc_attr( $v['close_size'] ); ?>" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Couleur de l\'icône', 'flib-up' ); ?></th>
					<td><?php $this->color_field( 'flibup_close_color', $v['close_color'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Couleur au survol', 'flib-up' ); ?></th>
					<td><?php $this->color_field( 'flibup_close_hover_color', $v['close_hover_color'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Position', 'flib-up' ); ?></th>
					<td>
						<select name="flibup_close_position">
							<?php foreach ( $positions as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $v['close_position'] ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Décalages (px)', 'flib-up' ); ?></th>
					<td>
						<?php esc_html_e( 'Horizontal', 'flib-up' ); ?> <input type="number" class="small-text" name="flibup_close_offset_x" value="<?php echo esc_attr( $v['close_offset_x'] ); ?>" />
						&nbsp; <?php esc_html_e( 'Vertical', 'flib-up' ); ?> <input type="number" class="small-text" name="flibup_close_offset_y" value="<?php echo esc_attr( $v['close_offset_y'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th><label for="flibup_close_hit_area"><?php esc_html_e( 'Zone cliquable (px)', 'flib-up' ); ?></label></th>
					<td><input type="number" id="flibup_close_hit_area" class="small-text" min="20" max="100" name="flibup_close_hit_area" value="<?php echo esc_attr( $v['close_hit_area'] ); ?>" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Fond de l\'icône', 'flib-up' ); ?></th>
					<td>
						<label><input type="checkbox" name="flibup_close_bg_enabled" value="1" <?php checked( 1, (int) $v['close_bg_enabled'] ); ?> /> <?php esc_html_e( 'Activer un fond', 'flib-up' ); ?></label>
						&nbsp; <?php $this->color_field( 'flibup_close_bg_color', $v['close_bg_color'] ); ?>
						&nbsp; <?php esc_html_e( 'Rayon (%)', 'flib-up' ); ?> <input type="number" class="small-text" min="0" max="50" name="flibup_close_bg_radius" value="<?php echo esc_attr( $v['close_bg_radius'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Comportement de fermeture', 'flib-up' ); ?></th>
					<td>
						<label><input type="checkbox" name="flibup_close_on_overlay" value="1" <?php checked( 1, (int) $v['close_on_overlay'] ); ?> /> <?php esc_html_e( 'Fermer au clic sur le masque extérieur', 'flib-up' ); ?></label><br />
						<label><input type="checkbox" name="flibup_close_on_esc" value="1" <?php checked( 1, (int) $v['close_on_esc'] ); ?> /> <?php esc_html_e( 'Fermer avec la touche Échap', 'flib-up' ); ?></label>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Onglet Avancé.
	 *
	 * @param array $v Valeurs.
	 * @return void
	 */
	private function tab_advanced( $v ) {
		?>
		<div id="flibup-tab-advanced" class="flibup-tab">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="flibup_priority"><?php esc_html_e( 'Priorité', 'flib-up' ); ?></label></th>
					<td>
						<input type="number" id="flibup_priority" class="small-text" min="0" max="1000" name="flibup_priority" value="<?php echo esc_attr( $v['priority'] ); ?>" />
						<p class="description"><?php esc_html_e( 'Priorité la plus élevée = affichée en premier lorsque plusieurs pop-ups sont éligibles.', 'flib-up' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
}
