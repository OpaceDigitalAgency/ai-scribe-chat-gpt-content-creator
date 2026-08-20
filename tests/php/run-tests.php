<?php
/**
 * AI-Scribe v3 P2 test suite.
 *
 * Usage: php tests/php/run-tests.php
 *
 * Covers: server-side prompt assembly per step (incl. the step-7
 * blind-writing regression), schema validation accept/reject, 2.6.2
 * option migration, Express compilation + persistence, estimator maths,
 * and the never-advance-on-failure guarantee.
 */

require __DIR__ . '/bootstrap.php';

$container = ai_scribe_get_container();
/** @var AI_Scribe_Prompt_Manager $prompts */
$prompts = $container->get('prompt_manager');
$config = $container->get('config');
$logger = $container->get('logger');

$settings = [
    'idea' => 'benefits of electric cars',
    'language' => 'English',
    'writing_style' => 'Business',
    'writing_tone' => 'Professional',
    'heading_tag' => 'H3',
    'number_of_headings' => 4,
    'tagline_position' => 'above',
    'avoid_keywords' => 'cheap, budget',
    'model' => 'gpt-4o-mini',
    'options' => [],
];
$selections = [
    'title' => 'Why Electric Cars Win',
    'keywords' => ['Electric Cars', 'EV Charging'],
    'outline' => ['Running Costs', 'Environment', 'Performance', 'Charging'],
    'introduction' => '<p>The intro paragraph.</p>',
    'tagline' => 'Drive the future',
];

// ---------------------------------------------------------------------------
test_section('Prompt assembly — placeholders resolved server-side');
// ---------------------------------------------------------------------------

$p1 = $prompts->assemble_step_prompt(1, $settings, []);
test_assert_contains('benefits of electric cars', $p1, 'step 1 resolves [Idea]');
test_assert_not_contains('[Idea]', $p1, 'step 1 leaves no [Idea] token');
test_assert_contains('current year is ' . gmdate('Y'), $p1, 'title prompt receives the verified current year');
test_assert_contains('SEO, AI, API, URL, HTML and WordPress', $p1, 'title policy preserves familiar acronym and brand casing');
test_assert_contains('at least two suggestions', $p1, 'fresh title topics request some explicit current-year options');

$p2 = $prompts->assemble_step_prompt(2, $settings, $selections);
test_assert_contains('Why Electric Cars Win', $p2, 'step 2 resolves [Title]');
test_assert_contains('demand_band to low, medium, high or unknown', $p2, 'keyword prompt requests a qualitative demand band');
test_assert_contains('estimate_basis to ai_unverified', $p2, 'keyword prompt marks every estimate as unverified AI output');
test_assert_contains('must never contain or imply a numeric search volume', $p2, 'keyword prompt refuses made-up numeric volume metrics');

$p3 = $prompts->assemble_step_prompt(3, $settings, $selections);
test_assert_contains('4 sections', $p3, 'step 3 resolves [No. Headings]');
test_assert_contains('following SEO keywords "Electric Cars" and "EV Charging"', $p3, 'step 3 resolves [Selected Keywords] with 2.6.2 join');
test_assert_contains('Exclude the following keywords "cheap", "budget"', $p3, 'exclude-keywords appended');

$p4 = $prompts->assemble_step_prompt(4, $settings, $selections);
test_assert_contains('English', $p4, 'step 4 resolves [Language]');
test_assert_contains('Business', $p4, 'step 4 resolves [Style]');
test_assert_contains('Professional', $p4, 'step 4 resolves [Tone]');

$p6 = $prompts->assemble_step_prompt(6, $settings, $selections);
test_assert_contains('H3', $p6, 'step 6 resolves [Heading Tag]');
test_assert_contains("1. Running Costs\n2. Environment\n3. Performance\n4. Charging", $p6, 'step 6 presents every selected heading as a numbered line');
test_assert_contains('Write exactly one body section for each of the 4 numbered headings', $p6, 'step 6 requires exact outline coverage');
test_assert_contains('Do not omit, rename, merge, repeat or add headings', $p6, 'step 6 rejects missing and unselected sections');
test_assert_not_contains('Drive the future', $p6, 'step 6 body prompt does not repeat the separately compiled tagline');
test_assert_not_contains('The intro paragraph.', $p6, 'step 6 body prompt does not repeat the separately compiled introduction');
test_assert_not_contains('[Selected Keywords]', $p6, 'step 6 leaves no keyword token');
test_assert_contains('Return only the body sections', $p6, 'body prompt excludes separately compiled article parts');

$outline_with_more = $selections;
$outline_with_more['outline'] = ['Initial A', 'Initial B', ' initial  a ', 'Generated C'];
$p6_more = $prompts->assemble_step_prompt(6, $settings, $outline_with_more);
test_assert_contains("1. Initial A\n2. Initial B\n3. Generated C", $p6_more, 'initial and Generate More headings share one ordered exact contract');
test_assert_not_contains('4. initial', $p6_more, 'duplicate Generate More headings are represented once');

/** @var AI_Scribe_Post_Service $post_service */
$post_service = $container->get('post_service');
$save_selections = [
    'title' => 'Generated sections save test',
    'outline' => ['Initial A', 'Initial B', ' initial  a ', 'Generated & More'],
    'keywords' => [],
    'meta' => [],
];
$incomplete_save = $post_service->create_from_conversation(
    $save_selections,
    ['content_html' => '<h2>Initial A</h2><p>A</p><h2>Initial B</h2><p>B</p>']
);
test_assert(is_wp_error($incomplete_save) && $incomplete_save->get_error_code() === 'outline_incomplete', 'save refuses an article missing a selected Generate More section');
test_assert_contains('Generated & More', $incomplete_save->get_error_message(), 'save failure names the missing selected heading');
$complete_save = $post_service->create_from_conversation(
    $save_selections,
    ['content_html' => '<h2>initial a</h2><p>A</p><h2>Initial B</h2><p>B</p><h2>Generated &amp; More</h2><p>C</p>']
);
test_assert(!is_wp_error($complete_save) && !empty($complete_save['post_id']), 'save accepts every selected unique heading after safe entity/case normalisation');

test_section('Publishing quality — clean HTML, featured image, slug and excerpt');
$clean_article = new ReflectionMethod(AI_Scribe_Post_Service::class, 'clean_article_html');
$clean_article->setAccessible(true);
$GLOBALS['__test_attachment_ids']['content.jpg'] = 51;
$GLOBALS['__test_attachment_metadata'][51] = ['width' => 1200, 'height' => 800];
$cleaned = $clean_article->invoke($post_service, '<p>Intro</p><p><br></p><div style="height: 700px; color: red"><br></div><p>Body</p><p><img role="button" tabindex="0" aria-label="Editor control" src="content.jpg"></p><p class="ai-scribe-image-caption">Useful caption</p>');
test_assert_not_contains('<p><br></p>', $cleaned, 'server removes empty editor spacer paragraphs before saving');
test_assert_not_contains('height:', $cleaned, 'server removes artificial block height from reviewed HTML');
test_assert_contains('loading="lazy" decoding="async"', $cleaned, 'saved content images use deferred loading and asynchronous decoding');
test_assert_not_contains('role="button"', $cleaned, 'saved images do not retain editor-only button semantics');
test_assert_not_contains('tabindex="0"', $cleaned, 'saved images do not retain editor-only keyboard controls');
test_assert_contains('class="ai-scribe-article-image wp-image-51"', $cleaned, 'saved images recover their WordPress attachment class from the media URL');
test_assert_contains('width="1200" height="800"', $cleaned, 'saved images restore intrinsic dimensions from attachment metadata');
test_assert_contains('<figure class="wp-block-image size-large ai-scribe-article-figure">', $cleaned, 'saved images use a responsive WordPress figure');
test_assert_contains('<figcaption>Useful caption</figcaption>', $cleaned, 'editable image captions remain attached to their image');

$normalise_semantics = new ReflectionMethod(AI_Scribe_Post_Service::class, 'normalise_article_semantics');
$normalise_semantics->setAccessible(true);
$malformed_heading = 'Ignoring structural technical debt will cost search visibility because crawlers become increasingly selective. This complete paragraph contains practical advice that belongs in normal article prose, not in the document outline or a huge bold heading.';
$malformed_bold = 'Run the audit with the engineering team, record each redirect chain, remove waste carefully, and verify every change before release. This second full paragraph must remain readable without turning every sentence into bold display copy.';
$malformed_article = '<h2>Actionable conclusion</h2><h3>' . $malformed_heading . '</h3><p><strong>' . $malformed_bold . '</strong></p><ul><li><a href="/audit">Audit list</a></li></ul>';
$semantic_article = $normalise_semantics->invoke($post_service, $malformed_article);
test_assert_contains('<h2>Actionable conclusion</h2>', $semantic_article, 'server preserves a concise legitimate heading');
test_assert_not_contains('<h3>' . $malformed_heading . '</h3>', $semantic_article, 'server demotes paragraph-length heading markup before save');
test_assert_contains('<p>' . $malformed_heading . '</p>', $semantic_article, 'server keeps every word from the demoted heading');
test_assert_not_contains('<strong>' . $malformed_bold . '</strong>', $semantic_article, 'server removes a full-paragraph bold wrapper');
test_assert_contains('<p>' . $malformed_bold . '</p>', $semantic_article, 'server keeps every word from full-paragraph bold prose');
test_assert_contains('<ul><li><a href="/audit">Audit list</a></li></ul>', $semantic_article, 'server normalisation preserves lists and links');

$semantic_save = $post_service->create_from_conversation(
    ['title' => 'Semantic save test', 'outline' => [], 'keywords' => [], 'meta' => []],
    ['content_html' => '<h1>Semantic save test</h1>' . $malformed_article]
);
test_assert(!is_wp_error($semantic_save), 'semantically repaired Review HTML saves successfully');
test_assert_not_contains('<h3>' . $malformed_heading . '</h3>', $GLOBALS['__test_last_insert_post']['post_content'], 'persisted post matches the repaired Review heading semantics');
test_assert_not_contains('<strong>' . $malformed_bold . '</strong>', $GLOBALS['__test_last_insert_post']['post_content'], 'persisted post matches the repaired Review emphasis semantics');
test_assert_contains($malformed_heading, $GLOBALS['__test_last_insert_post']['post_content'], 'persisted post loses no malformed-heading words');
test_assert_contains($malformed_bold, $GLOBALS['__test_last_insert_post']['post_content'], 'persisted post loses no malformed-bold words');

$remove_featured = new ReflectionMethod(AI_Scribe_Post_Service::class, 'remove_featured_image_from_content');
$remove_featured->setAccessible(true);
$without_featured = $remove_featured->invoke($post_service, '<p>Intro</p><figure><img class="wp-image-42" src="featured.jpg"><figcaption>Caption</figcaption></figure><p><img class="wp-image-51" src="content.jpg"></p>', 42);
test_assert_not_contains('wp-image-42', $without_featured, 'featured attachment is removed from article HTML before save');
test_assert_contains('wp-image-51', $without_featured, 'non-featured content images remain in article HTML');
$without_featured_sibling_caption = $remove_featured->invoke($post_service, '<p>Intro</p><p><img class="wp-image-42" src="featured.jpg"></p><p class="ai-scribe-image-caption">Featured caption</p><h2>Next</h2>', 42);
test_assert_not_contains('Featured caption', $without_featured_sibling_caption, 'promoting a Quill image removes its paired caption instead of leaving orphaned text');

$GLOBALS['__test_current_user_id'] = 17;
$published = $post_service->create_from_conversation(
    ['title' => 'Publishing details test', 'outline' => [], 'keywords' => [], 'meta' => []],
    [
        'content_html' => '<p>Useful article content.</p><p><img src="content.jpg"></p><p class="ai-scribe-image-caption">Useful caption</p>',
        'category_name' => 'Web Design',
        'tag_names' => 'responsive design, accessibility'
    ]
);
test_assert(!is_wp_error($published) && !empty($published['post_id']), 'save accepts publishing details alongside reviewed HTML');
test_assert(($GLOBALS['__test_last_insert_post']['post_author'] ?? 0) === 17, 'save assigns the current WordPress user as post author');
test_assert(($GLOBALS['__test_post_meta'][$published['post_id']]['_ai_scribe_generated'] ?? '') === '1', 'save marks generated posts for scoped front-end presentation');
test_assert(!empty($GLOBALS['__test_post_categories'][$published['post_id']]), 'save creates and assigns the chosen category');
test_assert(($GLOBALS['__test_post_tags'][$published['post_id']] ?? []) === ['responsive design', 'accessibility'], 'save assigns the chosen contextual tags');
test_assert(($published['publishing']['category'] ?? '') === 'Web Design', 'save response reports the category that was actually assigned');
test_assert(($published['publishing']['tags'] ?? []) === ['responsive design', 'accessibility'], 'save response reports the tags that were actually assigned');
$GLOBALS['__test_capabilities']['manage_categories'] = false;
$GLOBALS['__test_terms']['post_tag']['Existing Tag'] = 301;
$contributor_save = $post_service->create_from_conversation(
    ['title' => 'Contributor publishing test', 'outline' => [], 'keywords' => [], 'meta' => []],
    [
        'content_html' => '<p>Useful article content.</p>',
        'category_name' => 'Restricted New Category',
        'tag_names' => 'Existing Tag, Restricted New Tag'
    ]
);
test_assert(!is_wp_error($contributor_save), 'a contributor can still save an article without category-management rights');
test_assert(!isset($GLOBALS['__test_terms']['category']['Restricted New Category']), 'save never lets a contributor create a category they cannot manage');
test_assert(($GLOBALS['__test_post_tags'][$contributor_save['post_id']] ?? []) === [301], 'a contributor may assign an existing tag but cannot create a new tag');
test_assert(($contributor_save['publishing']['category'] ?? null) === '', 'save response does not claim an unassigned category');
test_assert(($contributor_save['publishing']['tags'] ?? []) === ['Existing Tag'], 'save response reports only the tag actually assigned');
unset($GLOBALS['__test_capabilities']['manage_categories']);

$build_slug = new ReflectionMethod(AI_Scribe_Post_Service::class, 'build_post_slug');
$build_slug->setAccessible(true);
$slug = $build_slug->invoke($post_service, 'Web Design Tips for 2026 That Actually Keep Real Humans From Bouncing');
test_assert($slug === 'web-design-tips-2026-actually-keep-real-humans', 'new slug keeps useful terms and never ends on filler');
$build_excerpt = new ReflectionMethod(AI_Scribe_Post_Service::class, 'build_post_excerpt');
$build_excerpt->setAccessible(true);
$excerpt = $build_excerpt->invoke($post_service, '<h2>Useful advice</h2><p>' . str_repeat('Practical guidance for readers. ', 12) . '</p>');
test_assert(strlen($excerpt) > 80 && strlen($excerpt) < 400 && strpos($excerpt, '<') === false, 'save generates a useful plain-text excerpt');

$p5 = $prompts->assemble_step_prompt(5, $settings, $selections);
test_assert_contains('Return exactly one short tagline', $p5, 'tagline prompt requests one result');

$saved_before_retired_defaults = get_option('ab_prompts_content', []);
$legacy_keyword_default = 'For the title "[Title]", provide a list of 5 relevant keywords or phrases each on a new line. These need to be popular searches (keywords or short phrases) that people are likely to enter into Google and capable of driving traffic to the article. Capitalise each word. For each keyword, use your best knowledge of SEO, Google search volume and trends to add the expected average monthly global search volume and level of competition to display in this format - {keyword} | {av. global search volume} | {competition}. Do not add any labels, notes or explanations.';
update_option('ab_prompts_content', ['Keywords_prompts' => $legacy_keyword_default]);
$retired_keyword_prompt = $prompts->assemble_step_prompt(2, $settings, $selections);
test_assert_not_contains('expected average monthly global search volume', $retired_keyword_prompt, 'unedited retired keyword default upgrades on read');
test_assert_contains('structured keyword objects', $retired_keyword_prompt, 'corrected keyword default replaces the retired built-in wording');
update_option('ab_prompts_content', $saved_before_retired_defaults);

