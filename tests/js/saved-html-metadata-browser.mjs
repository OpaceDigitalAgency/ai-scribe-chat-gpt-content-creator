import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright';

const controllerSource = fs.readFileSync('assets/js/controllers/WizardFlowController.js', 'utf8');
const browser = await chromium.launch({ headless: true });

try {
    const page = await browser.newPage();
    await page.setContent('<main><div id="editor"><h2>Responsive image markup</h2><p>Section copy.</p></div></main>');
    await page.addScriptTag({ content: 'class StepViewRegistry{}; window.StepViewRegistry=StepViewRegistry;' });
    await page.addScriptTag({ content: controllerSource });
    const placement = await page.evaluate(() => {
        const controller = Object.create(WizardFlowController.prototype);
        const root = document.querySelector('#editor');
        const quill = {
            root,
            getLength() { return 999; },
            getIndex(blot) { return blot && blot.node === root.querySelector('h2') ? 25 : 0; }
        };
        window.Quill = { find(block) { return { node: block, length() { return 1; } }; } };
        controller.editorForStep = () => quill;
        const inserted = [];
        const announcements = [];
        controller.insertImageAt = (step, editor, data, index) => {
            inserted.push({ step, url: data.url, index });
            return true;
        };
        controller.announceImages = (step, message) => announcements.push({ step, message });

        const matched = controller.insertImageIntelligently(6, {
            url: 'matched.jpg',
            source_section: 'Responsive Image Markup'
        }, false);
        const missing = controller.insertImageIntelligently(6, {
            url: 'missing.jpg',
            source_section: 'A Section That Does Not Exist'
        }, false);
        return { matched, missing, inserted, announcements };
    });

    assert.equal(placement.matched, true, 'normalised section headings accept the intended image');
    assert.equal(placement.missing, false, 'a missing section does not silently append its image');
    assert.deepEqual(placement.inserted, [{ step: 6, url: 'matched.jpg', index: 26 }]);
    assert.match(placement.announcements[0].message, /was not moved to the end/);
    console.log('Saved HTML and metadata browser regression: section-target placement is deterministic and never silently appends');
} finally {
    await browser.close();
}
