/**
 * Card Renderer Service
 *
 * Unified service for creating V4 cards from content data.
 * Eliminates duplication of 18+ card creation methods in DisplayManager.
 * Follows OOP/MVC principles with single responsibility.
 *
 * @package    AI_Content_Generator
 * @subpackage Services
 * @since      4.0.0
 * @author     AI Content Generator Team
 */
class CardRenderer {
    /**
     * Create cards for content items
     *
     * @param {Array} items - Array of content items to render
     * @param {string} contentType - Type of content (titles, keywords, outline, etc.)
     * @param {HTMLElement} container - Container element to append cards to
     * @param {Object} options - Additional options (appendMode, step, etc.)
     * @returns {void}
     */
    static createCards(items, contentType, container, options = {}) {
        if (!items || !Array.isArray(items) || items.length === 0) {
            console.warn('CardRenderer: No items provided for card creation');
            return;
        }

        if (!container) {
            console.error('CardRenderer: No container provided for card creation');
            return;
        }

        const { appendMode = false, step = null } = options;

        // 🚨 CRITICAL FIX: Enhanced debugging for append mode
        const existingCardsCount = container.querySelectorAll('.option-card, .keyword-card, .generic-card').length;

        console.log(`🏗️ CARDRENDERER DEBUG: Creating ${contentType} cards`, {
            itemCount: items.length,
            appendMode,
            step,
            existingCardsCount,
            containerHTML: container.innerHTML.length > 0 ? 'HAS_CONTENT' : 'EMPTY'
        });

        // Clear container if not in append mode
        if (!appendMode) {
            console.log(`🏗️ CARDRENDERER DEBUG: Clearing container (not append mode)`);
            container.innerHTML = '';
        } else {
            console.log(`🏗️ CARDRENDERER DEBUG: Preserving existing content (append mode) - ${existingCardsCount} existing cards`);
        }

        // Route to specific card creation method based on content type
        switch (contentType) {
            case 'titles':
                this.createTitleCards(items, container, options);
                break;
            case 'keywords':
                this.createKeywordCards(items, container, options);
                break;
            case 'outline':
            case 'introduction':
            case 'taglines':
            case 'conclusion':
            case 'qa':
                this.createGenericCards(items, container, contentType, options);
                break;
            default:
                console.warn(`CardRenderer: Unknown content type: ${contentType}`);
                this.createGenericCards(items, container, contentType, options);
        }

        // 🚨 CRITICAL FIX: Verify append mode worked
        const finalCardsCount = container.querySelectorAll('.option-card, .keyword-card, .generic-card').length;
        console.log(`🏗️ CARDRENDERER DEBUG: Final verification - Cards count: ${finalCardsCount}`);

        if (appendMode) {
            if (finalCardsCount > existingCardsCount) {
                console.log(`✅ CARDRENDERER SUCCESS: Append mode worked! Before: ${existingCardsCount}, After: ${finalCardsCount}`);
            } else {
                console.error(`🚨 CARDRENDERER ERROR: Append mode failed! Before: ${existingCardsCount}, After: ${finalCardsCount}`);
            }
        } else {
            console.log(`✅ CARDRENDERER SUCCESS: Replace mode completed. Cards count: ${finalCardsCount}`);
        }
    }

    /**
     * Create title cards
     * Uses V4 frontend structure for titles
     *
     * @param {Array} titles - Array of title strings
     * @param {HTMLElement} container - Container element
     * @param {Object} options - Additional options
     */
    static createTitleCards(titles, container, options = {}) {
        const offset = options.appendMode ? container.querySelectorAll('.option-card').length : 0;
        titles.forEach((title, index) => {
            const card = this.createTitleCard(title, offset + index);
            container.appendChild(card);
        });
    }

