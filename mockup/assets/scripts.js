/* ================= helpers ================= */
const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
const day=864e5, now0=Date.now(), d=n=>new Date(now0-n*day);
const esc=s=>String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
const SYMS={USD:'$',EUR:'\u20AC',GBP:'\u00A3',INR:'\u20B9',AED:'AED '};
function money(n){return state.settings.symbol+(+n).toLocaleString('en-US',{maximumFractionDigits:0})}
function money2(n){return state.settings.symbol+(+n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}
function fmtDate(v){return new Date(v).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}
function initials(n){return n.split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase()}
function hexA(hex,a){const h=hex.replace('#','');return `rgba(${parseInt(h.slice(0,2),16)},${parseInt(h.slice(2,4),16)},${parseInt(h.slice(4,6),16)},${a})`}
function byId(id){return state.phones.find(p=>p.id===id)}

/* ================= data ================= */
const BRANDS=['Apple','Samsung','Google','Xiaomi','OnePlus','Nothing','Motorola'];
const BRAND_COLORS={Apple:'#6366f1',Samsung:'#0ea5e9',Google:'#10b981',Xiaomi:'#f59e0b',OnePlus:'#ef4444',Nothing:'#8b5cf6',Motorola:'#14b8a6'};
function imeiOf(i){const n=('35692811'+String(456210+i*973).padStart(7,'0')).slice(0,14);return n.replace(/(\d{2})(\d{4})(\d{4})(\d{4})/,'$1 $2 $3 $4')}

let settings={shop:'Nova Mobiles',phone:'+1 (555) 010-2233',email:'hello@novamobiles.com',addr:'128 Market Street, Springfield',currency:'USD',symbol:'$',tax:8,low:3};
try{const s=JSON.parse(localStorage.getItem('nc-settings'));if(s)settings={...settings,...s}}catch(e){}

const state={settings,view:null,
  phones:[['iPhone 15 Pro Max','Apple','256GB',1199,1020,6,9],['iPhone 15','Apple','128GB',799,690,11,14],
  ['iPhone 13','Apple','128GB',599,520,2,6],['Galaxy S24 Ultra','Samsung','512GB',1299,1100,4,7],
  ['Galaxy S24','Samsung','256GB',859,730,9,8],['Galaxy A55','Samsung','128GB',449,380,15,12],
  ['Galaxy Z Flip 6','Samsung','256GB',1099,950,0,3],['Pixel 8 Pro','Google','256GB',999,860,5,5],
  ['Pixel 8','Google','128GB',699,600,8,9],['Xiaomi 14','Xiaomi','512GB',899,770,3,4],
  ['Redmi Note 13 Pro','Xiaomi','256GB',329,270,18,16],['OnePlus 12','OnePlus','256GB',799,680,6,5],
  ['Nothing Phone (2a)','Nothing','256GB',399,330,10,7],['Moto Edge 50 Pro','Motorola','256GB',599,500,1,2]]
  .map((a,i)=>({id:i+1,name:a[0],brand:a[1],storage:a[2],price:a[3],cost:a[4],qty:a[5],sold:a[6],imei:imeiOf(i+1)})),
  invoices:[],audits:[],auditDays:30,
  inv:{q:'',status:'all',brand:'all'},invF:{q:'',status:'all'},ni:{},seq:1043};

const CUSTOMERS=[['Amelia Carter','+1 555-0142'],['Liam Nguyen','+1 555-0198'],['Sofia Ricci','+1 555-0176'],['Noah Bennett','+1 555-0129'],['Maya Patel','+1 555-0163'],['Ethan Walsh','+1 555-0111'],['Ava Brooks','+1 555-0187'],['Omar Haddad','+1 555-0134'],['Chloe Fontaine','+1 555-0159'],['Daniel Kim','+1 555-0170'],['Grace Okafor','+1 555-0125'],['Hugo Lindberg','+1 555-0148']];
function computeTotals(inv){inv.sub=inv.items.reduce((s,it)=>s+it.price*it.q,0);inv.tax=+(inv.sub*state.settings.tax/100).toFixed(2);inv.total=+(inv.sub+inv.tax).toFixed(2)}
[[1,0,1,1,'Paid','Card'],[2,1,5,1,'Paid','Card'],[2,1,11,1,'Paid','Cash'],[4,2,2,2,'Paid','Card'],
 [6,3,4,1,'Pending','Card'],[9,4,9,1,'Paid','Cash'],[9,4,13,1,'Paid','Cash'],[12,5,10,1,'Paid','Card'],
 [15,6,6,2,'Paid','Card'],[19,7,8,1,'Overdue','Card'],[23,8,3,1,'Paid','Card'],[23,8,14,1,'Paid','Card'],
 [28,9,12,1,'Paid','Cash'],[34,10,2,1,'Paid','Card'],[34,10,9,1,'Paid','Card'],[41,11,7,1,'Paid','Card']]
.reduce((acc,k)=>{ // group by (day,customer)
  const key=k[0]+'-'+k[1];(acc[key]=acc[key]||{d:k[0],c:k[1],items:[],status:k[4],pay:k[5]}).items.push([k[2],k[3]]);return acc},{});
const INV_DEFS=[[1,0,[[1,1]],'Paid','Card'],[2,1,[[5,1],[11,1]],'Paid','Card'],[4,2,[[2,2]],'Paid','Card'],
 [6,3,[[4,1]],'Pending','Card'],[9,4,[[9,1],[13,1]],'Paid','Cash'],[12,5,[[10,1]],'Paid','Card'],
 [15,6,[[6,2]],'Paid','Card'],[19,7,[[8,1]],'Overdue','Card'],[23,8,[[3,1],[14,1]],'Paid','Card'],
 [28,9,[[12,1]],'Paid','Cash'],[34,10,[[2,1],[9,1]],'Paid','Card'],[41,11,[[7,1]],'Paid','Card']];
state.invoices=INV_DEFS.map((k,i)=>{const inv={id:'INV-'+(1031+i),customer:CUSTOMERS[k[1]][0],cphone:CUSTOMERS[k[1]][1],date:d(k[0]).toISOString(),status:k[3],pay:k[4],items:k[2].map(([pid,q])=>({pid,q,price:byId(pid).price}))};computeTotals(inv);return inv});

function seedAudRows(shift){return state.phones.map((p,i)=>{const exp=p.qty;let cnt=exp;if(shift&&i===4)cnt=Math.max(0,exp-1);return{name:p.name,exp,cnt}})}
state.audits=[
 {id:'AUD-118',date:d(32).toISOString(),period:'Last 30 days',items:14,matched:14,vars:0,acc:100,rows:seedAudRows(false)},
 {id:'AUD-117',date:d(64).toISOString(),period:'Last 30 days',items:14,matched:13,vars:1,acc:92.9,rows:seedAudRows(true)}
];

/* ================= shell / routing ================= */
const VIEWS={dashboard:renderDashboard,inventory:renderInventory,invoices:renderInvoices,audit:renderAudit,settings:renderSettings};
function go(v){state.view=v;$$('.view').forEach(s=>s.classList.toggle('active',s.id==='view-'+v));
  $$('[data-nav]').forEach(b=>b.classList.toggle('active',b.dataset.nav===v));
  window.scrollTo({top:0,behavior:'instant'in window?'instant':'auto'});
  closeNotif();VIEWS[v]();}
function rerender(){if(state.view&&VIEWS[state.view])VIEWS[state.view]();refreshNav()}
function refreshNav(){
  $('#cntStock').textContent=state.phones.reduce((s,p)=>s+p.qty,0)+' u';
  $('#cntInv').textContent=state.invoices.length;
  const alerts=state.phones.filter(p=>p.qty<=state.settings.low).length;
  $('#bellDot').style.display=alerts?'block':'none';
}

/* ================= dashboard ================= */
const charts={};
function countUp(el,val,{prefix='',suffix='',dur=900}={}){const t0=performance.now();
  (function step(t){const k=Math.min(1,(t-t0)/dur),e=1-Math.pow(1-k,3);
    el.textContent=prefix+Math.round(val*e).toLocaleString()+suffix;if(k<1)requestAnimationFrame(step)})(t0)}
function statCard(icon,color,label,valHtml,delta,cls){
  return `<div class="card stat"><div class="top"><div class="tile" style="background:${color}22;color:${color}"><svg class="ic" style="width:20px;height:20px"><use href="#i-${icon}"/></svg></div><span class="delta ${cls}">${delta}</span></div><div>${valHtml}<div class="lbl">${label}</div></div></div>`}
function renderDashboard(){
  const h=new Date().getHours();
  $('#greetWord').textContent=h<12?'Good morning':h<18?'Good afternoon':'Good evening';
  $('#greetDate').textContent=new Date().toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'});
  const units=state.phones.reduce((s,p)=>s+p.qty,0);
  const low=state.phones.filter(p=>p.qty<=state.settings.low).length;
  const sold30=state.phones.reduce((s,p)=>s+p.sold,0);
  const rev30=state.invoices.filter(i=>Date.now()-new Date(i.date)<30*day).reduce((s,i)=>s+i.total,0);
  $('#statCards').innerHTML=
    statCard('card','#0ea5e9','Revenue · last 30 days',`<b class="v" data-v="${Math.round(rev30)}" data-pre="${state.settings.symbol}">$0</b>`,'+12.4%','up')+
    statCard('phone','#6153f4','Units in stock · '+state.phones.length+' models',`<b class="v" data-v="${units}">0</b>`,'live','mut')+
    statCard('cart','#10b981','Phones sold · 30 days',`<b class="v" data-v="${sold30}">0</b>`,'+23.1%','up')+
    statCard('alert','#f59e0b','Stock alerts',`<b class="v" data-v="${low}">0</b>`,'needs action','warn');
  $$('#statCards .v').forEach(el=>countUp(el,+el.dataset.v,{prefix:el.dataset.pre||''}));

  buildCharts();
  // top sellers
  const top=[...state.phones].sort((a,b)=>b.sold-a.sold).slice(0,5),max=top[0].sold;
  $('#topSellers').innerHTML=top.map(p=>`<div class="bar-row"><div class="bar-top"><span style="color:var(--text)">${p.name}</span><span>${p.sold} sold</span></div><div class="bar-track"><div class="bar-fill" data-w="${Math.round(p.sold/max*100)}"></div></div></div>`).join('');
  requestAnimationFrame(()=>requestAnimationFrame(()=>$$('#topSellers .bar-fill').forEach(el=>el.style.width=el.dataset.w+'%')));
  // recent invoices
  $('#recentInvoices').innerHTML=[...state.invoices].sort((a,b)=>new Date(b.date)-new Date(a.date)).slice(0,5).map((v,i)=>{
    const c=Object.values(BRAND_COLORS)[i%7];
    return `<div class="row-item" onclick="openInvoice('${v.id}')"><div class="row-ic" style="background:${c}22;color:${c}">${initials(v.customer)}</div><div class="ri-t"><b>${v.id} · ${v.customer}</b><small>${v.items.reduce((s,x)=>s+x.q,0)} units · ${fmtDate(v.date)}</small></div><div class="ri-r">${money2(v.total)}<small>${v.status}</small></div></div>`}).join('');
  // low stock
  const lows=state.phones.filter(p=>p.qty<=state.settings.low).sort((a,b)=>a.qty-b.qty);
  $('#lowCnt').textContent=lows.length+' items';
  $('#lowStockList').innerHTML=lows.map(p=>{const c=BRAND_COLORS[p.brand];
    return `<div class="row-item" style="cursor:default"><div class="row-ic" style="background:${c}22;color:${c}">${p.brand[0]}</div><div class="ri-t"><b>${p.name}</b><small>${p.qty===0?'Out of stock':p.qty+' units left'}</small></div><button class="btn btn-soft" style="padding:7px 12px;font-size:12px" onclick="restock(${p.id})">+5</button></div>`}).join('')||'<div class="empty">All stocked up 🎉</div>';
}
function buildCharts(){
  Object.values(charts).forEach(c=>c&&c.destroy());charts.rev=charts.brand=null;
  if(!window.Chart){$$('.chart-wrap').forEach(w=>w.innerHTML='<div class="no-chart">Chart library unavailable</div>');return}
  const css=getComputedStyle(document.documentElement);
  const muted=css.getPropertyValue('--muted').trim(),border=css.getPropertyValue('--border').trim();
  const prim=css.getPropertyValue('--primary').trim(),surface=css.getPropertyValue('--surface').trim();
  Chart.defaults.font.family="'Plus Jakarta Sans',sans-serif";Chart.defaults.color=muted;
  const labels=[],vals=[1650,1420,2100,1880,2450,2950,3120,2240,1780,2050,2380,2760,3240,3510];
  for(let i=13;i>=0;i--)labels.push(new Date(Date.now()-i*day).toLocaleDateString('en-US',{month:'short',day:'numeric'}));
  $('#revTotal').textContent=money(vals.reduce((a,b)=>a+b,0))+' · 14d';
  const ctx=$('#revenueChart'),g=ctx.getContext('2d').createLinearGradient(0,0,0,270);
  g.addColorStop(0,hexA(prim,.30));g.addColorStop(1,hexA(prim,0));
  charts.rev=new Chart(ctx,{type:'line',data:{labels,datasets:[{data:vals,tension:.42,borderColor:prim,borderWidth:3,fill:true,backgroundColor:g,pointRadius:0,pointHoverRadius:5,pointHoverBackgroundColor:prim,pointHoverBorderColor:'#fff',pointHoverBorderWidth:2}]},
    options:{responsive:true,maintainAspectRatio:false,interaction:{intersect:false,mode:'index'},
      plugins:{legend:{display:false},tooltip:{backgroundColor:'#171a2c',padding:10,cornerRadius:10,displayColors:false, callbacks:{label:c=>' '+money(c.parsed.y)}}},
      scales:{x:{grid:{display:false},border:{display:false},ticks:{maxTicksLimit:7,font:{size:11}}},
              y:{border:{display:false},grid:{color:border},ticks:{maxTicksLimit:5,font:{size:11},callback:v=>state.settings.symbol+v}}}}});
  const by={};state.phones.forEach(p=>by[p.brand]=(by[p.brand]||0)+p.qty);
  const ent=Object.entries(by).sort((a,b)=>b[1]-a[1]);
  if(ent.length>6){const rest=ent.slice(5).reduce((s,e)=>s+e[1],0);ent.length=5;ent.push(['Other',rest])}
  const pal=['#6153f4','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#14b8a6'];
  $('#brandUnits').textContent=units_total()+' units';
  function units_total(){return state.phones.reduce((s,p)=>s+p.qty,0)}
  charts.brand=new Chart($('#brandChart'),{type:'doughnut',
    data:{labels:ent.map(e=>e[0]),datasets:[{data:ent.map(e=>e[1]),backgroundColor:pal.slice(0,ent.length),borderWidth:3,borderColor:surface,hoverOffset:8}]},
    options:{cutout:'70%',maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,pointStyle:'circle',padding:13,font:{size:11.5,weight:600}}},tooltip:{backgroundColor:'#171a2c',padding:10,cornerRadius:10, callbacks:{label:c=>' '+c.parsed+' units'}}}}});
}

