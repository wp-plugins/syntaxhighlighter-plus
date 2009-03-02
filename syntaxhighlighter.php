<?php /*

**************************************************************************

Plugin Name:      SyntaxHighlighter Plus
Plugin URI:       http://thislab.com/2007/12/16/release-wordpress-plugin-syntaxhighlighter-plus/
Version:          1.0b2
Description:      An advanced upload-and-activate WordPress implementation of Alex Gorbatchev's <a href="http://code.google.com/p/syntaxhighlighter/">SyntaxHighlighter</a> JavaScript code highlighting package. See WordPress.com's "<a href="http://faq.wordpress.com/2007/09/03/how-do-i-post-source-code/">How do I post source code?</a>" for details. <strong><a href="options-general.php?page=syntaxhighlighter-plus/syntaxhighlighter.php">Click here for options</a></strong>.
Author:           <a href="http://thislab.com/">Fred Wu</a>
Original Authors: <a href="http://photomatt.net/">Matt</a>, <a href="http://www.viper007bond.com/">Viper007Bond</a>, and <a href="http://blogwaffe.com/">mdawaffe</a>

**************************************************************************

Credits:

* Matt ( photomatt.net ) -- original concept and code on WP.com
* Viper007Bond ( viper007bond.com ) -- current plugin version

Simply put, Matt deserves the majority of the credit for this plugin.
I (Viper007Bond) just took the plugin I had already written (it looked a
lot like this current one) after seeing his code operate on WP.com and
incorporated his ingenius TinyMCE handling and some other misc. code.

**************************************************************************/

class AGSyntaxHighlighter {
	var $languages = array();
	var $aliases = array();
	var $languagesregex;
	var $jsfiles2load = array();
	var $pluginurl;
	var $kses_active = array();
	var $kses_filters = array();
	var $widget_format_to_edit = false;
	var $default_language = 'php';

	// WordPress hooks
	function AGSyntaxHighlighter() {
		add_action( 'init', array(&$this, 'SetVariables'), 1000 );
		add_action( 'wp_head', array(&$this, 'AddStylesheet'), 1000 );
		add_action( 'admin_head', array(&$this, 'AddStylesheet'), 1000 );
		add_action( 'wp_footer', array(&$this, 'FileLoader'), 1000 );
		add_action( 'admin_footer', array(&$this, 'FileLoader'), 1000 ); // For viewing comments in admin area

		// admin menu
		add_action( 'admin_menu', array(&$this, 'syntaxhighlighterplus_admin_menu') );

		// Find and replace the BBCode
		add_filter( 'the_content', array(&$this, 'BBCodeToHTML'), 8 );
		add_filter( 'widget_text', array(&$this, 'BBCodeToHTML'), 8 );

		// Account for kses
		add_filter( 'content_save_pre', array(&$this, 'before_kses_normalization'), 1 );
		add_filter( 'content_save_pre', array(&$this, 'after_kses_normalization'), 11 );
		add_action( 'admin_head', array(&$this, 'before_kses_normalization_widget'), 1 );
		add_action( 'update_option_widget_text', array(&$this, 'after_kses_normalization_widget'), 1, 2 );
		add_filter( 'format_to_edit', array(&$this, 'after_kses_normalization_widget_format_to_edit'), 1 );

		// Account for TinyMCE
		add_filter( 'content_save_pre', array(&$this, 'TinyMCEDecode'), 8 );
		add_filter( 'the_editor_content', array(&$this, 'TinyMCEEncode'), 8 );

		// Uncomment these next lines to allow commenters to post code
		//add_filter( 'comment_text', array(&$this, 'BBCodeToHTML'), 8 );
		//add_filter( 'pre_comment_content', array(&$this, 'before_kses_normalization_comment'), 1 );
		//add_filter( 'pre_comment_content', array(&$this, 'after_kses_normalization_comment'), 11 );
	}

	function syntaxhighlighterplus_admin_menu() {
		if ( function_exists('add_options_page') )
			add_options_page(__('SyntaxHighlighter Plus Configuration'), __('SyntaxHighlighter Plus'), 8, __FILE__, array(&$this, 'syntaxhighlighterplus_config'));
	}