    /**
     * Create keyword cards
     * Handles structured demand evidence while retaining legacy strings.
     *
     * @param {Array} keywords - Array of keyword strings
     * @param {HTMLElement} container - Container element
     * @param {Object} options - Additional options
     */
    static createKeywordCards(keywords, container, options = {}) {
        const { appendMode = false } = options;
        const existingCards = appendMode ? container.querySelectorAll('.keyword-card').length : 0;

        keywords.forEach((keyword, index) => {
            const card = this.createKeywordCard(keyword, existingCards + index);
            const row = document.createElement('div');
            row.className = 'keyword-result';
            row.appendChild(card);

            const trendsLink = document.createElement('a');
            const keywordText = card.dataset.keyword || '';
            trendsLink.className = 'keyword-trends-link';
            trendsLink.href = this.googleTrendsUrl([keywordText]);
            trendsLink.target = '_blank';
            trendsLink.rel = 'noopener noreferrer';
            trendsLink.setAttribute('aria-label', `Open ${keywordText} in Google Trends (opens in a new tab)`);
            trendsLink.innerHTML = 'Open in Google Trends <span aria-hidden="true">↗</span>';
            row.appendChild(trendsLink);
            container.appendChild(row);
        });
    }

    /**
     * Create generic content cards
     * Used for outline, introduction, taglines, conclusion, qa
     *
     * @param {Array} items - Array of content strings or objects
     * @param {HTMLElement} container - Container element
     * @param {string} contentType - Type of content
     * @param {Object} options - Additional options
     */
    static createGenericCards(items, container, contentType, options = {}) {
        // 🚨 CRITICAL FIX: Handle structured Q&A data from new backend format
        if (contentType === 'qa' && items.length > 0 && typeof items[0] === 'object' && items[0].question) {
            // New structured Q&A format
            items.forEach((qaItem, index) => {
                const card = this.createQACard(qaItem, index);
                container.appendChild(card);
            });

            // 🚨 CRITICAL FIX: Initialize lucide icons immediately for Q&A arrows
            if (window.lucide && window.lucide.createIcons) {
                window.lucide.createIcons();
            }
        } else {
            // Standard generic cards
            items.forEach((item, index) => {
                const card = this.createGenericCard(item, index, contentType);
                container.appendChild(card);
            });
        }
    }