/* ================= inventory ================= */
function statusOf(q){return q===0?['Out of stock','b-red']:q<=state.settings.low?['Low stock','b-amber']:['In stock','b-green']}
function tile(p){const c=BRAND_COLORS[p.brand]||'#6153f4';return `<div class="row-ic" style="background:${c}22;color:${c}">${p.brand[0]}</div>`}
function renderInventory(){
  const f=state.inv,list=state.phones.filter(p=>{
    const q=f.q.toLowerCase();
    const okQ=!q||[p.name,p.brand,p.imei,p.storage].join(' ').toLowerCase().includes(q);
    const st=statusOf(p.qty)[0];
    const okS=f.status==='all'||{in:'In stock',low:'Low stock',out:'Out of stock'}[f.status]===st;
    const okB=f.brand==='all'||p.brand===f.brand;
    return okQ&&okS&&okB}).sort((a,b)=>a.name.localeCompare(b.name));
  $('#invCount').textContent=`Showing ${list.length} of ${state.phones.length} models · ${state.phones.reduce((s,p)=>s+p.qty,0)} units in store`;
  $('#invBody').innerHTML=list.map(p=>{const[st,cls]=statusOf(p.qty);
    return `<tr><td class="no-label"><div class="p-cell">${tile(p)}<div><b>${esc(p.name)}</b><small>${p.brand} · ${p.storage}</small></div></div></td>
    <td data-l="IMEI"><span class="mono">${p.imei}</span></td>
    <td data-l="Price"><b>${money(p.price)}</b></td>
    <td data-l="In stock"><span class="stp"><button onclick="adjustQty(${p.id},-1)" aria-label="minus">−</button><b>${p.qty}</b><button onclick="adjustQty(${p.id},1)" aria-label="plus">+</button></span></td>
    <td data-l="Sold · 30d">${p.sold}</td>
    <td data-l="Status"><span class="badge ${cls}">${st}</span></td></tr>`}).join('')||`<tr><td colspan="6" class="empty">No phones match your filters</td></tr>`;
}
function adjustQty(id,dl){const p=byId(id);p.qty=Math.max(0,p.qty+dl);renderInventory();refreshNav()}
function restock(id){const p=byId(id);p.qty+=5;toast(`${p.name} restocked +5 units`);rerender()}