// Empty keywords: sentence removal, no leaked token.
$no_kw = $selections;
$no_kw['keywords'] = [];
$p3_empty = $prompts->assemble_step_prompt(3, $settings, $no_kw);
test_assert_not_contains('[Selected Keywords]', $p3_empty, 'step 3 with no keywords removes the token');
test_assert_not_contains('following SEO keywords', $p3_empty, 'step 3 with no keywords removes the sentence');

// Skip-tagline on step 6 removes tagline clauses (2.6.2 behaviour).
$p6_skip = $prompts->assemble_step_prompt(6, $settings, $selections, null, ['skip_tagline' => true]);
test_assert_not_contains('[The Tagline]', $p6_skip, 'step 6 skip-tagline leaves no token');
test_assert_not_contains('Add a tagline called', $p6_skip, 'step 6 skip-tagline removes instruction');

// Exclude keywords NOT appended on steps 9 and 11.
$p9 = $prompts->assemble_step_prompt(9, $settings, $selections);
test_assert_not_contains('Exclude the following keywords', $p9, 'step 9 (meta) gets no exclude-keywords append');
test_assert_contains('display guidance, not guaranteed search-engine limits', $p9, 'step 9 describes length ranges honestly');
test_assert_contains('best-effort attempt to cover it in BOTH fields', $p9, 'step 9 attempts every secondary in both fields');

test_assert_contains('Do not invent a brand name, statistics, credentials, offers, urgency or claims', $p9, 'step 9 prohibits unsupported metadata claims');
test_assert_contains('Electric Cars', $p9, 'step 9 receives the selected focus keyword');
test_assert_contains('Include its exact phrase naturally near the start of the meta title', $p9, 'step 9 requires the exact primary phrase in both fields');
test_assert_contains('Write the meta title in sensible title case', $p9, 'step 9 requires readable title casing');
test_assert_contains('Never keyword-stuff, repeat awkward phrases or sacrifice accuracy and readability', $p9, 'step 9 attempts broad secondary coverage without stuffing');
test_assert_contains('Use the spaced pipe " | " as the only separator', $p9, 'step 9 requires the spaced pipe title separator');
$custom_p9 = $prompts->assemble_step_prompt(9, $settings, $selections, 'MY CUSTOM SEO PROMPT FOR [Title]');
test_assert_contains('MY CUSTOM SEO PROMPT FOR Why Electric Cars Win', $custom_p9, 'custom SEO prompt remains intact');
test_assert_contains('SEO META ACCURACY POLICY', $custom_p9, 'stable accuracy policy also protects customised SEO prompts');
$p11 = $prompts->assemble_step_prompt(11, $settings, $selections);
test_assert_not_contains('Exclude the following keywords', $p11, 'step 11 (evaluate) gets no exclude-keywords append');

// Per-run prompt override still resolves placeholders (and gets the same
// exclude-keywords append the library prompt would).
$po = $prompts->assemble_step_prompt(4, $settings, $selections, 'Custom prompt about [Title] in [Language].');
test_assert_contains('Custom prompt about Why Electric Cars Win in English.', $po, 'prompt override resolves placeholders');
test_assert_contains('Exclude the following keywords', $po, 'prompt override still gets exclude-keywords append');

// Saved user edits win over defaults; missing keys fall back (option-name compatibility).
test_reset_options();
update_option('ab_prompts_content', ['Keywords_prompts' => 'MY CUSTOM KEYWORDS PROMPT for "[Title]"']);
$library = $prompts->get_prompt_library();
test_assert_contains('MY CUSTOM KEYWORDS PROMPT', $library['Keywords_prompts'], 'capital-K Keywords_prompts user edit preserved');
test_assert_contains('Provide 5 concise, genuinely different article titles', $library['title_prompts'], 'missing keys fall back to canonical defaults');
test_reset_options();

// ---------------------------------------------------------------------------
test_section('Conversation threading — step 7 receives the body (blind-writing regression)');
// ---------------------------------------------------------------------------

$conversations = $container->get('conversation_service');
$estimator = $container->get('cost_estimator');
$mock = new AI_Scribe_Test_Mock_Adapter();
$generation = new AI_Scribe_Generation_Service($logger, $config, $mock, $prompts, $conversations, $estimator);

$mock_meta_optimise = new AI_Scribe_Test_Mock_Adapter();
$mock_meta_optimise->queue(json_encode(['meta' => [
    'title' => 'Electric Cars | Costs, Performance and Charging Guide',
    'description' => 'Compare Electric Cars with EV Charging options, running costs and performance, using practical guidance to choose the right vehicle with confidence.',
]]));
$generation_meta_optimise = new AI_Scribe_Generation_Service($logger, $config, $mock_meta_optimise, $prompts, $conversations, $estimator);
$meta_cid = $conversations->create($settings, 'wizard');
$conversations->save_selection($meta_cid, 'keywords', ['Electric Cars', 'EV Charging']);
$meta_result = $generation_meta_optimise->optimise_meta($meta_cid, 'Electric Cars | A Very Long Guide to Costs Performance Charging and Ownership', str_repeat('Long metadata description ', 10));
test_assert(!empty($meta_result['success']), 'optional metadata optimiser returns a reviewed suggestion');
test_assert(($meta_result['meta']['title'] ?? '') === 'Electric Cars | Costs, Performance and Charging Guide', 'metadata optimiser preserves exact primary, target range and spaced pipe');
test_assert(isset($meta_result['cost']['running_total_usd']), 'metadata optimiser returns updated cost feedback');
test_assert(($meta_result['secondary_coverage'][0]['title'] ?? '') === 'partial' && ($meta_result['secondary_coverage'][0]['description'] ?? '') === 'exact', 'metadata optimiser self-audits every secondary against both fields');
$meta_request = $mock_meta_optimise->requests[0] ?? [];
$meta_prompt = $meta_request['messages'][count($meta_request['messages']) - 1]['content'] ?? '';
test_assert_contains('Do not end either field with an ellipsis', $meta_prompt, 'metadata optimiser prompt prohibits trailing ellipses');
test_assert_contains('For EVERY selected secondary keyword, attempt coverage in BOTH fields', $meta_prompt, 'metadata optimiser attempts every secondary in both fields');
test_assert_contains('Do not stuff or repeat awkward phrases', $meta_prompt, 'metadata optimiser avoids secondary-keyword stuffing');

$mock_meta_short = new AI_Scribe_Test_Mock_Adapter();
$mock_meta_short->queue(json_encode(['meta' => [
    'title' => 'Electric Cars | Guide',
    'description' => 'Electric Cars and EV Charging explained.',
]]));
$generation_meta_short = new AI_Scribe_Generation_Service($logger, $config, $mock_meta_short, $prompts, $conversations, $estimator);
$meta_short = $generation_meta_short->optimise_meta($meta_cid, str_repeat('Long title ', 8), str_repeat('Long description ', 12));
test_assert(empty($meta_short['success']) && ($meta_short['error']['code'] ?? '') === 'optimisation_failed', 'metadata optimiser rejects arbitrarily short output');

$mock_meta_separator = new AI_Scribe_Test_Mock_Adapter();
$mock_meta_separator->queue(json_encode(['meta' => [
    'title' => 'Electric Cars | Costs: Performance and Charging Guide',
    'description' => 'Compare Electric Cars with EV Charging options, running costs and performance, using practical guidance to choose the right vehicle with confidence.',
]]));
$generation_meta_separator = new AI_Scribe_Generation_Service($logger, $config, $mock_meta_separator, $prompts, $conversations, $estimator);
$meta_separator = $generation_meta_separator->optimise_meta($meta_cid, str_repeat('Long title ', 8), str_repeat('Long description ', 12));
test_assert(empty($meta_separator['success']) && ($meta_separator['error']['code'] ?? '') === 'optimisation_failed', 'metadata optimiser rejects colon as an extra title-component separator');

$long_primary_cid = $conversations->create($settings, 'wizard');
$conversations->save_selection($long_primary_cid, 'keywords', [str_repeat('Very Long Primary ', 5)]);
$long_primary_result = $generation_meta_optimise->optimise_meta($long_primary_cid, str_repeat('Long title ', 8), str_repeat('Long description ', 12));
test_assert(empty($long_primary_result['success']) && ($long_primary_result['error']['code'] ?? '') === 'primary_keyword_too_long', 'metadata optimiser explains when exact primary coverage makes the guide impossible');

$cid = $conversations->create($settings, 'wizard');
foreach (['title', 'keywords', 'outline', 'introduction', 'tagline'] as $key) {
    $conversations->save_selection($cid, $key, $selections[$key]);
}

$body_html = '<h1>Why Electric Cars Win</h1><h3>Running Costs</h3><p>UNIQUE-BODY-SENTINEL running costs are lower.</p>';
$mock->queue($body_html);
$r6 = $generation->run_step($cid, 6);
test_assert(!empty($r6['success']), 'step 6 (body) succeeds');
test_assert($r6['kind'] === 'longform' && strpos($r6['parsed']['html'], 'UNIQUE-BODY-SENTINEL') !== false, 'step 6 parsed html returned');

// THE regression test: the step-7 request must carry the article body.
$conversation = $conversations->get($cid);
$p7 = $prompts->assemble_step_prompt(7, $conversation['settings'], $conversation['selections']);
$messages7 = $generation->build_step_messages($conversation, 7, $p7, 'gpt-4o-mini');
$thread_text = '';
foreach ($messages7 as $m) {
    $thread_text .= is_string($m['content']) ? $m['content'] : json_encode($m['content']);
}
test_assert_contains('UNIQUE-BODY-SENTINEL', $thread_text, 'step 7 request contains the article body (fixes 2.6.2 blind writing)');
test_assert_contains('conclusion', strtolower($thread_text), 'step 7 request contains the conclusion prompt');

// Steps 8 and 9 also see full context.
$messages9 = $generation->build_step_messages($conversation, 9, $prompts->assemble_step_prompt(9, $conversation['settings'], $conversation['selections']), 'gpt-4o-mini');
$t9 = '';
foreach ($messages9 as $m) {
    $t9 .= is_string($m['content']) ? $m['content'] : json_encode($m['content']);
}
test_assert_contains('UNIQUE-BODY-SENTINEL', $t9, 'step 9 (meta) request contains the article body');

// Anthropic gets cache_control on the stable prefix.
$messages_claude = $generation->build_step_messages($conversation, 7, $p7, 'claude-sonnet-4-20250514');
$penultimate = $messages_claude[count($messages_claude) - 2];
test_assert(
    is_array($penultimate['content']) && isset($penultimate['content'][0]['cache_control']['type'])
        && $penultimate['content'][0]['cache_control']['type'] === 'ephemeral',
    'anthropic thread prefix carries cache_control'
);
$opts_claude = $generation->build_request_options('claude-sonnet-4-20250514', 7);
test_assert(isset($opts_claude['system'][0]['cache_control']), 'anthropic system prompt carries cache_control');

// Long-form request options: high max output, no stop sequences, unmodified temperature.
update_option('ab_gpt_ai_engine_settings', ['temp' => 0.5]);
$opts6 = $generation->build_request_options('gpt-4o-mini', 6);
test_assert(!isset($opts6['stop']), 'long-form has NO stop sequence');
test_assert(isset($opts6['max_tokens']) && $opts6['max_tokens'] >= 8000, 'long-form max output from ModelCapabilities (got ' . (isset($opts6['max_tokens']) ? $opts6['max_tokens'] : 'none') . ')');
test_reset_options();

// Structured options per provider for a choice step.
$opts1_openai = $generation->build_request_options('gpt-4o-mini', 1);
test_assert(isset($opts1_openai['response_format']['json_schema']['schema']), 'choice step openai gets response_format json_schema');
$opts1_claude = $generation->build_request_options('claude-sonnet-4-20250514', 1);
test_assert(isset($opts1_claude['tools'][0]['input_schema']) && $opts1_claude['tool_choice']['type'] === 'tool', 'choice step anthropic gets tool-forcing');
$opts1_gemini = $generation->build_request_options('gemini-2.5-flash', 1);
test_assert(isset($opts1_gemini['generationConfig']['responseSchema']), 'choice step gemini gets responseSchema');

// ---------------------------------------------------------------------------
test_section('Schema validation — accept and reject');
// ---------------------------------------------------------------------------

$ok = AI_Scribe_Schema_Registry::parse(1, '{"titles": ["A", "B", "C"]}');
test_assert($ok['ok'] && $ok['data']['titles'][1] === 'B', 'step 1 valid titles accepted');
$title_normalised = AI_Scribe_Schema_Registry::parse(1, '{"titles": ["Seo Tips for This Year", "SEO Tips for This Year"]}');
test_assert($title_normalised['ok'] && $title_normalised['data']['titles'] === ['SEO Tips for ' . gmdate('Y')], 'titles normalise SEO/year casing and remove duplicates');
$brand_title = AI_Scribe_Schema_Registry::parse(1, '{"titles": ["how to use seo with youtube and wordpress"]}');
test_assert($brand_title['ok'] && $brand_title['data']['titles'] === ['How to Use SEO with YouTube and WordPress'], 'generated titles use sensible title case while preserving brands and acronyms');
$keyword_evidence = AI_Scribe_Schema_Registry::parse(2, '{"keywords": ["seo tips | 1200 | High", "SEO tips | 999 | Low"]}');
test_assert(
    $keyword_evidence['ok']
    && $keyword_evidence['data']['keywords'] === [[
        'keyword' => 'SEO tips',
        'role' => 'primary',
        'demand_band' => 'unknown',
        'estimate_basis' => 'ai_unverified',
    ]],
    'legacy keyword strings lose old metrics, become unknown unverified estimates, and remove duplicates'
);
$structured_keywords = AI_Scribe_Schema_Registry::parse(2, '{"keywords": [{"keyword":"seo checklist","role":"supporting","demand_band":"HIGH","estimate_basis":"ai_unverified"},{"keyword":"technical seo audit guide","role":"long_tail","demand_band":"low","estimate_basis":"ai_unverified"},{"keyword":"seo tools","role":"primary","demand_band":"made-up","estimate_basis":"wrong"}]}');
test_assert($structured_keywords['ok'], 'structured keyword objects are accepted after safe normalisation');
test_assert($structured_keywords['data']['keywords'][0] === [
    'keyword' => 'SEO checklist',
    'role' => 'primary',
    'demand_band' => 'high',
    'estimate_basis' => 'ai_unverified',
], 'first keyword is the single primary and valid demand is normalised');
test_assert($structured_keywords['data']['keywords'][1]['role'] === 'long-tail', 'long-tail role spelling is normalised');
test_assert($structured_keywords['data']['keywords'][2]['role'] === 'supporting' && $structured_keywords['data']['keywords'][2]['demand_band'] === 'unknown' && $structured_keywords['data']['keywords'][2]['estimate_basis'] === 'ai_unverified', 'extra primary and invalid provenance or demand fail closed');
test_assert(AI_Scribe_Schema_Registry::keyword_phrases($structured_keywords['data']['keywords']) === ['SEO checklist', 'technical SEO audit guide', 'SEO tools'], 'structured keyword metadata reduces to clean phrases downstream');
$one_tagline = AI_Scribe_Schema_Registry::parse(5, '{"taglines": ["One specific line", "One specific line", "Another line"]}');
test_assert($one_tagline['ok'] && $one_tagline['data']['taglines'] === ['One specific line'], 'tagline output is exactly one unique suggestion');

$fenced = AI_Scribe_Schema_Registry::parse(1, "```json\n{\"titles\": [\"A\"]}\n```");
test_assert($fenced['ok'], 'code-fenced JSON accepted');

$bad = AI_Scribe_Schema_Registry::parse(1, '{"titles": []}');
test_assert(!$bad['ok'], 'empty titles array rejected (minItems)');

$wrong = AI_Scribe_Schema_Registry::parse(1, '{"headlines": ["A"]}');
test_assert(!$wrong['ok'], 'wrong property name rejected');

$notjson = AI_Scribe_Schema_Registry::parse(2, 'Here are some keywords: EV, cars');
test_assert(!$notjson['ok'], 'prose response rejected for choice step');

