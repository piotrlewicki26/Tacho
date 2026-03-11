<?php
/**
 * Weekly tachograph analysis – visual style matching tachograph-ddd-analyzer.jsx
 * @see https://github.com/piotrlewicki26/Tacho/blob/05369edb/tachograph-ddd-analyzer.jsx
 *
 * @var array  $file
 * @var int    $fileId
 * @var string $weekStart      (still used for summary table default)
 * @var array  $weeklyData
 * @var array  $activities     ALL activities for the file
 * @var array  $violations
 * @var array  $weekKeys
 */

// ── Map PHP activity types → JSX numeric types ──────────────────────────────
// JSX:  0=Odpoczynek  1=Dyspozycja  2=Praca  3=Jazda
$jsTypeMap = [
    'rest'         => 0,
    'break'        => 0,
    'availability' => 1,
    'work'         => 2,
    'driving'      => 3,
];

// ── Build per-day slots for JavaScript ────────────────────────────────────
$jsDays = [];
foreach ($activities as $a) {
    $date = $a['activity_date'];
    $sp   = explode(':', $a['start_time']);
    $ep   = explode(':', $a['end_time']);
    $sMin = (int)$sp[0] * 60 + (int)$sp[1];
    $eMin = (int)$ep[0] * 60 + (int)$ep[1];
    if ($eMin < $sMin) $eMin += 1440;   // midnight crossing: keep >1440 so JS can render it spanning into next day column
    $dur  = max(1, $eMin - $sMin);
    $jsDays[$date][] = [
        'a' => $jsTypeMap[$a['activity_type']] ?? 0,
        's' => $sMin,
        'e' => $eMin,
        'd' => $dur,
        'c' => $a['country_code'] ?? null,
    ];
}

// ── Weekly summary totals ─────────────────────────────────────────────────
$fmt = fn(int $m): string => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);

// Find date range
$allDates = array_keys($jsDays);
sort($allDates);
$firstDate = $allDates[0] ?? $weekStart;
$lastDate  = end($allDates) ?: $weekStart;

$actLabels = [
    'driving'      => 'Jazda',
    'work'         => 'Praca',
    'availability' => 'Dyspozycja',
    'rest'         => 'Odpoczynek',
    'break'        => 'Przerwa',
];

// Per-week totals for summary table – computed from all activities (not just current week)
$weeklyTotals = [];
foreach ($weekKeys as $wk) {
    $weeklyTotals[$wk] = ['driving'=>0,'work'=>0,'availability'=>0,'rest'=>0,'break'=>0];
}
foreach ($activities as $a) {
    $wk = date('Y-m-d', strtotime('last Monday', strtotime($a['activity_date'] . ' +1 day')));
    if (isset($weeklyTotals[$wk])) {
        $weeklyTotals[$wk][$a['activity_type']] = ($weeklyTotals[$wk][$a['activity_type']] ?? 0) + (int)$a['duration_minutes'];
    }
}
?>

<!-- ── Navigation ──────────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Analiza tygodniowa</h4>
    <p class="text-muted small mb-0"><?= htmlspecialchars($file['original_name']) ?></p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="/analysis/<?= $fileId ?>/daily" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-calendar-day me-1"></i>Dzienny
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer me-1"></i>Drukuj
    </button>
  </div>
</div>

