import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const mainCss = fs.readFileSync(new URL('../../assets/css/main.css', import.meta.url), 'utf8');
const componentsCss = fs.readFileSync(new URL('../../assets/css/components.css', import.meta.url), 'utf8');
const cardSource = fs.readFileSync(new URL('../../assets/js/utils/CardRenderer.js', import.meta.url), 'utf8');
const choiceSource = fs.readFileSync(new URL('../../assets/js/views/steps/ChoiceStepView.js', import.meta.url), 'utf8');
const titlesSource = fs.readFileSync(new URL('../../assets/js/views/steps/TitlesStepView.js', import.meta.url), 'utf8');
const keywordsSource = fs.readFileSync(new URL('../../assets/js/views/steps/KeywordsStepView.js', import.meta.url), 'utf8');
const wizardFlowSource = fs.readFileSync(new URL('../../assets/js/controllers/WizardFlowController.js', import.meta.url), 'utf8');
const taglineSource = fs.readFileSync(new URL('../../assets/js/views/steps/TaglineStepView.js', import.meta.url), 'utf8');
const streamingSource = fs.readFileSync(new URL('../../assets/js/views/steps/StreamingStepView.js', import.meta.url), 'utf8');
const introSource = fs.readFileSync(new URL('../../assets/js/views/steps/IntroStepView.js', import.meta.url), 'utf8');
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage({ viewport: { width: 1365, height: 900 } });
    await page.setContent(`<!doctype html><html><head><style>${mainCss}\n${componentsCss}</style></head><body>
      <main class="ai-scribe-app">
        <section data-step-panel="1"><div class="results-section"><div class="choice-guidance"><span data-testid="choice-selection-status"></span></div><div class="options-grid" id="titles-options"></div><button data-testid="continue" disabled>Continue</button></div><input data-testid="idea-input"></section>
        <section data-step-panel="2"><div class="results-section"><div class="choice-guidance"><p>Demand bands are qualitative AI estimates, not measured search-volume data.</p><div class="choice-guidance-actions"><span data-testid="choice-selection-status"></span><button data-choice-action="select-all">Select all</button><button data-choice-action="deselect-all">Deselect all</button><a data-keyword-action="compare-trends" class="keyword-compare-link is-disabled" href="#" target="_blank" rel="noopener noreferrer" aria-disabled="true">Compare selected in Google Trends</a></div><p data-testid="keyword-load-warning" hidden></p></div><div class="keywords-grid" id="keywords-options"></div><button data-testid="continue" disabled>Continue</button></div></section>
        <section data-step-panel="4"><div class="results-section"><div class="stream-output prose-output" id="intro-stream-output"></div><button data-testid="continue" disabled>Continue</button></div></section>
        <section data-step-panel="5"><div class="results-section"><div class="choice-guidance"><span data-testid="choice-selection-status"></span></div><div class="options-grid" id="tagline-options"></div><fieldset data-testid="tagline-placement" disabled><input type="radio"></fieldset><button data-testid="continue" disabled>Continue</button></div></section>
      </main></body></html>`);
    await page.addScriptTag({ content: `
      window.lucide={createIcons(){}};
      class BaseStepView {
        constructor(step, appState){this.step=step;this.appState=appState;this.panel=document.querySelector('[data-step-panel="'+step+'"]');this.state='idle';this.resultsSection=this.panel.querySelector('.results-section');this.bindPanelEvents();}
        bindPanelEvents(){}
        setNextEnabled(on){this.panel.querySelector('[data-testid="continue"]').disabled=!on;}
        showReady(){this.state='ready';}
        showEmpty(){}
        showLoading(){}
        showStreaming(){}
        renderTrustedHtml(el,html){el.innerHTML=html;}
      }
    ` });
    for (const source of [cardSource, choiceSource, titlesSource, keywordsSource, taglineSource, streamingSource, introSource]) {
        await page.addScriptTag({ content: source });
    }
    await page.addScriptTag({ content: wizardFlowSource });
    await page.evaluate(() => {
        window.state = { stepData: {} };
        const appState = { getStateSlice:key=>window.state[key], setStateSlice:(key,value)=>{window.state[key]=value;} };
        window.titles = new TitlesStepView(appState);
        window.keywords = new KeywordsStepView(appState);
        window.tagline = new TaglineStepView(appState);
        window.intro = new IntroStepView(appState);
        window.titles.renderTyped({titles:['SEO Tips for 2026','Evergreen SEO Advice']});
        window.keywords.renderTyped({keywords:[
          {keyword:'SEO tips for 2026',role:'primary',demand_band:'high',estimate_basis:'ai_unverified'},
          {keyword:'search optimisation guide',role:'long-tail',demand_band:'medium',estimate_basis:'ai_unverified'}
        ]});
        window.tagline.renderTyped({taglines:['Specific first tagline','Duplicate alternative']});
        window.intro.renderTyped({html:'<p>Editable full-width introduction copy.</p>'});
    });

    assert.equal(await page.locator('#keywords-options .keyword-card.selected').count(), 1, 'first keyword is selected by default');
    assert.equal(await page.locator('[data-step-panel="2"] [data-testid="choice-selection-status"]').textContent(), '1 of 2 selected');
    assert.match(await page.locator('#keywords-options').innerText(), /\(Estimated search volume: High — AI estimate, unverified\)/);
    assert.match(await page.locator('#keywords-options').innerText(), /Primary/);
    assert.match(await page.locator('#keywords-options').innerText(), /Long-tail/);
    assert.equal(await page.locator('.keyword-card').first().getAttribute('data-estimate-basis'), 'ai_unverified');
    assert.match(await page.locator('.keyword-card').first().getAttribute('data-index'), /^0$/);
    const firstTrends = new URL(await page.locator('.keyword-trends-link').first().getAttribute('href'));
    assert.equal(firstTrends.origin, 'https://trends.google.com');
    assert.equal(firstTrends.searchParams.get('q'), 'SEO tips for 2026');
    assert.equal(firstTrends.searchParams.get('date'), 'today 5-y');
    assert.equal(await page.locator('[data-keyword-action="compare-trends"]').getAttribute('aria-disabled'), 'false');
    assert.equal(await page.locator('.keyword-card .keyword-trends-link').count(), 0, 'external actions are not nested inside checkbox cards');
    await page.locator('[data-step-panel="2"] [data-choice-action="select-all"]').click();
    assert.equal(await page.locator('#keywords-options .selected').count(), 2);
    const compareTrends = new URL(await page.locator('[data-keyword-action="compare-trends"]').getAttribute('href'));
    assert.equal(compareTrends.origin, 'https://trends.google.com');
    assert.equal(compareTrends.searchParams.get('q'), 'SEO tips for 2026,search optimisation guide');
    assert.equal(compareTrends.searchParams.get('date'), 'today 5-y');
    assert.deepEqual(await page.evaluate(() => window.keywords.getSelection()), ['SEO tips for 2026', 'search optimisation guide']);
    assert.equal(
        await page.evaluate(() => WizardFlowController.normaliseOption({keyword:'SEO tips for 2026'})),
        'SEO tips for 2026',
        'stored structured keyword selections normalise for hydration'
    );

    for (const width of [375, 768, 1440]) {
        await page.setViewportSize({width, height:900});
        const geometry = await page.locator('[data-step-panel="2"] .results-section').evaluate((section) => ({
            clientWidth: section.clientWidth,
            scrollWidth: section.scrollWidth
        }));
        assert.ok(geometry.scrollWidth <= geometry.clientWidth, `keyword demand UI does not overflow at ${width}px`);
    }
    await page.locator('[data-step-panel="2"] [data-choice-action="deselect-all"]').click();
    assert.equal(await page.locator('[data-step-panel="2"] [data-testid="continue"]').isDisabled(), true);
    assert.match(await page.locator('[data-step-panel="2"] [data-testid="choice-selection-status"]').textContent(), /Select at least one/);
    assert.equal(await page.locator('[data-keyword-action="compare-trends"]').getAttribute('aria-disabled'), 'true');

    await page.evaluate(() => window.keywords.renderTyped({keywords:[
      {keyword:'third',role:'supporting',demand_band:'low',estimate_basis:'ai_unverified'},
      {keyword:'fourth',role:'supporting',demand_band:'low',estimate_basis:'ai_unverified'},
      {keyword:'fifth',role:'supporting',demand_band:'low',estimate_basis:'ai_unverified'},
      {keyword:'sixth',role:'long-tail',demand_band:'low',estimate_basis:'ai_unverified'}
    ]}, {append:true}));
    await page.locator('[data-step-panel="2"] [data-choice-action="select-all"]').click();
    assert.equal(await page.locator('[data-keyword-action="compare-trends"]').getAttribute('aria-disabled'), 'true');
    assert.match(await page.locator('[data-keyword-action="compare-trends"]').innerText(), /Select up to 5/);
    assert.equal(await page.locator('[data-testid="keyword-load-warning"]').isVisible(), true);
    assert.match(await page.locator('[data-testid="keyword-load-warning"]').textContent(), /1 primary keyword and 1–2 secondary/);

    await page.evaluate(() => window.keywords.renderTyped({keywords:['legacy keyword phrase']}));
    assert.match(await page.locator('#keywords-options').innerText(), /\(Estimated search volume: Unknown — AI estimate, unverified\)/);

    assert.equal(await page.locator('#tagline-options .option-card').count(), 1, 'normal tagline generation shows one result');
    assert.equal(await page.locator('#tagline-options .selected').count(), 1, 'single tagline is selected automatically');
    assert.equal(await page.locator('[data-testid="tagline-placement"]').isDisabled(), false);
    await page.evaluate(() => window.tagline.renderTyped({taglines:['Replacement tagline']}, {append:true}));
    assert.equal(await page.locator('#tagline-options .option-card').count(), 1, 'Try another replaces rather than accumulates taglines');
    assert.match(await page.locator('#tagline-options').innerText(), /Replacement tagline/);

    const titleGeometry = await page.locator('#titles-options .option-card').first().evaluate((card) => {
        const box = card.getBoundingClientRect();
        const check = card.querySelector('.checkbox').getBoundingClientRect();
        const text = card.querySelector('.option-text').getBoundingClientRect();
        return {height:box.height, checkboxLeft:check.left, textLeft:text.left};
    });
    assert.ok(titleGeometry.height < 70, 'choice rows are compact');
    assert.ok(titleGeometry.checkboxLeft < titleGeometry.textLeft, 'selection control sits beside, not far beyond, its label');

    const introGeometry = await page.locator('#intro-stream-output').evaluate((output) => ({
        width: output.getBoundingClientRect().width,
        childWidth: output.firstElementChild.getBoundingClientRect().width,
        editable: output.contentEditable,
        label: output.getAttribute('aria-label')
    }));
    assert.ok(introGeometry.childWidth > introGeometry.width * 0.9, 'introduction uses the available result width');
    assert.equal(introGeometry.editable, 'true');
    assert.equal(introGeometry.label, 'Editable introduction');
    await page.locator('#intro-stream-output').fill('Owner-edited introduction.');
    assert.match(await page.evaluate(() => window.state.stepData[4].contentHtml), /Owner-edited introduction/);

    console.log('Editorial workflow browser: compact choices, truthful keyword evidence, one tagline and editable full-width introduction passed');
} finally {
    await browser.close();
}