/* ================= invoices ================= */
function renderInvoices(){
  const f=state.invF,list=[...state.invoices].sort((a,b)=>new Date(b.date)-new Date(a.date)).filter(v=>{
    const q=f.q.toLowerCase();
    const okQ=!q||(v.id+' '+v.customer).toLowerCase().includes(q);
    const okS=f.status==='all'||v.status.toLowerCase()===f.status;
    return okQ&&okS});
  $('#invoiceBody').innerHTML=list.map(v=>{const cls={Paid:'b-green',Pending:'b-amber',Overdue:'b-red'}[v.status];
    return `<tr class="rowlink" onclick="openInvoice('${v.id}')">
    <td class="no-label"><b>${v.id}</b></td>
    <td data-l="Customer"><div class="p-cell" style="min-width:0"><div class="row-ic" style="width:32px;height:32px;font-size:11px;background:#6153f422;color:#6153f4">${initials(v.customer)}</div><div style="min-width:0"><b style="font-size:13px">${esc(v.customer)}</b></div></div></td>
    <td data-l="Date"><span class="mono">${fmtDate(v.date)}</span></td>
    <td data-l="Units">${v.items.reduce((s,x)=>s+x.q,0)}</td>
    <td data-l="Total"><b>${money2(v.total)}</b></td>
    <td data-l="Status"><span class="badge ${cls}">${v.status}</span></td>
    <td data-l=""><button class="icon-btn sm" onclick="event.stopPropagation();printInvoice('${v.id}')" aria-label="Print"><svg class="ic" style="width:15px;height:15px"><use href="#i-printer"/></svg></button></td></tr>`}).join('')
    ||`<tr><td colspan="7" class="empty">No invoices found</td></tr>`;
}
function openInvoice(id){
  const inv=state.invoices.find(v=>v.id===id);if(!inv)return;
  $('#invViewModal').innerHTML=`
    <div class="modal-h"><b>Invoice ${inv.id}</b><button class="icon-btn" onclick="closeOverlay('#ovInvoice')" aria-label="Close"><svg class="ic"><use href="#i-x"/></svg></button></div>
    <div class="modal-b"><div class="paper">${invoiceInner(inv)}</div></div>
    <div class="modal-f">
      <button class="btn btn-outline" onclick="closeOverlay('#ovInvoice')">Close</button>
      <button class="btn btn-primary" onclick="printInvoice('${inv.id}')"><svg class="ic"><use href="#i-printer"/></svg>Print / PDF</button>
    </div>`;
  openOverlay('#ovInvoice');
}
function invoiceInner(inv){
  const s=state.settings,stc={Paid:['#16a34a','#e8f7ee'],Pending:['#d97706','#fdf1df'],Overdue:['#e5484d','#fdeaea']}[inv.status]||['#697089','#f1f2f6'];
  return `<div class="pv-head">
    <div class="pv-shop"><div style="display:flex;gap:10px;align-items:center;margin-bottom:8px"><div class="logo-tile" style="width:34px;height:34px;border-radius:10px"><svg class="ic" style="width:17px;height:17px"><use href="#i-phone"/></svg></div><b>${esc(s.shop)}</b></div><span>${esc(s.addr)}</span><span>${esc(s.phone)} · ${esc(s.email)}</span></div>
    <div class="pv-meta"><div class="inword">INVOICE</div><b style="font-size:14px">${inv.id}</b><div style="color:#9aa0b5;font-size:12px;margin-top:2px">${fmtDate(inv.date)}</div><br><span class="pv-stamp" style="color:${stc[0]};background:${stc[1]}">${inv.status}</span></div>
  </div>
  <div class="pv-grid">
    <div><div class="pv-lbl">Billed to</div><b>${esc(inv.customer)}</b><div style="color:#697089;font-size:12.5px">${esc(inv.cphone||'—')}</div></div>
    <div style="text-align:right"><div class="pv-lbl">Payment</div><b>${inv.pay}</b><div style="color:#697089;font-size:12.5px">Due on receipt</div></div>
  </div>
  <table class="pv-items"><thead><tr><th>Item</th><th style="text-align:center">Qty</th><th class="r">Price</th><th class="r">Amount</th></tr></thead><tbody>
  ${inv.items.map(it=>{const p=byId(it.pid)||{name:'Item',brand:'',storage:'',imei:''};
    return `<tr><td><b>${esc(p.name)}</b><div style="color:#697089;font-size:11.5px">${p.brand} · ${p.storage} · IMEI ${p.imei}</div></td><td style="text-align:center">${it.q}</td><td class="r">${money2(it.price)}</td><td class="r"><b>${money2(it.price*it.q)}</b></td></tr>`}).join('')}
  </tbody></table>
  <div class="pv-tot">
    <div class="trow"><span>Subtotal</span><b>${money2(inv.sub)}</b></div>
    <div class="trow"><span>Tax (${state.settings.tax}%)</span><b>${money2(inv.tax)}</b></div>
    <div class="trow grand"><span>Total</span><span>${money2(inv.total)}</span></div>
  </div>
  <div class="pv-foot">
    <div><div class="pv-lbl">Notes</div><div style="color:#697089;font-size:12px">Thank you for shopping at ${esc(s.shop)}!<br>12-month manufacturer warranty included.</div></div>
    <div style="text-align:right"><div class="barcode"></div><div style="font-size:10.5px;letter-spacing:.2em;color:#9aa0b5;margin-top:5px">${inv.id}</div></div>
  </div>`;
}