$qna_ok = AI_Scribe_Schema_Registry::parse(8, '{"qna": [{"question": "Q?", "answer": "A."}]}');
test_assert($qna_ok['ok'], 'step 8 qna accepted');
$qna_bad = AI_Scribe_Schema_Registry::parse(8, '{"qna": [{"question": "Q?"}]}');
test_assert(!$qna_bad['ok'], 'qna missing answer rejected');

$meta_ok = AI_Scribe_Schema_Registry::parse(9, '{"meta": {"title": "seo tips for wordpress: a practical 2026 guide", "description": "Use seo and ai with wordpress."}}');
test_assert($meta_ok['ok'], 'step 9 meta accepted');
test_assert($meta_ok['data']['meta']['title'] === 'SEO Tips for WordPress | A Practical 2026 Guide', 'meta title gets sensible casing, canonical brands and the required spaced pipe');
test_assert($meta_ok['data']['meta']['description'] === 'Use SEO and AI with WordPress.', 'meta description preserves canonical acronyms and brands without title-casing prose');
$meta_bad_structure = AI_Scribe_Schema_Registry::parse(9, '{"meta": {"title": "SEO Tips for WordPress", "description": "A useful description."}}');
test_assert(!$meta_bad_structure['ok'] && strpos($meta_bad_structure['errors'][0], 'spaced pipe') !== false, 'meta title without two pipe-separated components fails closed');

$evaluation_unknown = AI_Scribe_Schema_Registry::parse(11, '{"checks":[{"label":"Authority","status":"unknown","detail":"The article does not identify a source.","suggestion":"Verify the claim."}]}');
test_assert($evaluation_unknown['ok'], 'step 11 accepts a truthful unknown status');

$long_ok = AI_Scribe_Schema_Registry::parse(4, '<p>An introduction.</p>');
test_assert($long_ok['ok'] && $long_ok['data']['html'] === '<p>An introduction.</p>', 'long-form non-empty HTML accepted');
$inline_markdown = AI_Scribe_Schema_Registry::parse(4, 'This is **bold** text.');
test_assert($inline_markdown['ok'] && $inline_markdown['data']['html'] === '<p>This is <strong>bold</strong> text.</p>', 'inline-only Markdown is formatted before the editor displays it');
$long_bad = AI_Scribe_Schema_Registry::parse(4, '   ');
test_assert(!$long_bad['ok'], 'long-form empty rejected');

// ---------------------------------------------------------------------------
test_section('Evaluate — exact Review HTML and deterministic structure facts');
// ---------------------------------------------------------------------------

$cid_eval = $conversations->create($settings, 'wizard');
$mock_eval = new AI_Scribe_Test_Mock_Adapter();
$generation_eval = new AI_Scribe_Generation_Service($logger, $config, $mock_eval, $prompts, $conversations, $estimator);
$final_review_html = '<nav><a href="#evidence">Evidence</a></nav><h1>Latest owner edit</h1><p>This exact revision includes the final illustration and a useful reference.</p><h2 id="evidence">Evidence</h2><p><img src="https://example.test/final.jpg" alt="Final diagram"></p><p><a href="/related-guide">Related guide</a> <a href="https://source.example/report">Supporting source</a></p>';
$mock_eval->queue('{"checks":[{"label":"Images and Visual Media","status":"fail","detail":"No images are present.","suggestion":"Add an image."},{"label":"Original perspective","status":"pass","detail":"The article has an original perspective.","suggestion":""}]}');
$r_eval = $generation_eval->run_step($cid_eval, 11, ['content_html' => $final_review_html]);
test_assert(!empty($r_eval['success']), 'step 11 succeeds with the exact final Review HTML');
test_assert($r_eval['parsed']['facts']['image_count'] === 1, 'image count is measured from final Review HTML');
test_assert($r_eval['parsed']['facts']['link_count'] === 3, 'total link count is measured from final Review HTML');
test_assert($r_eval['parsed']['facts']['valid_anchor_link_count'] === 1, 'TOC link is classified only when its target exists');
test_assert($r_eval['parsed']['facts']['internal_contextual_link_count'] === 1, 'relative text link is classified as internal contextual');
test_assert($r_eval['parsed']['facts']['external_contextual_link_count'] === 1, 'off-site text link is classified as external contextual');
test_assert($r_eval['parsed']['facts']['heading_count'] === 2, 'heading count is measured from final Review HTML');
test_assert($r_eval['parsed']['checks'][1]['label'] === 'Image accessibility markup' && $r_eval['parsed']['checks'][1]['status'] === 'pass', 'deterministic image check reports only verified alt-attribute markup');
$eval_labels = array_column($r_eval['parsed']['checks'], 'label');
test_assert(!in_array('Images and Visual Media', $eval_labels, true), 'contradictory provider image check is discarded');
test_assert(in_array('Original perspective', $eval_labels, true), 'grounded subjective check is retained');
$subjective_index = array_search('Original perspective', $eval_labels, true);
test_assert($r_eval['parsed']['checks'][$subjective_index]['status'] === 'pass', 'provider editorial pass remains an explicitly labelled AI review result');
test_assert_contains('AI editorial review of the supplied article', $r_eval['parsed']['checks'][$subjective_index]['detail'], 'provider editorial evidence is explicitly labelled as an AI review');
test_assert_contains('not external fact-checking', $r_eval['parsed']['checks'][$subjective_index]['detail'], 'provider editorial evidence never claims external verification');
test_assert(!in_array('unknown', array_column($r_eval['parsed']['checks'], 'status'), true), 'new evaluation output uses actionable pass, check or fail states rather than Unknown');
$eval_row = $conversations->get($cid_eval);
test_assert(($eval_row['selections']['final_article'] ?? '') === $final_review_html, 'exact final Review HTML is persisted for recovery and audit');
$eval_messages = $conversations->get_messages($cid_eval);
test_assert_contains('Latest owner edit', $eval_messages[0]['content'], 'provider receives latest Review editor content, not an earlier body snapshot');
test_assert_contains('"image_count":1', $eval_messages[0]['content'], 'provider receives measured structural facts');

$toc_only_facts = $generation_eval->analyse_article_html('<h1>Guide</h1><nav><a href="#part">Part</a></nav><h2 id="part">Part</h2>');
test_assert($toc_only_facts['valid_anchor_link_count'] === 1, 'TOC-only article records its valid anchor');
test_assert($toc_only_facts['internal_contextual_link_count'] === 0 && $toc_only_facts['external_contextual_link_count'] === 0, 'TOC links never count as internal or external contextual links');
$cid_toc_only = $conversations->create($settings, 'wizard');
$mock_toc_only = new AI_Scribe_Test_Mock_Adapter();
$generation_toc_only = new AI_Scribe_Generation_Service($logger, $config, $mock_toc_only, $prompts, $conversations, $estimator);
$mock_toc_only->queue('{"checks":[{"label":"Readability","status":"pass","detail":"The prose is easy to read.","suggestion":""}]}');
$toc_only_result = $generation_toc_only->run_step($cid_toc_only, 11, ['content_html' => '<h1>Guide</h1><nav><a href="#part">Part</a></nav><h2 id="part">Part</h2>']);
$toc_checks = [];
foreach ($toc_only_result['parsed']['checks'] as $check) {
    $toc_checks[$check['label']] = $check;
}
test_assert($toc_checks['Table of contents and anchor links']['status'] === 'pass', 'valid TOC markup can pass its own exact anchor-target check');
test_assert($toc_checks['Internal contextual links']['status'] === 'warn', 'TOC-only article receives an actionable Check for missing internal contextual links');
test_assert($toc_checks['External contextual links']['status'] === 'warn', 'TOC-only article receives an actionable Check for missing external contextual links');
test_assert($toc_checks['Readability']['status'] === 'pass', 'subjective readability retains the provider result while remaining explicitly labelled as AI review');
test_assert_contains('AI editorial review', $toc_checks['Readability']['detail'], 'subjective readability is not presented as a measured or externally verified fact');

$cid_eval_missing = $conversations->create($settings, 'wizard');
$missing_eval = $generation_eval->run_step($cid_eval_missing, 11);
test_assert(empty($missing_eval['success']) && $missing_eval['error']['code'] === 'invalid_params', 'evaluate fails closed when final Review HTML is unavailable');

$controller_src = file_get_contents(dirname(__DIR__, 2) . '/assets/js/controllers/WizardFlowController.js');
test_assert_contains("'[data-step-panel=\"10\"].active .ql-editor'", $controller_src, 'visible Review DOM is snapshotted before navigating to Evaluate');
test_assert_contains("extras.content_html = this.pendingEvaluateHtml", $controller_src, 'step 11 sends the pre-navigation Review snapshot');
test_assert_contains("nextView.state === 'idle' || step === 10", $controller_src, 'Continue from Review always refreshes Evaluate');
$evaluate_view_src = file_get_contents(dirname(__DIR__, 2) . '/assets/js/views/steps/EvaluateStepView.js');
test_assert_contains("['Status', 'Check', 'Evidence', 'What to do']", $evaluate_view_src, 'evaluate report separates evidence from the next action');
test_assert_not_contains('Could not verify', $evaluate_view_src, 'evaluate summary no longer presents a misleading bulk Could not verify count');
test_assert_contains("unknown: 'Review'", $evaluate_view_src, 'legacy stored Unknown rows render as a clear Review action');
$components_css = file_get_contents(dirname(__DIR__, 2) . '/assets/css/components.css');
test_assert_contains('.evaluation-table-container.stream-output', $components_css, 'evaluate overrides the nested streaming scroll container');
test_assert_contains('position: sticky', $components_css, 'evaluate table headings have sticky behaviour');

// ---------------------------------------------------------------------------
test_section('Never advance on failure — response persisted for retry');
// ---------------------------------------------------------------------------

$cid2 = $conversations->create($settings, 'wizard');
$mock2 = new AI_Scribe_Test_Mock_Adapter();
$generation2 = new AI_Scribe_Generation_Service($logger, $config, $mock2, $prompts, $conversations, $estimator);

$mock2->queue('This is not JSON at all.');
$fail = $generation2->run_step($cid2, 1);
test_assert(empty($fail['success']), 'invalid choice response returns failure');
test_assert($fail['error']['code'] === 'schema_validation_failed' && $fail['error']['retryable'] === true, 'typed retryable error');
$state2 = $conversations->get_state($cid2);
$steps2 = (array) $state2['steps'];
test_assert(isset($steps2['1']) && $steps2['1']['status'] === 'failed', 'failed step recorded, not complete');
test_assert($steps2['1']['raw'] === 'This is not JSON at all.', 'raw response persisted server-side (no wasted tokens)');
$messages_after_fail = $conversations->get_messages($cid2);
test_assert(count($messages_after_fail) === 0, 'failed step does NOT pollute the thread');

$mock2->queue(new WP_Error('http', 'Rate limit exceeded (429)'));
$fail429 = $generation2->run_step($cid2, 1);
test_assert(empty($fail429['success']) && $fail429['error']['code'] === 'rate_limited', 'provider 429 mapped to rate_limited');

// ---------------------------------------------------------------------------
test_section('run_step happy path — persisted before return, cost recorded');
// ---------------------------------------------------------------------------

$cid3 = $conversations->create($settings, 'wizard');
$mock3 = new AI_Scribe_Test_Mock_Adapter();
$generation3 = new AI_Scribe_Generation_Service($logger, $config, $mock3, $prompts, $conversations, $estimator);

$mock3->queue('{"titles": ["Title One", "Title Two"]}', ['prompt_tokens' => 2000, 'completion_tokens' => 100, 'total_tokens' => 2100]);
$r1 = $generation3->run_step($cid3, 1);
test_assert(!empty($r1['success']) && $r1['parsed']['titles'][0] === 'Title One', 'choice step parsed titles returned');
$state3 = $conversations->get_state($cid3);
$steps3 = (array) $state3['steps'];
test_assert($steps3['1']['status'] === 'complete' && $steps3['1']['parsed']['titles'][1] === 'Title Two', 'step response persisted');
test_assert($state3['cost']['running_total_usd'] > 0, 'actual cost recorded from usage block');
test_assert(count($conversations->get_messages($cid3)) === 2, 'prompt + response threaded into history');

// C-2-1: completing the keywords step persists the first suggestion as the
// default selection, so a user who just clicks Continue still gets a focus
// keyword on the saved post.
$conversations->save_selection($cid3, 'title', 'Title One');
$mock3->queue('{"keywords": ["Electric Cars", "EV Charging"]}');
$r2_kw = $generation3->run_step($cid3, 2);
$row_kw = $conversations->get($cid3);
test_assert(!empty($r2_kw['success']), 'keywords step succeeds');
test_assert(($r2_kw['parsed']['keywords'][0]['keyword'] ?? '') === 'Electric Cars', 'legacy provider strings are returned as structured keyword objects');
test_assert(($r2_kw['parsed']['keywords'][0]['demand_band'] ?? '') === 'unknown', 'legacy provider strings never receive an invented demand band');
test_assert(($row_kw['selections']['keywords'] ?? null) === ['Electric Cars'], 'default keyword persisted as the selection (C-2-1)');

$conversations->save_selection($cid3, 'keywords', [[
    'keyword' => 'EV charging costs',
    'role' => 'long-tail',
    'demand_band' => 'medium',
    'estimate_basis' => 'ai_unverified',
]]);
$row_kw = $conversations->get($cid3);
test_assert(($row_kw['selections']['keywords'] ?? null) === ['EV charging costs'], 'structured keyword selections persist as compatible phrase strings');

// An explicit user selection survives a regenerate untouched.
$conversations->save_selection($cid3, 'keywords', ['EV Charging']);
$mock3->queue('{"keywords": ["Something Else"]}');
$generation3->run_step($cid3, 2, ['regenerate' => true]);
$row_kw = $conversations->get($cid3);
test_assert(($row_kw['selections']['keywords'] ?? null) === ['EV Charging'], 'explicit keyword selection never overwritten by the backstop');

// ---------------------------------------------------------------------------
test_section('Migration from 2.6.2 options');
// ---------------------------------------------------------------------------

test_reset_options();
update_option('ab_prompts_content', [
    'title_prompts' => 'USER EDITED TITLE PROMPT [Idea]',
    'Keywords_prompts' => 'USER EDITED KEYWORDS [Title]',
    // outline/intro/... missing — must be filled from defaults
]);
update_option('ab_gpt_content_settings', ['language' => 'French']);
update_option('ab_gpt_ai_engine_settings', ['model' => 'gpt-4.5-preview', 'temp' => 0.9]);

AI_Scribe_Migration_Service::maybe_migrate($prompts);

$migrated_prompts = get_option('ab_prompts_content');
test_assert($migrated_prompts['title_prompts'] === 'USER EDITED TITLE PROMPT [Idea]', 'user prompt edits never overwritten');
test_assert($migrated_prompts['Keywords_prompts'] === 'USER EDITED KEYWORDS [Title]', 'capital-K Keywords_prompts preserved');
test_assert(isset($migrated_prompts['outline_prompts']) && strpos($migrated_prompts['outline_prompts'], 'article outline') !== false, 'missing prompt keys filled from defaults');
test_assert(isset($migrated_prompts['evaluate_prompts']), 'evaluate prompt filled');

$migrated_content = get_option('ab_gpt_content_settings');
test_assert($migrated_content['language'] === 'French', 'user content settings preserved');
test_assert($migrated_content['Heading_tag'] === 'H2', 'missing content defaults filled');

$migrated_engine = get_option('ab_gpt_ai_engine_settings');
test_assert($migrated_engine['temp'] === 0.9, 'user sampling preserved');
test_assert($migrated_engine['model'] === 'gpt-4o-mini' && $migrated_engine['model_pre_v3'] === 'gpt-4.5-preview', 'retired model remapped with original kept');

