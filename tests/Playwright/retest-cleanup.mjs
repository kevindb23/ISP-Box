import {chromium} from 'playwright';
import fs from 'node:fs/promises';
const browser=await chromium.launch({headless:true});
try{
const page=await browser.newPage({baseURL:'http://10.0.10.155'});
await page.goto('/login');
await page.locator('input[name="username"]').fill(process.env.QA_ADMIN_USER);
await page.getByLabel('Password',{exact:true}).fill(process.env.QA_ADMIN_PASSWORD);
await page.getByRole('button',{name:/sign in|log in|login/i}).click();
await page.waitForURL(u=>u.pathname!='/login');
const evidence=await page.evaluate(async()=>{
const token=document.querySelector('meta[name="csrf-token"]')?.content||document.querySelector('[name="csrf_token"]')?.value;
const api=async(url,data)=>{const r=await fetch(url,{method:data?'POST':'GET',headers:{'Content-Type':'application/json','X-CSRF-Token':token},...(data?{body:JSON.stringify(data)}:{})});return {status:r.status,body:await r.json()}};
const before=await api('/api/v1/subscriber-plans');
const rows=Array.isArray(before.body.data)?before.body.data:before.body.data?.items||[];
const record=rows.find(r=>Number(r.id)===79);
if(before.status!==200||!Array.isArray(before.body.data)&&!Array.isArray(before.body.data?.items))return {verified:false,status:before.status,message:before.body.message};
if(!record||record.plan_name!=='pw-qa-admin-1788578064250-plans')return {verified:true,ownership:false,absent:!record,rowCount:rows.length,qaRecords:rows.filter(r=>String(r.plan_name).startsWith('pw-qa-admin-')).map(r=>({id:r.id,name:r.plan_name}))};
const deleted=await api('/api/v1/subscriber-plans/delete',{id:79});
const after=await api('/api/v1/subscriber-plans');
const remaining=Array.isArray(after.body.data)?after.body.data:after.body.data?.items||[];
return {ownership:true,id:79,name:record.plan_name,deleted,absent:!remaining.some(r=>Number(r.id)===79),oldRecordsPreserved:rows.filter(r=>Number(r.id)!==79).every(r=>remaining.some(s=>s.id===r.id))};
});
console.log(JSON.stringify(evidence));
await fs.writeFile(new URL('./cleanup-followup.json',import.meta.url),JSON.stringify(evidence,null,2));
}finally{await browser.close()}