/* ================= new invoice ================= */
function openNewInvoice(){state.ni={};$('#niName').value='';$('#niPhone').value='';renderNI();openOverlay('#ovNew')}
function renderNI(){
  $('#niList').innerHTML=state.phones.map(p=>`<div class="ni-row">${tile(p)}
    <div class="ni-info"><b>${esc(p.name)}</b><small>${p.brand} · ${money(p.price)} · ${p.qty} in stock</small></div>
    <span class="stp"><button onclick="niStep(${p.id},-1)">−</button><b>${state.ni[p.id]||0}</b><button onclick="niStep(${p.id},1)">+</button></span></div>`).join('');
  const sub=Object.entries(state.ni).reduce((s,[id,q])=>s+byId(+id).price*q,0);
  const tax=sub*state.settings.tax/100;
  $('#niSub').textContent=money2(sub);$('#niTax').textContent=money2(tax);$('#niTot').textContent=money2(sub+tax);
}
function niStep(id,dl){const p=byId(id),cur=state.ni[id]||0;state.ni[id]=Math.min(p.qty,Math.max(0,cur+dl));renderNI()}
function createInvoice(){
  const items=Object.entries(state.ni).filter(([,q])=>q>0).map(([id,q])=>({pid:+id,q,price:byId(+id).price}));
  const name=$('#niName').value.trim();
  if(!items.length||!name){toast('Add a customer and at least one item','warn');return}
  items.forEach(it=>{const p=byId(it.pid);p.qty-=it.q;p.sold+=it.q});
  const inv={id:'INV-'+(state.seq++),customer:name,cphone:$('#niPhone').value.trim(),date:new Date().toISOString(),status:'Paid',pay:'Card',items};
  computeTotals(inv);state.invoices.unshift(inv);
  closeOverlay('#ovNew');toast(inv.id+' created');rerender();openInvoice(inv.id);
}
function openAddPhone(){
  $('#apName').value='';$('#apStorage').value='';$('#apPrice').value='';$('#apQty').value='';$('#apImei').value='';
  openOverlay('#ovPhone');
}
function savePhone(){
  const name=$('#apName').value.trim(),price=+$('#apPrice').value,qty=Math.max(0,+$('#apQty').value||0);
  if(!name||!price){toast('Model name and price are required','warn');return}
  const id=Math.max(0,...state.phones.map(p=>p.id))+1;
  state.phones.push({id,name,brand:$('#apBrand').value,storage:$('#apStorage').value||'—',price,cost:Math.round(price*.85),qty,sold:0,imei:$('#apImei').value.trim()||imeiOf(100+id)});
  closeOverlay('#ovPhone');toast(name+' added to inventory');rerender();
}

