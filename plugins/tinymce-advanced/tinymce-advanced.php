<?php
/*
Plugin Name: TinyMCE Advanced
Plugin URI: http://www.laptoptips.ca/projects/tinymce-advanced/
Description: Enables advanced features and plugins in TinyMCE, the visual editor in WordPress.
Version: 4.1.7
Author: Andrew Ozz
Author URI: http://www.laptoptips.ca/

Released under the GPL version 2.0, http://www.gnu.org/licenses/gpl-2.0.html

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License version 2.0 for more details.
*/

if ( ! class_exists('Tinymce_Advanced') ) :

class Tinymce_Advanced {

	private $settings;
	private $admin_settings;
	private $admin_options;

	private $plugins;
	private $options;
	private $toolbar_1;
	private $toolbar_2;
	private $toolbar_3;
	private $toolbar_4;
	private $used_buttons = array();
	private $all_buttons = array();
	private $buttons_filter = array();

	private $all_plugins = array(
		'advlist',
		'anchor',
		'code',
		'contextmenu',
		'emoticons',
		'importcss',
		'insertdatetime',
		'nonbreaking',
		'print',
		'searchreplace',
		'table',
		'visualblocks',
		'visualchars',
		'link',
		'textpattern',
	);

	private $default_settings = array(
		'options'	=> 'menubar,advlist',
		'toolbar_1' => 'bold,italic,blockquote,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,table,fullscreen,undo,redo,wp_adv',
		'toolbar_2' => 'formatselect,alignjustify,strikethrough,outdent,indent,pastetext,removeformat,charmap,wp_more,emoticons,forecolor,wp_help',
		'toolbar_3' => '',
		'toolbar_4' => '',
		'plugins'   => 'anchor,code,insertdatetime,nonbreaking,print,searchreplace,table,visualblocks,visualchars,emoticons,advlist',
	);

	private $default_admin_settings = array( 'options' => array() );

