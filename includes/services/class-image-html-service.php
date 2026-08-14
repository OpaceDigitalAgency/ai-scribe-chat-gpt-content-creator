<?php
/**
 * Image HTML Service Class for AI-Scribe Plugin
 *
 * Consolidates all image HTML generation functionality into a single service
 * to eliminate code duplication and ensure consistent image rendering across
 * all insertion points (admin, workflow, content service, etc.).
 *
 * @package AI_Scribe
 * @subpackage Services
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class AI_Scribe_Image_HTML_Service
 *
 * Provides centralized image HTML generation with multiple format support
 * and consistent property handling across all services.
 */
class AI_Scribe_Image_HTML_Service extends AI_Scribe_Base_Service {

	/**
	 * HTML format constants
	 */
	const FORMAT_DEFAULT         = 'default';
	const FORMAT_WORDPRESS_BLOCK = 'wordpress_block';
	const FORMAT_CUSTOM_STYLED   = 'custom_styled';

	/**
	 * Constructor
	 *
	 * @param AI_Scribe_Logger $logger Logger instance
	 * @param AI_Scribe_Config_Manager $config Configuration manager
	 */
	public function __construct( $logger = null, $config = null ) {
		parent::__construct( $logger, $config );
		$this->service_name = 'ImageHTMLService';
	}

	/**
	 * Validate service configuration and dependencies
	 *
	 * @return bool True if service is properly configured
	 */
	public function validate_service() {
		// ImageHTMLService has no external dependencies to validate
		// It only requires basic WordPress functions which are always available
		return true;
	}

	/**
	 * Generate image HTML with specified format
	 *
	 * @param array $image_data Image data containing url, alt_text, caption, etc.
	 * @param string $format HTML format to generate (default, wordpress_block, custom_styled)
	 * @param array $options Additional options for customization
	 * @return string Generated HTML string
	 */
	public function generateImageHTML( $image_data, $format = self::FORMAT_DEFAULT, $options = array() ) {
		// Validate input data
		if ( ! is_array( $image_data ) || empty( $image_data['url'] ) ) {
			$this->log_service_error( 'Invalid image data provided to generateImageHTML' );
			return '';
		}

		// Standardize image data properties
		$standardized_data = $this->standardizeImageData( $image_data );

		// Generate HTML based on format
		switch ( $format ) {
			case self::FORMAT_WORDPRESS_BLOCK:
				return $this->generateWordPressBlockHTML( $standardized_data, $options );

			case self::FORMAT_CUSTOM_STYLED:
				return $this->generateCustomStyledHTML( $standardized_data, $options );

			case self::FORMAT_DEFAULT:
			default:
				return $this->generateDefaultHTML( $standardized_data, $options );
		}
	}

	/**
	 * Generate JavaScript-compatible image HTML (for frontend insertion)
	 *
	 * @param array $image_data Image data
	 * @param array $options Additional options
	 * @return string JavaScript-safe HTML string
	 */
	public function generateJavaScriptHTML( $image_data, $options = array() ) {
		$html = $this->generateImageHTML( $image_data, self::FORMAT_CUSTOM_STYLED, $options );

		// Escape for JavaScript string concatenation
		return addslashes( $html );
	}

	/**
	 * Standardize image data properties to ensure consistency
	 *
	 * @param array $image_data Raw image data
	 * @return array Standardized image data
	 */
	private function standardizeImageData( $image_data ) {
		// Ensure consistent alt_text property (primary issue identified)
		$alt_text = '';
		if ( ! empty( $image_data['alt_text'] ) ) {
			$alt_text = $image_data['alt_text'];
		} elseif ( ! empty( $image_data['alt'] ) ) {
			$alt_text = $image_data['alt'];
		} elseif ( ! empty( $image_data['title'] ) ) {
			$alt_text = $image_data['title'];
		} else {
			$alt_text = 'Article image'; // Fallback only when no data available
		}

		// Ensure consistent caption handling
		$caption = '';
		if ( ! empty( $image_data['caption'] ) ) {
			$caption = $image_data['caption'];
		}

		return array(
			'url'           => $image_data['url'],
			'alt_text'      => $alt_text,
			'caption'       => $caption,
			'attachment_id' => $image_data['attachment_id'] ?? null,
			'width'         => isset( $image_data['width'] ) ? (int) $image_data['width'] : 0,
			'height'        => isset( $image_data['height'] ) ? (int) $image_data['height'] : 0,
			'class'         => $image_data['class'] ?? '',
			'style'         => $image_data['style'] ?? '',
		);
	}

