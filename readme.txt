=== AI Provider for HeyGen ===
Contributors: WordPress AI Team
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://spdx.org/licenses/GPL-2.0-or-later.html

Adds HeyGen's AI video generation to your WordPress AI capabilities.

== Description ==

This plugin integrates HeyGen's AI video generation service into WordPress through the AI Client, enabling
text-to-video generation and avatar-based video creation capabilities.

HeyGen allows you to generate AI-powered videos from text prompts using the Video Agent API, or produce
branded avatar videos using any of HeyGen's 175+ built-in avatars.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/ai-provider-for-hey-gen/` directory, or install the
   plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your HeyGen API key via the `HEYGEN_API_KEY` environment variable or constant.

== Requirements ==

- WordPress 6.9 or later
- PHP 7.4 or later
- HeyGen API key from https://app.heygen.com/settings?nav=API
- PHP AI Client (included in WordPress 7.0+, or install separately)

== Frequently Asked Questions ==

= What is HeyGen? =

HeyGen is an AI video generation platform that lets you create professional videos featuring AI avatars
from a simple text prompt.

= How do I get an API key? =

Visit https://app.heygen.com/settings?nav=API to generate a HeyGen API key.

= What models are available? =

The `heygen-video-agent` model uses HeyGen's Video Agent API, which automatically selects an avatar and
voice from your text prompt. Individual avatar models are also listed and correspond to HeyGen avatars
available on your account.

= How does video generation work? =

Video generation is asynchronous. After submitting a request, the plugin polls the HeyGen status endpoint
until the video is ready (up to 5 minutes). The result contains the URL of the completed video.

= Can I specify a voice for avatar videos? =

Yes. Pass a `voice_id` in the model config's `customOptions` array to specify which HeyGen voice to use
when generating avatar-based videos.

== Changelog ==

= 1.0.0 =
* Initial release
