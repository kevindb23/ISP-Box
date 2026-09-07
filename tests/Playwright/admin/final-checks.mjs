export default async function(h){
 await h.page.goto('/dashboard');await h.settle();await h.page.setViewportSize({width:390,height:844});
 const bell=h.page.locator('#globalNotificationsToggle');const box=await bell.boundingBox();
 h.check('responsive','Admin mobile notification bell reachable',box&&box.x>=0&&box.x+box.width<=390?'PASS':'FAIL','Bell within viewport',box);
 h.allow('/logout');await h.page.setViewportSize({width:1440,height:1000});
 await h.page.locator('.logout-top').click();await h.page.waitForURL('**/login');
 h.check('session','Admin logout','PASS','Return to login',new URL(h.page.url()).pathname);
}