<!-- ── Main Analyzer Card ─────────────────────────────────────────────────── -->
<div id="tacho-card" style="background:#fff;border:1px solid #E0E4E8;border-radius:6px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.06);margin-bottom:1.5rem">

  <!-- Header bar -->
  <div style="background:#F8F9FB;border-bottom:1px solid #E0E2E8;padding:8px 12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:8px">
      <div style="background:#1E88E5;color:#fff;padding:4px 9px;border-radius:4px;font-size:11px;font-weight:700;letter-spacing:1px">TACHO</div>
      <span style="font-size:15px;font-weight:600;color:#1A2030">Analyzer</span>
      <span style="font-size:10px;color:#9AA0AA;border:1px solid #DDE1E6;padding:2px 7px;border-radius:3px">EU 561/2006</span>
    </div>
    <div id="driver-badge" style="display:none;padding:4px 12px;background:#fff;border:1px solid #E0E4E8;border-radius:4px">
      <span style="font-size:11px;color:#9AA0AA">Kierowca:</span>
      <span id="driver-name" style="font-size:13px;font-weight:600;color:#1A2030;margin-left:4px"></span>
    </div>
    <div style="margin-left:auto;padding:4px 12px;background:#fff;border:1px solid #E0E4E8;border-radius:4px;font-size:11px;color:#5A6070">
      Jazda łącznie: <strong id="total-drive" style="color:#1A2030">—</strong>
    </div>
  </div>

  <!-- Legend -->
  <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:7px 12px;background:#F8F9FB;border-bottom:1px solid #E0E2E8">
    <span style="font-size:10px;color:#9AA0AA;font-weight:600">LEGENDA</span>
    <div style="display:flex;align-items:center;gap:5px">
      <div style="width:20px;height:10px;background:#4DD0E1;border:1px solid #00ACC180;border-radius:2px"></div>
      <span style="font-size:10px;color:#5A6070">Odpoczynek</span>
    </div>
    <div style="display:flex;align-items:center;gap:5px">
      <div style="width:20px;height:10px;background:#FFCDD2;border:1px solid #E5393580;border-radius:2px;position:relative;overflow:hidden">
        <div style="position:absolute;inset:0;background:repeating-linear-gradient(90deg,#E53935 0,#E53935 1.5px,transparent 1.5px,transparent 6px);opacity:0.6"></div>
      </div>
      <span style="font-size:10px;color:#5A6070">Jazda</span>
    </div>
    <div style="display:flex;align-items:center;gap:5px">
      <div style="width:20px;height:10px;background:#C8E6C9;border:1px solid #388E3C80;border-radius:2px;position:relative;overflow:hidden">
        <div style="position:absolute;inset:0;background:repeating-linear-gradient(90deg,#388E3C 0,#388E3C 1.5px,transparent 1.5px,transparent 6px);opacity:0.55"></div>
      </div>
      <span style="font-size:10px;color:#5A6070">Praca</span>
    </div>
    <div style="display:flex;align-items:center;gap:5px">
      <div style="width:20px;height:10px;background:#E8EAF6;border:1px solid #3949AB80;border-radius:2px;position:relative;overflow:hidden">
        <div style="position:absolute;inset:0;background:repeating-linear-gradient(90deg,#5C6BC0 0,#5C6BC0 1px,transparent 1px,transparent 6px);opacity:0.5"></div>
      </div>
      <span style="font-size:10px;color:#5A6070">Dyspozycja</span>
    </div>
    <div style="display:flex;align-items:center;gap:5px">
      <div style="width:20px;height:10px;background:#90CAF9;border:1px solid #1E88E580;border-radius:2px"></div>
      <span style="font-size:10px;color:#5A6070">Odpoczynek dobowy ≥9h</span>
    </div>
    <span style="font-size:9px;color:#2E7D32;font-weight:600">▼ Start odpoczynku dobowego</span>
    <div style="margin-left:auto;display:flex;gap:8px;font-size:10px;color:#9AA0AA">
      <span><span style="color:#43A047;font-size:12px">●</span> Zgodny</span>
      <span><span style="color:#FF9800;font-size:12px">●</span> Ostrzeżenie</span>
      <span><span style="color:#E53935;font-size:12px">●</span> Naruszenie</span>
    </div>
  </div>

  <!-- Zoom toolbar -->
  <div style="display:flex;align-items:center;gap:8px;padding:7px 12px;background:#F3F4F7;border-bottom:1px solid #E0E2E8;flex-wrap:wrap">
    <span style="font-size:10px;font-weight:600;color:#9AA0AA">ZOOM</span>
    <button class="tzb" onclick="tzZoom(7*1440,0)">7 dni</button>
    <button class="tzb" onclick="tzZoom(5*1440,0)">5 dni</button>
    <button class="tzb" onclick="tzZoom(3*1440,0)">3 dni</button>
    <button class="tzb" onclick="tzZoom(1440,0)">1 dzień</button>
    <button class="tzb" onclick="tzZoomIn()">+ Zbliż</button>
    <button class="tzb" onclick="tzZoomOut()">− Oddal</button>
    <div style="width:1px;height:16px;background:#DDE1E6"></div>
    <button id="tz-mode-sel" class="tzb tzb-active" onclick="tzMode('select')">[ ] Zaznacz</button>
    <button id="tz-mode-pan" class="tzb" onclick="tzMode('pan')">↔ Przesuwaj</button>
    <span style="font-size:10px;color:#C0C4CC">Scroll=zoom</span>
    <span id="tz-range-label" style="margin-left:auto;font-size:10px;color:#9AA0AA">7.0 dni</span>
  </div>

  <!-- Column headers -->
  <div style="display:flex;background:#F0F4F8;border-bottom:1px solid #E0E2E8">
    <div style="width:74px;flex-shrink:0;padding:5px 10px;font-size:9px;font-weight:700;color:#9AA0AA;letter-spacing:1px;border-right:1px solid #E2E4EA">TYDZIEŃ</div>
    <div style="flex:1;padding:5px 12px;font-size:9px;font-weight:700;color:#9AA0AA;letter-spacing:1px">
      OŚ CZASU 7 DNI — kliknij datę, aby zobaczyć dzień — kliknij kod granicy, aby zobaczyć przejście
    </div>
  </div>

  <!-- Chart area -->
  <div id="tz-chart"
       style="position:relative;cursor:crosshair;user-select:none;-webkit-user-select:none;background:#fff">
    <!-- Week rows injected by JS -->
  </div>

</div><!-- /card -->

<!-- ── Crossing modal ─────────────────────────────────────────────────────── -->
<div id="tz-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:10000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;width:360px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.3);overflow:hidden;font-family:Inter,sans-serif">
    <div id="tz-modal-body" style="padding:14px 16px"></div>
    <div style="padding:8px 16px;text-align:right;border-top:1px solid #E0E4E8">
      <button onclick="document.getElementById('tz-modal').style.display='none'"
              style="background:#1E88E5;color:#fff;border:none;padding:6px 16px;border-radius:4px;font-size:12px;cursor:pointer">Zamknij</button>
    </div>
  </div>
</div>

<!-- ── Summary table ──────────────────────────────────────────────────────── -->
<div class="card border-0 mb-4" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0">Podsumowanie tygodniowe</h6>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="text-muted small">
        <tr>
          <th>Tydzień</th>
          <?php foreach ($actLabels as $type => $label): ?><th><?= $label ?></th><?php endforeach; ?>
          <th>Jazda łącznie</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($weekKeys as $wk):
            $wt = $weeklyTotals[$wk];
            $weekNum = (int) date('W', strtotime($wk));
        ?>
        <tr>
          <td class="small fw-semibold">
            <a href="/analysis/<?= $fileId ?>/weekly?week=<?= $wk ?>" class="text-decoration-none">
              Tydz.&nbsp;<?= $weekNum ?> <span class="text-muted fw-normal">(<?= date('d.m', strtotime($wk)) ?>–<?= date('d.m.Y', strtotime($wk . ' +6 days')) ?>)</span>
            </a>
          </td>
          <?php foreach ($actLabels as $type => $label): ?>
          <td class="small <?= ($type === 'driving' && $wt[$type] > 3360) ? 'text-danger fw-semibold' : 'text-muted' ?>">
            <?= $wt[$type] > 0 ? $fmt($wt[$type]) : '—' ?>
          </td>
          <?php endforeach; ?>
          <td class="small fw-semibold <?= $wt['driving'] > 3360 ? 'text-danger' : '' ?>">
            <?= $fmt($wt['driving']) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Violations ─────────────────────────────────────────────────────────── -->