test_assert(get_option('ai_scribe_v3_migrated') === AI_Scribe_Migration_Service::MIGRATION_VERSION, 'migration marked done');
test_assert(get_option('ai_scribe_model_remap_notice') === ['from' => 'gpt-4.5-preview', 'to' => 'gpt-4o-mini', 'reason' => 'retired'], 'model remap and reason recorded for the visible notice (§15.1)');
$before = get_option('ab_prompts_content');
AI_Scribe_Migration_Service::maybe_migrate($prompts);
test_assert(get_option('ab_prompts_content') === $before, 'migration is one-time (idempotent)');
test_reset_options();

// ---------------------------------------------------------------------------
test_section('Express mode — compilation and persistence');
// ---------------------------------------------------------------------------

$express_prompt = $generation->compile_express_prompt($settings);
test_assert_contains('benefits of electric cars', $express_prompt, 'express prompt contains the idea');
test_assert_contains('4 sections', $express_prompt, 'express prompt contains heading count');
test_assert_contains('English', $express_prompt, 'express prompt contains language');
test_assert_contains('body_html', $express_prompt, 'express prompt requests structured shape');
test_assert_contains('Exclude the following keywords: cheap, budget', $express_prompt, 'express prompt carries avoid keywords');
test_assert_contains('one and only article title', $express_prompt, 'express prompt reserves H1 for the title field');
test_assert_contains('Do not include an H1 or repeat the article title or meta title', $express_prompt, 'express prompt excludes title artefacts from content fragments');

$express_normalised = AI_Scribe_Schema_Registry::parse('express', json_encode([
    'title' => '<strong>Canonical Article Title</strong>',
    'meta' => ['title' => 'SEO Meta Title | Site', 'description' => 'Description'],
    'tagline' => 'Tagline',
    'outline' => ['Section One'],
    'intro' => '<p>Introduction.</p>',
    'body_html' => '<h1>SEO Meta Title | Site</h1><h2>Section One</h2><p>Body.</p>',
    'conclusion' => '<h1>Canonical Article Title</h1><p>Conclusion.</p>',
    'qna' => [],
]));
test_assert(!empty($express_normalised['ok']), 'express response with provider-added H1 remains recoverable');
test_assert($express_normalised['data']['title'] === 'Canonical Article Title', 'express title is stored as plain authoritative text');
test_assert(stripos($express_normalised['data']['body_html'], '<h1') === false, 'express body persists without a second H1');
test_assert(stripos($express_normalised['data']['body_html'], 'SEO Meta Title') === false, 'meta-title-like H1 is removed from the body');
test_assert(stripos($express_normalised['data']['conclusion'], '<h1') === false, 'express conclusion persists without a second H1');

$cid4 = $conversations->create($settings, 'express');
$mock4 = new AI_Scribe_Test_Mock_Adapter();
$generation4 = new AI_Scribe_Generation_Service($logger, $config, $mock4, $prompts, $conversations, $estimator);
$express_article = [
    'title' => 'Express Title',
    'meta' => ['title' => 'Meta T', 'description' => 'Meta D'],
    'tagline' => 'Zip zap',
    'outline' => ['S1', 'S2', 'S3', 'S4'],
    'intro' => '<p>Intro.</p>',
    'body_html' => '<h1>Express Title</h1><h3>S1</h3><p>Body.</p>',
    'conclusion' => '<p>Concl.</p>',
    'qna' => [['question' => 'Q1?', 'answer' => 'A1.']],
];
$mock4->queue(json_encode($express_article), ['prompt_tokens' => 1500, 'completion_tokens' => 4000, 'total_tokens' => 5500]);
$rex = $generation4->run_express($cid4);
test_assert(!empty($rex['success']), 'express run succeeds');
test_assert($rex['article']['title'] === 'Express Title' && count($rex['article']['qna']) === 1, 'express article returned');
$state4 = $conversations->get_state($cid4);
$sel4 = (array) $state4['selections'];
$steps4 = (array) $state4['steps'];
test_assert($sel4['title'] === 'Express Title' && strpos($sel4['body'], '<h1') === false && strpos($sel4['body'], '<h3>S1</h3>') !== false, 'express persists the canonical H1-free body into selections');
test_assert($steps4['6']['status'] === 'complete' && $steps4['9']['parsed']['meta']['description'] === 'Meta D', 'express mirrored into wizard steps for refinement');
test_assert($state4['cost']['running_total_usd'] > 0, 'express cost recorded');

$mock5 = new AI_Scribe_Test_Mock_Adapter();
$generation5 = new AI_Scribe_Generation_Service($logger, $config, $mock5, $prompts, $conversations, $estimator);
$cid5 = $conversations->create($settings, 'express');
$mock5->queue('{"title": "only a title"}');
$rex_bad = $generation5->run_express($cid5);
test_assert(empty($rex_bad['success']) && $rex_bad['error']['code'] === 'schema_validation_failed', 'incomplete express payload rejected');

// ---------------------------------------------------------------------------
test_section('Cost estimator maths');
// ---------------------------------------------------------------------------

$pricing = $estimator->get_pricing('gpt-4o-mini');
test_assert(abs($pricing['input'] - 0.15) < 1e-9 && abs($pricing['output'] - 0.60) < 1e-9, 'known model pricing exact');
test_assert(abs($pricing['cached_input'] - 0.015) < 1e-9, 'cached input at 10% of input');

$prefix = $estimator->get_pricing('claude-sonnet-5-20260630');
test_assert(abs($prefix['input'] - 3.00) < 1e-9, 'prefix match finds claude-sonnet-5 pricing');

$unknown = $estimator->get_pricing('some-future-model');
test_assert($unknown['input'] > 0 && $unknown['output'] > 0, 'unknown model gets a safe fallback');

// actual_cost: 1M input + 1M output at gpt-4o-mini = 0.15 + 0.60
$usd = $estimator->actual_cost('gpt-4o-mini', ['prompt_tokens' => 1000000, 'completion_tokens' => 1000000]);
test_assert(abs($usd - 0.75) < 1e-9, 'actual cost = in*price_in + out*price_out (got ' . $usd . ')');

// cached tokens billed at cached rate
$usd_cached = $estimator->actual_cost('gpt-4o-mini', ['prompt_tokens' => 1000000, 'completion_tokens' => 0, 'cached_tokens' => 1000000]);
test_assert(abs($usd_cached - 0.015) < 1e-9, 'fully-cached input billed at cached rate');

$est = $estimator->estimate_step('gpt-4o-mini', 6, 4000, false);
$expected = (4000 * 0.15 + 9000 * 0.60) / 1000000;
test_assert(abs($est['usd_without_caching'] - round($expected, 6)) < 1e-9, 'step estimate maths (fresh)');
test_assert($est['output_tokens'] === 9000, 'body estimate includes both bounded corrective-output allowances');
$est_cached = $estimator->estimate_step('gpt-4o-mini', 6, 4000, true);
test_assert($est_cached['usd'] < $est_cached['usd_without_caching'], 'cached estimate cheaper than fresh');

$article_est = $estimator->estimate_article('gpt-4o-mini', 'wizard');
test_assert(count($article_est['steps']) === 10, 'wizard estimate covers 10 API steps (step 10 is client-side)');
test_assert($article_est['total']['cache_savings_usd'] > 0, 'article estimate shows cache savings');
$express_est = $estimator->estimate_article('gpt-4o-mini', 'express');
test_assert($express_est['total']['output_tokens'] === 13000, 'Express estimate includes both bounded corrective-output allowances');
test_assert($express_est['total']['usd'] < $article_est['total']['usd_without_caching'], 'express estimate cheaper than uncached wizard');

// ---------------------------------------------------------------------------
test_section('Conversation service basics');
// ---------------------------------------------------------------------------

$cid6 = $conversations->create(['idea' => 'x'], 'wizard');
test_assert($conversations->save_selection($cid6, 'meta', ['title' => 'T', 'description' => 'D']), 'meta selection saved');
test_assert(!$conversations->save_selection($cid6, 'bogus_key', 'x'), 'invalid selection key rejected');
$seo_view_source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/views/steps/SeoMetaStepView.js');
$review_source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/views/steps/ReviewStepView.js');
$post_source = file_get_contents(dirname(__DIR__, 2) . '/includes/services/class-post-service.php');
test_assert_contains("stepData[9].selection", $seo_view_source, 'SEO edits are persisted into AppState step 9');
test_assert_contains("meta: (stepData[9] && stepData[9].selection) || {}", $review_source, 'Review receives edited SEO metadata from AppState');
test_assert_contains("isset( \$selections['meta'] )", $post_source, 'post save reads the persisted edited metadata selection');
test_assert($conversations->get_state(999999) === null, 'unknown conversation returns null state');
$saved_post_html = '<h1>Saved article</h1><p>Confirmed version.</p>';
test_assert($conversations->update_settings($cid6, [
    'post_id' => 731,
    'post_status' => 'draft',
    'post_html' => $saved_post_html,
    'post_edit_link' => '/wp-admin/post.php?post=731&action=edit',
]), 'confirmed post destination is stored with the conversation');
$saved_destination_state = $conversations->get_state($cid6);
test_assert(($saved_destination_state['settings']['post_id'] ?? 0) === 731, 'saved post id survives conversation recovery');
test_assert(($saved_destination_state['settings']['post_status'] ?? '') === 'draft', 'saved post status survives conversation recovery');
test_assert(($saved_destination_state['settings']['post_html'] ?? '') === $saved_post_html, 'exact saved HTML survives for dirty-state comparison');
test_assert_contains('post=731', ($saved_destination_state['settings']['post_edit_link'] ?? ''), 'saved post edit link survives conversation recovery');
$conversations->set_status($cid6, 'complete');
$s6 = $conversations->get_state($cid6);
test_assert($s6['status'] === 'complete', 'status transition persisted');

// ---------------------------------------------------------------------------
test_section('P4 — Abilities API registration (mocked core API)');
// ---------------------------------------------------------------------------

$registrar = $container->get('abilities_registrar');
test_assert($registrar instanceof AI_Scribe_Abilities_Registrar, 'abilities_registrar service resolves');

$registrar->register_category();
$registrar->register_abilities();

test_assert(isset($GLOBALS['__test_ability_categories']['ai-scribe']), 'ai-scribe ability category registered');

$expected_abilities = [
    'ai-scribe/generate-article',
    'ai-scribe/generate-titles',
    'ai-scribe/humanize-content',
    'ai-scribe/generate-seo-meta',
];
foreach ($expected_abilities as $name) {
    $args = isset($GLOBALS['__test_abilities'][$name]) ? $GLOBALS['__test_abilities'][$name] : null;
    test_assert(is_array($args), "{$name} registered");
    if (!is_array($args)) {
        continue;
    }
    test_assert($args['category'] === 'ai-scribe', "{$name} in ai-scribe category");
    test_assert(is_array($args['input_schema']) && $args['input_schema']['type'] === 'object', "{$name} has object input_schema");
    test_assert(is_array($args['output_schema']) && $args['output_schema']['type'] === 'object', "{$name} has object output_schema");
    test_assert(is_callable($args['execute_callback']), "{$name} execute_callback callable");
    test_assert(is_callable($args['permission_callback']) && call_user_func($args['permission_callback']) === true, "{$name} permission callback = edit_posts");
}

test_assert(in_array('idea', $GLOBALS['__test_abilities']['ai-scribe/generate-article']['input_schema']['required'], true), 'generate-article requires idea');
test_assert(in_array('content', $GLOBALS['__test_abilities']['ai-scribe/generate-seo-meta']['input_schema']['required'], true), 'generate-seo-meta requires content');

// ---------------------------------------------------------------------------
test_section('P4 — Ability execution (stub container + mock adapter)');
// ---------------------------------------------------------------------------

$ability_mock = new AI_Scribe_Test_Mock_Adapter();
$ability_generation = new AI_Scribe_Generation_Service($logger, $config, $ability_mock, $prompts, $conversations, $estimator);
$stub = new AI_Scribe_Test_Stub_Container([
    'conversation_service' => $conversations,
    'generation_service' => $ability_generation,
    'prompt_manager' => $prompts,
    'config' => $config,
    'text_adapter' => $ability_mock,
]);
$exec_registrar = new AI_Scribe_Abilities_Registrar($stub);

// generate-titles
$ability_mock->queue(json_encode(['titles' => ['Title One', 'Title Two', 'Title Three']]));
$r = $exec_registrar->execute_generate_titles(['idea' => 'solar panels for homes']);
test_assert(!is_wp_error($r) && $r['titles'] === ['Title One', 'Title Two', 'Title Three'], 'generate-titles returns parsed titles');
test_assert(!is_wp_error($r) && is_int($r['conversation_id']), 'generate-titles returns a conversation id');

$r = $exec_registrar->execute_generate_titles(['idea' => '']);
test_assert(is_wp_error($r) && $r->get_error_code() === 'invalid_params', 'generate-titles rejects empty idea');

// humanize-content: system message must carry the Humanise instructions
$ability_mock->queue('<p>A properly human paragraph.</p>');
$r = $exec_registrar->execute_humanize_content(['content' => '<p>Robotic AI text.</p>']);
test_assert(!is_wp_error($r) && $r['content'] === '<p>A properly human paragraph.</p>', 'humanize-content returns rewritten content');
$last_request = end($ability_mock->requests);
test_assert($last_request['messages'][0]['role'] === 'system', 'humanize request carries a system message');
test_assert_contains('Robotic AI text.', $last_request['messages'][1]['content'], 'humanize request includes the original content');

$r = $exec_registrar->execute_humanize_content(['content' => '']);
test_assert(is_wp_error($r) && $r->get_error_code() === 'invalid_params', 'humanize-content rejects empty content');

// generate-seo-meta: the article body must be in the request context
$ability_mock->queue(json_encode(['meta' => ['title' => 'Meta Title | Useful Guide', 'description' => 'Meta description here.']]));
$r = $exec_registrar->execute_generate_seo_meta([
    'title' => 'Why Electric Cars Win',
    'content' => '<p>ABILITY-BODY-SENTINEL the full article body.</p>',
]);
test_assert(!is_wp_error($r) && $r['meta']['title'] === 'Meta Title | Useful Guide', 'generate-seo-meta returns parsed meta');
$last_request = end($ability_mock->requests);
$meta_context = '';
foreach ($last_request['messages'] as $m) {
    $meta_context .= is_string($m['content']) ? $m['content'] : json_encode($m['content']);
}
test_assert_contains('ABILITY-BODY-SENTINEL', $meta_context, 'generate-seo-meta request contains the article body (never written blind)');

// generate-article (Express)
$ability_mock->queue(json_encode([
    'title' => 'Express Title',
    'meta' => ['title' => 'Express Meta', 'description' => 'Express description.'],
    'tagline' => 'Express tagline',
    'outline' => ['One', 'Two'],
    'intro' => '<p>Intro.</p>',
    'body_html' => '<h2>One</h2><p>Body.</p>',
    'conclusion' => '<p>End.</p>',
    'qna' => [['question' => 'Q?', 'answer' => 'A.']],
]));
$r = $exec_registrar->execute_generate_article(['idea' => 'heat pumps explained']);
test_assert(!is_wp_error($r) && $r['article']['title'] === 'Express Title', 'generate-article returns the express article');
test_assert(!is_wp_error($r) && $r['article']['body_html'] === '<h2>One</h2><p>Body.</p>', 'generate-article article body intact');

// Provider failure surfaces as WP_Error (mock queue empty → provider error)
$r = $exec_registrar->execute_generate_titles(['idea' => 'another idea']);
test_assert(is_wp_error($r), 'ability surfaces provider failure as WP_Error');

// Missing services → not_configured (graceful, no fatal)
$empty_registrar = new AI_Scribe_Abilities_Registrar(new AI_Scribe_Test_Stub_Container([]));
$r = $empty_registrar->execute_generate_article(['idea' => 'x']);
test_assert(is_wp_error($r) && $r->get_error_code() === 'not_configured', 'missing services reported as not_configured');

// ---------------------------------------------------------------------------
test_section('P4 — WP AI Client adapter existence guards (WP < 7 environment)');
// ---------------------------------------------------------------------------

