const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('resources/views/layouts/app.blade.php','utf8');
const methods = source.slice(source.indexOf('            readNavRoutes(key, legacy){'),source.indexOf('            initNavPersonalization(){'));
function boot(){
 const data=new Map(); const messages=[];
 const state=vm.runInNewContext('({' + methods + '})',{localStorage:{getItem:k=>data.get(k),setItem:(k,v)=>data.set(k,v)},window:{showToast:msg=>messages.push(msg)}});
 Object.assign(state,{navRegistry:Object.fromEntries(Array.from({length:14},(_,i)=>['menu'+i,{route:'menu'+i,label:'Menu '+i,url:'/menu/'+i,patterns:['menu'+i,'menu'+i+'.*']}])),navPinnedRoutes:['menu0'],dockFavoriteRoutes:[],navDockRecent:[],currentRoute:'menu0',$nextTick:fn=>fn()});
 return {state,data,messages};
}
test('history displays at most ten unique menus with newest first',()=>{
 const {state}=boot();
 for(let i=0;i<14;i++)state.pushNavRecent('menu'+i);
 assert.equal(state.dockItems().length,10);assert.equal(state.dockItems()[0].route,'menu13');
 state.pushNavRecent('menu7');assert.equal(state.dockItems()[0].route,'menu7');
 assert.equal(new Set(state.dockItems().map(i=>i.route)).size,10);
});
test('favourites stay visible before history and can be removed',()=>{
 const {state}=boot();state.pushNavRecent('menu0');state.toggleNavFavorite('menu0');
 for(let i=1;i<14;i++)state.pushNavRecent('menu'+i);
 assert.equal(state.dockItems().length,10);assert.equal(state.dockItems()[0].route,'menu0');
 state.toggleNavFavorite('menu0');assert.equal(state.dockItems()[0].route,'menu13');
});
test('invalid or inaccessible routes are ignored and favourites are capped',()=>{
 const {state,data,messages}=boot();data.set('sims_nav_recent','{"invalid":true}');state.loadNavRecent();assert.equal(state.dockItems().length,0);
 data.set('sims_nav_recent','["unknown","constructor","menu1","menu1"]');state.loadNavRecent();assert.equal(state.dockItems().length,1);
 state.toggleNavFavorite('unknown');assert.equal(state.dockFavoriteRoutes.length,0);
 for(let i=0;i<11;i++)state.toggleNavFavorite('menu'+i);
 assert.equal(state.dockFavoriteRoutes.length,10);assert.equal(messages.length,1);
 state.currentRoute='menu2.edit';assert.equal(state.isNavActive(state.navRegistry.menu2),true);
});