/* ================= audit ================= */
function periodLabel(){
  if(state.auditDays==='custom'){const f=$('#cFrom').value,t=$('#cTo').value;return f&&t?fmtDate(f)+' — '+fmtDate(t):'Custom range'}
  return {30:'Last 30 days',60:'Last 2 months',90:'Last 3 months'}[state.auditDays];
}
function periodInvoices(){
  if(state.auditDays==='custom'){
    const f=$('#cFrom').value?new Date($('#cFrom').value+'T00:00:00'):new Date(0);
    const t=$('#cTo').value?new Date($('#cTo').value+'T23:59:59'):new Date();
    return state.invoices.filter(i=>{const dd=new Date(i.date);return dd>=f&&dd<=t});
  }
  const cut=Date.now()-state.auditDays*day;
  return state.invoices.filter(i=>new Date(i.date).getTime()>=cut);
}
function renderAudit(){renderPeriod();renderLastAudit();renderAudHist();
  if(!$('#cFrom').value){$('#cFrom').value=new Date(Date.now()-30*day).toISOString().slice(0,10);$('#cTo').value=new Date().toISOString().slice(0,10)}}
function renderPeriod(){
  const invs=periodInvoices();
  const units=invs.reduce((s,i)=>s+i.items.reduce((a,b)=>a+b.q,0),0);
  const rev=invs.reduce((s,i)=>s+i.total,0);
  $('#periodStats').innerHTML=`
    <div class="res-box"><b>${invs.length}</b><span>Invoices</span></div>
    <div class="res-box"><b>${units}</b><span>Units sold</span></div>
    <div class="res-box"><b style="font-size:15px">${money(rev)}</b><span>Revenue</span></div>`;
  $('#startLbl').textContent=`Start stocktake · ${state.phones.length} items`;
}
function renderLastAudit(){
  const a=state.audits[0];
  if(!a){$('#lastAuditCard').innerHTML='<div class="empty">No audits yet — run your first stocktake</div>';return}
  const C=2*Math.PI*30;
  $('#lastAuditCard').innerHTML=`<div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
    <div class="ring-wrap sm"><svg width="82" height="82"><circle cx="41" cy="41" r="30" fill="none" stroke="var(--surface-2)" stroke-width="7"/><circle class="lastRing" cx="41" cy="41" r="30" fill="none" stroke="var(--primary)" stroke-width="7" stroke-linecap="round" stroke-dasharray="${C}" stroke-dashoffset="${C}" style="transition:stroke-dashoffset 1.1s cubic-bezier(.22,1,.36,1)"/></svg><div class="ring-num" id="lastAcc">0%</div></div>
    <div style="flex:1;min-width:160px"><b style="font-size:15px">${a.id}</b><div class="sub">${a.period} · ${fmtDate(a.date)}</div>
    <div style="display:flex;gap:14px;margin-top:8px;font-size:12.5px;color:var(--muted);font-weight:700"><span style="color:var(--green)">✓ ${a.matched} verified</span><span style="color:var(--amber)">~ ${a.vars} variances</span></div></div>
  </div>`;
  requestAnimationFrame(()=>requestAnimationFrame(()=>{const r=$('.lastRing');if(r)r.style.strokeDashoffset=C*(1-a.acc/100)}));
  countUp($('#lastAcc'),a.acc,{suffix:'%'});
}
function renderAudHist(){
  $('#audHistBody').innerHTML=state.audits.map((a,i)=>`<tr>
    <td class="no-label"><b>${a.id}</b><small style="display:block;color:var(--muted);font-size:11.5px">${fmtDate(a.date)}</small></td>
    <td data-l="Period">${a.period}</td>
    <td data-l="Items">${a.items}</td>
    <td data-l="Variances">${a.vars===0?'<span class="badge b-green">Clean</span>':`<span class="badge b-amber">${a.vars}</span>`}</td>
    <td data-l="Accuracy"><b>${a.acc}%</b></td>
    <td data-l=""><button class="icon-btn sm" onclick="printAudit(${i})" aria-label="Print report"><svg class="ic" style="width:15px;height:15px"><use href="#i-printer"/></svg></button></td></tr>`).join('');
}
let aud=null;
function startAudit(){
  if(!state.phones.length){toast('Inventory is empty','warn');return}
  const rows=state.phones.map(p=>{const exp=p.qty;let cnt=exp;const r=Math.random();
    if(r<.13&&exp>0)cnt=exp-1;else if(r>.93)cnt=exp+1;
    return{name:p.name,exp,cnt}});
  aud={rows,i:0,t0:Date.now()};
  $('#audScan').style.display='block';$('#audDone').style.display='none';
  $('#audFeed').innerHTML='';$('#audFill').style.width='0%';$('#audPct').textContent='0%';
  $('#audFrac').textContent='0/'+rows.length;
  $('#audSub').textContent=periodLabel()+' · '+new Date().toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'});
  openOverlay('#ovAudit');
  aud.timer=setInterval(scanTick,320);
}
function scanTick(){
  const r=aud.rows[aud.i];
  $('#audItem').innerHTML=`Scanning <b style="color:var(--text)">${esc(r.name)}</b>…`;
  const ok=r.cnt===r.exp;
  $('#audFeed').insertAdjacentHTML('beforeend',`<div class="sf-row ${ok?'sf-ok':'sf-bad'}">
    <svg class="sf-i"><use href="#i-${ok?'check':'alert'}"/></svg><span>${esc(r.name)}</span>
    <span class="sf-res">${ok?r.exp+' / '+r.cnt:r.exp+' → '+r.cnt+(r.cnt>r.exp?' +':'')}</span></div>`);
  const feed=$('#audFeed');feed.scrollTop=feed.scrollHeight;
  aud.i++;
  const frac=aud.i/aud.rows.length;
  $('#audFill').style.width=(frac*100).toFixed(0)+'%';
  $('#audPct').textContent=(frac*100).toFixed(0)+'%';
  $('#audFrac').textContent=aud.i+'/'+aud.rows.length;
  if(aud.i>=aud.rows.length){clearInterval(aud.timer);aud.timer=null;$('#audItem').textContent='Count complete';setTimeout(finishAudit,650)}
}
function finishAudit(){
  const rows=aud.rows,matched=rows.filter(r=>r.cnt===r.exp).length,vars=rows.length-matched;
  const acc=+(100*matched/rows.length).toFixed(1),secs=Math.max(1,Math.round((Date.now()-aud.t0)/1000));
  const entry={id:'AUD-'+(117+state.audits.length),date:new Date().toISOString(),period:periodLabel(),items:rows.length,matched,vars,acc,rows};
  state.audits.unshift(entry);
  const C=2*Math.PI*62;
  $('#audDone').innerHTML=`
    <div class="ring-wrap"><svg width="150" height="150"><circle cx="75" cy="75" r="62" fill="none" stroke="var(--surface-2)" stroke-width="10"/><circle id="accRing" cx="75" cy="75" r="62" fill="none" stroke="var(--primary)" stroke-width="10" stroke-linecap="round" stroke-dasharray="${C}" stroke-dashoffset="${C}" style="transition:stroke-dashoffset 1.1s cubic-bezier(.22,1,.36,1)"/></svg><div class="ring-num"><span id="accNum">0%</span></div></div>
    <b style="font-size:16px">Stocktake complete</b>
    <div class="res-grid">
      <div class="res-box"><b>${matched}</b><span>Verified</span></div>
      <div class="res-box"><b>${vars}</b><span>Variances</span></div>
      <div class="res-box"><b>${secs}s</b><span>Duration</span></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
      <button class="btn btn-outline" onclick="closeOverlay('#ovAudit')">Done</button>
      <button class="btn btn-primary" onclick="printAudit(0)"><svg class="ic"><use href="#i-printer"/></svg>Print report</button>
    </div>`;
  $('#audScan').style.display='none';$('#audDone').style.display='block';
  requestAnimationFrame(()=>requestAnimationFrame(()=>{$('#accRing').style.strokeDashoffset=C*(1-acc/100)}));
  countUp($('#accNum'),acc,{suffix:'%'});
  confetti();toast(entry.id+' saved · '+acc+'% accuracy');renderAudit();
}
function abortAudit(){if(aud&&aud.timer){clearInterval(aud.timer);aud.timer=null;toast('Stocktake cancelled','warn')}closeOverlay('#ovAudit')}