<?php if (!empty($violations)): ?>
<div class="card border-0" style="background:#1a1d27">
  <div class="card-header border-0 bg-transparent">
    <h6 class="fw-semibold mb-0 text-danger">
      <i class="bi bi-exclamation-triangle me-2"></i>Naruszenia (<?= count($violations) ?>)
    </h6>
  </div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead class="text-muted small"><tr><th>Typ</th><th>Opis</th><th>Przepis</th><th>Grzywna</th><th>Waga</th></tr></thead>
      <tbody>
        <?php foreach ($violations as $v): ?>
        <tr>
          <td class="small"><?= htmlspecialchars($v['violation_type']) ?></td>
          <td class="small"><?= htmlspecialchars($v['description']) ?></td>
          <td class="small text-primary"><?= htmlspecialchars($v['regulation_ref'] ?? '') ?></td>
          <td class="small text-warning">
            <?= $v['fine_amount_min'] ? number_format($v['fine_amount_min'],0,',',' ').'–'.number_format($v['fine_amount_max'],0,',',' ').' zł' : '—' ?>
          </td>
          <td><span class="badge bg-<?= $v['severity'] === 'critical' ? 'danger' : ($v['severity'] === 'major' ? 'warning text-dark' : 'secondary') ?>"><?= $v['severity'] ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── JavaScript ─────────────────────────────────────────────────────────── -->
