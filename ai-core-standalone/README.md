# AI-Core - Universal AI Integration Hub for WordPress

**Version:** 1.0.0  
**Requires:** WordPress 5.0+, PHP 7.4+  
**License:** GPL v3 or later

## Overview

AI-Core is a centralised AI integration hub for WordPress that simplifies AI provider management. Configure your API keys once in AI-Core, and all compatible add-on plugins automatically gain access to OpenAI, Anthropic Claude, Google Gemini, and xAI Grok without requiring separate API key configuration.

## Key Features

- ✅ **Centralised API Key Management** - One place to manage all AI provider API keys
- ✅ **Multiple Provider Support** - OpenAI, Anthropic, Google Gemini, xAI Grok
- ✅ **Automatic Integration** - Add-on plugins automatically use configured API keys
- ✅ **Usage Statistics** - Track API usage, tokens, and costs
- ✅ **API Key Testing** - Validate keys before saving
- ✅ **Model Discovery** - Automatically fetch available models
- ✅ **Developer-Friendly API** - Simple API for creating AI-powered plugins
- ✅ **WordPress.org Ready** - Follows all WordPress coding standards

## Supported AI Providers

### OpenAI
- GPT-4o, GPT-4.5, o3, o3-mini, GPT-4o-mini
- DALL-E 3, GPT-Image-1

### Anthropic
- Claude Sonnet 4 (claude-sonnet-4-20250514)
- Claude Opus 4 (claude-opus-4-20250514)

### Google Gemini
- Gemini 2.0 Flash (Experimental)
- Gemini 1.5 Pro
- Gemini 1.5 Flash

### xAI Grok
- Grok Beta
- Grok Vision Beta

## Installation

1. Upload the `ai-core` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **AI-Core > Settings** to configure your API keys
4. Test each API key to ensure it's working
5. Install compatible add-on plugins

## Configuration

### Getting API Keys

- **OpenAI:** https://platform.openai.com/api-keys
- **Anthropic:** https://console.anthropic.com/
- **Google Gemini:** https://makersuite.google.com/app/apikey
- **xAI Grok:** https://console.x.ai/

### Settings

Navigate to **AI-Core > Settings** in WordPress admin:

1. **API Keys Configuration**
   - Enter API keys for each provider you want to use
   - Test each key to verify it's working
   - At least one API key is required

2. **General Settings**
   - Set default provider
   - Enable/disable usage statistics
   - Enable/disable model caching

## Developer API

### Basic Usage

```php
// Check if AI-Core is available
if (function_exists('ai_core')) {
    $ai_core = ai_core();
    
    // Check if configured
    if ($ai_core->is_configured()) {
        // Send a text generation request
        $response = $ai_core->send_text_request(
            'gpt-4o',
            array(
                array('role' => 'user', 'content' => 'Hello, AI!')
            ),
            array('max_tokens' => 100)
        );
        
        if (!is_wp_error($response)) {
            $content = $response['choices'][0]['message']['content'];
            echo $content;
        }
    }
}
```

### API Methods

#### Check Configuration

```php
$ai_core = ai_core();

// Check if any provider is configured
$is_configured = $ai_core->is_configured();

// Get list of configured providers
$providers = $ai_core->get_configured_providers();
// Returns: array('openai', 'anthropic', 'gemini', 'grok')

// Get default provider
$default = $ai_core->get_default_provider();
```

#### Text Generation

```php
$response = $ai_core->send_text_request(
    $model,      // Model identifier (e.g., 'gpt-4o', 'claude-sonnet-4-20250514')
    $messages,   // Array of messages
    $options     // Optional parameters
);

// Example with all options
$response = $ai_core->send_text_request(
    'gpt-4o',
    array(
        array('role' => 'system', 'content' => 'You are a helpful assistant.'),
        array('role' => 'user', 'content' => 'Write a short poem.')
    ),
    array(
        'max_tokens' => 200,
        'temperature' => 0.7,
        'top_p' => 1.0
    )
);
```

#### Image Generation

```php
$response = $ai_core->generate_image(
    $prompt,     // Image description
    $options,    // Optional parameters
    $provider    // Provider name (default: 'openai')
);

// Example
$response = $ai_core->generate_image(
    'A beautiful sunset over mountains',
    array(
        'model' => 'gpt-image-1',
        'size' => '1024x1024',
        'quality' => 'hd'
    ),
    'openai'
);
```

#### Get Available Models

```php
// Get models for a specific provider
$models = $ai_core->get_available_models('openai');

// Returns array of model identifiers
// Example: array('gpt-4o', 'gpt-4.5', 'o3', 'gpt-4o-mini')
```