/* ================= printing ================= */
function printHTML(html){$('#printRoot').innerHTML=html;document.body.classList.add('print-mode');setTimeout(()=>window.print(),80)}
window.addEventListener('afterprint',()=>{document.body.classList.remove('print-mode');$('#printRoot').innerHTML=''});
function printInvoice(id){const inv=state.invoices.find(v=>v.id===id);if(!inv)return;printHTML(`<div class="paper">${invoiceInner(inv)}</div>`)}
function printStockList(){
  const s=state.settings,units=state.phones.reduce((a,p)=>a+p.qty,0),value=state.phones.reduce((a,p)=>a+p.qty*p.price,0);
  printHTML(`<div class="paper">
    <div class="pv-head"><div class="pv-shop"><b>${esc(s.shop)}</b><span>Stock list · generated ${fmtDate(new Date())}</span></div><div class="pv-meta"><div class="inword">STOCK</div><b style="font-size:13px">${state.phones.length} models</b></div></div>
    <div class="rp-sum"><div class="rp-box"><b>${state.phones.length}</b><span>Models</span></div><div class="rp-box"><b>${units}</b><span>Units</span></div><div class="rp-box"><b>${money(value)}</b><span>Stock value</span></div></div>
    <table class="pv-items"><thead><tr><th>#</th><th>Product</th><th>Brand</th><th>Storage</th><th>IMEI</th><th class="r">Price</th><th class="r">Qty</th></tr></thead><tbody>
    ${state.phones.map((p,i)=>`<tr><td>${i+1}</td><td><b>${esc(p.name)}</b></td><td>${p.brand}</td><td>${p.storage}</td><td style="font-size:11px">${p.imei}</td><td class="r">${money(p.price)}</td><td class="r"><b>${p.qty}</b></td></tr>`).join('')}
    </tbody></table>
    <div class="pv-foot"><div style="color:#697089;font-size:12px">Printed from NovaCell · internal document</div><div><div class="barcode"></div><div style="font-size:10.5px;letter-spacing:.2em;color:#9aa0b5;margin-top:5px">STOCK-${new Date().getFullYear()}</div></div></div>
  </div>`);
}
function printAudit(i){
  const a=state.audits[i];if(!a)return;
  const s=state.settings;
  printHTML(`<div class="paper">
    <div class="pv-head"><div class="pv-shop"><b>${esc(s.shop)}</b><span>Stocktake report · performed by Alex Morgan</span></div><div class="pv-meta"><div class="inword">AUDIT</div><b style="font-size:13px">${a.id}</b><div style="color:#9aa0b5;font-size:12px;margin-top:2px">${fmtDate(a.date)}</div></div></div>
    <div class="rp-sum"><div class="rp-box"><b>${a.items}</b><span>Items</span></div><div class="rp-box"><b>${a.matched}</b><span>Verified</span></div><div class="rp-box"><b>${a.vars}</b><span>Variances</span></div><div class="rp-box"><b>${a.acc}%</b><span>Accuracy</span></div></div>
    <div class="pv-lbl">Period: ${esc(a.period)}</div>
    <table class="pv-items" style="margin-top:8px"><thead><tr><th>Product</th><th class="r">Expected</th><th class="r">Counted</th><th class="r">Difference</th></tr></thead><tbody>
    ${(a.rows||[]).map(r=>{const df=r.cnt-r.exp;
      const b=df===0?'<span class="dff" style="color:#16a34a;background:#e8f7ee">✓ match</span>':df>0?`<span class="dff" style="color:#d97706;background:#fdf1df">+${df}</span>`:`<span class="dff" style="color:#e5484d;background:#fdeaea">${df}</span>`;
      return `<tr><td><b>${esc(r.name)}</b></td><td class="r">${r.exp}</td><td class="r">${r.cnt}</td><td class="r">${b}</td></tr>`}).join('')||`<tr><td colspan="4" style="color:#9aa0b5">Detailed rows not stored for this audit.</td></tr>`}
    </tbody></table>
    <div class="sign-row"><div class="sign">Counted by — signature</div><div class="sign">Verified by — signature</div></div>
  </div>`);
}

