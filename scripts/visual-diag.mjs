import { chromium } from '@playwright/test';
const b = await chromium.launch(); const ctx = await b.newContext({viewport:{width:1920,height:1080}});
const p = await ctx.newPage();
const consoleMsgs = []; const netFails = [];
p.on('console', m => { if (m.type()==='error'||m.type()==='warning') consoleMsgs.push(`${m.type()}: ${m.text().slice(0,300)}`); });
p.on('response', async r => { if (r.url().includes('admin-ajax') ) { let body=''; try{body=(await r.text()).slice(0,400)}catch{}; netFails.push(`${r.status()} ${new URL(r.url()).search || 'POST'} :: ${body}`); } });
await p.goto('http://localhost:8888/wp-login.php');
await p.fill('#user_login','admin'); await p.fill('#user_pass','password'); await p.click('#wp-submit');
await p.waitForLoadState('networkidle');
await p.goto('http://localhost:8888/wp-admin/admin.php?page=ai_scribe_generate_article');
await p.waitForTimeout(6000); // let model load settle or fail
await p.screenshot({path:'/tmp/diag-wizard-1920.png', fullPage:true});
const modelText = await p.locator('[data-testid="selected-model"], .selected-model, #selected-model').first().textContent().catch(()=>'(selector not found)');
console.log('=== MODEL DISPLAY:', (modelText||'').trim().slice(0,200));
console.log('=== CONSOLE (err/warn):'); consoleMsgs.slice(0,15).forEach(m=>console.log(m));
console.log('=== ADMIN-AJAX CALLS:'); netFails.slice(0,15).forEach(m=>console.log(m));
await b.close();
