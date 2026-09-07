export default async function(h) {
 const {api,allow,check,result,prefix,page,save}=h;
 const rows=r=>Array.isArray(r.body?.data)?r.body.data:r.body?.data?.items||[];
 const ok=r=>r.status<400 && r.body?.success!==false;
 for(const type of ['plans','downtime']) {
  const isPlan=type==='plans', endpoint=isPlan?'/api/v1/subscriber-plans':'/api/v1/scheduled-downtime';
  const field=isPlan?'plan_name':'title',name=`${prefix}-${type}`;
  const payload=isPlan?{plan_name:name,price:1,speed_mbps:1,plan_type:'POSTPAID',is_active:0,description:'Disposable QA record'}:{title:name,message:'Disabled QA fixture',starts_at:'2030-01-01 01:00:00',ends_at:'2030-01-01 02:00:00',enabled:false};
  const create=isPlan?endpoint+'/store':endpoint;allow(create);
  let id=null;
  const before=rows(await api(endpoint));
  if(before.some(r=>r[field]===name))throw Error('QA name already exists');
  try {
   const invalid=await api(create,{...payload,[field]:''});
   check('CRUD validation',`${type}: missing required name`,!ok(invalid)?'PASS':'FAIL','Reject empty name',`HTTP ${invalid.status}`);
   const created=await api(create,payload);
   const found=rows(await api(endpoint)).find(r=>r[field]===name);id=found?.id;
   check('CRUD',`${type}: add and API read`,ok(created)&&id?'PASS':'FAIL','Create isolated disabled record and retrieve it',`HTTP ${created.status}; created ID ${id??'absent'}`);
   await save(); if(!id)continue;
   await page.goto(isPlan?'/subscriber-plans':'/scheduled-downtime');await h.settle();
   check('CRUD UI',`${type}: new record appears on page`,(await page.locator('body').innerText()).includes(name)?'PASS':'FAIL','New fixture visible in module',`QA ID ${id}`);
   const update=isPlan?`${endpoint}/update/${id}`:`${endpoint}/${id}`;allow(update);
   const changed={...payload,[field]:name+'-edited'};
   const updated=await api(update,changed);
   const read=rows(await api(endpoint)).find(r=>String(r.id)===String(id));
   check('CRUD',`${type}: edit/update persistence`,ok(updated)&&read?.[field]===changed[field]?'PASS':'FAIL','Updated name returned on a fresh read',`HTTP ${updated.status}; ID ${id}; match ${read?.[field]===changed[field]}`);
   await page.reload();await h.settle();
   check('CRUD UI',`${type}: update persists after reload`,(await page.locator('body').innerText()).includes(changed[field])?'PASS':'FAIL','Updated fixture visible after reload',`QA ID ${id}`);
  } finally {
   if(id){
    const live=rows(await api(endpoint)).find(r=>String(r.id)===String(id));
    if(!live || !live[field].startsWith(name))throw Error('Cleanup ownership check failed');
    const del=isPlan?endpoint+'/delete':`${endpoint}/${id}/delete`;allow(del);
    const deleted=await api(del,{id});const remaining=rows(await api(endpoint));
    const clean=ok(deleted)&&!remaining.some(r=>String(r.id)===String(id));
    check('CRUD cleanup',`${type}: delete QA-created record`,clean?'PASS':'FAIL','Delete only fixture and verify absence',`HTTP ${deleted.status}; ID ${id}; absent ${clean}`);
    result.cleanup.push({module:type,id,name,status:clean?'PASS':'FAIL',oldRecordsDeleted:0});
    check('CRUD cleanup',`${type}: prior records preserved`,before.every(old=>remaining.some(r=>String(r.id)===String(old.id)))?'PASS':'FAIL','Every pre-existing list ID remains','Compared before/after IDs without changing old records');
   }
   await save();
  }
 }
 result.limitations.push('CRUD executed through browser fetch plus UI read/reload assertions for disabled Plans and Scheduled Downtime fixtures. Other module CRUD requires dependency-specific disposable fixtures; not certified by these checks. Plan workflow creates/removes only its own RADIUS profile through application service.');
}