	function syntaxhighlighterplus_config() {
		$themes = scandir(ABSPATH . PLUGINDIR . '/syntaxhighlighter-plus/syntaxhighlighter/styles/');
		$options = get_option('syntaxhighlighterplus_options');

		if ( isset($_POST['Submit']) ) {
			check_admin_referer('syntaxhighlighterplus-update-options');
			$options['theme'] = $_POST['theme'];
			update_option('syntaxhighlighterplus_options', $options);
			echo '<div id="message" class="updated fade"><p><strong>' . __('Settings saved.') . '</strong></p></div>';
		}
		?>
		<div class="wrap">
			<h2><?php _e('SyntaxHighlighter Plus Configuration'); ?>.</h2>
			<form action="" method="post" id="syntaxhighlighter-plus" accept-charset="utf-8">
				<h3>Choose a theme</h3>
				<select name="theme" id="theme">
				<?php
					foreach ( (array) $themes as $theme ) {
						if (substr($theme, -3) == 'css' && $theme != 'shCore.css') {
							$selected = $theme == $options['theme'] ? ' selected="selected"' : '';
							echo '<option value="' . attribute_escape($theme) . '"' . $selected . '>' . attribute_escape($theme) . '</option>'."\n";
						}
					}
				?>
				</select>&nbsp;&nbsp;<a href="http://alexgorbatchev.com/wiki/SyntaxHighlighter:Themes" target="_blank">Theme Previews</a>
				<?php wp_nonce_field('syntaxhighlighterplus-update-options'); ?>
				<p class="submit"><input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes') ?>"/></p>
			</form>
		</div>
		<?php
	}

	// Set some variables now that we've given all other plugins a chance to load
	function SetVariables() {
		$wpurl = is_admin() && is_ssl() ? str_replace( 'http://', 'https://', get_bloginfo( 'wpurl' ) ) : get_bloginfo( 'wpurl' );
		$this->pluginurl = apply_filters( 'agsyntaxhighlighter_url', $wpurl . '/wp-content/plugins/syntaxhighlighter-plus/' );
		
		$this->aliases = apply_filters( 'agsyntaxhighlighter_aliases', array(
			'Bash'    => array('bash', 'sh', 'shell'),
			'Cpp'     => array('cpp', 'c', 'c++'),
			'CSharp'  => array('c#', 'c-sharp', 'csharp'),
			'Css'     => array('css'),
			'Delphi'  => array('delphi', 'pascal'),
			'Diff'    => array('diff', 'patch'),
			'Groovy'  => array('groovy'),
			'Java'    => array('java'),
			'JScript' => array('js', 'jscript', 'javascript'),
			'Perl'    => array('perl', 'pl'),
			'Php'     => array('php'),
			'Plain'   => array('', 'plain', 'text'),
			'Python'  => array('py', 'python'),
			'Ruby'    => array('rb', 'ruby', 'rails', 'ror'),
			'Scala'   => array('scala'),
			'Sql'     => array('sql'),
			'Vb'      => array('vb', 'vbnet', 'vb.net'),
			'Xml'     => array('xml', 'html', 'xhtml', 'xslt'),
		) );
		
		// Define all allowed languages and allow plugins to modify this
		$languages = array();
		foreach ($this->aliases as $lang => $aliases)
		{
			foreach ($aliases as $alias)
			{
				$languages[$alias] = "shBrush{$lang}.js";
			}
		}
		$this->languages = apply_filters( 'agsyntaxhighlighter_languages', $languages);

		// Quote them to make them regex safe
		$languages = array();
		foreach ( $this->languages as $language => $filename ) $languages[] = preg_quote( $language );

		// Generate the regex for them
		$this->languagesregex = '(' . implode( '|', $languages ) . ')';

		$this->kses_filters = apply_filters( 'agsyntaxhighlighter_kses_filters', array(
			'wp_filter_kses',
			'wp_filter_post_kses',
			'wp_filter_nohtml_kses'
		) );
	}


	// We need to stick the stylesheet in the header for best results
	function AddStylesheet() {
		echo '	<link type="text/css" rel="stylesheet" href="' . $this->pluginurl . 'syntaxhighlighter/styles/shCore.css"></link>' . "\n";

		$options = get_option('syntaxhighlighterplus_options');
		$options['theme'] = isset($options['theme']) ? $options['theme'] : 'shThemeDefault.css';

		echo '	<link type="text/css" rel="stylesheet" href="' . $this->pluginurl . 'syntaxhighlighter/styles/' . attribute_escape($options['theme']) . '"></link>' . "\n";
	}


