=== Embed Privacy ===
Contributors: epiphyt, kittmedia, krafit
Tags: oembed, privacy, gutenberg
Requires at least: 4.7
Tested up to: 5.7
Requires PHP: 5.6
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed Privacy prevents the loading of embedded external content and allows your site visitors to opt-in.

== Description ==

Content embedded from external sites such as YouTube or Twitter is loaded immediately when visitors access your site. Embed Privacy addresses this issue and prevents the loading of these contents until the visitor decides to allow loading of external content.
But Embed Privacy not only protects your visitor's privacy but also makes your site load faster.

All embeds will be replaced by placeholders, ready for you to apply style as you wish. With only a couple of lines of CSS. 

By clicking on the placeholder the respective content will be reloaded.


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/embed-privacy` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress.
1. Embedded content will automatically be replaced by a placeholder and can be loaded on demand by your visitors. There are no additional settings.
1. To allow users to opt-out of embed providers that they set to always active, place the shortcode `[embed_privacy_opt_out]` into your privacy policy.


== Frequently Asked Questions ==

= Can Embed Privacy keep external services from tracking me/my visitors? =

Yes. As long as you don't opt in to load external content, you/your visitors can't be tracked by these services.

= Does Embed Privacy make embedding content privacy-friendly? =

The embedding process itself will be privacy-friendly with Embed Privacy. That means, that no third-party embed provider can track users without their explicit consent by clicking on the overlay to allow the embed to be loaded. However, to make sure everything is fine you need to expand your privacy policy for each embed provider you’re using or you want to use because you need to specify, where data will be sent to and what happens to them.

= Does Embed Privacy support the Gutenberg editor? =

Sure thing! We enjoy playing with the new WordPress editor and developed Embed Privacy with Gutenberg in mind, the plugin will work no matter the editor you use.

= Which embeds are currently supported? =

We currently support all oEmbed providers known to WordPress core by default. Want to know about them? Here you go: Amazon Kindle, Animoto, Cloudup, DailyMotion, Facebook, Flickr, Funny Or Die, Imgur, Instagram, Issuu, Kickstarter, Meetup, Mixcloud, Photobucket, Polldaddy.com, Reddit, ReverbNation, Scribd, Sketchfab, SlideShare, SmugMug, SoundCloud, Speaker Deck, Spotify, TikTok, TED, Tumblr, Twitter, VideoPress, Vimeo, WordPress.org, WordPress.tv, YouTube.

Since version 1.2.0, you can also add custom embed providers by going to **Settings > Embed Privacy > Manage embeds**. Here you can also modify any existing embed provider, change its logo, add a background image, change the text displaying on the embed or disable the embed provider entirely.

= Developers: How to use Embed Privacy’s methods for custom content? =

Since version 1.1.0 you can now use our mechanism for content we don’t support in our plugin. You can do it the following way:

`
/**
 * Replace specific content with the Embed Privacy overlay of type 'google-maps'.
 * 
 * @param	string		$content The content to replace
 * @return	string The updated content
 */