#### Usage Statistics

```php
// Get all statistics
$stats = $ai_core->get_stats();

// Reset statistics
$ai_core->reset_stats();
```

### Response Format

All text generation responses are normalised to OpenAI format:

```php
array(
    'choices' => array(
        array(
            'message' => array(
                'content' => 'Generated text here',
                'role' => 'assistant'
            ),
            'finish_reason' => 'stop',
            'index' => 0
        )
    ),
    'usage' => array(
        'prompt_tokens' => 10,
        'completion_tokens' => 50,
        'total_tokens' => 60
    ),
    'model' => 'gpt-4o',
    'object' => 'chat.completion'
)
```

### Error Handling

```php
$response = $ai_core->send_text_request($model, $messages);

if (is_wp_error($response)) {
    $error_message = $response->get_error_message();
    // Handle error
} else {
    $content = $response['choices'][0]['message']['content'];
    // Use content
}
```

## Creating Add-on Plugins

### Plugin Structure

```
your-plugin/
├── your-plugin.php          # Main plugin file
├── includes/
│   └── class-your-plugin.php
└── readme.txt
```

### Main Plugin File

```php
<?php
/**
 * Plugin Name: Your AI Plugin
 * Description: Your plugin description
 * Requires Plugins: ai-core
 * Version: 1.0.0
 */

// Check if AI-Core is active
if (!function_exists('ai_core')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Your Plugin:</strong> Requires AI-Core plugin to be installed and activated.';
        echo '</p></div>';
    });
    return;
}

// Your plugin code here
```

### Using AI-Core in Your Plugin

```php
class Your_Plugin {
    
    private $ai_core;
    
    public function __construct() {
        $this->ai_core = ai_core();
        
        if (!$this->ai_core->is_configured()) {
            // Show notice to configure AI-Core
            add_action('admin_notices', array($this, 'show_config_notice'));
            return;
        }
        
        // Initialize your plugin
        $this->init();
    }
    
    public function generate_content($prompt) {
        $response = $this->ai_core->send_text_request(
            'gpt-4o',
            array(
                array('role' => 'user', 'content' => $prompt)
            ),
            array('max_tokens' => 500)
        );
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response['choices'][0]['message']['content'];
    }
}
```

## Architecture

### Directory Structure

```
ai-core/
├── ai-core.php                          # Main plugin file
├── lib/                                 # AI-Core library
│   ├── autoload.php
│   └── src/
│       ├── AICore.php                   # Main factory class
│       ├── Providers/                   # AI provider implementations
│       │   ├── OpenAIProvider.php
│       │   ├── AnthropicProvider.php
│       │   ├── GeminiProvider.php
│       │   └── GrokProvider.php
│       ├── Registry/
│       │   └── ModelRegistry.php        # Model definitions
│       ├── Response/
│       │   └── ResponseNormalizer.php   # Response normalisation
│       └── Http/
│           └── HttpClient.php           # HTTP client
├── includes/                            # Plugin classes
│   ├── class-ai-core-settings.php
│   ├── class-ai-core-api.php
│   ├── class-ai-core-validator.php
│   └── class-ai-core-stats.php
├── admin/                               # Admin interface
│   ├── class-ai-core-admin.php
│   ├── class-ai-core-ajax.php
│   └── class-ai-core-addons.php
├── assets/                              # CSS and JavaScript
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── readme.txt                           # WordPress.org readme
├── README.md                            # Developer documentation
└── uninstall.php                        # Uninstall script
```

### Design Principles

1. **Singleton Pattern** - All main classes use singleton pattern
2. **Factory Pattern** - AICore class creates provider instances
3. **Normalisation** - All responses normalised to OpenAI format
4. **Caching** - Model lists cached to reduce API calls
5. **WordPress Standards** - Follows WordPress coding standards
6. **Security** - Nonce verification, capability checks, sanitisation

## Compatible Add-ons

- **AI-Scribe** - Professional AI content creation and SEO optimisation
- **AI-Imagen** - AI-powered image generation

## Support

- **Documentation:** https://opace.agency/ai-core/docs
- **Support Forum:** https://wordpress.org/support/plugin/ai-core/
- **GitHub:** https://github.com/OpaceDigitalAgency/ai-core

## Contributing

Contributions are welcome! Please follow WordPress coding standards and submit pull requests via GitHub.

## License

GPL v3 or later - https://www.gnu.org/licenses/gpl-3.0.html

## Credits

Developed by [Opace Digital Agency](https://opace.agency)