// This test env has no wp_ai_client_prompt()/wp_supports_ai(): everything
// must degrade gracefully, never fatal.
test_assert(AI_Scribe_WP_AI_Client_Adapter::is_available() === false, 'is_available() false without the core AI client');
test_assert(AI_Scribe_WP_AI_Client_Adapter::is_configured() === false, 'is_configured() false without the core AI client');
test_assert(AI_Scribe_WP_AI_Client_Adapter::provider_choice() === null, 'provider_choice() hidden on WP < 7');
test_assert(AI_Scribe_WP_AI_Client_Adapter::get_provider_status() === [], 'get_provider_status() empty on WP < 7');
test_assert(AI_Scribe_WP_AI_Client_Adapter::is_selected('wordpress-ai'), 'is_selected() matches the wordpress-ai model id');
test_assert(AI_Scribe_WP_AI_Client_Adapter::is_selected('gpt-4o-mini', 'wordpress'), 'is_selected() matches the wordpress provider id');
test_assert(!AI_Scribe_WP_AI_Client_Adapter::is_selected('gpt-4o-mini'), 'is_selected() false for ordinary models');

$wp_adapter = new AI_Scribe_WP_AI_Client_Adapter($logger, $config);
$r = $wp_adapter->generate_text('wordpress-ai', [['role' => 'user', 'content' => 'Hello']]);
test_assert(is_wp_error($r) && $r->get_error_code() === 'wp_ai_client_unavailable', 'generate_text returns wp_ai_client_unavailable, not a fatal');
$r = $wp_adapter->generate_image('a cat');
test_assert(is_wp_error($r) && $r->get_error_code() === 'wp_ai_client_unsupported', 'generate_image declines gracefully');

// Container routing: with no wordpress-ai selection the text_adapter is the
// direct Opace AI Hub path.
$text_adapter = $container->get('text_adapter');
test_assert($text_adapter instanceof AI_Scribe_AI_Core_Adapter, 'text_adapter defaults to the Opace AI Hub adapter');
test_assert($container->has('wp_ai_client_adapter'), 'wp_ai_client_adapter registered in the container');

// ---------------------------------------------------------------------------
test_section('P5.6 — Humanizer writing modes (2.6.2 $behuman/$personal parity)');
// ---------------------------------------------------------------------------

$saved_content_settings = get_option('ab_gpt_content_settings', []);
$saved_content_settings = is_array($saved_content_settings) ? $saved_content_settings : [];

update_option('ab_prompts_content', array_merge(
    is_array(get_option('ab_prompts_content', [])) ? get_option('ab_prompts_content', []) : [],
    ['instructions_prompts' => 'CUSTOM-INSTRUCTIONS-SENTINEL']
));

update_option('ab_gpt_content_settings', array_merge($saved_content_settings, ['mode' => 'standard']));
$sys = $prompts->get_system_prompt();
test_assert_contains('CUSTOM-INSTRUCTIONS-SENTINEL', $sys, 'standard mode keeps the custom instructions');
test_assert_not_contains("'Humanizer' writing instructions", $sys, 'standard mode has no humanizer block');

update_option('ab_gpt_content_settings', array_merge($saved_content_settings, ['mode' => 'humanize']));
$sys = $prompts->get_system_prompt();
test_assert_contains("'Humanizer' writing instructions", $sys, 'humanize mode prepends the behuman block');
test_assert_contains('CUSTOM-INSTRUCTIONS-SENTINEL', $sys, 'humanize mode keeps the custom instructions');
test_assert_not_contains('hold no punches', $sys, 'humanize mode does not add the personality block');

update_option('ab_gpt_content_settings', array_merge($saved_content_settings, ['mode' => 'personality']));
$sys = $prompts->get_system_prompt();
test_assert_contains("'Humanizer' writing instructions", $sys, 'personality mode includes the behuman block');
test_assert_contains('hold no punches', $sys, 'personality mode adds the 2.6.2 personal block');
test_assert($prompts->get_writing_mode() === 'personality', 'get_writing_mode reads the stored mode');

update_option('ab_gpt_content_settings', array_merge($saved_content_settings, ['mode' => 'bogus']));
test_assert($prompts->get_writing_mode() === 'standard', 'unknown mode falls back to standard');

// ---------------------------------------------------------------------------
test_section('P3.2 — year prefix, spelling setting and the canonical hard rules');
// ---------------------------------------------------------------------------

$year = gmdate('Y');
$excluded_marker = 'Never use any of these words or phrases';

foreach (['standard', 'humanize', 'personality'] as $mode_under_test) {
    update_option('ab_gpt_content_settings', array_merge($saved_content_settings, ['mode' => $mode_under_test]));
    $sys = $prompts->get_system_prompt();

    test_assert(strpos($sys, "The year is {$year}.") === 0, "{$mode_under_test} system message opens with the year");
    test_assert_contains('Write in the English language using a Business writing style and a Professional writing tone', $sys, "{$mode_under_test} system message carries the language/style/tone line");
    test_assert_contains('Use British English spelling and idiom throughout', $sys, "{$mode_under_test} system message carries the spelling instruction");
    test_assert_contains('Never use em dashes; use commas, colons or full stops instead.', $sys, "{$mode_under_test} system message bans em dashes");
    test_assert_contains($excluded_marker, $sys, "{$mode_under_test} system message carries the excluded-words rule");
    test_assert(substr_count($sys, $excluded_marker) === 1, "{$mode_under_test} system message states the excluded-words rule exactly once");
    test_assert_contains('in sentence case, matching the capitalisation of the surrounding prose', $sys, "{$mode_under_test} system message fixes keyword casing");
    test_assert_contains('never lowercases an acronym, initialism, proper noun or brand name', $sys, "{$mode_under_test} system message protects acronyms and brand names");
    test_assert_not_contains('{excluded_words}', $sys, "{$mode_under_test} system message leaves no unresolved token");
}

// A pre-3.2 install still holds the seeded instructions carrying the
// {excluded_words} placeholder: the clause must be stripped on read so the
// banned-word rule is stated once, by the hard rules.
$legacy_instructions = 'These are your most basic writing instructions: Your name is AI-Scribe and you are a talented copywriter. '
    . 'Respond using plain language. CRITICAL REQUIREMENT: You must NEVER use any of these words or phrases in your response. '
    . 'This is mandatory and non-negotiable: {excluded_words}. If you use any of these words, the content will be rejected. '
    . 'Find alternative expressions instead.';
$stripped = AI_Scribe_Prompt_Manager::strip_excluded_words_clause($legacy_instructions);
test_assert_not_contains('{excluded_words}', $stripped, 'legacy excluded-words placeholder is stripped');
test_assert_not_contains('CRITICAL REQUIREMENT', $stripped, 'legacy excluded-words clause is removed whole, leaving no dangling sentence');
test_assert_contains('talented copywriter', $stripped, 'the rest of the legacy instructions survive');

$saved_prompts_legacy = is_array(get_option('ab_prompts_content', [])) ? get_option('ab_prompts_content', []) : [];
update_option('ab_prompts_content', array_merge($saved_prompts_legacy, ['instructions_prompts' => $legacy_instructions]));
$sys = $prompts->get_system_prompt();
test_assert(substr_count($sys, $excluded_marker) === 1, 'legacy seeded instructions still yield one excluded-words rule');
test_assert_not_contains('{excluded_words}', $sys, 'legacy seeded instructions leave no token in the system message');
update_option('ab_prompts_content', $saved_prompts_legacy);

// Hard rules sit after the persona and before the user's Custom Instructions.
update_option('ab_gpt_content_settings', array_merge($saved_content_settings, ['mode' => 'personality']));
$saved_prompts_content = is_array(get_option('ab_prompts_content', [])) ? get_option('ab_prompts_content', []) : [];
update_option('ab_prompts_content', array_merge($saved_prompts_content, ['user_instructions' => 'MY-OWN-BRAND-VOICE']));
$sys = $prompts->get_system_prompt();
test_assert(
    strpos($sys, 'hold no punches') < strpos($sys, 'These are your hard rules:')
    && strpos($sys, 'These are your hard rules:') < strpos($sys, 'MY-OWN-BRAND-VOICE'),
    'hard rules follow the persona and precede the Custom Instructions'
);
test_assert(substr($sys, -strlen('MY-OWN-BRAND-VOICE')) === 'MY-OWN-BRAND-VOICE', 'Custom Instructions have the last word');
update_option('ab_prompts_content', $saved_prompts_content);

// Spelling setting: British default, American opt-in, invalid falls back.
update_option('ab_gpt_content_settings', array_merge($saved_content_settings, ['mode' => 'standard']));
test_assert($prompts->get_spelling_variant() === 'british', 'spelling defaults to British when the key is absent');

update_option('ab_gpt_content_settings', array_merge($saved_content_settings, ['spelling' => 'american']));
test_assert($prompts->get_spelling_variant() === 'american', 'stored American spelling is read back');
$sys = $prompts->get_system_prompt();
test_assert_contains('Use American English spelling and idiom throughout', $sys, 'American spelling instruction reaches the system message');
test_assert_not_contains('Use British English spelling', $sys, 'British instruction is not sent alongside American');

update_option('ab_gpt_content_settings', array_merge($saved_content_settings, ['spelling' => 'klingon']));
test_assert($prompts->get_spelling_variant() === 'british', 'unknown spelling value falls back to British');

// Per-run settings override the saved language/style/tone in the header line.
update_option('ab_gpt_content_settings', $saved_content_settings);
$sys = $prompts->get_system_prompt(['language' => 'French', 'writing_style' => 'Casual', 'writing_tone' => 'Witty']);
test_assert_contains('Write in the French language using a Casual writing style and a Witty writing tone', $sys, 'conversation settings drive the header line');

// One source of truth for the mode persona text.
$humanize_text = $prompts->get_mode_instructions('humanize');
test_assert_contains("'Humanizer' writing instructions", $humanize_text, 'humanize persona resolves from the prompt library');
test_assert($prompts->get_mode_instructions('personal') === $prompts->get_mode_instructions('personality'), 'legacy "personal" key aliases to "personality"');
test_assert_not_contains('common grammatical errors', $humanize_text, 'humanize persona no longer asks for grammatical errors');
test_assert_not_contains('extra spaces', $humanize_text, 'humanize persona no longer asks for stray spaces');
test_assert_contains('personal anecdotes', $humanize_text, 'humanize persona keeps personal anecdotes');
test_assert_contains('Add humour', $humanize_text, 'humanize persona keeps humour');
$personality_text = $prompts->get_mode_instructions('personality');
test_assert_contains('Sarcastic and Witty Tone', $personality_text, 'personality persona keeps its sarcastic character');
test_assert_not_contains('natural errors', $personality_text, 'personality persona no longer licenses errors');

// Fresh-install defaults, read straight from the seed file.
$seed = json_decode(file_get_contents(AI_SCRIBE_DIR . 'includes/prompts/complete-prompts.json'), true);
test_assert($seed['default_settings']['content']['mode'] === 'humanize', 'fresh installs are seeded into Humanizer mode');
test_assert($seed['default_settings']['content']['spelling'] === 'british', 'fresh installs are seeded into British English');
test_assert(($seed['default_settings']['engine']['model'] ?? null) === '', 'fresh installs leave the model provider-neutral for dynamic Opace AI Hub selection');
test_assert(isset($seed['mode_specific_instructions']['personality']), 'seed file names the mode key "personality", matching the code');
test_assert(!isset($seed['mode_specific_instructions']['personal']), 'the drifted "personal" key is gone from the seed file');

update_option('ab_gpt_content_settings', $saved_content_settings);

// ---------------------------------------------------------------------------
test_section('P5.6 — check_Arr enhancement toggles feed the Evaluate step');
// ---------------------------------------------------------------------------

update_option('ab_gpt_content_settings', array_merge($saved_content_settings, [
    'check_Arr' => ['addkeywordBold' => 'addkeywordBold', 'addsubMatter' => 'addsubMatter'],
]));
$toggles = $prompts->get_enhancement_toggles();
test_assert($toggles['addkeywordBold'] === true && $toggles['addsubMatter'] === true, 'enabled toggles read true');
test_assert($toggles['addinsertToc'] === false && $toggles['addQNA'] === false, 'disabled toggles read false');

$p11 = $prompts->assemble_step_prompt(11, $settings, $selections);
test_assert_contains('Have any STRONG tags been added', $p11, 'evaluate prompt gains the bold-keywords question');
test_assert_contains('authorities on the subject matter', $p11, 'evaluate prompt gains the authorities question');
test_assert_not_contains('Have any A tags been added', $p11, 'disabled hyperlink question absent');

$p6_check = $prompts->assemble_step_prompt(6, $settings, $selections);
test_assert_not_contains('Have any STRONG tags been added', $p6_check, 'enhancement questions only on step 11');

update_option('ab_gpt_content_settings', $saved_content_settings);

// ---------------------------------------------------------------------------
test_section('P5.6 — conversation settings: partial update + tagline position');
// ---------------------------------------------------------------------------

/** @var AI_Scribe_Conversation_Service $conv_service */
$conv_service = $container->get('conversation_service');
$cid = $conv_service->create(['idea' => 'partial merge test', 'language' => 'French', 'tagline_position' => 'below'], 'wizard');
$conv_service->update_settings($cid, ['tagline_position' => 'above']);
$row = $conv_service->get($cid);
test_assert($row['settings']['tagline_position'] === 'above', 'update_settings changes tagline_position');
test_assert($row['settings']['idea'] === 'partial merge test', 'partial update preserves the idea');
test_assert($row['settings']['language'] === 'French', 'partial update preserves the language');

$p6_above = $prompts->assemble_step_prompt(6, $row['settings'], ['title' => 'T', 'tagline' => 'Drive the future']);
test_assert_not_contains('Drive the future', $p6_above, 'tagline placement is compiled outside the step-6 body prompt');

// ---------------------------------------------------------------------------
test_section('P5.6 — step 10 revision prompt (review step re-run path)');
// ---------------------------------------------------------------------------

$p10 = $prompts->assemble_step_prompt(10, $settings, $selections);
test_assert(trim($p10) !== '', 'step 10 (review revision) has a prompt');
test_assert_not_contains('[Title]', $p10, 'step 10 leaves no unresolved [Title]');
$parsed10 = AI_Scribe_Schema_Registry::parse(10, '<p>Revised article HTML.</p>');
test_assert(!empty($parsed10['ok']), 'step 10 response validates as long-form');
test_assert(AI_Scribe_Schema_Registry::is_choice_step(10) === false, 'step 10 is a long-form step');

// ---------------------------------------------------------------------------
test_section('P5.6 — frontend shortcode wiring (static regressions)');
// ---------------------------------------------------------------------------

$initializer_src = file_get_contents(dirname(__DIR__, 2) . '/includes/core/class-plugin-initializer.php');
test_assert_not_contains('This will be implemented in future phases', $initializer_src, 'handle_shortcode is no longer a stub');
test_assert_contains('send_shortcode_page_data', $initializer_src, 'frontend shortcode delegates to ShortcodeService');
test_assert_contains('remove_short_code_content()', $initializer_src, 'Remove deletes the DB row via ShortcodeService');

// ---------------------------------------------------------------------------
test_section('P5.6 — uninstall.php (opt-in delete-data cleanup)');
// ---------------------------------------------------------------------------

$uninstall_file = dirname(__DIR__, 2) . '/uninstall.php';
test_assert(file_exists($uninstall_file), 'uninstall.php exists at the plugin root');
$uninstall_src = file_get_contents($uninstall_file);
test_assert_contains("defined( 'WP_UNINSTALL_PLUGIN' )", $uninstall_src, 'uninstall.php guards on WP_UNINSTALL_PLUGIN');
test_assert_contains('ai_scribe_delete_data_on_uninstall', $uninstall_src, 'uninstall.php honours the opt-in option');
foreach (['ab_gpt_ai_engine_settings', 'ab_gpt_content_settings', 'ai_scribe_languages', 'ab_api_key', 'ab_anthropic_api_key', 'ai_scribe_conversations'] as $must_clean) {
    test_assert_contains($must_clean, $uninstall_src, "uninstall.php cleans {$must_clean}");
}

// ---------------------------------------------------------------------------
test_section('P7 — model parameter-schema inference (§13.3)');
// ---------------------------------------------------------------------------