/* ================= settings ================= */
function renderSettings(){
  const s=state.settings;
  $('#sName').value=s.shop;$('#sPhone').value=s.phone;$('#sEmail').value=s.email;$('#sAddr').value=s.addr;
  $('#sCurr').value=s.currency;$('#sTax').value=s.tax;$('#sLow').value=s.low;
  $('#thLight').classList.toggle('active',document.documentElement.dataset.theme!=='dark');
  $('#thDark').classList.toggle('active',document.documentElement.dataset.theme==='dark');
}
function saveSettings(){
  const s=state.settings;
  s.shop=$('#sName').value.trim()||s.shop;s.phone=$('#sPhone').value;s.email=$('#sEmail').value;s.addr=$('#sAddr').value;
  s.currency=$('#sCurr').value;s.symbol=SYMS[s.currency];s.tax=Math.max(0,+$('#sTax').value||0);s.low=Math.max(1,+$('#sLow').value||3);
  localStorage.setItem('nc-settings',JSON.stringify(s));
  toast('Settings saved');rerender();
}
function resetData(){localStorage.removeItem('nc-settings');location.reload()}

/* ================= theme ================= */
function setTheme(t){
  document.documentElement.dataset.theme=t;localStorage.setItem('nc-theme',t);
  $('#btnTheme').innerHTML=`<svg class="ic"><use href="#i-${t==='dark'?'sun':'moon'}"/></svg>`;
  if(state.view&&VIEWS[state.view])VIEWS[state.view]();
}
function toggleTheme(){setTheme(document.documentElement.dataset.theme==='dark'?'light':'dark')}

/* ================= overlays / toasts ================= */
function openOverlay(sel){$(sel).classList.add('open');document.body.style.overflow='hidden'}
function closeOverlay(sel){$(sel).classList.remove('open');document.body.style.overflow=''}
function toast(msg,type){
  const t=document.createElement('div');t.className='toast'+(type==='warn'?' warn':'');
  t.innerHTML=`<svg class="ic"><use href="#i-${type==='warn'?'alert':'check'}"/></svg>${esc(msg)}`;
  $('#toasts').appendChild(t);
  setTimeout(()=>{t.classList.add('out');setTimeout(()=>t.remove(),350)},2600);
}
function confetti(){
  const c=document.createElement('canvas');c.style.cssText='position:fixed;inset:0;z-index:200;pointer-events:none';
  document.body.appendChild(c);const ctx=c.getContext('2d');c.width=innerWidth;c.height=innerHeight;
  const cols=['#6153f4','#8b7bff','#10b981','#f59e0b','#ef4444','#0ea5e9'];
  const ps=Array.from({length:140},()=>({x:Math.random()*c.width,y:-20-Math.random()*c.height*.3,r:4+Math.random()*5,vy:2.2+Math.random()*3,vx:-1.2+Math.random()*2.4,rot:Math.random()*Math.PI,vr:-.12+Math.random()*.24,col:cols[(Math.random()*cols.length)|0]}));
  const t0=performance.now();
  (function f(t){const k=(t-t0)/1900;ctx.clearRect(0,0,c.width,c.height);
    ps.forEach(p=>{p.x+=p.vx;p.y+=p.vy;p.rot+=p.vr;ctx.save();ctx.translate(p.x,p.y);ctx.rotate(p.rot);ctx.fillStyle=p.col;ctx.globalAlpha=Math.max(0,1-k);ctx.fillRect(-p.r/2,-p.r/2,p.r,p.r*.62);ctx.restore()});
    if(k<1)requestAnimationFrame(f);else c.remove()})(t0);
}

/* ================= notifications ================= */
function toggleNotif(e){e.stopPropagation();const p=$('#notifPop');const open=!p.classList.contains('open');closeNotif();
  if(open){renderNotifs();p.classList.add('open')}}
function closeNotif(){$('#notifPop').classList.remove('open')}
function renderNotifs(){
  const items=state.phones.filter(p=>p.qty<=state.settings.low).sort((a,b)=>a.qty-b.qty);
  $('#notifPop').innerHTML=`<div class="pop-h">Notifications<span class="badge ${items.length?'b-amber':'b-green'}">${items.length}</span></div>`+
   (items.map(p=>`<div class="pop-item" onclick="closeNotif();go('inventory')">${tile(p)}<div><b>${esc(p.name)}</b><small>${p.qty===0?'Out of stock — restock soon':`Only ${p.qty} left — below threshold`}</small></div></div>`).join('')||'<div class="pop-ok">All stocked up ✓</div>');
}