    /**
     * Create a single title card
     * Uses exact V4 frontend structure with checkboxes (matching other card types)
     *
     * @param {string} title - Title text
     * @param {number} index - Card index
     * @returns {HTMLElement} - Card element
     */
    static createTitleCard(title, index) {
        const card = document.createElement('div');
        card.className = 'option-card';
        card.setAttribute('data-index', index);

        // Clean title (remove number prefix if present)
        const cleanTitle = title.replace(/^\d+\.\s*/, '');

        card.innerHTML = `
            <div class="option-content">
                <div class="option-text">${this.escapeHtml(cleanTitle)}</div>
            </div>
            <div class="checkbox">
                <i data-lucide="check" style="display: none;"></i>
            </div>
        `;

        // Add click handler for selection
        card.addEventListener('click', () => {
            // Toggle selection
            card.classList.toggle('selected');

            // Update checkbox visibility
            const checkIcon = card.querySelector('i[data-lucide="check"]');
            if (checkIcon) {
                checkIcon.style.display = card.classList.contains('selected') ? 'block' : 'none';
            }

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        return card;
    }

    /**
     * Create a single keyword card
     * Shows qualitative demand provenance and an external Trends action.
     *
     * @param {string} keyword - Keyword text (format: "keyword | volume | competition")
     * @param {number} index - Card index
     * @returns {HTMLElement} - Card element
     */
    static createKeywordCard(keyword, index) {
        const card = document.createElement('div');
        card.className = 'keyword-card';
        card.setAttribute('data-index', index);

        // Legacy providers sometimes returned "keyword | volume |
        // competition" guesses. They are not connected keyword-tool data,
        // so show the phrase but never present those estimates as measured.
        let keywordText = keyword;
        let role = '';
        let demand = 'unknown';
        let estimateBasis = '';

        // 🚨 CRITICAL FIX: Handle object inputs that cause [object Object] display
        let keywordString;
        if (typeof keyword === 'object' && keyword !== null) {
            // Extract meaningful text from object
            keywordString = keyword.keyword || keyword.keywords || keyword.title || keyword.content ||
                          keyword.text || keyword.name || keyword.value ||
                          Object.values(keyword).find(val => typeof val === 'string' && val.length > 0) ||
                          'Unknown keyword';
            role = ['primary', 'supporting', 'long-tail'].includes(keyword.role)
                ? keyword.role
                : '';
            demand = ['low', 'medium', 'high'].includes(keyword.demand_band)
                ? keyword.demand_band
                : 'unknown';
            estimateBasis = keyword.estimate_basis === 'ai_unverified'
                ? 'ai_unverified'
                : '';
        } else {
            keywordString = typeof keyword === 'string' ? keyword : String(keyword);
        }

        keywordText = keywordString.split('|')[0].trim();
        const demandLabel = demand.charAt(0).toUpperCase() + demand.slice(1);
        const roleLabel = role === 'long-tail'
            ? 'Long-tail'
            : (role ? role.charAt(0).toUpperCase() + role.slice(1) : '');
        card.innerHTML = `
            <div class="checkbox">
                <i data-lucide="check" style="display: none;"></i>
            </div>
            <div class="keyword-content">
                <div class="keyword-summary">
                    <div class="keyword-title">${this.escapeHtml(keywordText)}</div>
                    ${roleLabel ? `<span class="keyword-role">${this.escapeHtml(roleLabel)}</span>` : ''}
                </div>
                <div class="keyword-stats" aria-label="Keyword data provenance">
                    <span class="keyword-stat"><strong>(Estimated search volume: ${this.escapeHtml(demandLabel)} — AI estimate, unverified)</strong></span>
                </div>
            </div>
        `;

        card.dataset.keyword = keywordText;
        card.dataset.role = role;
        card.dataset.demandBand = demand;
        card.dataset.estimateBasis = estimateBasis;

        // Add click handler for selection
        card.addEventListener('click', () => {
            // Toggle selection
            card.classList.toggle('selected');

            // Update checkbox visibility
            const checkIcon = card.querySelector('i[data-lucide="check"]');
            if (checkIcon) {
                checkIcon.style.display = card.classList.contains('selected') ? 'block' : 'none';
            }

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        return card;
    }

    /**
     * Build a Google Trends comparison URL without treating Trends as volume.
     * No geography or date window is forced when the product has no such
     * setting, so Google applies its neutral defaults.
     *
     * @param {string[]} keywords Keyword phrases, up to Google's five-term limit.
     * @returns {string}
     */
    static googleTrendsUrl(keywords) {
        const terms = (Array.isArray(keywords) ? keywords : [])
            .map((item) => String(item || '').trim())
            .filter(Boolean);
        if (terms.length > 5) {
            return '';
        }
        const params = new URLSearchParams();
        if (terms.length) {
            params.set('q', terms.join(','));
        }
        params.set('date', 'today 5-y');
        return `https://trends.google.com/trends/explore${params.toString() ? `?${params.toString()}` : ''}`;
    }

    /**
     * Create a single generic content card
     * Used for outline, introduction, taglines, conclusion, qa
     *
     * @param {string} content - Content text
     * @param {number} index - Card index
     * @param {string} contentType - Type of content
     * @returns {HTMLElement} - Card element
     */
    static createGenericCard(content, index, contentType) {
        const card = document.createElement('div');
        card.className = `${contentType}-card option-card`;
        card.setAttribute('data-index', index);

        // 🚨 CRITICAL FIX: Handle object content to prevent [object Object] display
        let contentText = content;
        if (typeof content === 'object' && content !== null) {
            console.log('[DEBUG GEN MORE:] Object content detected in createGenericCard:', content);
            // Extract meaningful text from object
            contentText = content.content || content.text || content.title ||
                         content.question || content.answer || content.conclusion ||
                         content.introduction || content.tagline || content.outline ||
                         Object.values(content).find(val => typeof val === 'string' && val.length > 0) ||
                         'Content not available';
            console.log('[DEBUG GEN MORE:] Extracted content text:', contentText);
        } else if (typeof content !== 'string') {
            contentText = String(content || '');
        }

        // 🚨 CRITICAL FIX: Use proper V4 card design matching v4_frontend structure
        if (contentType === 'outline') {
            // 🚨 CRITICAL FIX: Create proper outline structure matching V4 frontend design
            const outlineContent = this.parseOutlineContent(contentText);
            card.innerHTML = `
                <div class="checkbox">
                    <i data-lucide="check" style="display: none;"></i>
                </div>
                <div class="outline-content">
                    <div class="outline-title">${outlineContent.title}</div>
                    <ul class="outline-structure">
                        ${outlineContent.items.map(item => `<li>${this.escapeHtml(item)}</li>`).join('')}
                    </ul>
                </div>
            `;
        } else if (contentType === 'meta') {
            // 🚨 CRITICAL FIX: Create combined SEO meta card with both title and description
            const metaType = content.type || 'unknown';
            console.log('🏗️ [DEBUG SEO CARD RENDER] Creating meta card with type:', metaType);
            console.log('🔍 [DEBUG SEO CARD RENDER] Content:', content);

            if (metaType === 'seo-combined') {
                // Combined SEO card with both title and description
                const metaTitle = content.metaTitle || '';
                const metaDescription = content.metaDescription || '';
                console.log('🔍 [DEBUG SEO CARD RENDER] Rendering combined SEO card');
                console.log('🔍 [DEBUG SEO CARD RENDER] metaTitle:', metaTitle);
                console.log('🔍 [DEBUG SEO CARD RENDER] metaDescription:', metaDescription);

                card.innerHTML = `
                    <div class="checkbox">
                        <i data-lucide="check" style="display: none;"></i>
                    </div>
                    <div class="meta-content">
                        <div class="meta-section">
                            <div class="meta-label">META TITLE</div>
                            <div class="meta-text meta-title">${this.escapeHtml(metaTitle)}</div>
                            <div class="meta-length">${metaTitle.length} characters</div>
                        </div>
                        ${metaDescription ? `
                        <div class="meta-section" style="margin-top: 12px;">
                            <div class="meta-label">META DESCRIPTION</div>
                            <div class="meta-text meta-description">${this.escapeHtml(metaDescription)}</div>
                            <div class="meta-length">${metaDescription.length} characters</div>
                        </div>
                        ` : ''}
                    </div>
                `;
            } else {
                // Legacy separate card format (fallback)
                const metaContent = content.content || contentText;
                const metaLabel = metaType === 'title' ? 'Meta Title' : 'Meta Description';
                const metaClass = metaType === 'title' ? 'meta-title' : 'meta-description';

                card.innerHTML = `
                    <div class="checkbox">
                        <i data-lucide="check" style="display: none;"></i>
                    </div>
                    <div class="meta-content">
                        <div class="meta-label">${metaLabel}</div>
                        <div class="meta-text ${metaClass}">${this.escapeHtml(metaContent)}</div>
                        <div class="meta-length">${metaContent.length} characters</div>
                    </div>
                `;
            }
        } else {
            // Standard card structure for other content types
            card.innerHTML = `
                <div class="checkbox">
                    <i data-lucide="check" style="display: none;"></i>
                </div>
                <div class="option-content">
                    <div class="option-text">${this.escapeHtml(contentText)}</div>
                </div>
            `;
        }

        // Add click handler for selection (matching other card types)
        card.addEventListener('click', () => {
            // Toggle selection
            card.classList.toggle('selected');

            // Update checkbox visibility
            const checkIcon = card.querySelector('i[data-lucide="check"]');
            if (checkIcon) {
                checkIcon.style.display = card.classList.contains('selected') ? 'block' : 'none';
            }

            // Re-initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        return card;
    }

    /**
     * Parse outline content into title and bullet points
     * 🚨 CRITICAL FIX: Matches V4 frontend design with proper structure
     */
    static parseOutlineContent(outlineText) {
        console.log('🔍 CARDRENDERER: Parsing outline content:', outlineText);

        // Split the outline text by newlines
        const lines = outlineText.split(/\n/).map(line => line.trim()).filter(line => line.length > 0);
        console.log('🔍 CARDRENDERER: Outline lines:', lines);

        // Use "Article Outline" as the title (matching V4 frontend design)
        const displayTitle = 'Article Outline';

        // All lines should be treated as outline items
        let outlineItems = lines;

        // Clean up outline items - remove any numbering or bullet points
        outlineItems = outlineItems.map(item => {
            return item.replace(/^[\d\.\-\*\+\s]+/, '').trim();
        }).filter(item => item.length > 0);

        console.log('🔍 CARDRENDERER: Final outline structure:', {
            title: displayTitle,
            items: outlineItems
        });

        return {
            title: displayTitle,
            items: outlineItems
        };
    }

    /**
     * Strip HTML tags and return clean text
     *
     * @param {string} text - Text that may contain HTML
     * @returns {string} - Clean text without HTML tags
     */
    static stripHtml(text) {
        if (!text || typeof text !== 'string') {
            return '';
        }

        // Create a temporary div to parse HTML and extract text content
        const div = document.createElement('div');
        div.innerHTML = text;
        return div.textContent || div.innerText || '';
    }

    /**
     * Escape HTML to prevent XSS (for display purposes)
     *
     * @param {string} text - Text to escape
     * @returns {string} - Escaped text
     */
    static escapeHtml(text) {
        if (!text || typeof text !== 'string') {
            return '';
        }

        // First strip any existing HTML tags, then escape for safe display
        const cleanText = this.stripHtml(text);
        const div = document.createElement('div');
        div.textContent = cleanText;
        return div.innerHTML;
    }

    /**
     * Find or create container for content type
     *
     * @param {string} contentType - Type of content
     * @param {number} step - Step number
     * @returns {HTMLElement} - Container element
     */
    static findOrCreateContainer(contentType, step = null) {
        console.log(`🔍 CardRenderer: Finding container for contentType="${contentType}", step=${step}`);

        // 🚨 CRITICAL FIX: Handle singular/plural container naming inconsistencies
        const singularForm = contentType.endsWith('s') ? contentType.slice(0, -1) : contentType;
        const pluralForm = contentType.endsWith('s') ? contentType : contentType + 's';

        // Try multiple possible container selectors with both singular and plural forms
        const selectors = [
            `#${contentType}-options`,
            `#${singularForm}-options`,  // e.g., tagline-options
            `#${pluralForm}-options`,    // e.g., taglines-options
            `#step-${step}-options`,
            `.${contentType}-grid`,
            `.${singularForm}-grid`,
            `.${pluralForm}-grid`,
            '.options-grid',
            '.results-container'
        ].filter(Boolean);

        console.log(`🔍 CardRenderer: Trying selectors:`, selectors);

        for (const selector of selectors) {
            const container = document.querySelector(selector);
            if (container) {
                console.log(`✅ CardRenderer: Found container with selector "${selector}":`, container);
                return container;
            }
        }

        console.warn(`⚠️ CardRenderer: No container found for contentType="${contentType}", step=${step}`);

        // Create container if none found
        const container = document.createElement('div');
        container.className = `${contentType}-grid options-grid`;
        container.id = `${contentType}-options`;

        // Find where to insert it
        const mainContainer = document.querySelector('.app-container, #ai-scribe-root, .workflow-container');
        if (mainContainer) {
            mainContainer.appendChild(container);
        }

        return container;
    }

    /**
     * Create a structured Q&A card with improved formatting
     * 🚨 CRITICAL FIX: New Q&A card format with bold questions and arrow indicators
     *
     * @param {Object} qaItem - Q&A item with question and answer
     * @param {number} index - Card index
     * @returns {HTMLElement} - Q&A card element
     */
    static createQACard(qaItem, index) {
        const card = document.createElement('div');
        card.className = 'qa-card option-card';
        card.setAttribute('data-index', index);

        // Add click handler for selection
        card.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleCardSelection(card);
        });

        // Create improved Q&A structure with bold question and arrow indicator
        card.innerHTML = `
            <div class="checkbox">
                <i data-lucide="check" style="display: none;"></i>
            </div>
            <div class="qa-content">
                <div class="qa-question">
                    <strong>${this.escapeHtml(qaItem.question)}</strong>
                </div>
                <div class="qa-answer">
                    <i data-lucide="arrow-right" style="width: 16px; height: 16px; margin-right: 8px; color: #666;"></i>
                    ${this.escapeHtml(qaItem.answer)}
                </div>
            </div>
        `;

        return card;
    }

    /**
     * Toggle card selection state
     * 🚨 CRITICAL FIX: Support multiple selection for Q&A cards
     */
    static toggleCardSelection(card) {
        const checkbox = card.querySelector('.checkbox i[data-lucide="check"]');
        const isSelected = card.classList.contains('selected');

        if (isSelected) {
            // Deselect
            card.classList.remove('selected');
            if (checkbox) checkbox.style.display = 'none';
        } else {
            // Select (allow multiple for Q&A)
            card.classList.add('selected');
            if (checkbox) checkbox.style.display = 'block';
        }

        // Trigger lucide icon refresh if available
        if (window.lucide && window.lucide.createIcons) {
            window.lucide.createIcons();
        }
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CardRenderer;
}