	function __construct() {
		// Don't run outside of WP
		if ( ! defined('ABSPATH') ) {
			return;
		}

		add_action( 'plugins_loaded', array( &$this, 'set_paths' ), 50 );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( &$this, 'add_menu' ) );
			add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
		}

		// Don't load on non-supported WP versions
		if ( ! $this->check_minimum_supported_version() ) {
			return;
		}

		add_filter( 'mce_buttons', array( &$this, 'mce_buttons_1' ), 999, 2 );
		add_filter( 'mce_buttons_2', array( &$this, 'mce_buttons_2' ), 999 );
		add_filter( 'mce_buttons_3', array( &$this, 'mce_buttons_3' ), 999 );
		add_filter( 'mce_buttons_4', array( &$this, 'mce_buttons_4' ), 999 );

		add_filter( 'tiny_mce_before_init', array( &$this, 'mce_options' ) );
		add_filter( 'htmledit_pre', array( &$this, 'htmledit' ), 999 );
		add_filter( 'mce_external_plugins', array( &$this, 'mce_external_plugins' ), 999 );
		add_filter( 'tiny_mce_plugins', array( &$this, 'tiny_mce_plugins' ), 999 );
		add_action( 'after_wp_tiny_mce', array( &$this, 'after_wp_tiny_mce' ) );

	
		// Customize mce editor font sizes
		if ( ! function_exists( 'wpex_mce_text_sizes' ) ) {
			function wpex_mce_text_sizes( $initArray ){
				$initArray['fontsize_formats'] = "8px 9px 10px 11px 12px 13px 14px 15px 16px 18px 20px 21px 22px 24px 28px 32px 36px";
				return $initArray;
			}
		}
		add_filter( 'tiny_mce_before_init', 'wpex_mce_text_sizes' );

		function wpex_mce_google_fonts_array( $initArray ) {
    		//$initArray['font_formats'] = 'Lato=Lato;Andale Mono=andale mono,times;Arial=arial,helvetica,sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;Comic Sans MS=comic sans ms,sans-serif;Courier New=courier new,courier;Georgia=georgia,palatino;Helvetica=helvetica;Impact=impact,chicago;Symbol=symbol;Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,monaco;Times New Roman=times new roman,times;Trebuchet MS=trebuchet ms,geneva;Verdana=verdana,geneva;Webdings=webdings;Wingdings=wingdings,zapf dingbats';
    			$theme_advanced_fonts = 'Aclonica=Aclonica;';
   		 	$theme_advanced_fonts .= 'Calibri=Calibri;';
  		  	$theme_advanced_fonts .= 'Michroma=Michroma;';
  		  	$theme_advanced_fonts .= 'Paytone One=Paytone One';
  		  	$initArray['font_formats'] = $theme_advanced_fonts;
   			return $initArray;
		}
		add_filter( 'tiny_mce_before_init', 'wpex_mce_google_fonts_array' );
	}

	// When using a plugin that changes the paths dinamically, set these earlier than 'plugins_loaded' 50.
	function set_paths() {
		if ( ! defined( 'TADV_URL' ) )
			define( 'TADV_URL', plugin_dir_url( __FILE__ ) );

		if ( ! defined( 'TADV_PATH' ) )
			define( 'TADV_PATH', plugin_dir_path( __FILE__ ) );
	}

	private function remove_settings( $all = false ) {
		if ( $all ) {
			delete_option( 'tadv_settings' );
			delete_option( 'tadv_admin_settings' );
			delete_option( 'tadv_version' );
		}

		// Delete old options
		delete_option('tadv_options');
		delete_option('tadv_toolbars');
		delete_option('tadv_plugins');
		delete_option('tadv_btns1');
		delete_option('tadv_btns2');
		delete_option('tadv_btns3');
		delete_option('tadv_btns4');
		delete_option('tadv_allbtns');
	}

	function enqueue_scripts( $page ) {
		if ( 'settings_page_tinymce-advanced' == $page ) {
			wp_enqueue_script( 'tadv-js', TADV_URL . 'js/tadv.js', array( 'jquery-ui-sortable' ), '4.0', true );
			wp_enqueue_style( 'tadv-mce-skin', includes_url( 'js/tinymce/skins/lightgray/skin.min.css' ), array(), '4.0' );
			wp_enqueue_style( 'tadv-css', TADV_URL . 'css/tadv-styles.css', array( 'editor-buttons' ), '4.0' );

			if ( substr( get_locale(), 0, 2 ) !== 'en' ) {
				add_action( 'admin_footer', array( &$this, 'load_mce_translation' ) );
			}
		}
	}

	function load_mce_translation() {
		if ( ! class_exists( '_WP_Editors' ) ) {
			require( ABSPATH . WPINC . '/class-wp-editor.php' );
		}

		$strings = _WP_Editors::wp_mce_translation();
		$strings = preg_replace( '/tinymce.addI18n[^{]+/', '', $strings );
		$strings = preg_replace( '/[^}]*$/', '', $strings );

		if ( $strings ) {
			?>
			<script type="text/javascript">var tadvTranslation = <?php echo $strings; ?>;</script>
			<?php
		}
	}

	function load_settings() {
		if ( empty( $_POST ) ) {
			$this->check_plugin_version();
		}

		if ( empty( $this->settings ) ) {
			$this->admin_settings = get_option( 'tadv_admin_settings', false );
			$this->settings = get_option( 'tadv_settings', false );
		}

		// load defaults if the options don't exist...
		if ( $this->admin_settings === false )
			$this->admin_settings = $this->default_admin_settings;

		$this->admin_options = ! empty( $this->admin_settings['options'] ) ? explode( ',', $this->admin_settings['options'] ) : array();

		if ( $this->settings === false )
			$this->settings = $this->default_settings;

		$this->options   = ! empty( $this->settings['options'] )   ? explode( ',', $this->settings['options'] )   : array();
		$this->plugins   = ! empty( $this->settings['plugins'] )   ? explode( ',', $this->settings['plugins'] )   : array();
		$this->toolbar_1 = ! empty( $this->settings['toolbar_1'] ) ? explode( ',', $this->settings['toolbar_1'] ) : array();
		$this->toolbar_2 = ! empty( $this->settings['toolbar_2'] ) ? explode( ',', $this->settings['toolbar_2'] ) : array();
		$this->toolbar_3 = ! empty( $this->settings['toolbar_3'] ) ? explode( ',', $this->settings['toolbar_3'] ) : array();
		$this->toolbar_4 = ! empty( $this->settings['toolbar_4'] ) ? explode( ',', $this->settings['toolbar_4'] ) : array();

		$this->used_buttons = array_merge( $this->toolbar_1, $this->toolbar_2, $this->toolbar_3, $this->toolbar_4 );
		$this->get_all_buttons();
	}

	// Min version 4.1-RC1
	private function check_minimum_supported_version() {
		return ( isset( $GLOBALS['wp_db_version'] ) && $GLOBALS['wp_db_version'] >= 30133 );
	}

	private function check_plugin_version() {
		$version = get_option( 'tadv_version', 0 );

		if ( ! $version || $version < 4000 ) {
			// First install or upgrade to TinyMCE 4.0
			$this->settings = $this->default_settings;
			$this->admin_settings = $this->default_admin_settings;

			update_option( 'tadv_settings', $this->settings );
			update_option( 'tadv_admin_settings', $this->admin_settings );
			update_option( 'tadv_version', 4000 );
		}

		if ( $version < 4000 ) {
			// Upgrade to TinyMCE 4.0, clean options
			$this->remove_settings();
		}
	}

	function get_all_buttons() {
		if ( ! empty( $this->all_buttons ) )
			return $this->all_buttons;

		$buttons = array(
			// Core
			'bold' => 'Bold',
			'italic' => 'Italic',
			'underline' => 'Underline',
			'strikethrough' => 'Strikethrough',
			'alignleft' => 'Align left',
			'aligncenter' => 'Align center',
			'alignright' => 'Align right',
			'alignjustify' => 'Justify',
			'styleselect' => 'Formats',
			'formatselect' => 'Paragraph',
			'fontselect' => 'Font Family',
			'fontsizeselect' => 'Font Sizes',
			'cut' => 'Cut',
			'copy' => 'Copy',
			'paste' => 'Paste',
			'bullist' => 'Bulleted list',
			'numlist' => 'Numbered list',
			'outdent' => 'Decrease indent',
			'indent' => 'Increase indent',
			'blockquote' => 'Blockquote',
			'undo' => 'Undo',
			'redo' => 'Redo',
			'removeformat' => 'Clear formatting',
			'subscript' => 'Subscript',
			'superscript' => 'Superscript',

			// From plugins
			'hr' => 'Horizontal line',
			'link' => 'Insert/edit link',
			'unlink' => 'Remove link',
			'image' => 'Insert/edit image',
			'charmap' => 'Special character',
			'pastetext' => 'Paste as text',
			'print' => 'Print',
			'anchor' => 'Anchor',
			'searchreplace' => 'Find and replace',
			'visualblocks' => 'Show blocks',
		//	'visualchars' => 'Hidden chars',
			'code' => 'Source code',
			'fullscreen' => 'Fullscreen',
			'insertdatetime' => 'Insert date/time',
			'media' => 'Insert/edit video',
			'nonbreaking' => 'Nonbreaking space',
			'table' => 'Table',
			'ltr' => 'Left to right',
			'rtl' => 'Right to left',
			'emoticons' => 'Emoticons',
			'forecolor' => 'Text color',
			'backcolor' => 'Background color',

			// Layer plugin ?
		//	'insertlayer' => 'Layer',

			// WP
			'wp_adv'		=> 'Toolbar Toggle',
			'wp_help'		=> 'Keyboard Shortcuts',
			'wp_more'		=> 'Read more...',
			'wp_page'		=> 'Page break',
		);

		// add/remove allowed buttons
		$buttons = apply_filters( 'tadv_allowed_buttons', $buttons );

		$this->all_buttons = $buttons;
		$this->buttons_filter = array_keys( $buttons );
		return $buttons;
	}

	function get_plugins( $plugins = array() ) {

		if ( ! is_array( $this->used_buttons ) ) {
			$this->load_settings();
		}

		if ( in_array( 'anchor', $this->used_buttons, true ) )
			$plugins[] = 'anchor';

		if ( in_array( 'visualchars', $this->used_buttons, true ) )
			$plugins[] = 'visualchars';

		if ( in_array( 'visualblocks', $this->used_buttons, true ) )
			$plugins[] = 'visualblocks';

		if ( in_array( 'nonbreaking', $this->used_buttons, true ) )
			$plugins[] = 'nonbreaking';

		if ( in_array( 'emoticons', $this->used_buttons, true ) )
			$plugins[] = 'emoticons';

		if ( in_array( 'insertdatetime', $this->used_buttons, true ) )
			$plugins[] = 'insertdatetime';

		if ( in_array( 'table', $this->used_buttons, true ) )
			$plugins[] = 'table';

		if ( in_array( 'print', $this->used_buttons, true ) )
			$plugins[] = 'print';

		if ( in_array( 'searchreplace', $this->used_buttons, true ) )
			$plugins[] = 'searchreplace';

		if ( in_array( 'insertlayer', $this->used_buttons, true ) )
			$plugins[] = 'layer';

		// From options
		if ( $this->check_setting( 'advlist' ) )
			$plugins[] = 'advlist';

		if ( $this->check_setting( 'advlink' ) )
			$plugins[] = 'link';

		if ( $this->check_admin_setting( 'importcss' ) )
			$plugins[] = 'importcss';

		if ( $this->check_setting( 'contextmenu' ) )
			$plugins[] = 'contextmenu';

		if ( $this->check_admin_setting( 'textpattern' ) )
			$plugins[] = 'textpattern';

		// add/remove used plugins
		$plugins = apply_filters( 'tadv_used_plugins', $plugins, $this->used_buttons );

		return array_unique( $plugins );
	}

	private function check_setting( $setting, $admin = false ) {
		if ( ! is_array( $this->options ) ) {
			$this->load_settings();
		}

		$array = $admin ? $this->admin_options : $this->options;
		return in_array( $setting, $array, true );
	}

	private function check_admin_setting( $setting ) {
		return $this->check_setting( $setting, true );
	}

	function mce_buttons_1( $original, $editor_id ) {
		if ( ! is_array( $this->options ) ) {
			$this->load_settings();
		}

		$buttons_1 = $this->toolbar_1;

		if ( is_array( $original ) && ! empty( $original ) ) {
			$original = array_diff( $original, $this->buttons_filter );
			$buttons_1 = array_merge( $buttons_1, $original );
		}

		return $buttons_1;
	}

	function mce_buttons_2( $original ) {
		if ( ! is_array( $this->options ) ) {
			$this->load_settings();
		}

		$buttons_2 = $this->toolbar_2;

		if ( is_array( $original ) && ! empty( $original ) ) {
			$original = array_diff( $original, $this->buttons_filter );
			$buttons_2 = array_merge( $buttons_2, $original );
		}

		return $buttons_2;
	}

	function mce_buttons_3( $original ) {
		if ( ! is_array( $this->options ) ) {
			$this->load_settings();
		}

		$buttons_3 = $this->toolbar_3;

		if ( is_array( $original ) && ! empty( $original ) ) {
			$original = array_diff( $original, $this->buttons_filter );
			$buttons_3 = array_merge( $buttons_3, $original );
		}

		return $buttons_3;
	}

	function mce_buttons_4( $original ) {
		if ( ! is_array( $this->options ) ) {
			$this->load_settings();
		}

		$buttons_4 = $this->toolbar_4;

		if ( is_array( $original ) && ! empty( $original ) ) {
			$original = array_diff( $original, $this->buttons_filter );
			$buttons_4 = array_merge( $buttons_4, $original );
		}

		return $buttons_4;
	}

	function mce_options( $init ) {
		if ( $this->check_admin_setting( 'no_autop' ) ) {
			$init['wpautop'] = false;
			$init['indent'] = true;
			$init['tadv_noautop'] = true;
		}

		if ( $this->check_setting('menubar') ) {
			$init['menubar'] = true;
		}

		if ( $this->check_setting('image') ) {
			$init['image_advtab'] = true;
		}

		if ( $this->check_setting( 'advlink' ) ) {
			$init['rel_list'] = '[{text: "None", value: ""}, {text: "Nofollow", value: "nofollow"}]';
		}

		if ( ! in_array( 'wp_adv', $this->toolbar_1, true ) ) {
			$init['wordpress_adv_hidden'] = false;
		}

		if ( $this->check_admin_setting( 'importcss' ) ) {
	//		$init['importcss_selector_filter'] = 'function(sel){return /^\.[a-z0-9]+$/i.test(sel);}';
			$init['importcss_file_filter'] = 'editor-style.css';
		}

		if ( $this->check_admin_setting( 'fontsize_formats' ) ) {
			$init['fontsize_formats'] =  '8px 10px 12px 14px 16px 20px 24px 28px 32px 36px';
		}

		if ( $this->check_setting( 'paste_images' ) ) {
			$init['paste_data_images'] = true;
			$init['paste_word_valid_elements'] = '-strong/b,-em/i,-span,-p,-ol,-ul,-li,-h1,-h2,-h3,-h4,-h5,-h6,-p/div,-a[href|name],' .
				'-table[width],-tr,-td[colspan|rowspan|width],-th,-thead,-tfoot,-tbody,sub,sup,strike,br,del,ins,img[src|alt|title|height|width]';
		}

		return $init;
	}

	function after_wp_tiny_mce() {
		?>
		<style type="text/css">
		.wp-fullscreen-wrap .mce-menubar { position: static !important; width: auto !important; }
		#wp-content-wrap .mce-tinymce.mce-fullscreen .mce-wp-dfw { display: none; }
		</style>
		<?php
	}

	function htmledit( $c ) {
		if ( $this->check_admin_setting( 'no_autop' ) ) {
			$c = str_replace( array('&amp;', '&lt;', '&gt;'), array('&', '<', '>'), $c );
			$c = wpautop( $c );
			$c = preg_replace( '/^<p>(https?:\/\/[^<> "]+?)<\/p>$/im', '$1', $c );
			$c = htmlspecialchars( $c, ENT_NOQUOTES, get_option( 'blog_charset' ) );
		}
		return $c;
	}

	function mce_external_plugins( $mce_plugins ) {
		// import user created editor-style.css
		if ( $this->check_admin_setting( 'editorstyle' ) ) {
			add_editor_style();
		}

		if ( ! is_array( $this->plugins ) ) {
			$this->plugins = array();
		}

		if ( $this->check_admin_setting( 'no_autop' ) ) {
			$this->plugins[] = 'wptadv';
		}

		$plugpath = TADV_URL . 'mce/';
		$mce_plugins = (array) $mce_plugins;
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		foreach ( $this->plugins as $plugin ) {
			$mce_plugins["$plugin"] = $plugpath . $plugin . "/plugin{$suffix}.js";
		}

		return $mce_plugins;
	}

	function tiny_mce_plugins( $plugins ) {
		// This calls load_settings()
		if ( $this->check_setting('image') && ! in_array( 'image', $plugins, true ) ) {
			$plugins[] = 'image';
		}

		if ( ( in_array( 'rtl', $this->used_buttons, true ) || in_array( 'ltr', $this->used_buttons, true ) ) &&
			! in_array( 'directionality', (array) $plugins, true ) ) {

			$plugins[] = 'directionality';
		}

		return $plugins;
	}

	private function parse_buttons( $toolbar_id = false, $buttons = false ) {
		if ( $toolbar_id && ! $buttons && ! empty( $_POST[$toolbar_id] ) )
			$buttons = $_POST[$toolbar_id];

		if ( is_array( $buttons ) ) {
			$_buttons = array_map( array( @$this, 'filter_name' ), $buttons );
			return implode( ',', array_filter( $_buttons ) );
		}

		return '';
	}

	private function filter_name( $str ) {
		if ( empty( $str ) || ! is_string( $str ) )
			return '';
		// Button names
		return preg_replace( '/[^a-z0-9_]/i', '', $str );
	}

	private function sanitize_settings( $settings ) {
		$_settings = array();

		if ( ! is_array( $settings ) ) {
			return $_settings;
		}

		foreach( $settings as $name => $value ) {
			$name = preg_replace( '/[^a-z0-9_]+/', '', $name );

			if ( strpos( $name, 'toolbar_' ) === 0 ) {
				$_settings[$name] = $this->parse_buttons( false, explode( ',', $value ) );
			} else if ( 'options' === $name || 'plugins' === $name || 'disabled_plugins' === $name ) {
				$_settings[$name] = preg_replace( '/[^a-z0-9_,]+/', '', $value );
			}
		}

		return $_settings;
	}

	function settings_page() {
		if ( ! defined( 'TADV_ADMIN_PAGE' ) ) {
			define( 'TADV_ADMIN_PAGE', true );
		}

		include_once( TADV_PATH . 'tadv_admin.php' );
	}

	function add_menu() {
		add_options_page( 'TinyMCE Advanced', 'TinyMCE Advanced', 'manage_options', 'tinymce-advanced', array( &$this, 'settings_page' ) );
	}
}

new Tinymce_Advanced;
endif;