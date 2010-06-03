=== SyntaxHighlighter Plus ===
Contributors: FredWu
Tags: source, code, sourcecode, php, javascript, xhtml, html, css, syntax, highlight, highlighter
Requires at least: 2.0
Tested up to: 2.7.x
Stable tag: trunk

Easily post source code such as PHP or HTML and display it in a styled box.

== Description ==

SyntaxHighlighter Plus is a Wordpress plugin for code syntax highlighting. It is an enhanced version of the original SyntaxHighlighter by Matt, Viper007Bond and mdawaffe. Please give them a big applause for making such a great plugin!

SyntaxHighlighter allows you to easily post syntax highlighted code all without loosing it's formatting or making an manual changes.

It supports the following languages (the alias for use in the post is listed next to the name):

* Bash -- `bash`, `sh`
* C++ -- `cpp`, `c`, `c++`
* C# -- `c#`, `c-sharp`, `csharp`
* CSS -- `css`
* Delphi -- `delphi`, `pascal`
* Diff -- `diff`
* Groovy -- `groovy`
* Java -- `java`
* JavaScript -- `js`, `jscript`, `javascript`
* Perl -- `perl`, `pl`
* PHP -- `php`
* Plain text -- `plain`, `text`
* Python -- `py`, `python`
* Ruby -- `rb`, `ruby`, `rails`, `ror`
* Scala -- `scala`
* SQL -- `sql`
* VB -- `vb`, `vb.net`
* XML/HTML -- `xml`, `html`, `xhtml`, `xslt`

This plugin uses the [SyntaxHighlighter JavaScript package by Alex Gorbatchev](http://code.google.com/p/syntaxhighlighter/).

== Installation ==

###Updgrading From A Previous Version###

To upgrade from a previous version of this plugin, delete the entire folder and files from the previous version of the plugin and then follow the installation instructions below.

* Starting from Wordpress 2.7, plugin upgrades are handled by Wordpress, there is no need to manually upload files.

Important: If you have made any changes to the CSS file, please make sure to back it up before upgrade!

###Uploading The Plugin###

Extract all files from the ZIP file, making sure to keep the file structure intact, and then upload it to `/wp-content/plugins/`.

This should result in the following file structure:

`- wp-content
    - plugins
        - syntaxhighlighter-plus
            | readme.txt
            | syntaxhighlighter.php
            - syntaxhighlighter
                - scripts
                    | clipboard.swf
                    | shBrushBash.js
                    | shBrushCpp.js
                    | shBrushCSharp.js
                    | [...]
                    | shCore.js
	                | shLegacy.js
                - src
                    | shCore.js
	                | shLegacy.js
                - styles
                    | SyntaxHighlighter.css`

**See Also:** ["Installing Plugins" article on the WP Codex](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins)

###Plugin Activation###

Go to the admin area of your WordPress install and click on the "Plugins" menu. Click on "Activate" for the "SyntaxHighlighter" plugin.

###Plugin Usage###

Just wrap your code in `[sourcecode language='css']code here[/sourcecode]`. The language attribute is **required**! See the [plugin's description](http://wordpress.org/extend/plugins/syntaxhighlighter/) for a list of valid language attributes.

== Frequently Asked Questions ==

= The BBCode in my post is being replaced with &lt;pre&gt;'s just fine, but I don't see the syntax highlighting! =

Make sure your theme's footer has `<?php wp_footer(); ?>` somewhere in it, otherwise the JavaScript highlighting files won't be loaded.

= I still see the BBCode in my post. What gives? =

Make sure you correctly use the BBCode with a valid language attribute. A malformed usage of it won't result in replacement.

= I use the visual editor, my code has lots of line breaks inserted, what do I do? =

Use 'shift + return' instead of 'return' when changing lines.

== Screenshots ==

[SyntaxHighlighter Themes](http://alexgorbatchev.com/wiki/SyntaxHighlighter:Themes)

== Other BBCode Methods ==

Find `[sourcecode language='css']code here[/sourcecode]` too long to type? Here's some alternative examples:

* `[source language='css']code here[/source]`
* `[code language='css']code here[/code]`
* `[sourcecode lang='css']code here[/sourcecode]`
* `[source lang='css']code here[/source]`
* `[code lang='css']code here[/code]`
* `[sourcecode='css']code here[/sourcecode]`
* `[source='css']code here[/source]`
* `[code='css']code here[/code]`
* `[lang='css']code here[/lang]`
* `[css]code here[/css]` (or any of the supported language)

Note: Quotation marks around the language can be omitted for even shorter and neater syntax. :-)

== ChangeLog ==

**Version 1.0b2**

* Upgraded SyntaxHighlighter to 2.0.296
* Added automatic SSL detection (thanks to g30rg3_x)
* Added anti-XSS and XSRF (thanks to g30rg3_x)
* Language aliases fix
* Code clean up

**Version 1.0b1**

* Upgraded the core engine to SyntaxHighlighter 2.0
* Added configuration: themes

**Version 0.18**

* Added PHP as the default language if no language is specified (e.g. `[code][/code]`)

**Version 0.17**

* Fixed a bug in the Bash highlighting code which caused conflicts between keywords and variables highlights

**Version 0.16**

* Ported SyntaxHighlighter 1.1.1 changes: 'Encode single quotes so `wptexturize()` doesn't transform them into fancy quotes and screw up code.'

**Version 0.15b**

* CSS fixes for IE6

**Version 0.15a**

* enhanced CSS

**Version 0.15**

* Fixed the IE scroll bar issue (backup your CSS file before upgrade!)

**Version 0.14**

* Added support for Bash (thanks to [Nick Anderson](http://www.cmdln.org/2008/04/07/syntaxhighlighter-plus-patch/))
* Now supports Wordpress MU (thanks to [Tim](http://tim.diary.tw/2008/03/05/syntaxhighlighter-plus/))

**Version 0.13**

* Changed plugin folder to 'syntaxhighlighter-plus'.

**Version 0.12 (broken)**

* Appeared on the Wordpress plugins site.
* Fixed readme.txt.
* Cleaned up the files.

**Version 0.11**

* Added more syntax variants.

**Version 0.10**

* Initial release!