function prefix_replace_content_with_overlay( $content ) {
	// check for Embed Privacy
	if ( ! class_exists( 'epiphyt\Embed_Privacy\Embed_Privacy' ) ) {
		return $content;
	}
	
	// get Embed Privacy instance
	$embed_privacy = epiphyt\Embed_Privacy\Embed_Privacy::get_instance();
	
	// check if provider is always active; if so, just return the content
	if ( ! $embed_privacy->is_always_active_provider( 'google-maps' ) ) {
		// replace the content with the overlay
		$content = $embed_privacy->get_output_template( 'Google Maps', 'google-maps', $content );
	}
	
	return $content;
}
`

= Can users opt-out of already opted in embed providers? =

Yes! You can use the shortcode `[embed_privacy_opt_out]` to add a list of embed providers anywhere you want (recommendation: add it to your privacy policy) to allow your users to opt-out.

= What parameters can be used in the shortcode? =

The shortcode `[embed_privacy_opt_out]` can be used to let users opt-out of embed providers that have been set to be always active by the user. It can have the following attributes:

<code>headline</code> – Add a custom headline (default: Embed providers)

`
[embed_privacy_opt_out headline="My custom headline"]
`

<code>subline</code> – Add a custom subline (default: Enable or disable embed providers globally. By enabling a provider, its embedded content will be displayed directly on every page without asking you anymore.)

`
[embed_privacy_opt_out subline="My custom subline"]
`

<code>show_all</code> – Whether to show all available embed providers or just the ones the user opted in (default: false)

`
[embed_privacy_opt_out show_all="1"]
`

You can also combine all of these attributes:

`
[embed_privacy_opt_out headline="My custom headline" subline="My custom subline" show_all="1"]
`

= Is this plugin compatible with my caching plugin? =

If you’re using a caching plugin, make sure you enable the "JavaScript detection for active providers" in **Settings > Embed Privacy > JavaScript detection**. Then, the plugin is fully compatible with your caching plugin.

= Who are you, folks? =

We are [Epiphyt](https://epiph.yt/), your friendly neighborhood WordPress plugin shop from southern Germany.


== Changelog ==

= 1.3.0 =
* Added local tweets without overlay
* Added option to preserve data on uninstall
* Added compatibility with theme Astra
* Added filter `embed_privacy_markup` for filtering the whole markup of an embed overlay
* Added proper support for embeds on the current domain
* Added support for embeds on other elements than `embed`, `iframe` and `object`
* Enqueue assets only if needed
* Removed images from media (which had been added in version 1.2.0) and use fallback images for default embed providers
* Improved regular expression for Google Maps
* Improved texts for clarity
* Fixed visibility of custom post type
* Fixed network-wide activation
* Fixed clearing oEmbed cache

= 1.2.2 =
* Added a check if a migration is already running
* Fixed a bug where the page markup could be changed unexpectedly
* `<object>` elements are now replaced correctly
* Added a missing textdomain to a string
* Excluded local embeds (with the same domain)
* Fixed Amazon Kindle regex being too greedy

= 1.2.1 =
* Fixed a bug where the page markup could be changed unexpectedly
* Fixed a warning if an embed provider has no regular expressions
* Improved migrations of embed provider metadata to make sure they have been added to the database

= 1.2.0 =
* Added support for managing embeds (add/remove/edit/disable)
* Added support for caching plugins by adding a JavaScript detection for always active embed providers
* Added CSS classes that indicate the current state of the embed (`is-disabled`/`is-enabled`)
* Added shortcode `[embed_privacy_opt_out]` to allow users to opt-out/in
* Fixed responsive design if the embed added an own width

= 1.1.3 =
* Changed provider name from Polldaddy to Crowdsignal
* Removed provider Hulu

= 1.1.2 =
* Fixed a possible difference in the used class name of the embed provider in HTML and CSS

= 1.1.1 =
* Removed provider CollegeHumor
* Fixed a bug with the automatic addition of paragraphs

= 1.1.0 =
* Added option to allow all embeds by one provider
* Added provider TikTok, introduced in WordPress 5.4
* Added support for Google Maps iframes
* Added URL rewrite to youtube-nocookie.com
* Added option to save user selection per embed provider
* Added provider logo to our placeholder
* Added option to filter our placeholders markup
* Added support for 'alignwide' and 'alignfull' Gutenberg classes
* Added support for using our embedding overlay mechanism for external developers
* Improved our placeholder markup to be actually semantic
* Changed .embed- classes to .embed-privacy-
* Fixed some embed providers that use custom z-index, which results in the embedded content being above the overlay
* Fixed typos

= 1.0.2 =
* Improved compatibility with [Autoptimize](https://wordpress.org/plugins/autoptimize/)
* Improved compatibility with [AMP](https://wordpress.org/plugins/amp/)
* Fix issue with Slideshare causing wrong (generic) placeholders

= 1.0.1 =
* Fixed support for PHP 5.6

= 1.0.0 =
* Initial release

== Upgrade Notice ==
