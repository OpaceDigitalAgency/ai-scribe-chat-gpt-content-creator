import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const choiceSource = fs.readFileSync(new URL('../../assets/js/views/steps/ChoiceStepView.js', import.meta.url), 'utf8');
const outlineSource = fs.readFileSync(new URL('../../assets/js/views/steps/OutlineStepView.js', import.meta.url), 'utf8');
const streamingSource = fs.readFileSync(new URL('../../assets/js/views/steps/StreamingStepView.js', import.meta.url), 'utf8');
const bodySource = fs.readFileSync(new URL('../../assets/js/views/steps/BodyStepView.js', import.meta.url), 'utf8');
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage();
    await page.setContent(`<!doctype html><html><body>
      <section data-step-panel="3"><div class="results-section"><span data-testid="choice-selection-status"></span><div id="outline-options"></div><button data-testid="continue">Continue</button></div></section>
      <section data-step-panel="6"><div class="results-section"><div id="body-stream-output"></div><button data-action="generate">Regenerate</button><button data-testid="continue">Continue</button></div><div data-testid="step-status"></div></section>
    </body></html>`);
    await page.addScriptTag({ content: `
      window.lucide={createIcons(){}};
      class BaseStepView {
        constructor(step,appState){this.step=step;this.appState=appState;this.panel=document.querySelector('[data-step-panel="'+step+'"]');this.resultsSection=this.panel.querySelector('.results-section');this.proseTarget=null;this.state='idle';this.lastRetryHandler=null;this.bindPanelEvents();}
        bindPanelEvents(){}
        setNextEnabled(on){this.panel.querySelector('[data-testid="continue"]').disabled=!on;}
        showReady(){this.state='ready';this.setNextEnabled(true);}
        showEmpty(){this.state='empty';}
        showLoading(){}
        showStreaming(){}
        renderTrustedHtml(el,html){if(el)el.innerHTML=html;}
        refreshIcons(){}
        announce(){}
        showError(error,onRetry){this.state='error';this.lastRetryHandler=onRetry;this.panel.dataset.error=error.message;}
      }
      class CardRenderer {}
    ` });
    for (const source of [choiceSource, outlineSource, streamingSource, bodySource]) {
        await page.addScriptTag({ content: source });
    }
    await page.evaluate(() => {
        window.state = { stepData: {} };
        const appState = {getStateSlice:(key)=>window.state[key],setStateSlice:(key,value)=>{window.state[key]=value;}};
        window.outline = new OutlineStepView(appState);
        window.body = new BodyStepView(appState);
        window.outline.renderTyped({outline:['Initial A','Initial B','Duplicate &amp; Entity']});
        window.outline.renderTyped({outline:[' initial   a ','Generated C','Duplicate & Entity']},{append:true});
    });

    assert.equal(await page.locator('#outline-options .outline-card').count(), 4, 'Generate More suppresses exact duplicates after entity/case/space normalisation');
    assert.equal(await page.locator('#outline-options .outline-card.selected').count(), 4, 'new unique Generate More headings are selected');
    assert.deepEqual(await page.evaluate(() => window.outline.getSelection()), ['Initial A','Initial B','Duplicate &amp; Entity','Generated C']);

    await page.evaluate(() => window.body.renderTyped({html:'<h2>initial a</h2><p>A</p><h2>Initial B</h2><p>B</p><h2>Duplicate &amp; Entity</h2><p>D</p><h2>Generated C</h2><p>C</p>'}));
    assert.equal(await page.locator('[data-step-panel="6"] [data-testid="continue"]').isDisabled(), false, 'all selected unique headings allow continuation');
    assert.equal(await page.evaluate(() => window.body.validateOutlineCoverage().valid), true);

    await page.evaluate(() => window.body.renderTyped({html:'<h2>Initial A</h2><p>A</p><h2>Initial B</h2><p>B</p><h2>Unselected heading</h2><p>X</p>'}));
    assert.equal(await page.locator('[data-step-panel="6"] [data-testid="continue"]').isDisabled(), true, 'missing or unselected headings block continuation');
    const error = await page.locator('[data-step-panel="6"]').getAttribute('data-error');
    assert.match(error, /Missing: Duplicate &amp; Entity; Generated C/);
    assert.match(error, /Not selected: Unselected heading/);
    assert.equal(await page.evaluate(() => typeof window.body.lastRetryHandler), 'function', 'coverage failure offers regeneration');

    console.log('Outline Generate More dedupe and exact body coverage gate passed');
} finally {
    await browser.close();
}
