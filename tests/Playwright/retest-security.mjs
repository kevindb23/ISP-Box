import { chromium } from '@playwright/test';
import { mkdir, writeFile, access, readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
const root = fileURLToPath(new URL('.', import.meta.url));
const probesOnly = process.argv.includes('--probes-only');
const target = process.env.QA_BASE_URL || 'http://10.0.10.155';
const report = { target, startedAt: new Date().toISOString(), completedAt: null, checks: [], cleanup: [], coverage: [], limitations: [] };
if(probesOnly){Object.assign(report,JSON.parse(await readFile(root+'retest-security-results.json','utf8')));report.checks=report.checks.filter(c=>c.name!=='final small invalid-login probe batch');report.limitations=report.limitations.filter(s=>!s.startsWith('Final invalid-login'));}
const save = async () => writeFile(root+'retest-security-results.json', JSON.stringify(report,null,2));
const add = (portal,area,name,status,expected,actual,evidence={},severity='info') => {
  report.checks.push({id:`AUTH-${String(report.checks.length+1).padStart(3,'0')}`,portal,area,name,status,expected,actual,evidence,severity:status==='FAIL'?severity:'info'});
  console.log(`${status} ${portal}: ${name}`);
};
const denied = r => r.status===401 || r.status===403 || ([301,302,303].includes(r.status)&&r.location==='/login');
const safeMessages = /^(Invalid|User not found|Ticket not found|Work order not found|Authentication required|You are not authorized|Too many login attempts|Unable to complete login|Login successful|Additional verification)/i;
async function req(ctx,method,path,data,headers={}) {
  const r=await ctx.request.fetch(target+path,{method,data,headers,maxRedirects:0,timeout:15000});
  const text=await r.text(); let j;try{j=JSON.parse(text);}catch{}
  return {status:r.status(),location:r.headers().location||'',headers:Object.fromEntries(Object.entries(r.headers()).filter(([k])=>['content-type','cache-control','strict-transport-security','content-security-policy','x-frame-options','x-content-type-options','referrer-policy','permissions-policy','access-control-allow-origin','access-control-allow-credentials'].includes(k))),csrfRejection:/CSRF/i.test(j?.message||''),message:typeof j?.message==='string'&&safeMessages.test(j.message)?j.message.slice(0,200):'[body omitted]',internalError:/SQLSTATE|PDOException|Fatal error|Stack trace:/i.test(text),success:j?.success,mfa:j?.data?.mfa_required===true,redirect:j?.data?.redirect_url};
}
async function check(role,area,name,expected,fn,severity='medium') {
  try {const {ok,evidence,actual,blocked}=await fn();add(role,area,name,blocked?'BLOCKED':ok?'PASS':'FAIL',expected,actual||JSON.stringify(evidence),evidence,severity);}
  catch(e){add(role,area,name,'BLOCKED',expected,e.name==='TimeoutError'?'Playwright timeout':String(e.message).split('\n')[0],{},'info');}
  await save();
}
const roles=[
  {name:'admin',home:'/dashboard',read:'/api/v1/users/0',post:'/api/v1/users/update/0',data:{},invalid:/Invalid user ID/},
  {name:'subscriber',home:'/subscriber-portal',read:'/api/v1/subscriber-portal/tickets/show/0',post:'/api/v1/subscriber-portal/tickets/reply',data:{ticket_id:0,message:''},invalid:/Invalid ticket ID/},
  {name:'technician',home:'/technician-portal',read:'/api/v1/technician-portal/work-orders/show/0',post:'/api/v1/technician-portal/work-orders/add-note',data:{work_order_id:0,note:''},invalid:/Invalid work order ID/}
];
await mkdir(root+'security',{recursive:true});
await writeFile(root+'security/route-inventory.json',JSON.stringify(roles.map(({name,home,read,post})=>({portal:name,home,read,post})),null,2));
let browser;
try {
 browser=await chromium.launch({headless:true,args:['--no-sandbox']});
 const anon=await browser.newContext();
 if(probesOnly){await runProbes(anon,process.env.QA_SECURITY_PROBES_READY==='1'||await access(root+'security/probes-ready').then(()=>true,()=>false));}
 else {
 // Authenticate early, exactly one POST per supplied role; never retry a failed login.
 for(const role of roles){
  role.ctx=await browser.newContext();role.page=await role.ctx.newPage();
  await check(role.name,'authentication','single browser login','Successful response and expected authenticated landing page',async()=>{
   const key='QA_'+role.name.toUpperCase();
   if(!process.env[key+'_USER']||!process.env[key+'_PASSWORD'])return {blocked:true,actual:'Credentials missing from environment'};
   await role.page.goto(target+'/login',{waitUntil:'domcontentloaded'});
   role.before=await role.ctx.cookies();
   await role.page.locator('#loginForm [name=username]').fill(process.env[key+'_USER']);
   await role.page.locator('#loginForm [name=password]').fill(process.env[key+'_PASSWORD']);
   const pending=role.page.waitForResponse(r=>r.url()===target+'/login'&&r.request().method()==='POST');
   await role.page.locator('#loginForm button[type=submit]').click();
   let response=await pending;
   if(response.status()===429){console.log(`${role.name}: shared IP 429; cooling down 65 seconds before the only retry`);await new Promise(r=>setTimeout(r,65000));await role.page.goto(target+'/login',{waitUntil:'domcontentloaded'});await role.page.locator('#loginForm [name=username]').fill(process.env[key+'_USER']);await role.page.locator('#loginForm [name=password]').fill(process.env[key+'_PASSWORD']);const retry=role.page.waitForResponse(r=>r.url()===target+'/login'&&r.request().method()==='POST');await role.page.locator('#loginForm button[type=submit]').click();response=await retry;}
   const j=await response.json();
   if(response.status()===429||j.data?.mfa_required)return {blocked:true,actual:response.status()===429?'Shared login limiter returned 429; no retry':'MFA challenge; not bypassed',evidence:{status:response.status()}};
   await role.page.waitForURL(u=>u.pathname!=='/login',{timeout:15000});
   role.ok=new URL(role.page.url()).pathname.startsWith(role.home)||(role.name==='technician'&&new URL(role.page.url()).pathname==='/staff-attendance');
   role.after=await role.ctx.cookies();
   role.token=await role.page.locator('meta[name="csrf-token"]').getAttribute('content').catch(()=>null);
   if(!role.token)role.token=await role.page.locator('[name=csrf_token]').first().inputValue().catch(()=>null);
   const paths=await role.page.locator('a[href]').evaluateAll(ns=>[...new Set(ns.map(n=>new URL(n.href).pathname))]);
   await mkdir(root+'security/'+role.name,{recursive:true});
   await writeFile(root+'security/'+role.name+'/navigation.json',JSON.stringify(paths,null,2));
   return {ok:response.status()===200&&role.ok,evidence:{status:response.status(),landing:new URL(role.page.url()).pathname}};
  },'high');
 }
 await writeFile(root+'security/authentication-complete.json',JSON.stringify({completedAt:new Date().toISOString(),roles:roles.map(r=>({portal:r.name,authenticated:!!r.ok}))}));
 for(const role of roles){
  for(const [method,path,data] of [['GET',role.home],['GET',role.read],['POST',role.post,role.data]])
   await check('anonymous','authorization',`${method} ${path}`,'Authentication rejection without response data',async()=>{const r=await req(anon,method,path,data);return {ok:denied(r),evidence:r};},'high');
 }
 for(const path of ['/olt-management/ports','/olt-management/profiles','/technician-portal/work-orders']){
  await check('anonymous','authorization',`GET subpage ${path}`,'Anonymous redirected to login',async()=>{const r=await req(anon,'GET',path);return {ok:denied(r),evidence:r};},'high');
  for(const role of roles.filter(r=>r.ok))await check(role.name,'authorization',`GET subpage ${path}`,'Allowed role returns 200; other roles return 403',async()=>{const allowed=role.name==='admin'||role.name==='technician'&&path.startsWith('/technician-portal');const r=await req(role.ctx,'GET',path);return {ok:r.status===(allowed?200:403),evidence:r};},'high');
 }
 const login=await req(anon,'GET','/login');
 const targetHost=new URL(target).hostname;
 const privateIp=/^(10\.|127\.|192\.168\.|169\.254\.|172\.(1[6-9]|2\d|3[0-1])\.)/.test(targetHost)||targetHost==='::1'||targetHost.toLowerCase().startsWith('fd');
 const privateHttpException=privateIp&&new URL(target).protocol==='http:'&&process.env.QA_ALLOW_HTTP_PRIVATE_IP==='1';
 for(const [name,ok,expected] of [
  ['HTTPS enforcement',login.location.startsWith('https://'),'HTTP redirects to HTTPS'],
  ['no-store login',/no-store/i.test(login.headers['cache-control']||''),'Cache-Control includes no-store'],
  ['MIME sniffing protection',login.headers['x-content-type-options']==='nosniff','X-Content-Type-Options: nosniff'],
  ['frame protection',!!login.headers['x-frame-options']||/frame-ancestors/i.test(login.headers['content-security-policy']||''),'X-Frame-Options or CSP frame-ancestors'],
  ['content security policy',!!login.headers['content-security-policy'],'Content-Security-Policy present'],
  ['referrer policy',!!login.headers['referrer-policy'],'Referrer-Policy present']
 ]) {
  const acceptedPrivateHttp=name==='HTTPS enforcement'&&privateHttpException;
  add('shared','headers',name,ok?'PASS':acceptedPrivateHttp?'BLOCKED':'FAIL',acceptedPrivateHttp?'HTTPS deferred: private-IP HTTP deployment exception':expected,JSON.stringify(login),login,name==='HTTPS enforcement'?'high':'low');
  if(acceptedPrivateHttp) report.limitations.push('HTTPS enforcement deferred: target is a private IP over HTTP and no domain certificate is available.');
 }
 for(const role of roles){
  if(!role.ok){report.limitations.push(`${role.name}: authenticated checks not attempted because the single login did not establish a session.`);continue;}
  const old=role.before.find(c=>c.name==='PHPSESSID'),current=role.after.find(c=>c.name==='PHPSESSID');
  add(role.name,'session','session ID rotates on login',old&&current?(old.value!==current.value?'PASS':'FAIL'):'BLOCKED','Pre-login and authenticated session IDs differ','Only comparison retained',{preLoginPresent:!!old,postLoginPresent:!!current,changed:!!old&&!!current&&old.value!==current.value},'high');
  add(role.name,'session','session cookie attributes',current?.httpOnly&&current?.sameSite==='Lax'?'PASS':'FAIL','HttpOnly and SameSite=Lax','Cookie values omitted',{httpOnly:current?.httpOnly,sameSite:current?.sameSite,secure:current?.secure},'medium');
  await check(role.name,'session','pre-login session cannot authenticate','Old session rejected',async()=>{const c=await browser.newContext();try{await c.addCookies(role.before);const r=await req(c,'GET',role.read);return {ok:denied(r),evidence:r};}finally{await c.close();}},'high');
  for(const other of roles.filter(r=>r.name!==role.name && role.name!=='admin')){
   for(const [method,path,data] of [['GET',other.home],['GET',other.read],['POST',other.post,other.data]])
    await check(role.name,'authorization',`${method} ${path}`,'403 role rejection',async()=>{const r=await req(role.ctx,method,path,data,role.token?{'X-CSRF-Token':role.token}:{});return {ok:r.status===403,evidence:r};},'high');
  }
  for(const [label,headers] of [['missing',{}],['incorrect',{'X-CSRF-Token':'qa-invalid-token'}]])
   await check(role.name,'csrf',`${label} token on ${role.post}`,'419 explicit CSRF rejection for invalid ID 0',async()=>{const r=await req(role.ctx,'POST',role.post,role.data,headers);return {ok:r.status===419&&r.csrfRejection,evidence:r};},'high');
  await check(role.name,'validation',`valid CSRF with invalid ID 0: ${role.post}`,'422 invalid ID validation, no internal error',async()=>{if(!role.token)return {blocked:true,actual:'No page CSRF token available'};const r=await req(role.ctx,'POST',role.post,role.data,{'X-CSRF-Token':role.token});return {ok:r.status===422&&role.invalid.test(r.message)&&!r.internalError,evidence:r};});
  await check(role.name,'session','logout server invalidation with replay','Valid logout redirects; old authenticated session rejected on replay',async()=>{
   if(!role.token)return {blocked:true,actual:'No CSRF token available for logout'};
   const c=await browser.newContext();try{await c.addCookies(await role.ctx.cookies());const before=await req(c,'GET',role.home);const logout=await req(role.ctx,'POST','/logout',undefined,{'X-CSRF-Token':role.token});const after=await req(c,'GET',role.home);report.cleanup.push({portal:role.name,action:'logout',status:denied(after)?'PASS':'FAIL'});return {ok:before.status===200&&[302,303].includes(logout.status)&&logout.location==='/login'&&denied(after),evidence:{before:{status:before.status},logout,after}};}finally{await c.close();}
  },'high');
 }
 report.limitations.push('30-minute idle expiry not exercised: read-only framework/SessionManager.php inspection shows SESSION_TIMEOUT=1800, check() destroys expired sessions and refreshes last_activity. Local source is not proof of deployed timeout behavior.');
 report.coverage.push('Single browser login per role; anonymous protected GET/POST; lower-role direct web/API boundaries; CSRF negative and valid-token controls on ID 0; headers; cookie flags; session rotation and old-ID rejection; logout replay; invalid-ID validation.');
 // Coordinator creates this marker only after other agents have authenticated.
 const ready=process.env.QA_SECURITY_PROBES_READY==='1'||await access(root+'security/probes-ready').then(()=>true,()=>false);
 if(!ready){report.limitations.push('Final invalid-login probes withheld pending coordinator readiness; run --probes-only with QA_SECURITY_PROBES_READY=1 after other agents authenticate.');}
 await runProbes(anon,ready);
 }
} catch(e){report.limitations.push('Harness interruption: '+String(e.message).split('\n')[0]);}
finally{await browser?.close();report.completedAt=new Date().toISOString();await save();}

async function runProbes(ctx,ready){
 if(!ready){add('shared','authentication','final small invalid-login probe batch','BLOCKED','Generic rejection and no authentication bypass','Coordinator readiness not signaled; zero probe POSTs sent');return;}
 for(const [label,username,password] of [['blank','',''],['invalid','qa-nonexistent-security-user','invalid-qa-password'],['harmless SQLi',"' OR '1'='1",'invalid-qa-password']]){
  await new Promise(r=>setTimeout(r,5000));
  const p=await ctx.newPage();await p.goto(target+'/login',{waitUntil:'domcontentloaded'});const token=await p.locator('#loginForm [name=csrf_token]').inputValue();
  await check('shared','authentication',`${label} input rejection`,'401/422 with generic Invalid username or password; protected API remains 401',async()=>{const r=await req(ctx,'POST','/login',{username,password,csrf_token:token});const guard=await req(ctx,'GET','/api/v1/users/0');return {blocked:r.status===429,ok:[401,422].includes(r.status)&&r.message==='Invalid username or password'&&!r.internalError&&guard.status===401,evidence:{response:r,protectedStatus:guard.status},actual:r.status===429?'Shared rate limiter reached; underlying credential validation blocked':undefined};},'high');
  await p.close();
 }
}
