import fs from 'node:fs/promises';
import {chromium} from 'playwright';
const files=(await fs.readdir('/tmp')).filter(f=>/^nexus-final-\d+\.png$/.test(f)).sort();
const browser=await chromium.launch({headless:true});
try{const page=await browser.newPage({viewport:{width:1400,height:1600}});
for(let i=0;i<files.length;i+=6){
const items=await Promise.all(files.slice(i,i+6).map(async f=>`<div><label>${f}</label><img src="data:image/png;base64,${(await fs.readFile('/tmp/'+f)).toString('base64')}"></div>`));
await page.setContent(`<style>body{margin:0;display:grid;grid-template-columns:1fr 1fr;gap:8px;background:#bbb}img{width:100%}label{display:block}</style>${items.join('')}`);
await page.screenshot({path:`/tmp/qa-sheet-${i/6}.png`,fullPage:true});
}console.log({pages:files.length,sheets:Math.ceil(files.length/6)});
}finally{await browser.close()}