	// This function checks for the BBCode cheaply so we don't waste CPU cycles on regex if it's not needed
	// It's in a seperate function since it's used in mulitple places (makes it easier to edit)
	function CheckForBBCode( $content ) {
		if ( stristr( $content, '[sourcecode' ) && stristr( $content, '[/sourcecode]' ) ) return TRUE;
		if ( stristr( $content, '[source' ) && stristr( $content, '[/source]' ) ) return TRUE;
		if ( stristr( $content, '[code' ) && stristr( $content, '[/code]' ) ) return TRUE;
		if ( stristr( $content, '[lang' ) && stristr( $content, '[/lang]' ) ) return TRUE;
		
		foreach ($this->aliases as $lang => $aliases)
		{
			foreach ($aliases as $alias)
			{
				if ( stristr( $content, '['.$alias ) && stristr( $content, '[/'.$alias.']' ) ) return TRUE;
			}
		}

		return FALSE;
	}


	// This function is a wrapper for preg_match_all() that grabs all BBCode calls
	// It's in a seperate function since it's used in mulitple places (makes it easier to edit)
	function GetBBCode( $content, $addslashes = FALSE ) {
		$regex = '/\[(sourcecode|source|code|lang|)( language=| lang=|=|)';
		if ( $addslashes ) $regex .= '\\\\';
		$regex .= '([\'"]|)' . $this->languagesregex;
		if ( $addslashes ) $regex .= '\\\\';
		$regex .= '\3\](.*?)\[\/(\1|\4)\]/si';

		preg_match_all( $regex, $content, $matches, PREG_SET_ORDER );
		
		return $matches;
	}


	/* If KSES is going to hit this text, we double encode stuff within the [sourcecode] tags to keep 
	 * 	wp_kses_normalize_entities from breaking them.
	 * $content = text to parse
	 * $which_filter = which filter to check to see if kses will be applied
	 * $addslashes = used by AGSyntaxHighlighter::GetBBCode
	 */
	function before_kses_normalization( $content, $which_filter = 'content_save_pre', $addslashes = true ) {
		global $wp_filter;
		if ( is_string($which_filter) && !isset($this->kses_active[$which_filter]) ) {
			$this->kses_active[$which_filter] = false;
			$filters = $wp_filter[$which_filter];
			foreach ( (array) $filters as $priority => $filter ) {
				foreach ( $filter as $k => $v ) {
					if ( in_array( $filter[$k]['function'], $this->kses_filters ) ) {
						$this->kses_active[$which_filter] = true;
						break 2;
					}
				}
			}
		}

		if ( ( true === $which_filter || $this->kses_active[$which_filter] ) && $this->CheckForBBCode( $content ) ) {
			$matches = $this->GetBBCode( $content, $addslashes );
			foreach( (array) $matches as $match )
				$content = str_replace( $match[5], htmlspecialchars( $match[5], ENT_QUOTES ), $content );
		}
		return $content;
	}


	/* We undouble encode the stuff within [sourcecode] tags to fix the output of
	 * 	AGSyntaxHighlighter::before_kses_normalization.
	 */
	function after_kses_normalization( $content, $which_filter = 'content_save_pre', $addslashes = true ) {
		if ( ( true === $which_filter || $this->kses_active[$which_filter] ) && $this->CheckForBBCode( $content ) ) {
			$matches = $this->GetBBCode( $content, $addslashes );
			foreach( (array) $matches as $match )
				$content = str_replace( $match[5], htmlspecialchars_decode( $match[5], ENT_QUOTES ), $content );
		}
		return $content;
	}


	// Wrapper for comment text
	function before_kses_normalization_comment( $content ) {
		return $this->before_kses_normalization( $content, 'pre_comment_content' );
	}


	function after_kses_normalization_comment( $content ) {
		return $this->after_kses_normalization( $content, 'pre_comment_content' );
	}


	/* "Wrapper" for widget text.  Since we lack the necessary filters, we directly alter the
	 * 	submitted $_POST variables before the widgets are updated.
	 */
	function before_kses_normalization_widget() {
		global $pagenow;
		if ( 'widgets.php' != $pagenow || current_user_can( 'unfiltered_html' ) )
			return;

		$i = 1;
		while ( isset($_POST["text-submit-$i"]) ) {
			$_POST["text-text-$i"] = $this->before_kses_normalization( $_POST["text-text-$i"], true );
			$i++;
		}
	}

	// Again, since we lack the needed filters, we have to check the freshly updated option and re-update it.
	function after_kses_normalization_widget( $old, $new ) {
		static $do_update = true;

		if ( !$do_update || current_user_can( 'unfiltered_html' ) )
			return;

		foreach ( array_keys($new) as $i => $widget )
			$new[$i]['text'] = $this->after_kses_normalization( $new[$i]['text'], true, false );

		$do_update = false;

		update_option( 'widget_text', $new );
		$this->widget_format_to_edit = true;

		$do_update = true;
	}

