<?php
require_once __DIR__ . '/inc/data.php';
$user = require_login();
$perms = permissions_for_user((int)$user['id'], $user['role']);
$full = load_source_data();
$source = filtered_source_for_user($full, $user);
$source['manualContacts'] = load_party_contacts($source);
$remarks = load_party_remarks($source);
$remarkHistory = load_party_remark_history($source);
$procurementSource = function_exists('load_procurement_pr_po_source') ? load_procurement_pr_po_source() : ['rows'=>[]];
$csrf = csrf_token();
$liveGoogle = defined('GOOGLE_SHEET_LIVE') && GOOGLE_SHEET_LIVE;
$sourceMode = function_exists('data_source_mode') ? data_source_mode() : ($liveGoogle ? 'google' : 'manual');

define('RECEIVABLES_APP', true);
ob_start();
include __DIR__ . '/private/dashboard_template.php';
$html = ob_get_clean();

$sourceJson = json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$remarksJson = json_encode($remarks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$remarkHistoryJson = json_encode($remarkHistory, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$procurementJson = json_encode($procurementSource, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$html = preg_replace_callback('~(<script id="embeddedData" type="application/json">).*?(</script>)~s', fn($m) => $m[1] . $sourceJson . $m[2], $html, 1);
$html = preg_replace_callback('~(<script id="embeddedRemarks" type="application/json">).*?(</script>)~s', fn($m) => $m[1] . $remarksJson . $m[2], $html, 1);
$html = preg_replace_callback('~(<script id="embeddedRemarkHistory" type="application/json">).*?(</script>)~s', fn($m) => $m[1] . $remarkHistoryJson . $m[2], $html, 1);
$html = preg_replace_callback('~(<script id="embeddedProcurementData" type="application/json">).*?(</script>)~s', fn($m) => $m[1] . $procurementJson . $m[2], $html, 1);

$userInfo = ['id'=>(int)$user['id'], 'name'=>$user['full_name'], 'username'=>$user['username'], 'role'=>$user['role'], 'data_scope'=>$user['data_scope']];
$cfg = json_encode([
    'user'=>$userInfo,
    'permissions'=>$perms,
    'csrf'=>$csrf,
    'adminUrl'=>'admin/users.php',
    'logoutUrl'=>'logout.php',
    'passwordUrl'=>'change_password.php',
    'liveGoogle'=>$liveGoogle,
    'sourceMode'=>$sourceMode,
    'liveSync'=>$source['liveSync'] ?? null,
    'googleSyncUrl'=>'api/sync_google.php',
    'prPoLive'=>function_exists('procurement_live_enabled') ? procurement_live_enabled() : false,
    'prPoSync'=>$procurementSource['liveSync'] ?? null,
    'prPoSyncUrl'=>'api/sync_pr_po.php',
    'billFollowupUrl'=>'api/update_bill_followup.php',
    'partyContactUrl'=>'api/save_party_contact.php',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$inject = <<<HTML
<style>
.server-user{display:flex;align-items:center;gap:7px;font-size:10px;color:#607789;border-left:1px solid #dde4eb;padding-left:12px}.server-user strong{color:#425a70}.server-link{font-size:10px;color:#087f8c;text-decoration:none}.server-link:hover{text-decoration:underline}.live-pill{display:inline-flex;align-items:center;gap:6px;font-size:9px;font-weight:650;border:1px solid #cce7df;background:#eef9f5;color:#2e7b67;border-radius:999px;padding:5px 9px;white-space:nowrap}.live-pill:before{content:"";width:6px;height:6px;border-radius:50%;background:#36a886}.live-pill.stale{background:#fff7e9;border-color:#eeddb7;color:#98722a}.live-pill.stale:before{background:#c8952d}.live-pill.fallback{background:#fff0ee;border-color:#efceca;color:#a95451}.live-pill.fallback:before{background:#c55d58}.live-pill.manual{background:#eef3ff;border-color:#cfdbf6;color:#385f9b}.live-pill.manual:before{background:#4e7cc2}.sync-google{height:32px;font-size:10px;padding:6px 10px}.sync-pr-po{height:30px;font-size:10px;padding:6px 10px;border-radius:9px;background:#0b4a86;color:#fff;border-color:#0b4a86}.pr-po-pill{display:inline-flex;align-items:center;gap:6px;font-size:9px;font-weight:750;border:1px solid #bfd7f3;background:#edf5ff;color:#245c98;border-radius:999px;padding:5px 9px;white-space:nowrap}.pr-po-pill:before{content:"";width:6px;height:6px;border-radius:50%;background:#2f80ed}.pr-po-pill.stale:before,.pr-po-pill.fallback_file:before{background:#d48928}.pr-po-pill.stale,.pr-po-pill.fallback_file{background:#fff7e9;border-color:#eeddb7;color:#98722a}
</style>
<script>
window.SERVER_MODE=true;window.APP_CONFIG=$cfg;
window.serverSaveImportedData=async function(raw){
 const res=await fetch('api/save_data.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':APP_CONFIG.csrf},body:JSON.stringify({source:raw})});
 const j=await res.json().catch(()=>({ok:false,message:'Invalid server response'}));
 if(!res.ok||!j.ok)throw new Error(j.message||'Server save failed');
 APP_CONFIG.sourceMode='manual';
 if(typeof toast==='function')toast('Excel imported: '+j.rows+' rows. Imported data is now active; use Sync Google Sheet to switch back to live data.');
 try{sessionStorage.setItem('basik_open_master_after_import','1');}catch(e){}
 setTimeout(()=>location.reload(),650);
};
(function(){
 const p=APP_CONFIG.permissions||{};const viewMap={overview:'overview',ageing:'ageing',customers:'customers',bills:'bills',checks:'checks',overdue:'overdue',weekly:'weekly',remarks:'remarks',master:'master',crm:'crm',pr:'pr',po:'po',pod:'pod',grn:'grn'};
 function escServer(s){return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
 function apply(){
  document.querySelectorAll('[data-view]').forEach(el=>{const k=viewMap[el.dataset.view];if(k&&!p[k])el.remove();});
  Object.keys(viewMap).forEach(v=>{if(!p[viewMap[v]]){const el=document.getElementById(v+'View');if(el)el.remove();}});
  const actions={exportBtn:'export',exportExcelBtn:'export',pdfBtn:'export',printHeaderBtn:'print',templateBtn:'import',importBtn:'import',printBtn:'print',saveHtmlBtn:'save_copy'};Object.entries(actions).forEach(([id,k])=>{const el=document.getElementById(id);if(el&&!p[k])el.remove();});
  if(APP_CONFIG.user.role!=='admin'){const imp=document.getElementById('importBtn');if(imp)imp.remove();}
  const right=document.querySelector('.topbar-right');if(right&&!right.querySelector('.server-user')){const box=document.createElement('div');box.className='server-user';box.innerHTML='<strong>'+escServer(APP_CONFIG.user.name)+'</strong><span>·</span>'+(APP_CONFIG.user.role==='admin'?'<a class="server-link" href="'+APP_CONFIG.adminUrl+'">Admin</a><span>·</span>':'')+'<a class="server-link" href="'+APP_CONFIG.passwordUrl+'">Password</a><span>·</span><a class="server-link" href="'+APP_CONFIG.logoutUrl+'">Logout</a>';right.appendChild(box);}
  if(APP_CONFIG.liveGoogle){
   const sync=APP_CONFIG.liveSync||{};const manual=APP_CONFIG.sourceMode==='manual';const st=manual?'manual':(sync.status||'live');
   const controls=document.querySelector('.source-controls');
   if(controls){
    if(!controls.querySelector('.live-pill')){
    const pill=document.createElement('span');pill.className='live-pill '+(st==='manual'?'manual':st==='stale'?'stale':st==='fallback'?'fallback':'');pill.textContent=st==='manual'?'Imported Excel active':st==='stale'?'Google Sheet cached':st==='fallback'?'Google Sheet fallback':'Google Sheet live';controls.prepend(pill);
    }
    if(APP_CONFIG.user.role==='admin'&&!controls.querySelector('.sync-google')){
     const btn=document.createElement('button');btn.type='button';btn.className='btn sync-google';btn.textContent=manual?'Use Google Sheet':'Sync Google Sheet';btn.onclick=async()=>{btn.disabled=true;btn.textContent='Syncing...';try{const r=await fetch(APP_CONFIG.googleSyncUrl,{method:'POST',headers:{'X-CSRF-Token':APP_CONFIG.csrf}});const j=await r.json().catch(()=>({ok:false,message:'Invalid server response'}));if(!r.ok||!j.ok)throw new Error(j.message||'Sync failed');if(typeof toast==='function')toast('Google Sheet synced: '+j.rows+' rows. Live data is now active.');setTimeout(()=>location.reload(),500);}catch(e){btn.disabled=false;btn.textContent=manual?'Use Google Sheet':'Sync Google Sheet';if(typeof toast==='function')toast(e.message||'Google Sheet sync failed.',true);}};controls.appendChild(btn);
    }
   }
   const footer=document.getElementById('footerText');if(footer)footer.textContent=manual?'Imported Excel · stored on server · employee permissions enforced':'Live Google Sheet · server-side sync · employee permissions enforced';
   const badge=document.getElementById('snapshotBadge');if(badge)badge.textContent=(manual?'Imported data':'Live Sheet')+' · '+(sync.rowCount||0)+' rows';
   if(!manual&&(st==='stale'||st==='fallback')&&typeof toast==='function'){setTimeout(()=>toast(st==='stale'?'Google Sheet could not refresh; showing the last cached copy.':'Google Sheet could not load; showing fallback data.',true),400);}
  }
  if(APP_CONFIG.prPoLive || APP_CONFIG.prPoSync){
   const ps=APP_CONFIG.prPoSync||{};const pst=ps.status||'file';
   document.querySelectorAll('#prView .procure-note,#poView .procure-note,#grnView .procure-note').forEach(note=>{
    if(!note.querySelector('.pr-po-pill')){const pill=document.createElement('span');pill.className='pr-po-pill '+pst;const txt=pst==='live'?'PR/PO Live':pst==='cached'?'PR/PO Cached':pst==='stale'?'PR/PO Stale':pst==='fallback_file'?'PR/PO Fallback':'PR/PO Source';pill.textContent=txt;note.appendChild(pill);}
    if(APP_CONFIG.user.role==='admin'&&APP_CONFIG.prPoLive&&APP_CONFIG.prPoSyncUrl&&!note.querySelector('.sync-pr-po')){const b=document.createElement('button');b.type='button';b.className='btn sync-pr-po';b.textContent='Sync PR/PO Sheet';b.onclick=async()=>{b.disabled=true;b.textContent='Syncing PR/PO...';try{const r=await fetch(APP_CONFIG.prPoSyncUrl,{method:'POST',headers:{'X-CSRF-Token':APP_CONFIG.csrf}});const j=await r.json().catch(()=>({ok:false,message:'Invalid server response'}));if(!r.ok||!j.ok)throw new Error(j.message||'PR/PO sync failed');if(typeof toast==='function')toast('PR/PO Google Sheet synced: '+j.rows+' rows.');setTimeout(()=>location.reload(),500);}catch(e){b.disabled=false;b.textContent='Sync PR/PO Sheet';if(typeof toast==='function')toast(e.message||'PR/PO sync failed.',true);}};note.appendChild(b);}
   });
  }
  const first=Object.keys(viewMap).find(v=>p[viewMap[v]]);if(first&&typeof state!=='undefined'&&!p[viewMap[state.view]]&&typeof switchView==='function')switchView(first);
  try{if(p.master&&sessionStorage.getItem('basik_open_master_after_import')==='1'&&typeof switchView==='function'){sessionStorage.removeItem('basik_open_master_after_import');switchView('master');}}catch(e){}
 }
 document.addEventListener('DOMContentLoaded',apply);
 window.serverSavePartyRemark=async function(party,remark){
  if(!p.remarks)throw new Error('Remark permission missing');
  const r=await fetch('api/save_remark.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':APP_CONFIG.csrf},body:JSON.stringify({party:party,remark:remark})});
  const j=await r.json().catch(()=>({ok:false,message:'Invalid server response'}));
  if(!r.ok||!j.ok)throw new Error(j.message||'Save failed');
  return j;
 };
 window.serverSaveBillFollowup=async function(payload){
  if(!p.bills)throw new Error('Bill register permission missing');
  const r=await fetch(APP_CONFIG.billFollowupUrl||'api/update_bill_followup.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':APP_CONFIG.csrf},body:JSON.stringify(payload)});
  const j=await r.json().catch(()=>({ok:false,message:'Invalid server response'}));
  if(!r.ok||!j.ok)throw new Error(j.message||'Follow-up save failed');
  return j;
 };
 window.serverSavePartyContact=async function(payload){
  if(!(p.remarks||p.bills||p.master))throw new Error('Contact update permission missing');
  const r=await fetch(APP_CONFIG.partyContactUrl||'api/save_party_contact.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':APP_CONFIG.csrf},body:JSON.stringify(payload)});
  const j=await r.json().catch(()=>({ok:false,message:'Invalid server response'}));
  if(!r.ok||!j.ok)throw new Error(j.message||'Contact save failed');
  return j;
 };
})();
</script>
HTML;
if (preg_match('~</body>\s*</html>\s*$~i', $html)) {
    $html = preg_replace('~</body>\s*</html>\s*$~i', $inject . '</body></html>', $html, 1);
} else {
    $html .= $inject;
}
echo $html;