// Live-registered Anthropic model (generic default schema) gets fixed:
// correct max_tokens wire key, honest 64k cap, default ≥ 8192, thinking knobs.
AICore\Registry\ModelRegistry::registerModel('claude-sonnet-4-5-20250929', ['provider' => 'anthropic']);
AI_Scribe_Model_Schema_Inference::apply('claude-sonnet-4-5-20250929');
$schema_claude = AICore\Registry\ModelRegistry::getParameterSchema('claude-sonnet-4-5-20250929');
test_assert($schema_claude['max_tokens']['request_key'] === 'max_tokens', 'anthropic inferred wire key is max_tokens (not max_output_tokens)');
test_assert((int) $schema_claude['max_tokens']['max'] <= 64000, 'anthropic inferred output cap ≤ 64k (not the 200k context window)');
test_assert((int) $schema_claude['max_tokens']['default'] >= 8192, 'anthropic inferred output default ≥ 8192 (2048 starves body/express)');
test_assert(isset($schema_claude['extended_thinking']) && isset($schema_claude['thinking_budget']), 'claude 4.x family gets extended-thinking toggle + budget');

// Live-registered GPT-5 family: reasoning effort, no sampling params.
AICore\Registry\ModelRegistry::registerModel('gpt-5-nano-2025-08-07', ['provider' => 'openai']);
AI_Scribe_Model_Schema_Inference::apply('gpt-5-nano-2025-08-07');
$schema_gpt5 = AICore\Registry\ModelRegistry::getParameterSchema('gpt-5-nano-2025-08-07');
test_assert(isset($schema_gpt5['reasoning_effort']), 'gpt-5 family gets reasoning_effort');
test_assert(($schema_gpt5['reasoning_effort']['default'] ?? '') === 'medium', 'OpenAI reasoning effort defaults to medium');
test_assert(!isset($schema_gpt5['temperature']), 'gpt-5 family drops temperature (unsupported)');
test_assert((int) $schema_gpt5['max_tokens']['default'] >= 16384, 'gpt-5 family output default ≥ 16384');
test_assert(AICore\Registry\ModelRegistry::getEndpoint('gpt-5-nano-2025-08-07') === 'responses', 'gpt-5 family routed to the Responses API');

// Gemini 3 Flash is the balanced writing family; reasoning defaults to medium.
AICore\Registry\ModelRegistry::registerModel('gemini-3.7-flash', ['provider' => 'gemini']);
AI_Scribe_Model_Schema_Inference::apply('gemini-3.7-flash');
$schema_gemini_flash = AICore\Registry\ModelRegistry::getParameterSchema('gemini-3.7-flash');
test_assert(isset($schema_gemini_flash['thinking_level']), 'Gemini 3 Flash gets thinking_level');
test_assert(($schema_gemini_flash['thinking_level']['default'] ?? '') === 'medium', 'Gemini writing thinking defaults to medium');

// o1-mini never supported reasoning effort — inference must not add it.
AICore\Registry\ModelRegistry::registerModel('o1-mini-2024-09-12', ['provider' => 'openai']);
AI_Scribe_Model_Schema_Inference::apply('o1-mini-2024-09-12');
$schema_o1mini = AICore\Registry\ModelRegistry::getParameterSchema('o1-mini-2024-09-12');
test_assert(!isset($schema_o1mini['reasoning_effort']), 'o1-mini gets NO reasoning_effort');

// ---------------------------------------------------------------------------
test_section('P7 — saved model params reach the provider (schema-key forwarding)');
// ---------------------------------------------------------------------------

// Providers read options by SCHEMA key; forwarding under the wire key
// silently dropped saved parameters (found in the §13 audit).
// ConfigManager caches option groups at construction — flush it so the
// updated options below are actually read.
$p7_flush_config = function () use ($config) {
    $prop = new ReflectionProperty(get_class($config), 'config_cache');
    $prop->setAccessible(true);
    $prop->setValue($config, []);
    $init = new ReflectionMethod(get_class($config), 'load_configuration');
    $init->setAccessible(true);
    $init->invoke($config);
};
update_option('ab_gpt_ai_engine_settings', ['model_params' => ['reasoning_effort' => 'high']]);
$p7_flush_config();
$opts_o3 = $generation->build_request_options('o3', 1);
test_assert(isset($opts_o3['reasoning_effort']) && $opts_o3['reasoning_effort'] === 'high', 'saved reasoning_effort forwarded under its schema key');
test_assert(!isset($opts_o3['reasoning.effort']), 'no stray wire-key option in the request');

// Extended-thinking invariants: budget rides along, temperature pinned to 1.
update_option('ab_gpt_ai_engine_settings', ['temp' => 0.5, 'model_params' => ['extended_thinking' => 'enabled']]);
$p7_flush_config();
$opts_think = $generation->build_request_options('claude-sonnet-4-5-20250929', 7);
test_assert(isset($opts_think['extended_thinking']) && $opts_think['extended_thinking'] === 'enabled', 'extended thinking forwarded');
test_assert(isset($opts_think['thinking_budget']) && (int) $opts_think['thinking_budget'] >= 1024, 'thinking budget defaulted when enabled');
test_assert(isset($opts_think['temperature']) && (float) $opts_think['temperature'] === 1.0, 'temperature pinned to 1 while thinking');

// Thinking off: no thinking options leak into the request.
update_option('ab_gpt_ai_engine_settings', ['model_params' => ['extended_thinking' => '', 'thinking_budget' => 4096]]);
$p7_flush_config();
$opts_nothink = $generation->build_request_options('claude-sonnet-4-5-20250929', 7);
test_assert(!isset($opts_nothink['extended_thinking']) && !isset($opts_nothink['thinking_budget']), 'disabled thinking sends no thinking options');
test_reset_options();
$p7_flush_config();

// ---------------------------------------------------------------------------
test_section('P7 — zombie purge + hardcoded pricing removal (static regressions)');
// ---------------------------------------------------------------------------

$assets_js = dirname(__DIR__, 2) . '/assets/js';
foreach (['models/CostModel.js', 'models/WorkflowModel.js', 'models/ValidationModel.js', 'controllers/GenerationController.js', 'controllers/ImageController.js'] as $zombie) {
    test_assert(!file_exists($assets_js . '/' . $zombie), "zombie module deleted: {$zombie}");
}
$init_src = file_get_contents(dirname(__DIR__, 2) . '/includes/core/class-plugin-initializer.php');
test_assert(!file_exists(dirname(__DIR__, 2) . '/includes/ajax/class-ajax-handler-service.php'), 'retired AjaxHandlerService deleted');
test_assert_not_contains('ai_scribe_get_pricing', $init_src, 'PluginInitializer pricing endpoint removed');
$main_js = file_get_contents($assets_js . '/main.js');
test_assert_not_contains('getPricing', $main_js, 'main.js no longer references the pricing endpoint');
test_assert_not_contains('WordPressAjax', $main_js, 'legacy WordPressAjax network layer deleted');

// Settings surface refuses provider-key writes when the hub owns them (§13.12).
$settings_ajax_src = file_get_contents(dirname(__DIR__, 2) . '/includes/ajax/class-settings-ajax-controller.php');
test_assert_contains('managed_by_hub', $settings_ajax_src, 'save_api_keys defers to the hub when active');
$settings_tpl_src = file_get_contents(dirname(__DIR__, 2) . '/templates/settings_template.php');
test_assert_contains('managed-by-hub', $settings_tpl_src, 'settings template has the Managed by Opace AI Hub panel');
test_assert_contains('ai-core-settings', $settings_tpl_src, 'managed panel links to Opace AI Hub settings');
test_assert_contains("isset( \$ai_scribe_engine['temp'] ) ? (float) \$ai_scribe_engine['temp'] : 0.5", $settings_tpl_src, 'fresh Settings temperature mirrors the effective 0.5 engine seed');

// ---------------------------------------------------------------------------
test_section('P8 — key storage hardening (§14.3): encrypted at rest, never leaked');
// ---------------------------------------------------------------------------

test_reset_options();
$p8_config = new AI_Scribe_Config_Manager($logger);

// 1. Every provider key round-trips through encryption and the stored bytes
//    carry the versioned marker + never contain the plaintext.
$p8_secrets = [
    'ai_engine.api_key'           => 'sk-p8-openai-test-key-123456789012345678901234',
    'ai_engine.anthropic_api_key' => 'sk-ant-p8-anthropic-test-key-abcdefghijklmnopqrs',
    'ai_engine.gemini_api_key'    => 'AIzaP8GeminiTestKey_abcdefghijklmnop',
    'ai_engine.grok_api_key'      => 'xai-p8-grok-test-key-abcdefghijklmnopqrstuv',
];
foreach ($p8_secrets as $p8_key => $p8_plain) {
    $p8_config->set($p8_key, $p8_plain);
    $p8_stored_group = get_option('ab_gpt_ai_engine_settings', []);
    $p8_subkey = explode('.', $p8_key, 2)[1];
    $p8_stored = $p8_stored_group[$p8_subkey] ?? '';
    test_assert(strpos($p8_stored, 'aisenc1:') === 0, "{$p8_subkey} stored with versioned ciphertext marker");
    test_assert(strpos($p8_stored, $p8_plain) === false, "{$p8_subkey} plaintext absent from stored option");
    test_assert(strpos((string) json_encode($p8_stored_group), $p8_plain) === false, "{$p8_subkey} plaintext absent from whole option group");
}

// 2. Reads decrypt back to the exact plaintext (fresh instance = fresh cache).
//    openai/anthropic reads prefer the legacy individual options, which the
//    real save path (store_key) mirrors — mirror them here the same way.
update_option('ab_api_key', $p8_config->encrypt_for_storage($p8_secrets['ai_engine.api_key']));
update_option('ab_anthropic_api_key', $p8_config->encrypt_for_storage($p8_secrets['ai_engine.anthropic_api_key']));
$p8_config2 = new AI_Scribe_Config_Manager($logger);
foreach ($p8_secrets as $p8_key => $p8_plain) {
    test_assert($p8_config2->get($p8_key) === $p8_plain, "{$p8_key} decrypts to original plaintext");
}

// 3. Legacy plaintext keys saved before hardening still read back unmangled.
test_reset_options();
update_option('ab_gpt_ai_engine_settings', ['gemini_api_key' => 'AIzaLegacyPlaintextKey123456']);
$p8_config3 = new AI_Scribe_Config_Manager($logger);
test_assert($p8_config3->get('ai_engine.gemini_api_key') === 'AIzaLegacyPlaintextKey123456', 'pre-hardening plaintext gemini key reads back unchanged');

// 4. A tampered/undecryptable marked ciphertext fails CLOSED (empty string,
//    never raw stored bytes).
test_reset_options();
update_option('ab_api_key', 'aisenc1:' . base64_encode(str_repeat("\x00", 48)));
$p8_config4 = new AI_Scribe_Config_Manager($logger);
test_assert($p8_config4->get('ai_engine.api_key') === '', 'undecryptable marked ciphertext returns empty, not raw bytes');
test_reset_options();

// 5. Static regressions: save path encrypts, fields are autocomplete-hardened,
//    responses never carry key material, uninstall wipes stored keys, and the
//    legacy plaintext-key endpoints stay unregistered.
$p8_settings_ajax = file_get_contents(dirname(__DIR__, 2) . '/includes/ajax/class-settings-ajax-controller.php');
test_assert_contains('encrypt_for_storage', $p8_settings_ajax, 'store_key() encrypts keys at rest');
$p8_settings_tpl = file_get_contents(dirname(__DIR__, 2) . '/templates/settings_template.php');
// Opace AI Hub is now a hard dependency and owns provider keys outright, so
// AI-Scribe renders no key fields at all. The autocomplete hardening this
// assertion used to check moved with them; what matters now is that no key
// input can reappear here as a second place to configure a provider.
test_assert(
    false === strpos($p8_settings_tpl, 'api-key-') && false === strpos($p8_settings_tpl, 'type="password"'),
    'settings template renders no provider key fields (Opace AI Hub owns them)'
);
test_assert_contains("unset( \$engine['api_key'], \$engine['anthropic_api_key'], \$engine['gemini_api_key'], \$engine['grok_api_key'] )", $p8_settings_ajax, 'get_settings strips all key material before responding');
$p8_uninstall = file_get_contents(dirname(__DIR__, 2) . '/uninstall.php');
test_assert_contains('ab_api_key', $p8_uninstall, 'uninstall wipes the OpenAI key option');
test_assert_contains('ab_anthropic_api_key', $p8_uninstall, 'uninstall wipes the Anthropic key option');
test_assert_contains('ab_gpt_ai_engine_settings', $p8_uninstall, 'uninstall wipes the grouped engine settings (gemini/grok keys)');
$p8_init_src = file_get_contents(dirname(__DIR__, 2) . '/includes/core/class-plugin-initializer.php');
test_assert_not_contains("register_ajax_action( 'al_scribe_engine_request_data'", $p8_init_src, 'legacy plaintext-key endpoint al_scribe_engine_request_data unregistered');
test_assert_not_contains("register_ajax_action( 'save_engine_settings'", $p8_init_src, 'legacy plaintext-key endpoint save_engine_settings unregistered');
test_assert_not_contains("register_ajax_action( 'ai_scribe_validate_api_key'", $p8_init_src, 'key-echoing validation endpoint unregistered');

// ---------------------------------------------------------------------------
test_section('P9 — upgrade path (§15.1): legacy plaintext keys encrypted on migrate');
// ---------------------------------------------------------------------------

// 1. The exact 2.6.2 shape: plaintext keys inside ab_gpt_ai_engine_settings.
test_reset_options();
$p9_openai_plain    = 'sk-legacy262-openai-key-0123456789abcdef0123456789';
$p9_anthropic_plain = 'sk-ant-legacy262-anthropic-key-abcdefghijklmnopqrst';
update_option('ab_gpt_ai_engine_settings', [
    'model'             => 'gpt-4.5-preview',
    'temp'              => 0.9,
    'api_key'           => $p9_openai_plain,
    'anthropic_api_key' => $p9_anthropic_plain,
]);
AI_Scribe_Migration_Service::maybe_migrate($prompts);

$p9_engine = get_option('ab_gpt_ai_engine_settings');
test_assert(strpos($p9_engine['api_key'], 'aisenc1:') === 0, '2.6.2 plaintext OpenAI key encrypted at rest on migrate');
test_assert(strpos($p9_engine['anthropic_api_key'], 'aisenc1:') === 0, '2.6.2 plaintext Anthropic key encrypted at rest on migrate');
test_assert(strpos((string) json_encode($p9_engine), $p9_openai_plain) === false, 'no plaintext OpenAI key left in the stored option');
test_assert(strpos((string) json_encode($p9_engine), $p9_anthropic_plain) === false, 'no plaintext Anthropic key left in the stored option');

// 2. The migrated key decrypts through the new encryption path.
$p9_config = new AI_Scribe_Config_Manager($logger);
$p9_group  = $p9_config->get_group('ai_engine');
test_assert($p9_group['api_key'] === $p9_openai_plain, 'migrated OpenAI key decrypts to the original plaintext');
test_assert($p9_group['anthropic_api_key'] === $p9_anthropic_plain, 'migrated Anthropic key decrypts to the original plaintext');

// 2b. §15.1 sim finding: ConfigManager::get() short-circuits api_key /
// anthropic_api_key to the INDIVIDUAL options and never falls back to the
// group — so migration must mirror the group keys there, or a pure 2.6.2
// site's keys are invisible post-upgrade.
test_assert(strpos((string) get_option('ab_api_key'), 'aisenc1:') === 0, 'group OpenAI key mirrored into ab_api_key (runtime read path)');
test_assert(strpos((string) get_option('ab_anthropic_api_key'), 'aisenc1:') === 0, 'group Anthropic key mirrored into ab_anthropic_api_key');
$p9_config_rt = new AI_Scribe_Config_Manager($logger);
test_assert($p9_config_rt->get_api_key('openai') === $p9_openai_plain, 'get_api_key(openai) returns the 2.6.2 key post-migration');
test_assert($p9_config_rt->get_api_key('anthropic') === $p9_anthropic_plain, 'get_api_key(anthropic) returns the 2.6.2 key post-migration');

