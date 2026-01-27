<?php
/**
 * Content Type Interface
 *
 * @package WPProductBuilder
 */

declare(strict_types=1);

namespace WPProductBuilder\Content;

/**
 * Interface for content type implementations
 */
interface ContentTypeInterface {
    /**
     * Get content type key
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Get display name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Build prompt for AI generation
     *
     * @param array $products Products data
     * @param array $options Generation options
     * @return string The prompt
     */
    public function buildPrompt(array $products, array $options): string;

    /**
     * Generate title for content
     *
     * @param array $products Products data
     * @param array $options Options
     * @return string Generated title
     */
    public function generateTitle(array $products, array $options): string;
}
