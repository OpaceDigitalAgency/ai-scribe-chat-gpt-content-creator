'use strict';

const assert = require('assert');

global.BaseStepView = class {};
const SeoMetaStepView = require('../../assets/js/views/steps/SeoMetaStepView.js');

assert.deepStrictEqual(
    SeoMetaStepView.lengthGuidance('x'.repeat(55), 50, 60, 'title').status,
    'good',
    'title inside the display guide should be good'
);
assert.deepStrictEqual(
    SeoMetaStepView.lengthGuidance('x'.repeat(61), 50, 60, 'title').status,
    'warning',
    'title above the display guide should warn, not block'
);
assert.deepStrictEqual(
    SeoMetaStepView.lengthGuidance('', 50, 60, 'title').status,
    'error',
    'empty title should be an error'
);
assert.deepStrictEqual(
    SeoMetaStepView.lengthGuidance('', 120, 160, 'description').status,
    'warning',
    'empty description should warn without blocking save'
);

const both = SeoMetaStepView.keywordGuidance(
    'Heat Pump Installation | A Practical Home Guide',
    'Compare heat pump installation choices for your home.',
    'heat pump installation'
);
assert.strictEqual(both.status, 'good', 'keyword comparison should be case-insensitive');

const titleMissing = SeoMetaStepView.keywordGuidance(
    'A Better Heating Guide',
    'Compare heat pump installation choices for your home.',
    'heat pump installation'
);
assert.strictEqual(titleMissing.status, 'warning');
assert.ok(titleMissing.message.includes('title absent'), 'guidance should identify the exact missing field');

const multiple = [
    SeoMetaStepView.keywordCoverage(
        '2026 SEO Strategy | AI Trends and Search Intent',
        'Update your 2026 SEO strategy with AI SEO trends and search intent optimisation for useful content and measurable results.',
        '2026 SEO Strategy',
        true
    ),
    SeoMetaStepView.keywordCoverage(
        '2026 SEO Strategy | AI Trends and Search Intent',
        'Update your 2026 SEO strategy with AI SEO trends and search intent optimisation for useful content and measurable results.',
        'AI SEO trends'
    ),
    SeoMetaStepView.keywordCoverage(
        '2026 SEO Strategy | AI Trends and Search Intent',
        'Update your 2026 SEO strategy with AI SEO trends and search intent optimisation for useful content and measurable results.',
        'search intent optimisation'
    )
];
assert.strictEqual(multiple[0].titleCoverage, 'exact', 'primary keyword should be exact in the title');
assert.strictEqual(multiple[0].descriptionCoverage, 'exact', 'primary match should be case-insensitive in the description');
assert.strictEqual(multiple[1].titleCoverage, 'combined', 'overlapping secondary terms should count as intelligently combined');
assert.strictEqual(multiple[1].descriptionCoverage, 'exact', 'secondary phrase should report exact description coverage');
assert.strictEqual(multiple[2].titleCoverage, 'partial', 'shortened secondary phrase should report partial title coverage');
assert.strictEqual(multiple[2].status, 'warning', 'partial secondary coverage should remain visible for refinement');

const compressedTitle = 'Local SEO Strategy 2026 | AI Overviews for Local Clients';
assert.notStrictEqual(
    SeoMetaStepView.fieldCoverage(compressedTitle, 'AI Overviews Impact On Local SEO'),
    'absent',
    'compressed title should retain a distinctive concept from the AI Overviews secondary'
);
assert.notStrictEqual(
    SeoMetaStepView.fieldCoverage(compressedTitle, 'How To Get Local Clients With AI Search'),
    'absent',
    'compressed title should retain a distinctive concept from the local clients secondary'
);

assert.strictEqual(
    SeoMetaStepView.separatorGuidance('2026 SEO Strategy | What Changed').status,
    'good',
    'spaced pipe should pass separator guidance'
);
assert.strictEqual(
    SeoMetaStepView.separatorGuidance('2026 SEO Strategy: What Changed').status,
    'warning',
    'colon should fail separator guidance'
);

const noKeyword = SeoMetaStepView.keywordGuidance('Title', 'Description', '');
assert.strictEqual(noKeyword.status, 'warning');
assert.ok(noKeyword.message.includes('Confirm relevance manually'), 'no keyword must not produce a made-up relevance claim');

console.log('SEO meta policy: 17 passed, 0 failed');