// 3. Re-running the migration never double-encrypts (aisenc1 values skipped).
$p9_before = get_option('ab_gpt_ai_engine_settings');
update_option('ai_scribe_v3_migrated', 'force-rerun');
AI_Scribe_Migration_Service::maybe_migrate($prompts);
test_assert(get_option('ab_gpt_ai_engine_settings') === $p9_before, 'migration re-run leaves encrypted keys untouched (no double-encrypt)');

// 4. Remap remains visible after re-run and model_pre_v3 preserved.
test_assert($p9_engine['model'] === 'gpt-4o-mini' && $p9_engine['model_pre_v3'] === 'gpt-4.5-preview', 'retired model remapped with original kept');
test_assert(is_array(get_option('ai_scribe_model_remap_notice')), 'remap notice payload persisted for the admin notice');

// 5. Interim-format individual options are normalised to aisenc1 too.
test_reset_options();
$p9_tmp_config  = new AI_Scribe_Config_Manager($logger);
$p9_unprefixed  = substr($p9_tmp_config->encrypt_for_storage($p9_openai_plain), 8); // old base64(IV+cipher) layout
update_option('ab_api_key', $p9_unprefixed);
update_option('ab_anthropic_api_key', $p9_anthropic_plain); // straight plaintext
AI_Scribe_Migration_Service::maybe_migrate($prompts);
test_assert(strpos(get_option('ab_api_key'), 'aisenc1:') === 0, 'old-format individual OpenAI key normalised to versioned ciphertext');
test_assert(strpos(get_option('ab_anthropic_api_key'), 'aisenc1:') === 0, 'plaintext individual Anthropic key encrypted');
$p9_config2 = new AI_Scribe_Config_Manager($logger);
test_assert($p9_config2->get('ai_engine.api_key') === $p9_openai_plain, 'normalised individual OpenAI key decrypts to original');
test_assert($p9_config2->get('ai_engine.anthropic_api_key') === $p9_anthropic_plain, 'encrypted individual Anthropic key decrypts to original');

// 6. The fake 2.6.2 Claude id (silently remapped upstream, §3.4) is retired.
test_reset_options();
update_option('ab_gpt_ai_engine_settings', ['model' => 'claude-3-5-sonnet-20250514']);
AI_Scribe_Migration_Service::maybe_migrate($prompts);
$p9_claude = get_option('ab_gpt_ai_engine_settings');
test_assert($p9_claude['model'] === 'claude-sonnet-4-5' && $p9_claude['model_pre_v3'] === 'claude-3-5-sonnet-20250514', 'fake 2.6.2 Claude id remapped to a real model');

// ---------------------------------------------------------------------------
test_section('P9 — onboarding notice (§15.2): gating, dismissal, feature flag');
// ---------------------------------------------------------------------------

test_reset_options();
test_assert(AI_Scribe_Onboarding_Notice::should_show_onboarding() === true, 'onboarding shows when never dismissed');
update_option(AI_Scribe_Onboarding_Notice::DISMISSED_OPTION, '3.0.3');
test_assert(AI_Scribe_Onboarding_Notice::should_show_onboarding() === false, 'onboarding never re-shows once dismissed');

test_assert(AI_Scribe_Onboarding_Notice::should_show_remap() === false, 'remap notice hidden without a recorded remap');
update_option(AI_Scribe_Onboarding_Notice::REMAP_OPTION, ['from' => 'gpt-4.5-preview', 'to' => 'gpt-4o-mini']);
test_assert(AI_Scribe_Onboarding_Notice::should_show_remap() === true, 'remap notice shows while the payload exists');
update_option(AI_Scribe_Onboarding_Notice::REMAP_OPTION, ['bad' => 'shape']);
test_assert(AI_Scribe_Onboarding_Notice::should_show_remap() === false, 'malformed remap payload never renders');

// Opace AI Hub does not yet have a verified public WordPress.org listing, so the
// direct install CTA stays off until a distributor explicitly opts in.
test_assert(AI_Scribe_Onboarding_Notice::hub_install_cta_enabled() === false, 'hub install CTA defaults OFF until the dependency has a verified public listing');
// The filter escape hatch is asserted statically: this harness stubs
// add_filter(), so exercising it at runtime would prove nothing.
$p9_notice_src_cta = file_get_contents(dirname(__DIR__, 2) . '/includes/core/class-onboarding-notice.php');
test_assert_contains("'install-plugin_'", $p9_notice_src_cta, 'install CTA is nonced with the core install-plugin action');
test_assert_contains("current_user_can( 'install_plugins' )", $p9_notice_src_cta, 'install CTA is capability gated');
test_assert_contains("apply_filters( 'ai_scribe_hub_install_cta'", $p9_notice_src_cta, 'install CTA remains filterable for non-wp.org distribution');
test_assert_contains("apply_filters( 'ai_scribe_hub_install_cta', \$available )", $p9_notice_src_cta, 'install CTA filter follows WordPress dependency availability');

// Static: dismiss handler is nonce + capability gated and the bootstrap wires the class.
$p9_notice_src = file_get_contents(dirname(__DIR__, 2) . '/includes/core/class-onboarding-notice.php');
test_assert_contains('wp_verify_nonce', $p9_notice_src, 'dismiss handler verifies the nonce');
test_assert_contains("current_user_can( 'manage_options' )", $p9_notice_src, 'dismiss handler is capability gated');
$p9_boot_src = file_get_contents(dirname(__DIR__, 2) . '/article_builder.php');
test_assert_contains('AI_Scribe_Onboarding_Notice::register()', $p9_boot_src, 'bootstrap registers the onboarding notice');
test_reset_options();

// ---------------------------------------------------------------------------
test_section('P9 — key hand-off: AI-Scribe keys pushed into Opace AI Hub\'s store');
// ---------------------------------------------------------------------------
// This section MUST stay last before test_summary(): it defines a global
// ai_core() stub, and every earlier test relies on the hub being absent.

// 1. Hub-gated: without ai_core() nothing is written and no flag is set.
test_reset_options();
$hk_openai_plain = 'sk-handoff-openai-key-0123456789abcdef0123456789ab';
update_option('ab_gpt_ai_engine_settings', ['api_key' => $hk_openai_plain]);
AI_Scribe_Migration_Service::maybe_migrate($prompts);
test_assert(AI_Scribe_Migration_Service::maybe_migrate_keys_to_hub() === false, 'key hand-off is a silent no-op without the hub');
test_assert(get_option(AI_Scribe_Migration_Service::HUB_KEYS_OPTION) === false, 'hand-off flag stays unset without the hub');
test_assert(get_option('ai_core_settings') === false, 'no hub option is created without the hub');

// Minimal hub stub — the hand-off itself only needs function_exists('ai_core').
// Both definitions are deliberately conditional: unconditional top-level
// declarations are hoisted to script start, which would make the hub "active"
// for every earlier test.
if (!class_exists('AI_Scribe_Test_Stub_Hub')) {
    class AI_Scribe_Test_Stub_Hub
    {
        public function is_configured()
        {
            $s = get_option('ai_core_settings', []);
            return !empty($s['openai_api_key']) || !empty($s['anthropic_api_key'])
                || !empty($s['gemini_api_key']) || !empty($s['grok_api_key']);
        }
    }
}
if (!function_exists('ai_core')) {
    function ai_core() { static $hub = null; return $hub ?: $hub = new AI_Scribe_Test_Stub_Hub(); }
}

// 2. With the hub: the migrated (encrypted-at-rest) key lands in the hub
// store as plaintext for the hub's own sanitize/encrypt filters to secure.
test_assert(AI_Scribe_Migration_Service::maybe_migrate_keys_to_hub() === true, 'key hand-off completes with the hub active');
$hk_hub = get_option('ai_core_settings');
test_assert(is_array($hk_hub) && ($hk_hub['openai_api_key'] ?? '') === $hk_openai_plain, 'AI-Scribe OpenAI key carried into ai_core_settings');
test_assert(get_option(AI_Scribe_Migration_Service::HUB_KEYS_OPTION) === AI_Scribe_Migration_Service::HUB_KEYS_VERSION, 'hand-off flag set once keys verified in the hub');
test_assert(ai_core()->is_configured() === true, 'hub reports configured after the hand-off');
$hk_scribe = get_option('ab_gpt_ai_engine_settings');
test_assert(strpos((string) ($hk_scribe['api_key'] ?? ''), 'aisenc1:') === 0, 'AI-Scribe copy is untouched (non-destructive hand-off)');

// 3. A key already in the hub is never overwritten.
test_reset_options();
update_option('ab_gpt_ai_engine_settings', ['api_key' => $hk_openai_plain]);
AI_Scribe_Migration_Service::maybe_migrate($prompts);
delete_option(AI_Scribe_Migration_Service::HUB_KEYS_OPTION);
update_option('ai_core_settings', ['openai_api_key' => 'hub-users-own-key']);
AI_Scribe_Migration_Service::maybe_migrate_keys_to_hub();
$hk_hub2 = get_option('ai_core_settings');
test_assert(($hk_hub2['openai_api_key'] ?? '') === 'hub-users-own-key', 'existing hub key wins — hand-off never overwrites');

// 4. Idempotent: the completion flag short-circuits a second run.
update_option('ai_core_settings', []);
test_assert(AI_Scribe_Migration_Service::maybe_migrate_keys_to_hub() === true, 'flagged hand-off short-circuits');
test_assert(get_option('ai_core_settings') === [], 'short-circuited run writes nothing');
test_reset_options();

// ---------------------------------------------------------------------------
test_section('Image workflow — caption and article-local option regressions');
// ---------------------------------------------------------------------------

$image_html_source = file_get_contents(dirname(__DIR__, 2) . '/includes/services/class-image-html-service.php');
test_assert_not_contains("elseif ( ! empty( \$image_data['alt_text'] ) )", $image_html_source, 'alt text is never promoted to a visible caption');
$image_html_service = $container->get('image_html_service');
$plain_image_html = $image_html_service->generateImageHTML([
    'url' => 'https://example.test/generated.png',
    'alt_text' => 'Accessible description only',
    'attachment_id' => 42,
    'width' => 1200,
    'height' => 800,
], AI_Scribe_Image_HTML_Service::FORMAT_WORDPRESS_BLOCK);
test_assert_contains('alt="Accessible description only"', $plain_image_html, 'generated block carries descriptive alt text');
test_assert_contains('loading="lazy" decoding="async" width="1200" height="800"', $plain_image_html, 'content image HTML includes intrinsic size and deferred loading');
test_assert_not_contains('<figcaption', $plain_image_html, 'generated block has no visible caption without explicit caption input');
$captioned_image_html = $image_html_service->generateImageHTML([
    'url' => 'https://example.test/authored.png',
    'alt_text' => 'Accessible authored description',
    'caption' => 'User-authored caption',
], AI_Scribe_Image_HTML_Service::FORMAT_WORDPRESS_BLOCK);
test_assert_contains('<figcaption>User-authored caption</figcaption>', $captioned_image_html, 'explicit user-authored captions remain supported');

$image_service_source = file_get_contents(dirname(__DIR__, 2) . '/includes/services/class-image-service.php');
test_assert_contains("\$caption    = \$this->automatic_caption", $image_service_source, 'new generated attachments receive a validated automatic caption');
test_assert_contains("'caption'       => \$caption", $image_service_source, 'generated image response carries the automatic editable caption');
test_assert_contains('request_image_overrides()', $image_service_source, 'image generation accepts request-scoped article overrides');
test_assert_not_contains("update_option( 'ab_gpt_image_settings'", $image_service_source, 'article image generation never writes global image settings');
$image_service = $container->get('image_service');
$visual_description = new ReflectionMethod(AI_Scribe_Image_Service::class, 'visual_description_from_prompt');
$visual_description->setAccessible(true);
$alt_subject = $visual_description->invoke($image_service, 'Accessible navigation - Create an image that visually illustrates the topic. Do not include any words or text in the image.');
test_assert($alt_subject === 'Accessible navigation', 'media alt text and filename use the visual subject, never generation instructions');
$featured_subject = $visual_description->invoke($image_service, 'Editorial image for the section "article introduction" in the article "Technical SEO Tips: Fix the Broken Plumbing". Show the section specific subject. Do not include words.');
test_assert($featured_subject === 'Technical SEO Tips: Fix the Broken Plumbing', 'featured image metadata uses the article subject instead of the generic introduction label');
$section_subject = $visual_description->invoke($image_service, 'Editorial image for the section "Diagnosing redirect chains" in the article "Technical SEO". Show the section specific subject.');
test_assert($section_subject === 'Diagnosing redirect chains', 'section image metadata uses the specific section subject');
test_assert($visual_description->invoke($image_service, 'article introduction') === '', 'generic article-introduction label is rejected rather than exposed as a caption');
test_assert($visual_description->invoke($image_service, 'featured image') === '', 'generic featured-image label is rejected rather than exposed as a caption');

$automatic_caption = new ReflectionMethod(AI_Scribe_Image_Service::class, 'automatic_caption');
$automatic_caption->setAccessible(true);
$provider_caption = $automatic_caption->invoke($image_service, ['caption' => 'A plumber tightening a leaking pipe beneath pressure gauges.'], 'article introduction');
test_assert($provider_caption === 'A plumber tightening a leaking pipe beneath pressure gauges', 'provider caption describing visible content takes precedence over planner labels');
$nested_provider_caption = $automatic_caption->invoke($image_service, ['data' => [['revised_prompt' => 'A line drawing showing a plumber repairing corroded industrial pipework. No text.']]], 'article introduction');
test_assert($nested_provider_caption === 'A line drawing showing a plumber repairing corroded industrial pipework', 'nested provider description is reduced to a complete caption without instruction boilerplate');
test_assert($automatic_caption->invoke($image_service, ['caption' => 'featured image'], 'article introduction') === '', 'generic provider and prompt fallbacks fail to a blank editable caption');

$image_retry_method = new ReflectionMethod(AI_Scribe_Image_Service::class, 'is_retryable_image_error');
$image_retry_method->setAccessible(true);
test_assert($image_retry_method->invoke(null, new WP_Error('request_failed', 'Gemini returned HTTP 503: service unavailable')) === true, 'Gemini 503 image errors are marked retryable');
test_assert($image_retry_method->invoke(null, new WP_Error('invalid_argument', 'Image prompt is invalid')) === false, 'permanent image validation errors are not marked retryable');

$image_controller_source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/controllers/WizardFlowController.js');
test_assert_contains('prompt_used:', $image_controller_source, 'gallery persists the exact prompt used');
test_assert_contains('insertAllImages(step)', $image_controller_source, 'image studio provides deterministic place-all');
test_assert_contains('if (this.isImagePlaced(quill, data.url))', $image_controller_source, 'placement rejects duplicate image URLs');
test_assert_contains("setStateSlice('imageOverrides'", $image_controller_source, 'article-local image overrides persist in AppState');
test_assert_contains('maybeAutoGenerateFeaturedImage()', $image_controller_source, 'Body preview auto-generates the featured image through an idempotent gate');

// ---------------------------------------------------------------------------
test_section('Gemini image discovery — live cache provenance and capabilities');
// ---------------------------------------------------------------------------

test_reset_options();
$gemini_test_key = 'gemini-test-key-not-real';
update_option('ai_core_settings', ['gemini_api_key' => $gemini_test_key]);
$gemini_live_models = [
    'gemini-3.6-flash',
    'gemini-3.1-flash-image',
    'gemini-3-pro-image',
    'imagen-4.0-generate-001',
    'nano-banana-pro-preview',
];
$gemini_cache_key = AI_Scribe_Model_Resolver::cache_key('gemini', $gemini_test_key);
set_transient($gemini_cache_key, $gemini_live_models, HOUR_IN_SECONDS);
test_assert_contains('ai_scribe_models_v2_live_gemini_', $gemini_cache_key, 'real Gemini discovery uses the versioned live cache namespace');
test_assert(AI_Scribe_Model_Resolver::live_models('gemini') === $gemini_live_models, 'resolver reads the shared current-provenance model cache');
test_assert(AI_Scribe_Model_Resolver::best_live_image_model('gemini') === 'gemini-3.1-flash-image', 'resolver selects the newest supported live Gemini image model');
test_assert(AI_Scribe_Model_Resolver::best_live_model('gemini') === 'gemini-3.6-flash', 'resolver selects the newest available non-Lite Gemini Flash writing model');
test_assert(AI_Scribe_Model_Resolver::provider_of('nano-banana-pro-preview') === 'gemini', 'Nano Banana routes to Gemini even without image in its id');
test_assert(in_array('gemini', AI_Scribe_Image_Service::available_image_providers(), true), 'configured Gemini appears as an image provider when its live list has image capability');

