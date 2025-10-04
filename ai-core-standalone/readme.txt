=== AI-Core - Universal AI Integration Hub ===
Contributors: opacewebdesign
Tags: ai, openai, claude, gemini, grok
Requires at least: 5.0
Tested up to: 6.8.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Centralised AI integration hub for WordPress. Manage API keys for OpenAI, Anthropic Claude, Google Gemini, and xAI Grok in one place.

== Description ==

**AI-Core** is the universal AI integration hub for WordPress that simplifies AI provider management. Configure your API keys once, and all compatible plugins automatically gain access to OpenAI, Anthropic Claude, Google Gemini, and xAI Grok.

= Key Features =

* **Centralised API Key Management** - Configure all your AI provider API keys in one place
* **Multiple Provider Support** - OpenAI, Anthropic Claude, Google Gemini, and xAI Grok
* **Automatic Integration** - Add-on plugins automatically use your configured API keys
* **Usage Statistics** - Track API usage, tokens, and costs across all providers
* **API Key Testing** - Validate API keys before saving with built-in testing
* **Model Discovery** - Automatically fetch available models from each provider
* **Developer-Friendly API** - Simple API for creating AI-powered plugins
* **Secure Storage** - API keys stored securely in WordPress database
* **Beautiful Admin Interface** - Modern, intuitive settings interface

= Supported AI Providers =

* **OpenAI** - GPT-4o, GPT-4.5, o3, o3-mini, GPT-4o-mini, DALL-E 3, GPT-Image-1
* **Anthropic** - Claude Sonnet 4, Claude Opus 4
* **Google Gemini** - Gemini 2.0 Flash, Gemini 1.5 Pro, Gemini 1.5 Flash
* **xAI Grok** - Grok Beta, Grok Vision Beta

= Compatible Add-ons =

* **AI-Scribe** - Professional AI content creation and SEO optimisation
* **AI-Imagen** - AI-powered image generation with DALL-E and GPT-Image-1

= Perfect For =

* Content creators using multiple AI tools
* Developers building AI-powered WordPress plugins
* Agencies managing multiple AI integrations
* Anyone wanting simplified AI provider management

= Developer API =

AI-Core provides a simple, clean API for developers:

`
// Check if AI-Core is available
if (function_exists('ai_core')) {
    $ai_core = ai_core();
    
    // Send a text generation request
    $response = $ai_core->send_text_request(
        'gpt-4o',
        array(
            array('role' => 'user', 'content' => 'Hello, AI!')
        ),
        array('max_tokens' => 100)
    );
}
`

= Privacy & Security =

* API keys are stored securely in your WordPress database
* No data is sent to external servers except the AI providers you configure
* Usage statistics are stored locally on your server
* Full control over which providers to enable

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-core/` or install through WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to AI-Core > Settings to configure your API keys
4. Test each API key to ensure it's working correctly
5. Install compatible add-on plugins to start using AI features

== Frequently Asked Questions ==

= Do I need API keys for all providers? =

No, you only need API keys for the providers you want to use. At least one API key is required for the plugin to function.

= Where do I get API keys? =

* OpenAI: https://platform.openai.com/api-keys
* Anthropic: https://console.anthropic.com/
* Google Gemini: https://makersuite.google.com/app/apikey
* xAI Grok: https://console.x.ai/

= Are API keys stored securely? =

Yes, API keys are stored in your WordPress database using WordPress security best practices.

= Can I use this without add-on plugins? =

AI-Core is designed to be used with add-on plugins. While it can function standalone, its primary purpose is to provide centralised API management for other plugins.

= How do I create an add-on plugin? =

Check the documentation at https://opace.agency/ai-core/docs for developer guides and API reference.

= Does this work with existing AI plugins? =

AI-Core is designed for plugins specifically built to use it. Existing plugins with their own API key management won't automatically use AI-Core.

== Screenshots ==

1. Dashboard - Overview of configured providers and usage statistics
2. Settings - Configure API keys for all supported providers
3. Statistics - Detailed usage tracking and analytics
4. Add-ons - Discover compatible plugins that extend AI-Core

== Changelog ==

= 1.0.0 =
* Initial release
* Support for OpenAI, Anthropic, Google Gemini, and xAI Grok
* Centralised API key management
* Usage statistics tracking
* API key testing and validation
* Model discovery and caching
* Developer API for add-on plugins
* Beautiful admin interface

== Upgrade Notice ==

= 1.0.0 =
Initial release of AI-Core - Universal AI Integration Hub for WordPress.

== Additional Information ==

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* At least one AI provider API key

= Support =

For support, please visit https://opace.agency/support or use the WordPress.org support forum.

= Contributing =

AI-Core is open source. Contributions are welcome via the WordPress.org plugin repository.

== Privacy Policy ==

AI-Core does not collect or transmit any data except:
* API requests to the AI providers you configure (OpenAI, Anthropic, Google, xAI)
* Usage statistics stored locally in your WordPress database

Your API keys and usage data never leave your server except when making API calls to your configured providers.

