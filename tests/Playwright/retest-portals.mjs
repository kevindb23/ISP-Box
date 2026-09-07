import { chromium } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// Run with QA_SUBSCRIBER_USER/PASSWORD and QA_TECHNICIAN_USER/PASSWORD in the environment.
// One authentication attempt per role. All subsequent non-GET requests are blocked.
const root = path.dirname(fileURLToPath(import.meta.url));
const target = process.env.QA_BASE_URL || 'http://10.0.10.155';
const report = {target: `${target}/login`, startedAt:new Date().toISOString(), completedAt:null, checks:[], cleanup:[], coverage:[], limitations:[]};
const secrets = ['QA_SUBSCRIBER_USER','QA_SUBSCRIBER_PASSWORD','QA_TECHNICIAN_USER','QA_TECHNICIAN_PASSWORD'].map(k=>process.env[k]).filter(Boolean);
function clean(x) { let s= typeof x==='string'?x:JSON.stringify(x); for(const v of secrets) s=s.split(v).join('[REDACTED]'); return s.replace(/((?:token|password|cookie|csrf|authorization)["'\s:=]+)[^\s,;"'<>]+/gi,'$1[REDACTED]'); }
function url(x) { try {const u=new URL(x,target);return u.pathname + (u.searchParams.has('status')?'?status='+u.searchParams.get('status'):'');}catch{return '[invalid URL]';} }
function row(portal,area,name,status,expected,actual,evidence=[],severity=status==='FAIL'?'medium':'info') {report.checks.push({id:`PORTALS-${String(report.checks.length+1).padStart(4,'0')}`,portal,area,name,status,expected:expected.slice(0,500),actual:clean(actual).slice(0,1800),evidence,severity});}
async function save(){await fs.writeFile(path.join(root,'retest-portals-results.json'),JSON.stringify(report,null,2));}
const routes={subscriber:['/subscriber-portal','/subscriber-portal/account','/subscriber-portal/services','/subscriber-portal/invoices','/subscriber-portal/payments','/subscriber-portal/tickets','/subscriber-portal/security'],technician:['/technician-portal','/technician-portal/work-orders']};
const apis={subscriber:['me','maintenance','dashboard','summary','services','invoices','payments','security','tickets','tickets/visit-slots?date='+new Date().toISOString().slice(0,10)],technician:['dashboard','work-orders']};
const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
try {
 for(const role of ['subscriber','technician']) {
  const dir=path.join(root,role,'retest-portals');await fs.mkdir(dir,{recursive:true});
  const context=await browser.newContext({baseURL:target,viewport:{width:1440,height:900},ignoreHTTPSErrors:true});
  let loginAllowed=true;const prevented=[];
  await context.route('**/*',async route=>{const req=route.request();if(!['GET','HEAD','OPTIONS'].includes(req.method()) && !(loginAllowed && new URL(req.url()).pathname==='/login')){prevented.push({method:req.method(),path:url(req.url())});return route.abort('blockedbyclient');}return route.continue();});
  const page=await context.newPage();page.setDefaultTimeout(6000);page.setDefaultNavigationTimeout(25000);
  let events=[];const data=new Map();
  page.on('console',m=>{if(m.type()==='error')events.push({type:'console',message:clean(m.text()).slice(0,600)});});
  page.on('pageerror',e=>events.push({type:'pageerror',message:clean(e.message).slice(0,600)}));
  page.on('requestfailed',r=>events.push({type:'requestfailed',method:r.method(),path:url(r.url()),message:r.failure()?.errorText}));
  page.on('response',async r=>{if(r.status()>=400)events.push({type:'http',status:r.status(),path:url(r.url())}); if(r.url().includes(`/api/v1/${role}-portal/`) && r.request().method()==='GET'){try{data.set(new URL(r.url()).pathname,await r.json());}catch{}}});
  async function shot(name){const file=path.join(dir,name.replace(/[^a-z0-9_-]/gi,'_')+'.png');await page.screenshot({path:file,fullPage:true,mask:[page.locator('input'),...secrets.map(s=>page.getByText(s,{exact:false}))]});return path.relative(root,file);}
  async function check(area,name,fn){try{await fn();}catch(e){row(role,area,name,'FAIL','Check completes successfully',clean(e.message).slice(0,900));}await save();}
  async function settle(){await page.waitForLoadState('networkidle',{timeout:7000}).catch(()=>{});await page.waitForTimeout(450);}
  async function closeModal(modal){const close=modal.locator('[data-bs-dismiss="modal"]').first();if(await close.isVisible())await close.click();else await page.keyboard.press('Escape');await page.waitForTimeout(400);}
  try{
   await page.goto('/login');
   const user=process.env[`QA_${role.toUpperCase()}_USER`],pass=process.env[`QA_${role.toUpperCase()}_PASSWORD`];
   if(!user||!pass)throw new Error('Required credential environment variables missing; no login attempted');
   await page.locator('input[name="username"]').fill(user);await page.locator('input[name="password"]').fill(pass);
   const loginResponse=page.waitForResponse(r=>new URL(r.url()).pathname==='/login'&&r.request().method()==='POST');
   await page.locator('#loginForm button[type="submit"],button[type="submit"]').first().click();
   const initial=await loginResponse;
   if(initial.status()===429){console.log(`${role}: shared IP 429; waiting 65 seconds before one retry`);await page.waitForTimeout(65000);await page.goto('/login');await page.locator('input[name="username"]').fill(user);await page.locator('input[name="password"]').fill(pass);await page.locator('#loginForm button[type="submit"],button[type="submit"]').first().click();report.limitations.push(`${role}: initial HTTP 429; one retry after 65 seconds.`);}
   await page.waitForURL(u=>u.pathname!='/login',{timeout:20000});loginAllowed=false;await settle();
   row(role,'authentication','Single valid login','PASS','Authenticated portal reached',`Landing path ${url(page.url())}; one login attempt`);
   const nav=await page.locator('a[href]').evaluateAll(els=>els.map(e=>({href:e.getAttribute('href'),label:e.textContent.trim().slice(0,90)})));
   const discovered=nav.filter(n=>n.href?.startsWith('/')&&!/logout|download|print|receipt/.test(n.href)&&!n.href.startsWith('/api/')).map(n=>n.href.split('?')[0]);
   const paths=[...new Set([...routes[role],...discovered])];
   report.coverage.push({portal:role,browser:browser.version(),routes:paths,navigation:nav.filter(n=>discovered.includes(n.href)),viewports:[{width:1440,height:900},{width:390,height:844},{width:768,height:1024}]});await save();
   for(const p of paths)await check('routes',p,async()=>{
    events=[];const response=await page.goto(p);await settle();
    const text=await page.locator('body').innerText();const fatal=/SQLSTATE|PDOException|Fatal error|Uncaught (?:Error|Exception)/.test(text);
    const evidence=[await shot('desktop'+p)];
    row(role,'routes',`Load ${p}`,response.status()<400&&!fatal&&!/\/login$/.test(page.url())?'PASS':'FAIL','Authenticated page, HTTP <400, no raw server exception',`HTTP ${response.status()}; final ${url(page.url())}; raw exception ${fatal}`,evidence);
    row(role,'diagnostics',`Runtime/network ${p}`,events.length?'FAIL':'PASS','No HTTP >=400, failed requests, console errors or page exceptions',events.length?events:'No observed errors',evidence);
    const structure=await page.evaluate(()=>{const nodes=[...document.querySelectorAll('[id]')],ids=nodes.map(n=>n.id);return{duplicateIds:[...new Set(ids.filter((x,i)=>ids.indexOf(x)!==i))],unnamedButtons:[...document.querySelectorAll('button')].filter(e=>e.getBoundingClientRect().width&&!e.innerText.trim()&&!e.getAttribute('aria-label')&&!e.title).map(e=>e.id||e.className),headings:document.querySelectorAll('h1,h2,h3,h4,h5').length};});
    row(role,'accessibility',`Structure ${p}`,structure.duplicateIds.length||structure.unnamedButtons.length?'FAIL':'PASS','Unique IDs and visible buttons have accessible labels (smoke heuristic)',structure,evidence);
    await page.keyboard.press('Tab');const focus=await page.evaluate(()=>({tag:document.activeElement.tagName,outline:getComputedStyle(document.activeElement).outlineStyle,shadow:getComputedStyle(document.activeElement).boxShadow}));
    row(role,'accessibility',`Keyboard focus ${p}`,focus.tag==='BODY'?'FAIL':'PASS','Tab moves focus to a control',focus,evidence);
    for(const width of [390,768]){await page.setViewportSize({width,height:width===390?844:1024});await page.waitForTimeout(200);const overflow=await page.evaluate(()=>({viewport:innerWidth,body:document.body.scrollWidth,document:document.documentElement.scrollWidth}));row(role,'responsive',`${width}px overflow ${p}`,overflow.document>width+2?'FAIL':'PASS','No document horizontal overflow',overflow,[await shot(`width${width}${p}`)]);}
    await page.setViewportSize({width:1440,height:900});
   });
   // Read-only supported APIs; retain only shapes/counts and errors, never full account payloads.
   for(const endpoint of apis[role])await check('api',endpoint,async()=>{
    const p=`/api/v1/${role}-portal/${endpoint}`,r=await context.request.get(p);let j;try{j=await r.json();}catch{}
    if(j)data.set(p.split('?')[0],j);
    const d=j?.data||j;row(role,'api',`GET ${p}`,r.ok()&&j&&j.success!==false?'PASS':'FAIL','HTTP 2xx and successful JSON response',{status:r.status(),keys:d&&typeof d==='object'?Object.keys(d):[],error:!r.ok()||j?.success===false?j?.message:undefined});
   });
   function items(p){const j=data.get(`/api/v1/${role}-portal/${p}`);const d=j?.data||j;return Array.isArray(d)?d:Array.isArray(d?.items)?d.items:[];}
   const kinds=role==='subscriber'?['tickets','invoices','payments']:['work-orders'];
   for(const kind of kinds)await check('details',kind,async()=>{
    const list=items(kind);const ids=list.map(x=>x.id||x.invoice_id||x.payment_id).filter(Boolean);
    report.coverage.push({portal:role,collection:kind,availableOnFetchedPage:ids.length,detailsAttempted:ids.length,pagination:'Only returned list page; no guessed existing record IDs'});
    if(!ids.length){row(role,'details',`${kind} details`,'BLOCKED','At least one owned/assigned record available','No returned record IDs; no fixture created because cleanup unsupported');return;}
    for(const id of ids){const p=kind==='payments'?`/subscriber-portal/payments/receipt/${id}`:`/api/v1/${role}-portal/${kind}/show/${id}`;const r=await context.request.get(p);let j;try{j=await r.json();}catch{}row(role,'details',`${kind} record ${id}` ,r.ok()&&j?.success!==false?'PASS':'FAIL','Returned own/assigned record detail loads',{path:p,status:r.status(),error:!r.ok()?j?.message:undefined});}
    if(kind==='payments')return;
    await page.goto(`/${role}-portal/${kind}`);await settle();
    const attr=kind==='tickets'?'data-ticket-id':kind==='invoices'?'data-invoice-id':'data-work-order-id';const triggers=page.locator(`[${attr}]`);const modal=page.locator(kind==='tickets'?'#spTicketDetailsModal':kind==='invoices'?'#spInvoiceDetailsModal':'#techWorkOrderModal');
    if(!await triggers.count()){row(role,'modal',`${kind} UI detail`,'FAIL','Detail trigger for loaded record','API records exist but no detail trigger');return;}
    events=[];await triggers.first().click();await settle();const visible=await modal.isVisible();row(role,'modal',`${kind} open and content`,visible&&!/Loading\.\.\./.test(await modal.innerText())?'PASS':'FAIL','Detail dialog visible and populated',{visible,errors:events},[await shot(`${kind}-detail`)]);
    row(role,'diagnostics',`${kind} detail runtime/network`,events.length?'FAIL':'PASS','No errors while opening record details',events.length?events:'No observed errors');
    await page.setViewportSize({width:390,height:844});const bounds=await modal.locator('.modal-dialog').boundingBox();row(role,'responsive',`${kind} mobile modal`,bounds&&bounds.x>=0&&bounds.x+bounds.width<=392?'PASS':'FAIL','Dialog fits 390px viewport',bounds,[await shot(`${kind}-detail-mobile`)]);
    await closeModal(modal);row(role,'modal',`${kind} close`,!await modal.isVisible()?'PASS':'FAIL','Dialog dismisses without writing','Visibility after dismissal: '+await modal.isVisible());await page.setViewportSize({width:1440,height:900});
   });
   await check('notifications','Bell and responsive navigation',async()=>{
    await page.goto(routes[role][0]);await settle();await page.setViewportSize({width:390,height:844});
    const toggle=page.locator('#sidebarToggleBtn');if(await toggle.isVisible()){await toggle.click();const open=await page.locator('body').evaluate(e=>e.classList.contains('sidebar-mobile-open'));row(role,'responsive','Mobile navigation opens',open?'PASS':'FAIL','Sidebar opens from toggle',`sidebar-mobile-open=${open}`,[await shot('mobile-navigation')]);const scrim=page.locator('#sidebarScrim');if(await scrim.isVisible())await scrim.click({force:true});}else row(role,'responsive','Mobile navigation toggle','BLOCKED','Visible mobile navigation control','Expected toggle absent or hidden');
    const bell=page.locator('#globalNotificationsToggle');if(await bell.isVisible()){events=[];await bell.click();await settle();const panel=page.locator('#globalNotifications');row(role,'notifications','Notification dropdown',await panel.isVisible()?'PASS':'FAIL','Notification panel opens without changing read state',{visible:await panel.isVisible(),errors:events},[await shot('notifications')]);const badge=page.locator('#globalNotificationsCount');row(role,'notifications','Zero badge suppression',await badge.isVisible()&&(await badge.innerText()).trim()==='0'?'FAIL':'PASS','Zero-count badge hidden','Zero-count visibility checked');await page.keyboard.press('Escape');}else row(role,'notifications','Notification dropdown','BLOCKED','Visible notification toggle','Expected toggle absent or hidden');await page.setViewportSize({width:1440,height:900});
   });
   await check('filters','Read-only filters',async()=>{
    await page.goto(`/${role}-portal/${role==='subscriber'?'tickets':'work-orders'}`);await settle();const selects=page.locator('select:visible');let count=0;
    for(let i=0;i<await selects.count();i++){const s=selects.nth(i);const id=await s.getAttribute('id');if(!/filter/i.test(id||''))continue;const opts=await s.locator('option').evaluateAll(n=>n.map(e=>({value:e.value,text:e.textContent})));const original=await s.inputValue();for(const opt of opts){events=[];await s.selectOption(opt.value);await settle();row(role,'filters',`${id} ${opt.text}`,events.length?'FAIL':'PASS','Filter settles without API/runtime errors',events.length?events:'Filter selected; result semantics not independently verified',[await shot(`filter-${id}-${opt.value||'all'}`)]);count++;}await s.selectOption(original);}
    if(!count)row(role,'filters','List filter controls','BLOCKED','Visible list filter to exercise','No visible filter select on list page');
   });
   await check('forms','Read-only form and attendance inventory',async()=>{
    await page.goto(routes[role][0]);await settle();const controls=await page.locator('button,select,form').evaluateAll(n=>n.filter(e=>/attendance|time.?in|time.?out|status/i.test((e.id||'')+' '+(e.textContent||''))).map(e=>({tag:e.tagName,id:e.id,text:e.tagName==='BUTTON'?e.textContent.trim().slice(0,70):undefined})).slice(0,30));
    row(role,'attendance','Attendance display and controls',role==='technician'&&controls.length?'PASS':'BLOCKED','Read-only attendance display available',controls.length?controls:'No attendance controls identified on portal landing page',[await shot('attendance-inventory')]);
    if(role==='subscriber'){await page.goto('/subscriber-portal/tickets');await settle();const trigger=page.locator('[data-bs-target="#spRaiseConcernModal"]');if(await trigger.count()){await trigger.first().click();await settle();const modal=page.locator('#spRaiseConcernModal');const required=await modal.locator('[required]').evaluateAll(n=>n.map(e=>({name:e.name,valid:e.checkValidity()})));row(role,'forms','Create ticket dialog required fields',await modal.isVisible()&&required.some(x=>!x.valid)?'PASS':'FAIL','Create form opens and empty required fields are invalid',required,[await shot('ticket-create-empty')]);await closeModal(modal);row(role,'forms','Cancel ticket creation',!await modal.isVisible()?'PASS':'FAIL','Cancel dismisses without saving',`Visible=${await modal.isVisible()}`);}else row(role,'forms','Create ticket dialog','BLOCKED','Create trigger available','No matching create trigger');}
   });
   for(const [area,name,why] of [['crud','Create/update/delete QA tickets or work orders','Inspected portal and management routes: no supported ticket/work-order delete/archive cleanup endpoint; no mutation attempted.'],['ownership','Cross-account QA fixture ownership','No cleanup-safe QA fixture available; existing record ID enumeration prohibited.'],['attendance','Attendance mutation and status transitions','Explicitly excluded by user; existing attendance/status untouched.']])row(role,area,name,'BLOCKED','Safe authorized fixture/control available',why);
  }catch(e){loginAllowed=false;row(role,'execution','Role execution','BLOCKED','Complete authenticated portal checks',clean(e.message).slice(0,1000));}
  finally{report.cleanup.push({portal:role,createdRecords:0,mutatedRecords:0,cleanupRequired:false,preventedRequests:prevented,contextClosed:true});await context.close();await save();}
 }
}finally{await browser.close();report.completedAt=new Date().toISOString();report.limitations.push('Security login probes, logout/session-expiry and credential/MFA changes deliberately excluded. One login attempt per role; contexts reused.','CRUD and fixture ownership blocked by absent supported cleanup endpoints. No existing record mutations attempted.','Read-only page navigation can cause application-managed access/audit logs. Non-GET browser requests except initial login blocked.','Chromium only. Accessibility checks are smoke heuristics, not a WCAG audit. No full response bodies, traces, cookies, tokens or credentials retained.','Details cover records returned on first API list page; no exhaustive historical pagination, export/download attachment content, destructive recovery or concurrency checks.');await save();console.log(JSON.stringify({result:path.join(root,'retest-portals-results.json'),counts:report.checks.reduce((a,c)=>(a[c.status]=(a[c.status]||0)+1,a),{}),checks:report.checks.length}));}