<script>
(function(){
'use strict';

// ── Constants matching JSX ────────────────────────────────────────────────
const EU = { maxWeek:3360, maxDay:540, maxDayEx:600, minRest:660, maxCont:270 };
const LW=74, T1Y=32, T1H=36, T2Y=76, T2H=18, AXY=102, RH=120;
const ACT_STROKE=["#00ACC1","#3949AB","#388E3C","#E53935"];
const ACT_TEXT  =["#006064","#1A237E","#1B5E20","#B71C1C"];
const ACT_NAME  =["Odpoczynek","Dyspozycja","Praca","Jazda"];
const FILE_ID   = <?= (int)$fileId ?>;
const DAILY_URL = '/analysis/'+FILE_ID+'/daily?date=';

// Country style (matching JSX CC object)
const CC={
  PL:{bg:"#FFEBEE",bd:"#E53935",tx:"#C62828"},
  DE:{bg:"#FFFDE7",bd:"#F9A825",tx:"#E65100"},
  CZ:{bg:"#E3F2FD",bd:"#1E88E5",tx:"#1565C0"},
  SK:{bg:"#E8F5E9",bd:"#43A047",tx:"#2E7D32"},
  AT:{bg:"#FFF3E0",bd:"#EF6C00",tx:"#BF360C"},
  HU:{bg:"#F3E5F5",bd:"#8E24AA",tx:"#6A1B9A"},
  FR:{bg:"#E8EAF6",bd:"#3949AB",tx:"#283593"},
  NL:{bg:"#FBE9E7",bd:"#FF7043",tx:"#BF360C"},
};
function ccStyle(code){ return CC[code]||{bg:"#F5F5F5",bd:"#9E9E9E",tx:"#616161"}; }

// ── Data from PHP ─────────────────────────────────────────────────────────
// jsDays: { "2025-02-17": [{a,s,e,d,c}, ...], ... }
const DAYS_RAW = <?= json_encode($jsDays, JSON_UNESCAPED_UNICODE) ?>;

// ── Helper functions (matching JSX) ───────────────────────────────────────
function hhmm(m){ return String(Math.floor(m/60)).padStart(2,'0')+':'+String(m%60).padStart(2,'0'); }
function hm(m){ const h=Math.floor(m/60),mm=m%60; return mm?h+'h '+mm+'m':h+'h'; }
function fmt(d){ return String(d.getDate()).padStart(2,'0')+'.'+String(d.getMonth()+1).padStart(2,'0')+'.'+d.getFullYear(); }
function fmtShort(d){ return String(d.getDate()).padStart(2,'0')+'.'+String(d.getMonth()+1).padStart(2,'0'); }
function isoWeek(d){
  const t=new Date(Date.UTC(d.getFullYear(),d.getMonth(),d.getDate()));
  t.setUTCDate(t.getUTCDate()+4-(t.getUTCDay()||7));
  return Math.ceil((((t-new Date(Date.UTC(t.getUTCFullYear(),0,1)))/864e5)+1)/7);
}
function monDay(d){ const r=new Date(d),dw=r.getDay(); r.setDate(r.getDate()-(dw===0?6:dw-1)); r.setHours(0,0,0,0); return r; }
function addD(d,n){ const r=new Date(d); r.setDate(r.getDate()+n); return r; }
function clamp(v,a,b){ return Math.max(a,Math.min(b,v)); }
function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

// ── Day status (matching JSX dayStatus) ──────────────────────────────────
function dayStatus(slots){
  if(!slots||!slots.length) return null;
  const drive=slots.filter(s=>s.a===3).reduce((a,s)=>a+s.d,0);
  const rest =slots.filter(s=>s.a===0).reduce((a,s)=>a+s.d,0);
  let e=false,w=false;
  if(drive>EU.maxDayEx) e=true; else if(drive>EU.maxDay) w=true;
  let cont=0;
  slots.forEach(s=>{ if(s.a===3){cont+=s.d;} else if(s.a===0&&s.d>=15){cont=0;} });
  if(cont>EU.maxCont) w=true;
  if(rest<EU.minRest&&drive>0) w=true;
  return e?'error':w?'warn':'ok';
}

// ── Build all-weeks data structure (matching JSX allWeeks) ────────────────
const availDates = Object.keys(DAYS_RAW).sort();
if(!availDates.length){ document.getElementById('tz-chart').innerHTML='<div style="padding:20px;text-align:center;color:#9AA0AA;font-size:13px">Brak danych aktywności</div>'; return; }

// Determine week range: from Monday of first date to Monday of last date
const firstMon = monDay(new Date(availDates[0]));
const lastMon  = monDay(new Date(availDates[availDates.length-1]));

// Collect week starts in order
const weekStarts=[];
for(let d=new Date(firstMon);d<=lastMon;d=addD(d,7)) weekStarts.push(new Date(d));

// Build weeks array: [{start, days:[{date, slots, crossings}|null]}]
const allWeeks = weekStarts.map(ws=>{
  const days=[];
  for(let di=0;di<7;di++){
    const d=addD(ws,di);
    const key=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
    const rawSlots=DAYS_RAW[key]||null;
    if(!rawSlots){ days.push(null); continue; }
    // Detect country crossings from consecutive country_code changes
    const crossings=[];
    let prevC=null;
    rawSlots.forEach(s=>{
      if(s.c&&s.c!==prevC){
        if(prevC) crossings.push({atMin:s.s,from:prevC,to:s.c,date:fmt(d),timeLabel:hhmm(s.s)});
        prevC=s.c;
      } else if(s.c){ prevC=s.c; }
    });
    days.push({date:d,key,slots:rawSlots,crossings,distance:0});
  }
  return{start:ws,days};
});

// ── Update header totals ──────────────────────────────────────────────────
const totalDrive=allWeeks.reduce((s,w)=>s+w.days.reduce((s2,d)=>s2+(d?d.slots.filter(x=>x.a===3).reduce((a,b)=>a+b.d,0):0),0),0);
document.getElementById('total-drive').textContent=hhmm(totalDrive);
// Show driver badge with file name
const driverBadge=document.getElementById('driver-badge');
const driverName=document.getElementById('driver-name');
driverName.textContent='<?= htmlspecialchars($file['original_name']) ?>';
driverBadge.style.display='flex';

// ── State ─────────────────────────────────────────────────────────────────
let vs=0, ve=7*1440;        // visible range in minutes (across 7-day span per row)
let mode='select';
let panStart=null;
let selStart=null, selEnd=null;

// ── DOM refs ──────────────────────────────────────────────────────────────
const chartEl = document.getElementById('tz-chart');
let chartWidth = Math.max(400, chartEl.getBoundingClientRect().width - LW - 2);

// ── Render functions ──────────────────────────────────────────────────────

function px(m){ return ((m-vs)/(ve-vs))*chartWidth; }

/** Build SVG string for a single week row (matches JSX WeekRow) */
function buildWeekSvg(ws, days){
  const dur=ve-vs;
  const p=m=>((m-vs)/dur)*chartWidth;
  const cw=chartWidth;

  // Collect flat data
  const flat=[], longRests=[], allCross=[], restStarts=[], driveMarkers=[];
  days.forEach((day,di)=>{
    if(!day) return;
    day.slots.forEach(s=>{
      flat.push({absS:di*1440+s.s,absE:di*1440+s.e,a:s.a,d:s.d,date:day.date});
      if(s.a===0&&s.d>=9*60){
        longRests.push({absS:di*1440+s.s,absE:di*1440+s.e,d:s.d});
        restStarts.push({absM:di*1440+s.s,label:hhmm(s.s)});
      }
    });
    (day.crossings||[]).forEach(c=>{
      allCross.push({...c,absM:di*1440+c.atMin});
    });
  });
  // Drive start/end markers
  let prev=null;
  flat.forEach(s=>{
    if(s.a===3&&(!prev||prev.a!==3)) driveMarkers.push({abs:s.absS,type:'start'});
    else if(s.a!==3&&prev&&prev.a===3) driveMarkers.push({abs:prev.absE,type:'end'});
    prev=s;
  });
  if(prev&&prev.a===3) driveMarkers.push({abs:prev.absE,type:'end'});

  // Status dots & week drive total
  const weekDrive=days.reduce((s,d)=>s+(d?d.slots.filter(x=>x.a===3).reduce((a,b)=>a+b.d,0):0),0);
  const dCol=weekDrive>EU.maxWeek?'#E53935':weekDrive>EU.maxWeek*0.85?'#FF9800':'#43A047';
  const dayDots=days.map(d=>{const st=dayStatus(d&&d.slots);return st==='error'?'#E53935':st==='warn'?'#FF9800':st==='ok'?'#43A047':null;});
  const totals={0:0,1:0,2:0,3:0};
  days.forEach(d=>d&&d.slots.forEach(s=>{totals[s.a]=(totals[s.a]||0)+s.d;}));

  // Now indicator
  const now=new Date();
  const todayDi=Array.from({length:7},(_,i)=>addD(ws,i)).findIndex(d=>d&&d.toDateString()===now.toDateString());
  const nowAbs=todayDi>=0?todayDi*1440+now.getHours()*60+now.getMinutes():-1;
  const nowX=nowAbs>=0?p(nowAbs):-1;
  const showNow=nowAbs>=vs&&nowAbs<=ve;

  let s=`<svg width="${cw}" height="${RH}" style="display:block;flex-shrink:0">
<defs>
  <pattern id="p3${ws.getTime()}" x="0" y="0" width="6" height="6" patternUnits="userSpaceOnUse">
    <rect width="6" height="6" fill="#FFCDD2"/>
    <line x1="3" y1="0" x2="3" y2="6" stroke="#E53935" stroke-width="1.5" opacity="0.6"/>
  </pattern>
  <pattern id="p2${ws.getTime()}" x="0" y="0" width="6" height="6" patternUnits="userSpaceOnUse">
    <rect width="6" height="6" fill="#C8E6C9"/>
    <line x1="3" y1="0" x2="3" y2="6" stroke="#388E3C" stroke-width="1.5" opacity="0.55"/>
  </pattern>
  <pattern id="p1${ws.getTime()}" x="0" y="0" width="6" height="6" patternUnits="userSpaceOnUse">
    <rect width="6" height="6" fill="#E8EAF6"/>
    <line x1="3" y1="0" x2="3" y2="6" stroke="#5C6BC0" stroke-width="1" opacity="0.5"/>
  </pattern>
</defs>`;

  // Day background bands
  for(let di=0;di<7;di++){
    const x1=p(di*1440),x2=p((di+1)*1440);
    const rx=Math.max(0,x1),rw=Math.min(cw,x2)-rx;
    if(rw>0) s+=`<rect x="${rx}" y="0" width="${rw}" height="${RH}" fill="${di%2===0?'#FFF':'#F6F7FA'}"/>`;
  }

  // Day status dots
  dayDots.forEach((col,di)=>{
    if(!col) return;
    const xc=p(di*1440+720);
    if(xc>=4&&xc<=cw-4) s+=`<circle cx="${xc}" cy="10" r="3" fill="${col}" opacity="0.75"/>`;
  });

  // T1 track background
  s+=`<rect x="0" y="${T1Y}" width="${cw}" height="${T1H}" fill="#E0F7FA" rx="2" opacity="0.4"/>`;
  s+=`<rect x="0" y="${T1Y}" width="${cw}" height="${T1H}" fill="none" stroke="#B2EBF2" stroke-width="0.8" rx="2"/>`;

  // Activity bars on T1
  const pid=ws.getTime();
  flat.filter(s2=>s2.absE>vs&&s2.absS<ve).forEach((s2,i)=>{
    const x1=Math.max(0,p(s2.absS)),x2=Math.min(cw,p(s2.absE)),bw=x2-x1;
    if(bw<0.4) return;
    const fill=s2.a===0?'#4DD0E1':s2.a===3?`url(#p3${pid})`:s2.a===2?`url(#p2${pid})`:`url(#p1${pid})`;
    s+=`<g data-act="${s2.a}" data-abs-s="${s2.absS}" data-abs-e="${s2.absE}" data-dur="${s2.d}" data-date="${s2.date?fmt(s2.date):''}">`;
    s+=`<rect x="${x1}" y="${T1Y+1}" width="${bw}" height="${T1H-2}" fill="${fill}" rx="2"/>`;
    s+=`<rect x="${x1}" y="${T1Y+1}" width="${bw}" height="${T1H-2}" fill="none" stroke="${ACT_STROKE[s2.a]}" stroke-width="0.5" rx="2" opacity="0.3" pointer-events="none"/>`;
    if(bw>36) s+=`<text x="${x1+bw/2}" y="${T1Y+T1H/2+4}" text-anchor="middle" fill="${ACT_TEXT[s2.a]}" font-size="${bw>60?10:8}" font-family="Inter,sans-serif" font-weight="600" pointer-events="none">${hhmm(s2.d)}</text>`;
    s+=`</g>`;
  });

  // T2 track background (long rests)
  s+=`<rect x="0" y="${T2Y}" width="${cw}" height="${T2H}" fill="#E3F2FD" rx="2" opacity="0.35"/>`;
  s+=`<rect x="0" y="${T2Y}" width="${cw}" height="${T2H}" fill="none" stroke="#BBDEFB" stroke-width="0.8" rx="2"/>`;

  // Long rest bars on T2
  longRests.filter(r=>r.absE>vs&&r.absS<ve).forEach((r)=>{
    const x1=Math.max(0,p(r.absS)),x2=Math.min(cw,p(r.absE)),bw=x2-x1;
    if(bw<0.4) return;
    s+=`<g data-act="-1" data-abs-s="${r.absS}" data-abs-e="${r.absE}" data-dur="${r.d}" data-date="">`;
    s+=`<rect x="${x1}" y="${T2Y+1}" width="${bw}" height="${T2H-2}" fill="#90CAF9" rx="2" opacity="0.75"/>`;
    if(bw>35) s+=`<text x="${x1+bw/2}" y="${T2Y+T2H/2+4}" text-anchor="middle" fill="#1565C0" font-size="8" font-family="Inter,sans-serif" font-weight="600" pointer-events="none">${hhmm(r.d)}</text>`;
    s+=`</g>`;
  });

  // Rest start markers (green triangle + time label)
  restStarts.filter(r=>r.absM>=vs&&r.absM<=ve).forEach((r)=>{
    const x=p(r.absM);
    if(x<0||x>cw) return;
    s+=`<g pointer-events="none">`;
    s+=`<line x1="${x}" y1="${T2Y-2}" x2="${x}" y2="${T2Y+T2H+2}" stroke="#43A047" stroke-width="2" opacity="0.9"/>`;
    s+=`<polygon points="${x},${T2Y-2} ${x-5},${T2Y-10} ${x+5},${T2Y-10}" fill="#43A047"/>`;
    s+=`<rect x="${x-16}" y="${T2Y-22}" width="32" height="12" fill="#E8F5E9" stroke="#43A047" stroke-width="0.8" rx="2"/>`;
    s+=`<text x="${x}" y="${T2Y-13}" text-anchor="middle" fill="#2E7D32" font-size="8" font-family="Inter,sans-serif" font-weight="700">${r.label}</text>`;
    s+=`</g>`;
  });

  // Dashed day separators (green, matching JSX)
  for(let di=1;di<7;di++){
    const x=p(di*1440);
    if(x>=0&&x<=cw) s+=`<line x1="${x}" y1="${T1Y-8}" x2="${x}" y2="${T2Y+T2H+4}" stroke="#66BB6A" stroke-width="1.2" stroke-dasharray="4,3" opacity="0.5"/>`;
  }

    // Country crossing markers
  allCross.filter(c=>c.absM>=vs&&c.absM<=ve).forEach((c)=>{
    const x=p(c.absM);
    if(x<0||x>cw) return;
    const cs=ccStyle(c.to);
    const label=c.from&&c.from!=='?'?c.from+'>'+c.to:c.to;
    const bw=label.length*5+8;
    s+=`<g data-from="${esc(c.from||'')}" data-to="${esc(c.to||'')}" data-cross-date="${esc(c.date||'')}" data-cross-time="${esc(c.timeLabel||'')}" style="cursor:pointer">`;
    s+=`<line x1="${x}" y1="${T1Y-2}" x2="${x}" y2="${T1Y+T1H+2}" stroke="${cs.bd}" stroke-width="2" opacity="0.85"/>`;
    s+=`<polygon points="${x},${T1Y} ${x-4},${T1Y-7} ${x+4},${T1Y-7}" fill="${cs.bd}"/>`;
    s+=`<rect x="${x-bw/2}" y="${T1Y-21}" width="${bw}" height="13" fill="${cs.bg}" stroke="${cs.bd}" stroke-width="1" rx="2"/>`;
    s+=`<text x="${x}" y="${T1Y-11}" text-anchor="middle" fill="${cs.tx}" font-size="7" font-family="Inter,sans-serif" font-weight="700">${esc(label)}</text>`;
    s+=`</g>`;
  });

  // Drive start/end markers
  driveMarkers.forEach((m)=>{
    const x=p(m.abs);
    if(x<-20||x>cw+20) return;
    s+=`<g pointer-events="none">`;
    s+=`<line x1="${x}" y1="${T1Y}" x2="${x}" y2="${T1Y+T1H}" stroke="#37474F" stroke-width="1.2"/>`;
    if(m.type==='start') s+=`<polygon points="${x},${T1Y} ${x-3},${T1Y-5} ${x+3},${T1Y-5}" fill="#37474F"/>`;
    else                 s+=`<circle cx="${x}" cy="${T1Y-3}" r="2.5" fill="#37474F"/>`;
    s+=`</g>`;
  });

  // Date labels (axis, clickable → daily view)
  for(let di=0;di<7;di++){
    const xm=p(di*1440+720);
    if(xm<22||xm>cw-22) continue;
    const d=addD(ws,di);
    const dateKey=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
    const isWE=di>=5;
    s+=`<g data-day-url="${DAILY_URL+dateKey}" style="cursor:pointer">`;
    s+=`<rect x="${xm-30}" y="${AXY+2}" width="60" height="14" fill="transparent"/>`;
    s+=`<text x="${xm}" y="${AXY+13}" text-anchor="middle" fill="${isWE?'#9AA0AA':'#1565C0'}" font-size="10" font-family="Inter,sans-serif" font-weight="${isWE?400:600}" text-decoration="underline">${fmt(d)}</text>`;
    s+=`</g>`;
  }

  // "Now" indicator
  if(showNow){
    s+=`<g pointer-events="none">`;
    s+=`<line x1="${nowX}" y1="${T1Y-8}" x2="${nowX}" y2="${T2Y+T2H+4}" stroke="#F44336" stroke-width="1.5" stroke-dasharray="3,2" opacity="0.7"/>`;
    s+=`<rect x="${nowX-13}" y="${T1Y-20}" width="26" height="12" fill="#F44336" rx="2"/>`;
    s+=`<text x="${nowX}" y="${T1Y-11}" text-anchor="middle" fill="#fff" font-size="8" font-family="Inter,sans-serif" font-weight="600">${hhmm(now.getHours()*60+now.getMinutes())}</text>`;
    s+=`</g>`;
  }

  // Axis line
  s+=`<line x1="0" y1="${AXY}" x2="${cw}" y2="${AXY}" stroke="#E0E2E8" stroke-width="1"/>`;

  s+=`</svg>`;
  return {svgHtml:s, weekDrive, dCol, totals};
}

/** Build one week row DOM element */
function buildWeekRow(wk){
  const {start, days}=wk;
  const {svgHtml, weekDrive, dCol, totals}=buildWeekSvg(start, days);
  const weekNum=String(isoWeek(start)).padStart(2,'0');
  const labelNames=['Odpocz.','Dysp.','Praca','Jazda'];
  const labelColors=['#00ACC1','#3949AB','#388E3C','#E53935'];
  const dist=days.reduce((s,d)=>s+(d?d.distance:0),0);

  const wrapper=document.createElement('div');
  wrapper.style.cssText='border-bottom:1px solid #E2E4EA;background:#FFF';
  wrapper.innerHTML=`
    <div style="height:3px;background:linear-gradient(90deg,#1E88E5,#42A5F5);opacity:0.5"></div>
    <div style="display:flex;align-items:stretch">
      <div style="width:${LW}px;flex-shrink:0;background:#F8F9FB;border-right:1px solid #E2E4EA;padding:6px 10px;display:flex;flex-direction:column;justify-content:center">
        <div style="display:flex;align-items:center;gap:4px;margin-bottom:2px">
          <div style="width:5px;height:5px;border-radius:50%;background:${dCol}"></div>
          <span style="font-size:13px;font-weight:700;color:#1565C0">W${weekNum}</span>
        </div>
        <div style="font-size:9px;color:#9AA0AA;line-height:1.5">${fmtShort(start)}</div>
        <div style="font-size:9px;color:#9AA0AA">${fmtShort(addD(start,6))}</div>
        <div style="margin-top:3px;font-size:10px;font-weight:700;color:${dCol}">${hhmm(weekDrive)}</div>
      </div>
      <div class="tz-svg-wrap" style="flex:1;overflow:hidden;position:relative">${svgHtml}</div>
    </div>
    <div style="display:flex;align-items:stretch;background:#F8F9FB;border-top:1px solid #EEF0F4">
      <div style="width:${LW}px;flex-shrink:0;border-right:1px solid #E2E4EA;padding:4px 8px;display:flex;align-items:center">
        ${dist>0?`<span style="font-size:9px;color:#9AA0AA;font-weight:500">${dist} km</span>`:''}
      </div>
      <div style="flex:1;display:flex;align-items:center;flex-wrap:wrap">
        ${[3,2,1,0].map(k=>{
          const val=totals[k]||0;
          if(!val) return '';
          return `<div style="display:flex;align-items:center;gap:5px;padding:4px 12px;border-right:1px solid #EEF0F4">
            <div style="width:8px;height:8px;border-radius:2px;background:${labelColors[k]};flex-shrink:0"></div>
            <span style="font-size:9px;color:#6A7080;white-space:nowrap"><span style="font-weight:600;color:${labelColors[k]}">${labelNames[k]}</span> ${hm(val)}</span>
          </div>`;
        }).join('')}
      </div>
    </div>
  `;

  // Attach event listeners to SVG elements
  const svgEl=wrapper.querySelector('svg');
  if(!svgEl) return wrapper;

  // Hover tooltip (activity blocks and long-rest blocks)
  const tooltip=document.getElementById('tz-tooltip');
  svgEl.addEventListener('mousemove', e=>{
    const g=e.target.closest('[data-act]');
    if(!g){ tooltip.style.display='none'; return; }
    const act=parseInt(g.dataset.act);
    const absS=parseInt(g.dataset.absS);
    const absE=parseInt(g.dataset.absE);
    const dur=parseInt(g.dataset.dur);
    const dateStr=g.dataset.date;
    const col=act>=0?ACT_STROKE[act]:'#1E88E5';
    const name=act>=0?ACT_NAME[act]:'Odpoczynek dobowy';
    const textCol=act>=0?ACT_TEXT[act]:'#1565C0';
    tooltip.innerHTML=`<div style="font-weight:700;font-size:13px;margin-bottom:6px;color:${textCol}">${name}</div>
      <div style="display:flex;justify-content:space-between;gap:14px;margin-bottom:2px"><span style="color:#9AA0AA;font-size:10px">Od</span><span style="color:#333;font-weight:500">${hhmm(absS%1440)}</span></div>
      <div style="display:flex;justify-content:space-between;gap:14px;margin-bottom:2px"><span style="color:#9AA0AA;font-size:10px">Do</span><span style="color:#333;font-weight:500">${hhmm(absE%1440)}</span></div>
      <div style="display:flex;justify-content:space-between;gap:14px;margin-bottom:2px"><span style="color:#9AA0AA;font-size:10px">Czas</span><span style="color:#333;font-weight:500">${hm(dur)}</span></div>
      ${dateStr?`<div style="margin-top:5px;font-size:9px;color:#BFC5CC">${dateStr}</div>`:''}`;
    tooltip.style.display='block';
    tooltip.style.borderLeft=`3px solid ${col}`;
    tooltip.style.left=(e.clientX+16)+'px';
    tooltip.style.top=(e.clientY-50)+'px';
  });
  svgEl.addEventListener('mouseleave', ()=>{ tooltip.style.display='none'; });

  // Click: day link or country crossing
  svgEl.addEventListener('click', e=>{
    // Country crossing (uses separate data-from/to/date/time attributes)
    const crossG=e.target.closest('[data-from]');
    if(crossG&&crossG.dataset.to){
      showCrossModal({from:crossG.dataset.from,to:crossG.dataset.to,date:crossG.dataset.crossDate,timeLabel:crossG.dataset.crossTime});
      return;
    }
    // Day link
    const dayG=e.target.closest('[data-day-url]');
    if(dayG){ window.location.href=dayG.dataset.dayUrl; return; }
  });

  return wrapper;
}

/** Show country crossing modal */
function showCrossModal(c){
  const csF=ccStyle(c.from||'?');
  const cs=ccStyle(c.to||'?');
  document.getElementById('tz-modal-body').innerHTML=`
    <div style="display:flex;align-items:center;gap:6px;margin-bottom:14px">
      <div style="font-size:11px;color:#9AA0AA">Przekroczenie granicy</div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
      <div style="padding:4px 10px;background:${esc(csF.bg)};border:1px solid ${csF.bd};border-radius:3px;font-size:12px;color:${csF.tx};font-weight:700">${esc(c.from||'?')}</div>
      <span style="font-size:12px;color:#9AA0AA">→ wjazd do</span>
      <div style="padding:4px 10px;background:${esc(cs.bg)};border:1px solid ${cs.bd};border-radius:3px;font-size:12px;color:${cs.tx};font-weight:700">${esc(c.to||'?')}</div>
    </div>
    <div style="display:flex;gap:16px">
      <div><div style="font-size:9px;color:#9AA0AA;font-weight:600;margin-bottom:3px">DATA</div><div style="font-size:14px;font-weight:600;color:#1A2030">${esc(c.date||'—')}</div></div>
      <div><div style="font-size:9px;color:#9AA0AA;font-weight:600;margin-bottom:3px">GODZINA</div><div style="font-size:14px;font-weight:600;color:#1A2030">${esc(c.timeLabel||'—')}</div></div>
    </div>`;
  const modal=document.getElementById('tz-modal');
  modal.style.display='flex';
  modal.onclick=e=>{ if(e.target===modal) modal.style.display='none'; };
}

/** Rebuild all week rows */
function render(){
  chartWidth=Math.max(400, chartEl.getBoundingClientRect().width - LW - 2);
  chartEl.innerHTML='';
  // sel overlay
  if(selStart!==null&&selEnd!==null&&Math.abs(selEnd-selStart)>4){
    const selDiv=document.createElement('div');
    const sx=LW+Math.min(selStart,selEnd),sw=Math.abs(selEnd-selStart);
    const dur=ve-vs;
    selDiv.style.cssText=`position:absolute;top:0;left:${sx}px;width:${sw}px;height:100%;background:rgba(30,136,229,0.1);border:1px solid #1E88E5;border-radius:2px;pointer-events:none;z-index:10`;
    selDiv.innerHTML=`<div style="position:absolute;top:4px;left:50%;transform:translateX(-50%);background:#1E88E5;color:#fff;font-size:9px;padding:2px 7px;border-radius:2px;white-space:nowrap;font-family:Inter,sans-serif;font-weight:600">${hm(Math.round((sw/chartWidth)*dur))}</div>`;
    chartEl.appendChild(selDiv);
  }
  allWeeks.forEach(wk=>{ chartEl.appendChild(buildWeekRow(wk)); });
  // Update range label
  const days=((ve-vs)/1440);
  document.getElementById('tz-range-label').textContent=days.toFixed(1)+' dni';
}

// ── Zoom helpers ──────────────────────────────────────────────────────────
function tzZoom(newDur,anchorMin){
  newDur=clamp(newDur,360,7*1440);
  const mid=anchorMin!=null?anchorMin:(vs+ve)/2;
  let ns=mid-newDur/2, ne=mid+newDur/2;
  if(ns<0){ns=0;ne=newDur;}
  if(ne>7*1440){ne=7*1440;ns=7*1440-newDur;}
  vs=clamp(ns,0,7*1440);ve=clamp(ne,0,7*1440);
  render();
}
function tzZoomIn(){
  const c=(vs+ve)/2;
  tzZoom((ve-vs)/2,c);
}
function tzZoomOut(){
  const c=(vs+ve)/2,nd=clamp((ve-vs)*1.6,ve-vs,7*1440);
  tzZoom(nd,c);
}
function tzMode(m){
  mode=m;
  document.getElementById('tz-mode-sel').classList.toggle('tzb-active',m==='select');
  document.getElementById('tz-mode-pan').classList.toggle('tzb-active',m==='pan');
  chartEl.style.cursor=m==='pan'?'grab':'crosshair';
}
window.tzZoom=tzZoom;window.tzZoomIn=tzZoomIn;window.tzZoomOut=tzZoomOut;window.tzMode=tzMode;

// ── Mouse wheel zoom ──────────────────────────────────────────────────────
chartEl.addEventListener('wheel', e=>{
  e.preventDefault();
  const rect=chartEl.getBoundingClientRect();
  const mx=e.clientX-rect.left-LW;
  if(mx<0||mx>chartWidth) return;
  const mMin=vs+(mx/chartWidth)*(ve-vs);
  const fac=e.deltaY>0?1.3:0.77;
  let nd=clamp((ve-vs)*fac,360,7*1440);
  tzZoom(nd,mMin);
}, {passive:false});

// ── Mouse drag / select ──────────────────────────────────────────────────
chartEl.addEventListener('mousedown', e=>{
  if(e.button!==0) return;
  e.preventDefault();
  const rect=chartEl.getBoundingClientRect();
  const mx=e.clientX-rect.left-LW;
  if(mx<0||mx>chartWidth) return;
  if(mode==='pan'){ panStart={clientX:e.clientX,vs,ve}; chartEl.style.cursor='grabbing'; }
  else { selStart=mx; selEnd=mx; }
});
window.addEventListener('mousemove', e=>{
  if(!panStart&&selStart===null) return;
  const rect=chartEl.getBoundingClientRect();
  const mx=clamp(e.clientX-rect.left-LW,0,chartWidth);
  if(mode==='pan'&&panStart){
    const dx=e.clientX-panStart.clientX;
    const shift=(dx/chartWidth)*(ve-vs)*-1;
    let ns=panStart.vs+shift, ne=panStart.ve+shift;
    if(ns<0){ns=0;ne=ne-ns;}if(ne>7*1440){ne=7*1440;ns=ns-(ne-7*1440);}
    vs=clamp(ns,0,7*1440);ve=clamp(ne,0,7*1440);
    render();
  } else if(selStart!==null){
    selEnd=mx; render();
  }
});
window.addEventListener('mouseup', ()=>{
  if(mode==='pan'){ panStart=null; chartEl.style.cursor='grab'; }
  else if(selStart!==null){
    const a=Math.min(selStart,selEnd||selStart);
    const b=Math.max(selStart,selEnd||selStart);
    if(b-a>10){
      const dur=ve-vs;
      const ns=vs+(a/chartWidth)*dur;
      const ne=vs+(b/chartWidth)*dur;
      vs=ns;ve=ne;
    }
    selStart=null;selEnd=null;
    render();
  }
});
chartEl.addEventListener('mouseleave', ()=>{ panStart=null; });

// ── Resize ────────────────────────────────────────────────────────────────
let resizeTimer;
window.addEventListener('resize', ()=>{
  clearTimeout(resizeTimer);
  resizeTimer=setTimeout(render, 80);
});

// ── Initial render ────────────────────────────────────────────────────────
render();

})();
</script>

<!-- Floating tooltip (shared) -->
<div id="tz-tooltip"
     style="display:none;position:fixed;background:#FFF;border:1px solid #E0E4E8;border-left:3px solid #1E88E5;
            padding:9px 13px;border-radius:4px;pointer-events:none;z-index:9999;
            font-family:Inter,sans-serif;font-size:12px;box-shadow:0 6px 24px rgba(0,0,0,.12);
            min-width:155px;max-width:220px"></div>

<style>
.tzb{
  background:#FFF;border:1px solid #DDE1E6;color:#5A6070;
  padding:4px 10px;border-radius:4px;font-size:10px;
  font-family:Inter,sans-serif;cursor:pointer;
}
.tzb:hover{background:#F0F4FF;border-color:#1E88E5;color:#1E88E5}
.tzb-active{background:#E3F2FD!important;border-color:#1E88E5!important;color:#1E88E5!important;font-weight:600}
@media print{
  #sidebar,.topbar,.btn,select,.tzb,#tz-toolbar{display:none!important}
  .main-wrapper{margin:0!important}
  #tacho-card{border:1px solid #ccc!important;box-shadow:none!important}
}
</style>