\AICore\AICore::init(['gemini_api_key' => $gemini_test_key]);
test_assert(\AICore\AICore::resolveImageProvider('nano-banana-pro-preview') === 'gemini', 'Opace AI Hub routes Nano Banana to its Gemini image provider');
\AICore\Registry\ModelRegistry::registerModel('nano-banana-test-image-model', ['provider' => 'gemini']);
test_assert(\AICore\Registry\ModelRegistry::isImageModel('nano-banana-test-image-model'), 'cached unknown Nano Banana ids register with image capability');

$hub_defaults_path = dirname(__DIR__, 3) . '/AI CORE MODULAR/ai-core-standalone/includes/class-ai-core-model-defaults.php';
if (!file_exists($hub_defaults_path)) {
    $hub_defaults_path = dirname(__DIR__, 3) . '/ai-core-standalone/includes/class-ai-core-model-defaults.php';
}
require_once $hub_defaults_path;
test_assert(AI_Core_Model_Defaults::best_image_model(['nano-banana-pro-preview']) === 'nano-banana-pro-preview', 'Opace AI Hub default selection recognises Nano Banana as an image model');

// Provider-aware defaults stay dynamic while preserving an explicit valid user choice.
test_reset_options();
$openai_test_key = 'openai-test-key-not-real';
update_option('ai_core_settings', [
    'openai_api_key' => $openai_test_key,
    'default_provider' => 'openai',
]);
$openai_live_models = ['gpt-5.7-sol', 'gpt-5.7-terra', 'gpt-5.7-mini', 'gpt-image-2', 'gpt-image-3'];
foreach ($openai_live_models as $model_id) {
    AICore\Registry\ModelRegistry::registerModel($model_id, ['provider' => 'openai']);
}
set_transient(AI_Scribe_Model_Resolver::cache_key('openai', $openai_test_key), $openai_live_models, HOUR_IN_SECONDS);
test_assert(AI_Scribe_Model_Resolver::best_live_model('openai') === 'gpt-5.7-terra', 'resolver dynamically selects the newest available OpenAI Terra writing model');
test_assert(AI_Scribe_Model_Resolver::best_live_image_model('openai') === 'gpt-image-3', 'resolver dynamically selects the newest available GPT Image model');
test_assert(AI_Scribe_Model_Resolver::resolve('gpt-5.7-mini') === 'gpt-5.7-mini', 'resolver preserves an explicit valid saved model');
test_assert_contains('_live_gemini_', AI_Core_Model_Defaults::cache_key('gemini', $gemini_test_key), 'Opace AI Hub mock and live model caches are isolated');

$gemini_provider_source = file_get_contents(dirname(__DIR__, 3) . '/AI CORE MODULAR/ai-core-standalone/lib/src/Providers/GeminiProvider.php');
test_assert_contains("strpos(\$identifier, 'nano-banana')", $gemini_provider_source, 'Gemini live discovery categorises Nano Banana as image capability');
$resolver_source = file_get_contents(dirname(__DIR__, 2) . '/includes/services/class-model-resolver.php');
test_assert_contains("defined( 'AI_SCRIBE_AUTOMATED_TEST' ) && AI_SCRIBE_AUTOMATED_TEST", $resolver_source, 'mock cache provenance requires the automated-test safety guard');
$hub_defaults_source = file_get_contents($hub_defaults_path);
test_assert_contains("defined('AI_SCRIBE_AUTOMATED_TEST') && AI_SCRIBE_AUTOMATED_TEST", $hub_defaults_source, 'Opace AI Hub uses the same effective mock safety condition');

// ---------------------------------------------------------------------------
test_section('Settings persistence — complete save round-trip');
// ---------------------------------------------------------------------------

test_reset_options();
$settings_payload = [
    'model' => 'gemini-test',
    'model_params' => ['thinking_level' => 'medium', 'thinking_budget' => 4096],
    'temperature' => 0.6,
    'top_p' => 0.85,
    'language' => 'English',
    'writing_style' => 'Casual',
    'writing_tone' => 'Witty',
    'spelling' => 'british',
    'heading_tag' => 'H3',
    'number_of_headings' => 9,
    'article_length_mode' => 'in_depth',
    'article_word_count' => 2800,
    'avoid_keywords' => 'cheap, filler',
    'mode' => 'humanize',
    'check_arr' => ['addQNA' => 'addQNA', 'addinsertToc' => 'addinsertToc'],
    'delete_data_on_uninstall' => false,
    'images' => [
        'enabled' => true,
        'model' => 'imagen-test',
        'size' => '1536x1024',
        'quality' => 'high',
        'format' => 'webp',
        'background' => 'opaque',
        'style' => 'Line art',
    ],
];
$_POST = ['security' => 'test-nonce', 'settings' => json_encode($settings_payload)];
ob_start();
ai_scribe()->get_initializer()->handle_save_content_settings();
ob_end_clean();

$saved_content = get_option('ab_gpt_content_settings', []);
$saved_engine = get_option('ab_gpt_ai_engine_settings', []);
$saved_images = get_option('ab_gpt_image_settings', []);
test_assert(($saved_content['writing_style'] ?? '') === 'Casual', 'writing style survives server save');
test_assert(($saved_content['writing_tone'] ?? '') === 'Witty', 'writing tone survives server save');
test_assert(($saved_content['language'] ?? '') === 'English', 'language survives server save');
test_assert(($saved_content['spelling'] ?? '') === 'british', 'spelling survives server save');
test_assert(($saved_content['heading_tag'] ?? '') === 'H3' && ($saved_content['number_of_headings'] ?? 0) === 9, 'heading settings survive server save');
test_assert(($saved_content['article_length_mode'] ?? '') === 'in_depth' && ($saved_content['article_word_count'] ?? 0) === 2800, 'global article length preference survives server save');
test_assert(($saved_content['avoid_keywords'] ?? '') === 'cheap, filler', 'avoid keywords survive server save');
test_assert(($saved_content['mode'] ?? '') === 'humanize', 'writing mode survives server save');
test_assert(($saved_content['check_Arr']['addQNA'] ?? '') === 'addQNA' && ($saved_content['check_Arr']['addinsertToc'] ?? '') === 'addinsertToc', 'enhancement toggles survive server save');
test_assert(($saved_engine['model'] ?? '') === 'gemini-test', 'model survives server save');
test_assert(($saved_engine['model_params']['thinking_level'] ?? '') === 'medium', 'thinking level survives server save');
test_assert(($saved_engine['model_params']['thinking_budget'] ?? 0) === 4096.0, 'numeric model parameter survives server save');
test_assert(($saved_engine['temperature'] ?? 0) === 0.6 && ($saved_engine['top_p'] ?? 0) === 0.85, 'sampling controls survive server save');
test_assert($saved_images === $settings_payload['images'], 'image settings survive server save');
test_assert(get_option('ai_scribe_delete_data_on_uninstall') === 'no', 'uninstall retention preference survives server save');

// The activation hook runs after a retained-data delete/reinstall. It must
// fill gaps without resetting the settings that uninstall.php deliberately
// kept, including Opace AI Hub/Gemini model parameters when no legacy keys exist.
delete_option('ab_api_key');
delete_option('ab_anthropic_api_key');
$before_activation_content = get_option('ab_gpt_content_settings', []);
$before_activation_engine = get_option('ab_gpt_ai_engine_settings', []);
$before_activation_images = get_option('ab_gpt_image_settings', []);
$initializer = ai_scribe()->get_initializer();
$activation_defaults = new ReflectionMethod($initializer, 'seed_activation_defaults');
$activation_defaults->setAccessible(true);
$activation_defaults->invoke($initializer);
$after_activation_content = get_option('ab_gpt_content_settings', []);
$after_activation_engine = get_option('ab_gpt_ai_engine_settings', []);
$after_activation_images = get_option('ab_gpt_image_settings', []);
test_assert(($after_activation_content['writing_style'] ?? '') === ($before_activation_content['writing_style'] ?? ''), 'activation preserves saved writing style');
test_assert(($after_activation_engine['model'] ?? '') === ($before_activation_engine['model'] ?? ''), 'activation preserves saved model without legacy API-key options');
test_assert(($after_activation_engine['model_params']['thinking_level'] ?? '') === ($before_activation_engine['model_params']['thinking_level'] ?? ''), 'activation preserves saved thinking level');
test_assert(($after_activation_images['style'] ?? '') === ($before_activation_images['style'] ?? ''), 'activation preserves saved image settings');
$initializer_source = file_get_contents(dirname(__DIR__, 2) . '/includes/core/class-plugin-initializer.php');
$activation_start = strpos($initializer_source, 'public function activate_plugin()');
$activation_end = strpos($initializer_source, 'private function seed_activation_defaults()', $activation_start);
$activation_body = substr($initializer_source, $activation_start, $activation_end - $activation_start);
test_assert_not_contains('perform_phantom_data_cleanup()', $activation_body, 'activation never runs legacy phantom-key cleanup');

$_POST = ['security' => 'test-nonce', 'prompts' => json_encode(['title_prompts' => 'Saved title prompt'])];
ob_start();
ai_scribe()->get_initializer()->handle_save_prompt_settings();
ob_end_clean();
test_assert((get_option('ab_prompts_content', [])['title_prompts'] ?? '') === 'Saved title prompt', 'prompt field survives server save');
$_POST = [];

// ---------------------------------------------------------------------------
test_section('P0 — conversation ownership isolates authors');
// ---------------------------------------------------------------------------

$GLOBALS['__test_current_user_id'] = 41;
$owner_conversations = new AI_Scribe_Conversation_Service();
$owner_cid = $owner_conversations->create(['idea' => 'Private owner draft', 'model' => 'test-model'], 'wizard');
test_assert($owner_cid > 0, 'authenticated conversation creation succeeds');
$owner_row = $owner_conversations->get($owner_cid);
test_assert((int) ($owner_row['user_id'] ?? 0) === 41, 'new conversation records the current user as owner');
$owner_conversations->save_selection($owner_cid, 'title', 'Owner title');
$owner_conversations->record_cost($owner_cid, 1, 0.25);

$GLOBALS['__test_current_user_id'] = 42;
test_assert($owner_conversations->get($owner_cid) === null, 'second author cannot read another author conversation');
test_assert($owner_conversations->get_state($owner_cid) === null, 'second author cannot read another author public state');
test_assert($owner_conversations->save_selection($owner_cid, 'title', 'Attacker title') === false, 'second author cannot mutate selections');
test_assert($owner_conversations->update_settings($owner_cid, ['post_id' => 999]) === false, 'second author cannot mutate save state');
test_assert($owner_conversations->record_cost($owner_cid, 9, 99.0) === false, 'second author cannot mutate billing state');
test_assert($owner_conversations->set_status($owner_cid, 'complete') === false, 'second author cannot complete another author conversation');

$ownership_generation = new class {
    public $step_calls = 0;
    public $express_calls = 0;
    public $improve_calls = 0;
    public $meta_calls = 0;
    public function run_step($id, $step, array $args = []) { $this->step_calls++; return ['success' => true]; }
    public function run_express($id) { $this->express_calls++; return ['success' => true]; }
    public function improve_article_length($id) { $this->improve_calls++; return ['success' => true]; }
    public function optimise_meta($id, $title, $description) { $this->meta_calls++; return ['success' => true]; }
    public function stream_step($id, $step, array $args, callable $emit) { $this->step_calls++; }
};
$ownership_estimator = new class {
    public $calls = 0;
    public function estimate_tokens($text) { $this->calls++; return 1; }
    public function estimate_step($model, $step, $tokens, $cached = true) { $this->calls++; return []; }
    public function estimate_article($model, $mode = 'wizard', $tokens = 900) { $this->calls++; return []; }
};
$ownership_prompts = new class {
    public function get_system_prompt() { return 'system'; }
    public function assemble_step_prompt($step, $settings, $selections) { return 'prompt'; }
};
$ownership_posts = new class {
    public $calls = 0;
    public function create_from_conversation(array $selections, array $args = []) { $this->calls++; return ['post_id' => 1]; }
};
$ownership_controller = new AI_Scribe_Conversation_Ajax_Controller(
    null,
    $owner_conversations,
    $ownership_generation,
    $ownership_estimator,
    $ownership_prompts,
    $ownership_posts
);

$ownership_request = function ($method, array $post) use ($ownership_controller) {
    $_POST = array_merge(['security' => 'test-nonce'], $post);
    $_REQUEST = $_POST;
    ob_start();
    $ownership_controller->{$method}();
    $raw = ob_get_clean();
    return json_decode($raw, true);
};

$blocked_meta = $ownership_request('handle_optimise_meta', [
    'conversation_id' => $owner_cid,
    'title' => 'A deliberately overlong metadata title for the optimiser',
    'description' => 'A deliberately overlong metadata description for the optimiser ownership boundary.',
]);
test_assert(($blocked_meta['data']['code'] ?? '') === 'conversation_not_found', 'metadata optimiser hides another author conversation');
test_assert($ownership_generation->meta_calls === 0, 'metadata optimiser makes no provider call for another author conversation');

$blocked_step = $ownership_request('handle_run_step', ['conversation_id' => $owner_cid, 'step' => 1]);
test_assert(($blocked_step['data']['code'] ?? '') === 'conversation_not_found', 'generation endpoint rejects another author conversation');
test_assert($ownership_generation->step_calls === 0, 'generation endpoint makes no provider call for another author conversation');

$blocked_improvement = $ownership_request('handle_improve_article_length', ['conversation_id' => $owner_cid]);
test_assert(($blocked_improvement['data']['code'] ?? '') === 'conversation_not_found', 'length-improvement endpoint rejects another author conversation');
test_assert($ownership_generation->improve_calls === 0, 'length-improvement endpoint makes no provider call for another author conversation');

$blocked_save = $ownership_request('handle_save_post', ['conversation_id' => $owner_cid, 'post_status' => 'draft']);
test_assert(($blocked_save['data']['code'] ?? '') === 'conversation_not_found', 'post-save endpoint rejects another author conversation');
test_assert($ownership_posts->calls === 0, 'post-save endpoint creates no post for another author conversation');

$blocked_cost = $ownership_request('handle_estimate_cost', ['conversation_id' => $owner_cid, 'step' => 9]);
test_assert(($blocked_cost['data']['code'] ?? '') === 'conversation_not_found', 'cost endpoint rejects another author conversation');
test_assert($ownership_estimator->calls === 0, 'cost endpoint does not inspect another author conversation');

$GLOBALS['__test_current_user_id'] = 41;
$owner_after_attack = $owner_conversations->get($owner_cid);
test_assert(($owner_after_attack['selections']['title'] ?? '') === 'Owner title', 'blocked selection mutation leaves owner data unchanged');
test_assert((float) ($owner_after_attack['cost']['running_total_usd'] ?? 0) === 0.25, 'blocked billing mutation leaves owner cost unchanged');
test_assert((int) ($owner_after_attack['settings']['post_id'] ?? 0) === 0, 'blocked save-state mutation leaves owner post id unchanged');
test_assert(($owner_after_attack['status'] ?? '') === 'active', 'blocked status mutation leaves owner workflow active');

$GLOBALS['__test_current_user_id'] = 0;
test_assert($owner_conversations->create(['idea' => 'Anonymous'], 'wizard') === 0, 'conversation service refuses unauthenticated creation');
$GLOBALS['__test_current_user_id'] = 1;
$_POST = [];
$_REQUEST = [];

test_summary();