	/**
	 * Generate default HTML format
	 *
	 * @param array $data Standardized image data
	 * @param array $options Additional options
	 * @return string HTML string
	 */
	private function generateDefaultHTML( $data, $options = array() ) {
		$img_class = ! empty( $data['attachment_id'] ) ? 'wp-image-' . $data['attachment_id'] : '';
		if ( ! empty( $data['class'] ) ) {
			$img_class .= ' ' . $data['class'];
		}

		$html  = '<img src="' . esc_url( $data['url'] ) . '"';
		$html .= ' alt="' . esc_attr( $data['alt_text'] ) . '"';
		$html .= $this->imageLoadingAttributes( $data, $options );

		if ( ! empty( $img_class ) ) {
			$html .= ' class="' . esc_attr( trim( $img_class ) ) . '"';
		}

		if ( ! empty( $data['style'] ) ) {
			$html .= ' style="' . esc_attr( $data['style'] ) . '"';
		}

		$html .= ' />';

		return $html;
	}

	/**
	 * Generate WordPress block format HTML
	 *
	 * @param array $data Standardized image data
	 * @param array $options Additional options
	 * @return string HTML string
	 */
	private function generateWordPressBlockHTML( $data, $options = array() ) {
		$img_class = ! empty( $data['attachment_id'] ) ? 'wp-image-' . $data['attachment_id'] : '';

		$html  = '<figure class="wp-block-image size-large">';
		$html .= '<img src="' . esc_url( $data['url'] ) . '"';
		$html .= ' alt="' . esc_attr( $data['alt_text'] ) . '"';
		$html .= $this->imageLoadingAttributes( $data, $options );

		if ( ! empty( $img_class ) ) {
			$html .= ' class="' . esc_attr( $img_class ) . '"';
		}

		$html .= ' />';

		if ( ! empty( $data['caption'] ) ) {
			$html .= '<figcaption>' . esc_html( $data['caption'] ) . '</figcaption>';
		}

		$html .= '</figure>';

		return $html;
	}

	/**
	 * Generate custom styled HTML (for JavaScript insertion)
	 *
	 * @param array $data Standardized image data
	 * @param array $options Additional options
	 * @return string HTML string
	 */
	private function generateCustomStyledHTML( $data, $options = array() ) {
		$default_style   = 'text-align: center; margin: 20px 0;';
		$container_style = $options['container_style'] ?? $default_style;

		$default_img_style = 'max-width: 100%; height: auto; border-radius: 8px;';
		$img_style         = $options['img_style'] ?? $default_img_style;

		$html  = '<div class="ai-scribe-generated-image" style="' . esc_attr( $container_style ) . '">';
		$html .= '<img src="' . esc_url( $data['url'] ) . '"';
		$html .= ' alt="' . esc_attr( $data['alt_text'] ) . '"';
		$html .= $this->imageLoadingAttributes( $data, $options );
		$html .= ' style="' . esc_attr( $img_style ) . '" />';

		if ( ! empty( $data['caption'] ) ) {
			$caption_style = $options['caption_style'] ?? 'font-style: italic; margin-top: 10px; color: #666;';
			$html         .= '<p style="' . esc_attr( $caption_style ) . '">' . esc_html( $data['caption'] ) . '</p>';
		}

		$html .= '</div>';

		return $html;
	}

	/** @param array $data Standard image data. @param array $options Rendering options. */
	private function imageLoadingAttributes( $data, $options ) {
		$featured = ! empty( $options['featured'] );
		$html     = $featured ? ' loading="eager" fetchpriority="high"' : ' loading="lazy" decoding="async"';
		if ( ! empty( $data['width'] ) && ! empty( $data['height'] ) ) {
			$html .= ' width="' . (int) $data['width'] . '" height="' . (int) $data['height'] . '"';
		}
		return $html;
	}

	/**
	 * Generate image data array for AJAX responses
	 *
	 * @param array $image_data Raw image data
	 * @param string $format HTML format to include
	 * @return array Standardized response data
	 */
	public function generateImageResponse( $image_data, $format = self::FORMAT_DEFAULT ) {
		$standardized_data = $this->standardizeImageData( $image_data );

		return array(
			'url'           => $standardized_data['url'],
			'alt_text'      => $standardized_data['alt_text'],
			'caption'       => $standardized_data['caption'],
			'attachment_id' => $standardized_data['attachment_id'],
			'width'         => $standardized_data['width'],
			'height'        => $standardized_data['height'],
			'image_html'    => $this->generateImageHTML( $image_data, $format ),
		);
	}

	/**
	 * Log error message using parent's protected method
	 *
	 * @param string $message Error message
	 */
	private function log_service_error( $message ) {
		$this->log_error( $message, array( 'service' => $this->service_name ) );
	}
}