	// Totally lame.  The output of the widget form in the admin screen is cached from before our re-update.
	function after_kses_normalization_widget_format_to_edit( $content ) {
		if ( !$this->widget_format_to_edit )
			return $content;

		$content = $this->after_kses_normalization( $content, true, false );

		$this->widget_format_to_edit = false;

		return $content;
	}

	// Reverse changes TinyMCE made to the entered code
	function TinyMCEDecode( $content ) {
		if ( !user_can_richedit() || !$this->CheckForBBCode( $content ) ) return $content;

		// Find all BBCode (remember, it's all slash escaped!)
		$matches = $this->GetBBCode( $content, TRUE );

		if ( empty($matches) ) return $content; // No BBCode found, we can stop here

		// Loop through each match and decode the code
		foreach ( (array) $matches as $match ) {
			$content = str_replace( $match[5], htmlspecialchars_decode( $match[5] ), $content );
		}

		return $content;
	}


	// (Re)Encode the code so TinyMCE will display it correctly
	function TinyMCEEncode( $content ) {
		if ( !user_can_richedit() || !$this->CheckForBBCode( $content ) ) return $content;

		$matches = $this->GetBBCode( $content );

		if ( empty($matches) ) return $content; // No BBCode found, we can stop here

		// Loop through each match and encode the code
		foreach ( (array) $matches as $match ) {
			$code = htmlspecialchars( $match[5] );
			$code = str_replace( '&amp;', '&amp;amp;', $code );
			$code = str_replace( '&amp;lt;', '&amp;amp;lt;', $code );
			$code = str_replace( '&amp;gt;', '&amp;amp;gt;', $code );

			$content = str_replace( $match[5], $code, $content );
		}

		return $content;
	}


	// The meat of the plugin. Find all valid BBCode calls and replace them with HTML for the Javascript to handle.
	function BBCodeToHTML( $content ) {
		if ( !$this->CheckForBBCode( $content ) ) return $content;

		$matches = $this->GetBBCode( $content );

		if ( empty($matches) ) return $content; // No BBCode found, we can stop here

		// Loop through each match and replace the BBCode with HTML
		foreach ( (array) $matches as $match ) {
			$language = $match[4] == '' ? $this->default_language : strtolower( $match[4] );
			$content = str_replace( $match[0], '<pre class="brush: ' . $language . "\">" . htmlspecialchars( $match[5], ENT_QUOTES ) . "</pre>", $content );
			$this->jsfiles2load[$language] = $this->languages[$language];
		}
		
		return $content;
	}
	
	// Output the HTML to load all of SyntaxHighlighter's Javascript, CSS, and SWF files
	function FileLoader() {
		?>

<!-- SyntaxHighlighter Stuff -->
<script type="text/javascript" src="<?php echo $this->pluginurl; ?>syntaxhighlighter/src/shCore.js"></script>
<?php foreach ( $this->jsfiles2load as $lang => $filename ) : ?>
	<script type="text/javascript" src="<?php echo $this->pluginurl . 'syntaxhighlighter/scripts/' . $filename; ?>"></script>
	<!-- Reassign aliases -->
	<script type="text/javascript">
		<?php $langName = substr($filename, 7, -3); // default language name in the filename ?>
		SyntaxHighlighter.brushes.<?php echo $langName == 'Css' ? 'CSS' : $langName ?>.aliases = ["<?php echo implode('", "', $this->aliases[$langName]) ?>"];
	</script>
<?php endforeach; ?>
<script type="text/javascript">
	SyntaxHighlighter.all();
</script>

<?php
	}
}

// Initiate the plugin class
$AGSyntaxHighlighter = new AGSyntaxHighlighter();

// For those poor souls stuck on PHP4
if ( !function_exists( 'htmlspecialchars_decode' ) ) {
	function htmlspecialchars_decode( $string, $quote_style = ENT_COMPAT ) {
		return strtr( $string, array_flip( get_html_translation_table( HTML_SPECIALCHARS, $quote_style) ) );
	}
}

// WP < 2.6.0 Back-Compat
if ( !function_exists( 'is_ssl' ) ) {
	function is_ssl() {
		if ( isset($_SERVER['HTTPS']) ) {
			if ( 'on' == strtolower($_SERVER['HTTPS']) )
				return true;
			if ( '1' == $_SERVER['HTTPS'] )
				return true;
		} elseif ( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
			return true;
		}
		return false;
	}
}
?>