/* ================= command palette ================= */
let palIdx=0,palItems=[];
function openPalette(){openOverlay('#ovPal');$('#palInput').value='';palIdx=0;buildPal();setTimeout(()=>$('#palInput').focus(),60)}
function buildPal(){
  const q=$('#palInput').value.toLowerCase().trim();
  const P=(g,icon,label,sub,run)=>({g,icon,label,sub,run});
  palItems=[
    P('Pages','home','Dashboard','Overview & stats',()=>go('dashboard')),
    P('Pages','box','Inventory','Browse & manage stock',()=>go('inventory')),
    P('Pages','receipt','Invoices','Sales & billing',()=>go('invoices')),
    P('Pages','clip','Stock audit','Run a stocktake',()=>go('audit')),
    P('Pages','gear','Settings','Shop preferences',()=>go('settings')),
    P('Actions','plus','New invoice','Create a sale',()=>openNewInvoice()),
    P('Actions','scan','Start stocktake','Live scan of all items',()=>{go('audit');setTimeout(startAudit,350)}),
    P('Actions','printer','Print stock list','Full inventory sheet',printStockList),
    P('Actions','moon','Toggle theme','Light / dark mode',toggleTheme),
    ...state.phones.map(p=>P('Products','phone',p.name,`${p.brand} · ${money(p.price)} · ${p.qty} in stock`,()=>{go('inventory');$('#invQ').value=p.name;state.inv.q=p.name;renderInventory()})),
    ...state.invoices.map(v=>P('Invoices','receipt',v.id,`${v.customer} · ${money2(v.total)} · ${v.status}`,()=>openInvoice(v.id)))
  ].filter(x=>!q||(x.label+' '+x.sub).toLowerCase().includes(q));
  palIdx=Math.max(0,Math.min(palIdx,palItems.length-1));
  let lastG='',html='';
  palItems.forEach((it,i)=>{
    if(it.g!==lastG){html+=`<div class="pal-g">${it.g}</div>`;lastG=it.g}
    html+=`<div class="pal-item ${i===palIdx?'on':''}" onclick="palRun(${i})" onmouseenter="palHover(${i})">
      <span class="pi-ic"><svg class="ic" style="width:16px;height:16px"><use href="#i-${it.icon}"/></svg></span>
      <span style="min-width:0"><b>${esc(it.label)}</b><small>${esc(it.sub)}</small></span><span class="enter">↵</span></div>`;
  });
  $('#palList').innerHTML=html||`<div class="pal-empty">No matches for "${esc($('#palInput').value)}"</div>`;
}
function palRun(i){closeOverlay('#ovPal');const it=palItems[i];if(it)setTimeout(()=>it.run(),80)}
function palHover(i){if(i===palIdx)return;palIdx=i;$$('#palList .pal-item').forEach((el,j)=>el.classList.toggle('on',j===palIdx))}
function palKey(e){
  if(e.key==='ArrowDown'){e.preventDefault();palIdx=Math.min(palIdx+1,palItems.length-1);buildPal();scrollPal()}
  else if(e.key==='ArrowUp'){e.preventDefault();palIdx=Math.max(palIdx-1,0);buildPal();scrollPal()}
  else if(e.key==='Enter'){e.preventDefault();palRun(palIdx)}
}
function scrollPal(){const el=$('#palList .pal-item.on');if(el)el.scrollIntoView({block:'nearest'})}

/* ================= sheet ================= */
function openSheet(){openOverlay('#ovSheet')}

/* ================= init ================= */
(function init(){
  setTheme(localStorage.getItem('nc-theme')||'light');
  $('#invBrand').innerHTML='<option value="all">All brands</option>'+BRANDS.map(b=>`<option value="${b}">${b}</option>`).join('');
  $('#invBrand').onchange=e=>{state.inv.brand=e.target.value;renderInventory()};
  $('#invQ').oninput=e=>{state.inv.q=e.target.value;renderInventory()};
  $('#stockChips').onclick=e=>{const b=e.target.closest('.chip');if(!b)return;
    $$('#stockChips .chip').forEach(c=>c.classList.remove('active'));b.classList.add('active');
    state.inv.status=b.dataset.s;renderInventory()};
  $('#invSearch').oninput=e=>{state.invF.q=e.target.value;renderInvoices()};
  $('#invFChips').onclick=e=>{const b=e.target.closest('.chip');if(!b)return;
    $$('#invFChips .chip').forEach(c=>c.classList.remove('active'));b.classList.add('active');
    state.invF.status=b.dataset.s;renderInvoices()};
  $('#tfChips').onclick=e=>{const b=e.target.closest('.chip');if(!b)return;
    $$('#tfChips .chip').forEach(c=>c.classList.remove('active'));b.classList.add('active');
    state.auditDays=b.dataset.days==='custom'?'custom':+b.dataset.days;
    $('#customRange').style.display=state.auditDays==='custom'?'grid':'none';renderPeriod()};
  $('#cFrom').onchange=renderPeriod;$('#cTo').onchange=renderPeriod;
  $('#apBrand').innerHTML=BRANDS.map(b=>`<option>${b}</option>`).join('');
  $('#palInput').oninput=()=>{palIdx=0;buildPal()};
  $$('[data-nav]').forEach(b=>b.onclick=()=>go(b.dataset.nav));
  ['#ovNew','#ovPhone','#ovInvoice','#ovSheet','#ovPal','#ovAudit'].forEach(id=>{
    $(id).addEventListener('click',e=>{if(e.target!==$(id))return;
      if(id==='#ovAudit')abortAudit();else closeOverlay(id)})});
  document.addEventListener('click',e=>{if(!e.target.closest('#notifPop')&&!e.target.closest('[onclick*="toggleNotif"]'))closeNotif()});
  document.addEventListener('keydown',e=>{
    if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==='k'){e.preventDefault();
      $('#ovPal').classList.contains('open')?closeOverlay('#ovPal'):openPalette();return}
    if(e.key==='Escape'){$$('.overlay.open').forEach(o=>o.id==='#ovAudit'||o.id==='ovAudit'?abortAudit():closeOverlay('#'+o.id));closeNotif();return}
    if($('#ovPal').classList.contains('open'))palKey(e);
  });
  refreshNav();go('dashboard');
})();