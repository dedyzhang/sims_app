const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const code = fs.readFileSync('public/js/sims-net.js','utf8');
function boot(reduceMotion = false, preferenceStore = new Map()) {
    const events = {}, clones = [], attrs = {}, props = new Map();
    const copy = {classList:{add(){}},removeAttribute(){},setAttribute(k,v){attrs[k]=v;}};
    const source = {scrollWidth:1600,cloneNode:()=>copy,parentElement:{appendChild:n=>clones.push(n)}};
    const button = {hidden:true,addEventListener:(k,fn)=>events.click=fn,setAttribute:(k,v)=>attrs[k]=v};
    const viewport = {clientWidth:900};
    const root = {dataset:{},style:{getPropertyValue:k=>props.get(k),setProperty:(k,v)=>props.set(k,v)},querySelector:s=>s.includes('stats')?viewport:s.includes('original')?source:button};
    const reduced = {matches:reduceMotion,addEventListener:(k,fn)=>events.motion=fn};
    const document = {hidden:false,querySelector:()=>root,addEventListener:(k,fn)=>events.visibility=fn};
    const context = vm.createContext({document,sessionStorage:{getItem:k=>preferenceStore.get(k),setItem:(k,v)=>preferenceStore.set(k,v),removeItem:k=>preferenceStore.delete(k)},window:{matchMedia:()=>reduced},ResizeObserver:class {constructor(fn){events.resize=fn;}observe(){}},setInterval(){throw Error('Animation must not register timers');},fetch(){throw Error('Animation must not fetch data');}});
    vm.runInContext(code,context);
    return {root,props,clones,attrs,events,document,reduced,context,button};
}
test('ticker uses one visual copy with no network calls or frame timer; repeated setup is ignored',()=>{
    const state=boot(); assert.equal(state.clones.length,1); assert.equal(state.attrs['aria-hidden'],'true');
    assert.equal(state.props.get('--sims-net-duration'),'50s'); assert.equal(state.root.dataset.moving,'true');
    vm.runInContext(code,state.context); assert.equal(state.clones.length,1);
});
test('manual pause, hidden tab and reduced motion are independent states',()=>{
    const state=boot(); state.events.click(); assert.equal(state.root.dataset.paused,'true'); assert.equal(state.attrs['aria-pressed'],'true');
    state.document.hidden=true; state.events.visibility(); assert.equal(state.root.dataset.hidden,'true');
    state.document.hidden=false; state.events.visibility(); assert.equal(state.root.dataset.paused,'true');
    state.reduced.matches=true; state.events.motion({matches:true}); assert.equal(state.root.dataset.reduced,'true');
    state.events.click(); assert.equal(state.root.dataset.paused,'false'); assert.equal(state.button.textContent,'Jeda');
});

test('reduced motion offers an explicit start control and remembers consent within the tab',()=>{
    const store = new Map(); const state=boot(true, store);
    assert.equal(state.button.hidden,false); assert.equal(state.button.textContent,'Gerakkan');
    state.events.click(); assert.equal(state.root.dataset.reduced,'false'); assert.equal(state.root.dataset.forceMotion,'true');
    const reload=boot(true,store); assert.equal(reload.root.dataset.reduced,'false');
    reload.events.click(); assert.equal(reload.root.dataset.paused,'true');
    reload.events.click(); assert.equal(reload.root.dataset.paused,'false'); assert.equal(reload.button.textContent,'Jeda');
    reload.events.motion({matches:true}); assert.equal(reload.root.dataset.reduced,'true'); assert.equal(reload.button.textContent,'Gerakkan');
});
