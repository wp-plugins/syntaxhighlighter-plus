<?php /*

**************************************************************************

Plugin Name:     SyntaxHighlighter Plus
Plugin URI:      http://thislab.com/2007/12/16/release-wordpress-plugin-syntaxhighlighter-plus/
Version:         0.15
Description:     An advanced upload-and-activate WordPress implementation of Alex Gorbatchev's <a href="http://code.google.com/p/syntaxhighlighter/">SyntaxHighlighter</a> JavaScript code highlighting package. See WordPress.com's "<a href="http://faq.wordpress.com/2007/09/03/how-do-i-post-source-code/">How do I post source code?</a>" for details.
Author:          <a href="http://thislab.com/">Fred Wu</a>
Original Author: <a href="http://photomatt.net/">Matt</a>, <a href="http://www.viper007bond.com/">Viper007Bond</a>, and <a href="http://blogwaffe.com/">mdawaffe</a>

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
	var $languagesregex;
	var $jsfiles2load = array();
	var $pluginurl;
	var $kses_active = array();
	var $kses_filters = array();
	var $widget_format_to_edit = false;

	// WordPress hooks
	function AGSyntaxHighlighter() {
		add_action( 'init', array(&$this, 'SetVariables'), 1000 );
		add_action( 'wp_head', array(&$this, 'AddStylesheet'), 1000 );
		add_action( 'admin_head', array(&$this, 'AddStylesheet'), 1000 );
		add_action( 'wp_footer', array(&$this, 'FileLoader'), 1000 );
		add_action( 'admin_footer', array(&$this, 'FileLoader'), 1000 ); // For viewing comments in admin area

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


	// Set some variables now that we've given all other plugins a chance to load
	function SetVariables() {
		$this->pluginurl = apply_filters( 'agsyntaxhighlighter_url', get_bloginfo( 'wpurl' ) . '/wp-content/plugins/syntaxhighlighter-plus/shlfiles/' );

		// Define all allowed languages and allow plugins to modify this
		$this->languages = apply_filters( 'agsyntaxhighlighter_languages', array(
			'bash'       => 'shBrushBash.js',
			'sh'         => 'shBrushBash.js',
			'cpp'        => 'shBrushCpp.js',
			'c'          => 'shBrushCpp.js',
			'c++'        => 'shBrushCpp.js',
			'c#'         => 'shBrushCSharp.js',
			'c-sharp'    => 'shBrushCSharp.js',
			'csharp'     => 'shBrushCSharp.js',
			'css'        => 'shBrushCss.js',
			'delphi'     => 'shBrushDelphi.js',
			'pascal'     => 'shBrushDelphi.js',
			'java'       => 'shBrushJava.js',
			'js'         => 'shBrushJScript.js',
			'jscript'    => 'shBrushJScript.js',
			'javascript' => 'shBrushJScript.js',
			'php'        => 'shBrushPhp.js',
			'py'         => 'shBrushPython.js',
			'python'     => 'shBrushPython.js',
			'rb'         => 'shBrushRuby.js',
			'ruby'       => 'shBrushRuby.js',
			'rails'      => 'shBrushRuby.js',
			'ror'        => 'shBrushRuby.js',
			'sql'        => 'shBrushSql.js',
			'vb'         => 'shBrushVb.js',
			'vb.net'     => 'shBrushVb.js',
			'xml'        => 'shBrushXml.js',
			'html'       => 'shBrushXml.js',
			'xhtml'      => 'shBrushXml.js',
			'xslt'       => 'shBrushXml.js',
		) );

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
		echo '	<link type="text/css" rel="stylesheet" href="' . $this->pluginurl . 'SyntaxHighlighter.css"></link>' . "\n";
	}


	// This function checks for the BBCode cheaply so we don't waste CPU cycles on regex if it's not needed
	// It's in a seperate function since it's used in mulitple places (makes it easier to edit)
	function CheckForBBCode( $content ) {
		if ( stristr( $content, '[sourcecode' ) && stristr( $content, '[/sourcecode]' ) ) return TRUE;
		if ( stristr( $content, '[source' ) && stristr( $content, '[/source]' ) ) return TRUE;
		if ( stristr( $content, '[code' ) && stristr( $content, '[/code]' ) ) return TRUE;
		if ( stristr( $content, '[lang' ) && stristr( $content, '[/lang]' ) ) return TRUE;
		
		if ( stristr( $content, '[bash' ) && stristr( $content, '[/bash]' ) ) return TRUE;
		if ( stristr( $content, '[sh' ) && stristr( $content, '[/sh]' ) ) return TRUE;
		if ( stristr( $content, '[cpp' ) && stristr( $content, '[/cpp]' ) ) return TRUE;
		if ( stristr( $content, '[c++' ) && stristr( $content, '[/c++]' ) ) return TRUE;
		if ( stristr( $content, '[c#' ) && stristr( $content, '[/c#]' ) ) return TRUE;
		if ( stristr( $content, '[c-sharp' ) && stristr( $content, '[/c-sharp]' ) ) return TRUE;
		if ( stristr( $content, '[csharp' ) && stristr( $content, '[/csharp]' ) ) return TRUE;
		if ( stristr( $content, '[css' ) && stristr( $content, '[/css]' ) ) return TRUE;
		if ( stristr( $content, '[delphi' ) && stristr( $content, '[/delphi]' ) ) return TRUE;
		if ( stristr( $content, '[pascal' ) && stristr( $content, '[/pascal]' ) ) return TRUE;
		if ( stristr( $content, '[java' ) && stristr( $content, '[/java]' ) ) return TRUE;
		if ( stristr( $content, '[js' ) && stristr( $content, '[/js]' ) ) return TRUE;
		if ( stristr( $content, '[jscript' ) && stristr( $content, '[/jscript]' ) ) return TRUE;
		if ( stristr( $content, '[javascript' ) && stristr( $content, '[/javascript]' ) ) return TRUE;
		if ( stristr( $content, '[php' ) && stristr( $content, '[/php]' ) ) return TRUE;
		if ( stristr( $content, '[py' ) && stristr( $content, '[/py]' ) ) return TRUE;
		if ( stristr( $content, '[python' ) && stristr( $content, '[/python]' ) ) return TRUE;
		if ( stristr( $content, '[rb' ) && stristr( $content, '[/rb]' ) ) return TRUE;
		if ( stristr( $content, '[ruby' ) && stristr( $content, '[/ruby]' ) ) return TRUE;
		if ( stristr( $content, '[rails' ) && stristr( $content, '[/rails]' ) ) return TRUE;
		if ( stristr( $content, '[ror' ) && stristr( $content, '[/ror]' ) ) return TRUE;
		if ( stristr( $content, '[sql' ) && stristr( $content, '[/sql]' ) ) return TRUE;
		if ( stristr( $content, '[vb' ) && stristr( $content, '[/vb]' ) ) return TRUE;
		if ( stristr( $content, '[vb.net' ) && stristr( $content, '[/vb.net]' ) ) return TRUE;
		if ( stristr( $content, '[xml' ) && stristr( $content, '[/xml]' ) ) return TRUE;
		if ( stristr( $content, '[html' ) && stristr( $content, '[/html]' ) ) return TRUE;
		if ( stristr( $content, '[xhtml' ) && stristr( $content, '[/xhtml]' ) ) return TRUE;
		if ( stristr( $content, '[xslt' ) && stristr( $content, '[/xslt]' ) ) return TRUE;

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
			$language = strtolower( $match[4] );
			$content = str_replace( $match[0], '<pre class="syntax-highlight:' . $language . "\">" . htmlspecialchars( $match[5] ) . "</pre>", $content );
			$this->jsfiles2load[$this->languages[$language]] = TRUE;
		}
		
		return $content;
	}


	// Output the HTML to load all of SyntaxHighlighter's Javascript, CSS, and SWF files
	function FileLoader() {
		?>

<!-- SyntaxHighlighter Stuff -->
<script type="text/javascript" src="<?php echo $this->pluginurl; ?>shCore.js"></script>
<?php foreach ( $this->jsfiles2load as $filename => $foobar ) : ?>
<script type="text/javascript" src="<?php echo $this->pluginurl . $filename; ?>"></script>
<?php endforeach; ?>
<script type="text/javascript">
	dp.SyntaxHighlighter.ClipboardSwf = '<?php echo $this->pluginurl; ?>clipboard.swf';
	dp.SyntaxHighlighter.HighlightAll('code');
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

?>
