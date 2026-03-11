<?php
/**
 * @var array      $drivers
 * @var array|null $fileId
 * @var array      $files
 */
$tachoFileId = (int)($_GET['file_id'] ?? 0);
?>
<!-- ── Page header ─────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0">Delegacja – Pakiet Mobilności UE</h4>
    <p class="text-muted small mb-0">Oblicz wynagrodzenie i diety zgodnie z dyrektywą 2020/1057/UE</p>
  </div>
  <?php if ($tachoFileId): ?>
  <a href="/analysis/<?= $tachoFileId ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Powrót do analizatora
  </a>
  <?php endif; ?>
</div>

<!-- ── Delegation container ───────────────────────────────────────────── -->
<div id="delegation-root"></div>

<!-- React 18 + Babel standalone (CDN) -->
<script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

<script>
  const TACHO_FILE_ID = <?= $tachoFileId ?: 'null' ?>;
</script>

<!-- ── Delegation Calculator ────────────────────────────────────────── -->
<script type="text/babel">
const { useState, useRef, useEffect, useMemo, useCallback } = React;


// ═══════════════════════════════════════════════════════════════
// TACHOGRAPH CONSTANTS
// ═══════════════════════════════════════════════════════════════
const EU = { maxWeek:3360, maxDay:540, maxDayEx:600, minRest:660, maxCont:270 };
const LW=74, T1Y=32, T1H=36, T2Y=76, T2H=18, AXY=102, RH=120;
const ACT_FILL  =["#80DEEA","#9FA8DA","#FFCC80","#EF9A9A"];
const ACT_SOLID =["#00ACC1","#5C6BC0","#EF6C00","#E53935"];
const ACT_STROKE=["#00838F","#3949AB","#BF360C","#C62828"];
const ACT_TEXT  =["#006064","#1A237E","#BF360C","#B71C1C"];
const ACT_NAME  =["Odpoczynek","Dyspozycyjnosc","Praca","Jazda"];
const ACT_HFRAC =[0.30, 0.52, 0.72, 1.00];

const CC={
  PL:{bg:"#FFEBEE",bd:"#E53935",tx:"#C62828"},DE:{bg:"#FFFDE7",bd:"#F9A825",tx:"#E65100"},
  CZ:{bg:"#E3F2FD",bd:"#1E88E5",tx:"#1565C0"},SK:{bg:"#E8F5E9",bd:"#43A047",tx:"#2E7D32"},
  AT:{bg:"#FFF3E0",bd:"#EF6C00",tx:"#BF360C"},HU:{bg:"#F3E5F5",bd:"#8E24AA",tx:"#6A1B9A"},
  FR:{bg:"#E8EAF6",bd:"#3949AB",tx:"#283593"},NL:{bg:"#FBE9E7",bd:"#FF7043",tx:"#BF360C"},
};
function ccStyle(code){ return CC[code]||{bg:"#F5F5F5",bd:"#9E9E9E",tx:"#616161"}; }

const BORDER_DB={
  "PL-DE":[{name:"Slubice/Frankfurt(Oder)",lat:52.3483,lon:14.5533,road:"A2/E30"},{name:"Olszyna/Forst",lat:51.355,lon:15.001,road:"A18/E36"},{name:"Zgorzelec/Goerlitz",lat:51.153,lon:14.997,road:"A4/E40"},{name:"Kolbaskowo/Pomellen",lat:53.533,lon:14.42,road:"A6/E28"}],
  "DE-PL":[{name:"Frankfurt(Oder)/Slubice",lat:52.3483,lon:14.5533,road:"A2/E30"},{name:"Forst/Olszyna",lat:51.355,lon:15.001,road:"A18/E36"},{name:"Goerlitz/Zgorzelec",lat:51.153,lon:14.997,road:"A4/E40"}],
  "PL-CZ":[{name:"Cieszyn/Cesky Tesin",lat:49.7497,lon:18.6321,road:"E75"},{name:"Kudowa-Slone/Nachod",lat:50.4308,lon:16.2467,road:"E67"}],
  "CZ-PL":[{name:"Cesky Tesin/Cieszyn",lat:49.7497,lon:18.6321,road:"E75"},{name:"Nachod/Kudowa-Slone",lat:50.4308,lon:16.2467,road:"E67"}],
  "PL-SK":[{name:"Chyzne/Trstena",lat:49.4167,lon:19.6167,road:"E77"},{name:"Zwardon/Makov",lat:49.5,lon:18.9667,road:"E75"}],
  "SK-PL":[{name:"Trstena/Chyzne",lat:49.4167,lon:19.6167,road:"E77"},{name:"Makov/Zwardon",lat:49.5,lon:18.9667,road:"E75"}],
  "CZ-SK":[{name:"Mosty u Jablunkova/Cadca",lat:49.5397,lon:18.7667,road:"E75"}],
  "SK-CZ":[{name:"Cadca/Mosty u Jablunkova",lat:49.5397,lon:18.7667,road:"E75"}],
  "SK-HU":[{name:"Sturovo/Esztergom",lat:47.7986,lon:18.7036,road:"E77"},{name:"Komarno/Komarom",lat:47.7595,lon:18.1286,road:"E575"}],
  "HU-SK":[{name:"Esztergom/Sturovo",lat:47.7986,lon:18.7036,road:"E77"}],
  "DE-NL":[{name:"Venlo/Kaldenkirchen",lat:51.3667,lon:6.1675,road:"A61/E31"},{name:"Oldenzaal/Bad Bentheim",lat:52.3133,lon:6.9286,road:"A1/E30"},{name:"Emmerich/Elten",lat:51.8386,lon:6.2428,road:"A3/E35"}],
  "NL-DE":[{name:"Kaldenkirchen/Venlo",lat:51.3667,lon:6.1675,road:"A61/E31"},{name:"Bad Bentheim/Oldenzaal",lat:52.3133,lon:6.9286,road:"A1/E30"}],
  "DE-AT":[{name:"Freilassing/Salzburg",lat:47.7939,lon:12.9583,road:"A1/E60"},{name:"Kufstein/Kiefersfelden",lat:47.6097,lon:12.1764,road:"A93/E45"}],
  "AT-DE":[{name:"Salzburg/Freilassing",lat:47.7939,lon:12.9583,road:"A1/E60"},{name:"Kiefersfelden/Kufstein",lat:47.6097,lon:12.1764,road:"A93/E45"}],
};
function getCrossings(from,to){const key=from+"-"+to;return BORDER_DB[key]||[{name:from+" > "+to+" (brak danych)",lat:50.06,lon:19.94,road:"-"}];}

// ═══════════════════════════════════════════════════════════════
// DELEGATION CONSTANTS
// ═══════════════════════════════════════════════════════════════
const DEFAULT_COUNTRIES=[
  {code:"DE",name:"Niemcy",flag:"🇩🇪",dietRate:49,minWageEUR:12.41,currency:"EUR"},
  {code:"FR",name:"Francja",flag:"🇫🇷",dietRate:50,minWageEUR:11.65,currency:"EUR"},
  {code:"NL",name:"Holandia",flag:"🇳🇱",dietRate:45,minWageEUR:13.27,currency:"EUR"},
  {code:"BE",name:"Belgia",flag:"🇧🇪",dietRate:45,minWageEUR:11.08,currency:"EUR"},
  {code:"IT",name:"Włochy",flag:"🇮🇹",dietRate:48,minWageEUR:9.50,currency:"EUR"},
  {code:"ES",name:"Hiszpania",flag:"🇪🇸",dietRate:50,minWageEUR:9.10,currency:"EUR"},
  {code:"AT",name:"Austria",flag:"🇦🇹",dietRate:52,minWageEUR:12.38,currency:"EUR"},
  {code:"CH",name:"Szwajcaria",flag:"🇨🇭",dietRate:88,minWageEUR:24.00,currency:"CHF"},
  {code:"NO",name:"Norwegia",flag:"🇳🇴",dietRate:82,minWageEUR:20.00,currency:"NOK"},
  {code:"SE",name:"Szwecja",flag:"🇸🇪",dietRate:64,minWageEUR:14.00,currency:"SEK"},
  {code:"DK",name:"Dania",flag:"🇩🇰",dietRate:76,minWageEUR:18.00,currency:"DKK"},
  {code:"CZ",name:"Czechy",flag:"🇨🇿",dietRate:45,minWageEUR:5.33,currency:"CZK"},
  {code:"SK",name:"Słowacja",flag:"🇸🇰",dietRate:45,minWageEUR:5.74,currency:"EUR"},
  {code:"HU",name:"Węgry",flag:"🇭🇺",dietRate:50,minWageEUR:4.50,currency:"HUF"},
  {code:"RO",name:"Rumunia",flag:"🇷🇴",dietRate:45,minWageEUR:3.74,currency:"RON"},
  {code:"PL",name:"Polska",flag:"🇵🇱",dietRate:45,minWageEUR:5.82,currency:"PLN"},
];
const MOBILITY_PACKAGE_INFO={
  cabotage:{label:"Kabotaż",description:"Maks. 3 operacje w 7 dni"},
  crossTrade:{label:"Cross-trade",description:"Każda operacja cross-trade"},
  international:{label:"Tranzyt międzynarodowy",description:"Przy wjeździe do kraju"},
};
const SAMPLE_CSV=`imie,nazwisko,pesel,nr_prawa_jazdy,kategoria,data_zatrudnienia,wynagrodzenie_podstawowe
Jan,Kowalski,85010112345,PL123456,C+E,2020-03-15,5500`;

// ═══════════════════════════════════════════════════════════════
// SHARED UTILITIES
// ═══════════════════════════════════════════════════════════════
function isoWeek(d){const t=new Date(Date.UTC(d.getFullYear(),d.getMonth(),d.getDate()));t.setUTCDate(t.getUTCDate()+4-(t.getUTCDay()||7));return Math.ceil((((t-new Date(Date.UTC(t.getUTCFullYear(),0,1)))/864e5)+1)/7);}
function monDay(d){const r=new Date(d),dw=r.getDay();r.setDate(r.getDate()-(dw===0?6:dw-1));r.setHours(0,0,0,0);return r;}
function addD(d,n){const r=new Date(d);r.setDate(r.getDate()+n);return r;}
function hhmm(m){return String(Math.floor(m/60)).padStart(2,"0")+":"+String(m%60).padStart(2,"0");}
function hm(m){const h=Math.floor(m/60),mm=m%60;return mm?h+"h "+mm+"m":h+"h";}
function fmtDate(d){return String(d.getDate()).padStart(2,"0")+"."+String(d.getMonth()+1).padStart(2,"0")+"."+d.getFullYear();}
function fmtNum(n,decimals=2){return Number(n).toLocaleString("pl-PL",{minimumFractionDigits:decimals,maximumFractionDigits:decimals});}
function clamp(v,a,b){return Math.max(a,Math.min(b,v));}
function diffDays(from,to){if(!from||!to)return 0;return Math.max(0,Math.round((new Date(to)-new Date(from))/86400000));}
function toInputDate(d){if(!d)return"";const dd=new Date(d);return dd.getFullYear()+"-"+String(dd.getMonth()+1).padStart(2,"0")+"-"+String(dd.getDate()).padStart(2,"0");}

function dayStatus(slots){
  if(!slots||!slots.length)return null;
  const drive=slots.filter(s=>s.activity===3).reduce((a,s)=>a+s.duration,0);
  const rest=slots.filter(s=>s.activity===0).reduce((a,s)=>a+s.duration,0);
  let e=false,w=false;
  if(drive>EU.maxDayEx)e=true;else if(drive>EU.maxDay)w=true;
  let cont=0,maxC=0;
  slots.forEach(s=>{if(s.activity===3){cont+=s.duration;if(cont>maxC)maxC=cont;}else if(s.activity===0&&s.duration>=15)cont=0;});
  if(maxC>EU.maxCont)w=true;
  if(rest<EU.minRest&&drive>0)w=true;
  return e?"error":w?"warn":"ok";
}

function parseCSV(text){
  const lines=text.trim().split("\n");
  const headers=lines[0].split(",").map(h=>h.trim());
  return lines.slice(1).map(line=>{const vals=line.split(",").map(v=>v.trim());return Object.fromEntries(headers.map((h,i)=>[h,vals[i]||""]));});
}

function parseDDD(buffer){
  // EU tachograph driver card – circular buffer parser
  // Confirmed bit layout (binary analysis):
  //   bit15=slot(0=driver), bit14=manning, bits13-11=act(0=REST,1=AVAIL,2=WORK,3=DRIVE), bits10-0=time_min
  // Record header: TimeReal(4) + presenceCounter(2) + distanceKm(2) + entries(2*n)
  // presenceCounter increments by 1 per day → use it for chronological ordering
  // Driver card buffer is circular: new data at "head", wraps around

  const u8=new Uint8Array(buffer),dv=new DataView(buffer);
  const len=u8.length;

  // ── 1. Driver name ──
  let driver=null,vehicle=null;
  const readStr=(s,n)=>{let o='';for(let k=0;k<n&&s+k<len;k++){const b=u8[s+k];o+=(b>=32&&b<127)?String.fromCharCode(b):'\0';}return o;};
  for(let i=0;i<len-4;i++){
    if(u8[i]!==0x05||u8[i+1]!==0x20)continue;
    const bl=dv.getUint16(i+2,false);
    if(bl<40||bl>3000||i+4+bl>len)continue;
    for(let k=0;k<bl-72;k++){
      const b=u8[i+4+k];
      if(b<65||b>90)continue;
      const sn=readStr(i+4+k,36).replace(/\0/g,'').trim();
      const fn=readStr(i+4+k+36,36).replace(/\0/g,'').trim();
      if(sn.length>=3&&/^[A-Z][a-z]{2}/.test(sn)&&fn.length>=2){driver=(fn+' '+sn).trim();break;}
    }
    if(driver)break;
  }

  // ── 2. Vehicle reg ──
  for(let i=0;i<len-14;i++){
    const s=readStr(i,14).trim();
    if(/^[A-Z]{2,4}\s[A-Z0-9]{4,6}$/.test(s)){vehicle=s;break;}
  }

  // ── 3. Find ALL candidate record headers (pres 500-8000, dist≤1100, year 2023-2027) ──
  const cands=[];
  for(let i=0;i<len-8;i+=2){
    let ts,yr;
    try{ts=dv.getUint32(i,false);yr=new Date(ts*1000).getUTCFullYear();}catch(e_){continue;}
    const _yr=new Date().getUTCFullYear();if(yr<_yr-3||yr>_yr+2)continue;
    const pres=dv.getUint16(i+4,false);
    const dist=dv.getUint16(i+6,false);
    if(pres<500||pres>8000||dist>1100)continue;
    cands.push({off:i,ts,pres,dist});
  }

  // ── 4. Deduplicate by date: per date keep the record with median presenceCounter ──
  const byDate={};
  for(const c of cands){
    const d=new Date(c.ts*1000);
    const k=d.toISOString().slice(0,10);
    if(!byDate[k])byDate[k]=[];
    byDate[k].push(c);
  }
  // Pick the candidate with presCounter closest to the median for that date
  const deduped=Object.values(byDate).map(arr=>{
    arr.sort((a,b)=>a.pres-b.pres);
    return arr[Math.floor(arr.length/2)]; // median
  });

  // ── 5. Sort by presenceCounter (chronological) ──
  deduped.sort((a,b)=>a.pres-b.pres);

  // ── 6. Filter outlier presenceCounts (remove records far from main cluster) ──
  const presVals=deduped.map(r=>r.pres).sort((a,b)=>a-b);
  const p25=presVals[Math.floor(presVals.length*0.25)],p75=presVals[Math.floor(presVals.length*0.75)];
  const iqr=p75-p25;
  const presMin=p25-3*iqr,presMax=p75+3*iqr;
  const filtered=deduped.filter(r=>r.pres>=presMin&&r.pres<=presMax);

  // ── 7. Build a lookup of offset→next-record-offset for bounded entry scanning ──
  const offsets=filtered.map(r=>r.off).sort((a,b)=>a-b);
  const nextOff=(off)=>{
    const idx=offsets.indexOf(off);
    return idx>=0&&idx<offsets.length-1?offsets[idx+1]:off+400;
  };

  // ── 8. Parse activity entries for each record ──
  const mkSlots=pts=>{
    // Strictly monotonic filter
    const mono=[];let lt=-1;
    for(const p of pts){if(p.tmin>lt){mono.push(p);lt=p.tmin;}}
    return mono.map((p,i)=>({activity:p.act,startMin:p.tmin,
      endMin:i<mono.length-1?mono[i+1].tmin:1440}))
      .filter(s=>s.endMin>s.startMin).map(s=>({...s,duration:s.endMin-s.startMin}));
  };

  const days=[];
  for(const r of filtered){
    const bound=Math.min(nextOff(r.off),r.off+600,len-1);
    const pts=[];
    for(let j=r.off+8;j<bound-1;j+=2){
      const raw=dv.getUint16(j,false);
      const slot=(raw>>15)&1,act=(raw>>11)&7,tmin=raw&0x7FF;
      if(slot===0&&act<=3&&tmin>=0&&tmin<=1440)pts.push({act,tmin});
    }
    const slots=mkSlots(pts);
    const total=slots.reduce((s,x)=>s+x.duration,0);
    if(total<1350||total>1460)continue;
    days.push({date:new Date(r.ts*1000),slots,distance:r.dist,crossings:[],vehicle});
  }

  if(!days.length)return null;
  return{driver,days};
}


function extractDelegationFromTacho(tachoData) {
  if (!tachoData || !tachoData.days || !tachoData.days.length) return null;
  const sortedDays = [...tachoData.days].sort((a, b) => a.date - b.date);
  const nameParts = (tachoData.driver || '').split(' ').filter(Boolean);
  const imie = nameParts[0] || '';
  const nazwisko = nameParts.slice(1).join(' ') || '';
  const firstDay = sortedDays[0].date;
  const lastDay = sortedDays[sortedDays.length - 1].date;

  const allCrossings = [];
  sortedDays.forEach(day => {
    (day.crossings || []).forEach(c => {
      allCrossings.push({ date: new Date(day.date), atMin: c.atMin, from: c.from || 'PL', to: c.to });
    });
  });
  allCrossings.sort((a, b) => a.date - b.date || a.atMin - b.atMin);

  const countryMap = {};
  let currentCountry = allCrossings.length ? allCrossings[0].from : 'PL';
  sortedDays.forEach(day => {
    const dayCrossings = allCrossings.filter(c => c.date.toDateString() === day.date.toDateString());
    const driveMin = day.slots.filter(s => s.activity === 3).reduce((a, s) => a + s.duration, 0);
    const driveHours = driveMin / 60;
    if (!countryMap[currentCountry]) countryMap[currentCountry] = { days: 0, hours: 0 };
    countryMap[currentCountry].days += 1;
    countryMap[currentCountry].hours += driveHours;
    if (dayCrossings.length) currentCountry = dayCrossings[dayCrossings.length - 1].to;
  });

  const trasa = Object.entries(countryMap)
    .filter(([, v]) => v.days > 0)
    .map(([country, v]) => ({
      country,
      days: v.days,
      hours: Math.max(1, Math.min(13, Math.round(v.hours / v.days) || 8)),
      operationType: country === 'PL' ? 'international' : 'cabotage',
      kilometers: 0,
    }));

  const vehicle = sortedDays.find(d => d.vehicle)?.vehicle || '';
  return {
    driver: { imie, nazwisko, pesel: '', nr_prawa_jazdy: '', kategoria: 'C+E', data_zatrudnienia: '', wynagrodzenie_podstawowe: '' },
    trip: {
      nr_delegacji: `DEL/${new Date().getFullYear()}/${String(Math.floor(Math.random()*999)+1).padStart(3,'0')}`,
      data_wyjazdu: toInputDate(firstDay),
      data_powrotu: toInputDate(lastDay),
      nr_rejestracyjny: vehicle,
      cel_podrozy: '',
      trasa: trasa.length ? trasa : [{ country: 'PL', days: 1, hours: 8, operationType: 'international', kilometers: 0 }],
    }
  };
}

// ═══════════════════════════════════════════════════════════════
// TACHOGRAPH COMPONENTS
// ═══════════════════════════════════════════════════════════════
function TachoSym({act,cx,cy,s}){
  const sc=s||1;const col=ACT_TEXT[act];
  if(act===0)return(<g transform={"translate("+cx+","+cy+") scale("+sc+")"} style={{pointerEvents:"none"}}><rect x={-6} y={1} width={12} height={5} rx={1.5} fill={col} opacity={0.85}/><rect x={-6} y={-2} width={5} height={3.5} rx={1.5} fill={col} opacity={0.7}/><rect x={-7} y={-4} width={2} height={9} rx={1} fill={col} opacity={0.9}/><rect x={5} y={-1} width={2} height={7} rx={1} fill={col} opacity={0.9}/></g>);
  if(act===3){const r=5.5,ri=2;return(<g transform={"translate("+cx+","+cy+") scale("+sc+")"} style={{pointerEvents:"none"}}><circle r={r} fill="none" stroke={col} strokeWidth={1.8} opacity={0.9}/><circle r={ri} fill={col} opacity={0.85}/><line x1={0} y1={-ri} x2={0} y2={-r} stroke={col} strokeWidth={1.4}/><line x1={ri*0.87} y1={ri*0.5} x2={r*0.87} y2={r*0.5} stroke={col} strokeWidth={1.4}/><line x1={-ri*0.87} y1={ri*0.5} x2={-r*0.87} y2={r*0.5} stroke={col} strokeWidth={1.4}/></g>);}
  if(act===2)return(<g transform={"translate("+cx+","+cy+") scale("+sc+")"} style={{pointerEvents:"none"}}><line x1={-4} y1={4} x2={1} y2={-1} stroke={col} strokeWidth={1.6} strokeLinecap="round"/><rect x={0} y={-5} width={5} height={3} rx={0.8} fill={col} opacity={0.9} transform="rotate(-45,2.5,-3.5)"/><line x1={4} y1={4} x2={-1} y2={-1} stroke={col} strokeWidth={1.6} strokeLinecap="round"/><rect x={-5} y={-5} width={5} height={3} rx={0.8} fill={col} opacity={0.9} transform="rotate(45,-2.5,-3.5)"/></g>);
  if(act===1)return(<g transform={"translate("+cx+","+cy+") scale("+sc+")"} style={{pointerEvents:"none"}}><polygon points="-5,-5 5,-5 0,0" fill={col} opacity={0.6}/><polygon points="-5,5 5,5 0,0" fill={col} opacity={0.85}/><line x1={-5} y1={-5} x2={5} y2={-5} stroke={col} strokeWidth={1.5} strokeLinecap="round"/><line x1={-5} y1={5} x2={5} y2={5} stroke={col} strokeWidth={1.5} strokeLinecap="round"/></g>);
  return null;
}

function CrossingModal({crossing,onClose}){
  const [idx,setIdx]=useState(0);
  if(!crossing)return null;
  const {from,to,date,timeLabel}=crossing;
  const options=getCrossings(from,to);
  const safeIdx=Math.min(idx,options.length-1);
  const loc=options[safeIdx];
  const cs=ccStyle(to);const csF=ccStyle(from);
  return(
    <div onClick={onClose} style={{position:"fixed",inset:0,background:"rgba(0,0,0,0.45)",zIndex:10000,display:"flex",alignItems:"center",justifyContent:"center"}}>
      <div onClick={e=>e.stopPropagation()} style={{background:"#FFF",borderRadius:8,width:380,maxWidth:"95vw",boxShadow:"0 20px 60px rgba(0,0,0,0.3)",overflow:"hidden"}}>
        <div style={{padding:"12px 16px",background:"#F8F9FB",borderBottom:"1px solid #E0E4E8",display:"flex",alignItems:"center",gap:8}}>
          <div style={{padding:"2px 8px",background:csF.bg,border:"1px solid "+csF.bd,borderRadius:4,fontSize:11,fontWeight:700,color:csF.tx}}>{from}</div>
          <span style={{fontSize:14,color:"#9AA0AA"}}>→</span>
          <div style={{padding:"2px 8px",background:cs.bg,border:"1px solid "+cs.bd,borderRadius:4,fontSize:11,fontWeight:700,color:cs.tx}}>{to}</div>
          <div style={{marginLeft:"auto",fontSize:10,color:"#9AA0AA"}}>{date} {timeLabel}</div>
          <button onClick={onClose} style={{background:"none",border:"none",fontSize:16,color:"#9AA0AA",cursor:"pointer",padding:"0 2px"}}>✕</button>
        </div>
        <div style={{padding:"14px 16px"}}>
          <div style={{fontSize:11,fontWeight:700,color:"#5A6070",marginBottom:8,textTransform:"uppercase",letterSpacing:1}}>Przejście graniczne</div>
          {options.map((o,i)=>(
            <div key={i} onClick={()=>setIdx(i)} style={{padding:"8px 12px",marginBottom:6,borderRadius:6,border:"1.5px solid "+(i===safeIdx?cs.bd:"#E0E4E8"),background:i===safeIdx?cs.bg:"#FAFBFC",cursor:"pointer",transition:"all .15s"}}>
              <div style={{fontWeight:600,fontSize:12,color:i===safeIdx?cs.tx:"#1A2030"}}>{o.name}</div>
              <div style={{fontSize:10,color:"#9AA0AA",marginTop:2}}>Droga: {o.road} | {o.lat.toFixed(4)}°N {o.lon.toFixed(4)}°E</div>
            </div>
          ))}
          <a href={`https://maps.google.com/?q=${loc.lat},${loc.lon}`} target="_blank" rel="noreferrer"
            style={{display:"block",marginTop:8,padding:"8px",background:cs.bg,border:"1px solid "+cs.bd,borderRadius:6,textAlign:"center",fontSize:12,fontWeight:600,color:cs.tx,textDecoration:"none"}}>
            📍 Otwórz w Google Maps
          </a>
        </div>
      </div>
    </div>
  );
}

function DayModal({day,onClose}){
  const dow=["Niedziela","Poniedzialek","Wtorek","Sroda","Czwartek","Piatek","Sobota"];
  if(!day)return null;
  const dowd=day.date.getDay();
  const drive=day.slots.filter(s=>s.activity===3).reduce((a,s)=>a+s.duration,0);
  const work=day.slots.filter(s=>s.activity===2).reduce((a,s)=>a+s.duration,0);
  const rest=day.slots.filter(s=>s.activity===0).reduce((a,s)=>a+s.duration,0);
  const avail=day.slots.filter(s=>s.activity===1).reduce((a,s)=>a+s.duration,0);
  const st=dayStatus(day.slots);
  const stCol=st==="error"?"#E53935":st==="warn"?"#FF9800":"#43A047";
  const stLbl=st==="error"?"Naruszenie":st==="warn"?"Ostrzezenie":"Zgodny";
  return(
    <div onClick={onClose} style={{position:"fixed",inset:0,background:"rgba(0,0,0,0.5)",zIndex:10000,display:"flex",alignItems:"center",justifyContent:"center",padding:16}}>
      <div onClick={e=>e.stopPropagation()} style={{background:"#FFF",borderRadius:8,width:560,maxWidth:"100%",maxHeight:"85vh",display:"flex",flexDirection:"column",boxShadow:"0 24px 64px rgba(0,0,0,0.3)",overflow:"hidden"}}>
        <div style={{padding:"14px 18px",background:"#F0F4F8",borderBottom:"1px solid #E0E4E8",display:"flex",alignItems:"center",gap:12,flexShrink:0}}>
          <div><div style={{fontSize:10,color:"#9AA0AA",fontWeight:600,marginBottom:2}}>{dow[dowd].toUpperCase()}</div><div style={{fontSize:18,fontWeight:700,color:"#1A2030"}}>{fmtDate(day.date)}</div></div>
          {day.vehicle&&<div style={{padding:"3px 10px",background:"#E3F2FD",border:"1px solid #BBDEFB",borderRadius:4,fontSize:11,color:"#1565C0",fontWeight:600}}>{day.vehicle}</div>}
          {day.distance>0&&<div style={{padding:"3px 10px",background:"#F3F4F7",border:"1px solid #DDE1E6",borderRadius:4,fontSize:11,color:"#5A6070",fontWeight:500}}>{day.distance} km</div>}
          <div style={{padding:"3px 10px",background:stCol+"18",border:"1px solid "+stCol+"60",borderRadius:4,fontSize:11,color:stCol,fontWeight:600}}>{stLbl}</div>
          <button onClick={onClose} style={{marginLeft:"auto",background:"none",border:"none",fontSize:18,color:"#9AA0AA",cursor:"pointer",padding:"0 4px",lineHeight:1}}>&#x2715;</button>
        </div>
        <div style={{display:"flex",gap:0,borderBottom:"1px solid #EEF0F4",flexShrink:0}}>
          {[{act:3,val:drive},{act:2,val:work},{act:1,val:avail},{act:0,val:rest}].filter(x=>x.val>0).map(({act,val})=>(
            <div key={act} style={{flex:1,padding:"10px 14px",borderRight:"1px solid #EEF0F4",background:"#FAFBFC"}}>
              <div style={{display:"flex",alignItems:"center",gap:5,marginBottom:3}}><div style={{width:8,height:8,borderRadius:2,background:ACT_SOLID[act]}}/><span style={{fontSize:9,color:"#9AA0AA",fontWeight:600}}>{ACT_NAME[act].toUpperCase()}</span></div>
              <div style={{fontSize:15,fontWeight:700,color:ACT_SOLID[act],fontFamily:"monospace"}}>{hhmm(val)}</div>
            </div>
          ))}
        </div>
        <div style={{overflowY:"auto",flex:1}}>
          <table style={{width:"100%",borderCollapse:"collapse",fontSize:12,fontFamily:"Inter"}}>
            <thead style={{position:"sticky",top:0,zIndex:1}}>
              <tr style={{background:"#F0F4F8"}}>
                {["#","Start","Stop","Czas","Aktywnosc"].map(h=><th key={h} style={{padding:"7px 14px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"2px solid #E0E4E8"}}>{h}</th>)}
              </tr>
            </thead>
            <tbody>
              {day.slots.map((s,i)=>(
                <tr key={i} style={{background:i%2===0?"#FFF":"#F8FAFC",borderBottom:"1px solid #F0F2F5"}}>
                  <td style={{padding:"6px 14px",color:"#BFC5CC",fontSize:10}}>{i+1}</td>
                  <td style={{padding:"6px 14px",fontFamily:"monospace",fontWeight:600,color:"#1A2030"}}>{hhmm(s.startMin)}</td>
                  <td style={{padding:"6px 14px",fontFamily:"monospace",fontWeight:600,color:"#1A2030"}}>{hhmm(s.endMin)}</td>
                  <td style={{padding:"6px 14px",fontFamily:"monospace",fontWeight:700,color:ACT_SOLID[s.activity]}}>{hhmm(s.duration)}</td>
                  <td style={{padding:"6px 14px"}}><span style={{display:"inline-flex",alignItems:"center",gap:6}}><span style={{width:8,height:8,borderRadius:2,background:ACT_SOLID[s.activity],display:"inline-block",flexShrink:0}}/><span style={{fontWeight:600,color:ACT_SOLID[s.activity]}}>{ACT_NAME[s.activity]}</span></span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}


// ═══════════════════════════════════════════════════════════════
// DELEGATION UI HELPERS
// ═══════════════════════════════════════════════════════════════
function Badge({color,children}){
  const styles={
    blue:{background:"#DBEAFE",color:"#1e40af",border:"1px solid #bfdbfe"},
    green:{background:"#d1fae5",color:"#065f46",border:"1px solid #a7f3d0"},
    amber:{background:"#fef3c7",color:"#92400e",border:"1px solid #fde68a"},
    red:{background:"#fee2e2",color:"#991b1b",border:"1px solid #fecaca"},
    slate:{background:"#f1f5f9",color:"#475569",border:"1px solid #e2e8f0"},
  };
  const s=styles[color]||styles.slate;
  return <span style={{...s,fontSize:11,fontWeight:700,padding:"2px 8px",borderRadius:9999,display:"inline-block"}}>{children}</span>;
}
function Card({children,style={}}){return <div style={{background:"#fff",borderRadius:16,border:"1px solid #e2e8f0",boxShadow:"0 1px 4px rgba(0,0,0,0.06)",padding:24,...style}}>{children}</div>;}
function SectionTitle({icon,title,subtitle}){return(<div style={{display:"flex",alignItems:"center",gap:12,marginBottom:20}}><div style={{width:40,height:40,borderRadius:12,background:"linear-gradient(135deg,#3b82f6,#6366f1)",display:"flex",alignItems:"center",justifyContent:"center",color:"#fff",fontSize:18,boxShadow:"0 2px 8px rgba(99,102,241,0.3)",flexShrink:0}}>{icon}</div><div><div style={{fontSize:16,fontWeight:700,color:"#1e293b"}}>{title}</div>{subtitle&&<div style={{fontSize:13,color:"#64748b"}}>{subtitle}</div>}</div></div>);}
function Row({label,value}){return(<div style={{display:"flex",justifyContent:"space-between",gap:16,fontSize:13,marginBottom:4}}><span style={{color:"#64748b",flexShrink:0}}>{label}:</span><span style={{fontWeight:600,color:"#1e293b",textAlign:"right"}}>{value||"—"}</span></div>);}

const DEL_TABS=[{id:"driver",label:"Kierowca",icon:"👤"},{id:"trip",label:"Trasa",icon:"🗺️"},{id:"rates",label:"Stawki",icon:"💶"},{id:"result",label:"Wynik",icon:"📄"}];

// ═══════════════════════════════════════════════════════════════
// DELEGATION PANEL
// ═══════════════════════════════════════════════════════════════
function DelegationPanel({tachoData}) {
  const [activeTab,setActiveTab]=useState("driver");
  const [drivers,setDrivers]=useState([]);
  const [selectedDriver,setSelectedDriver]=useState(null);
  const [manualDriver,setManualDriver]=useState({imie:"",nazwisko:"",pesel:"",nr_prawa_jazdy:"",kategoria:"C+E",data_zatrudnienia:"",wynagrodzenie_podstawowe:""});
  const [driverMode,setDriverMode]=useState("manual");
  const [countries,setCountries]=useState(DEFAULT_COUNTRIES);
  const [trip,setTrip]=useState({nr_delegacji:"DEL/2025/001",data_wyjazdu:"",data_powrotu:"",nr_rejestracyjny:"",cel_podrozy:"",trasa:[{country:"DE",days:1,hours:8,operationType:"international",kilometers:0}]});
  const [result,setResult]=useState(null);
  const [importNotice,setImportNotice]=useState(null);
  const fileRef=useRef();

  const handleFile=useCallback(e=>{
    const file=e.target.files[0];if(!file)return;
    const reader=new FileReader();
    reader.onload=ev=>{try{let data;if(file.name.endsWith(".json"))data=JSON.parse(ev.target.result);else data=parseCSV(ev.target.result);setDrivers(data);setDriverMode("file");if(data.length)setSelectedDriver(data[0]);}catch{alert("Błąd odczytu pliku.");}};
    reader.readAsText(file);
  },[]);

  const doImportFromTacho=useCallback(()=>{
    const extracted=extractDelegationFromTacho(tachoData);
    if(!extracted)return;
    setManualDriver(extracted.driver);
    setDriverMode("manual");
    setTrip(extracted.trip);
    setImportNotice(`Zaimportowano dane tachografu: ${tachoData.driver||"nieznany"} · ${tachoData.days.length} dni · ${extracted.trip.trasa.length} kraj(ów)`);
    setActiveTab("driver");
    setTimeout(()=>setImportNotice(null),6000);
  },[tachoData]);

  useEffect(()=>{
    if(tachoData&&!tachoData.demo){doImportFromTacho();}
  },[tachoData]);

  const activeDriver=driverMode==="file"?selectedDriver:manualDriver;
  const addLeg=()=>setTrip(t=>({...t,trasa:[...t.trasa,{country:"DE",days:1,hours:8,operationType:"international",kilometers:0}]}));
  const removeLeg=i=>setTrip(t=>({...t,trasa:t.trasa.filter((_,idx)=>idx!==i)}));
  const updateLeg=(i,field,val)=>setTrip(t=>({...t,trasa:t.trasa.map((l,idx)=>idx===i?{...l,[field]:val}:l)}));
  const updateCountry=(code,field,val)=>setCountries(cs=>cs.map(c=>c.code===code?{...c,[field]:Number(val)}:c));

  const calculate=()=>{
    const driver=activeDriver;
    if(!driver?.imie&&!driver?.nazwisko){alert("Uzupełnij dane kierowcy.");return;}
    const totalDays=diffDays(trip.data_wyjazdu,trip.data_powrotu);
    let totalDiet=0,totalMinWage=0;const breakdown=[];
    trip.trasa.forEach(leg=>{
      const country=countries.find(c=>c.code===leg.country);if(!country)return;
      const dietAmount=country.dietRate*leg.days;
      const minWageAmount=country.minWageEUR*leg.hours*leg.days;
      totalDiet+=dietAmount;totalMinWage+=minWageAmount;
      breakdown.push({country,leg,dietAmount,minWageAmount,operationType:MOBILITY_PACKAGE_INFO[leg.operationType]?.label||leg.operationType});
    });
    const baseSalary=Number(driver.wynagrodzenie_podstawowe)||0;
    const eurPln=4.28;const minWagePLN=totalMinWage*eurPln;const delta=minWagePLN-baseSalary;const requiresTopUp=delta>0;
    setResult({driver,trip,breakdown,totalDiet,totalMinWage,minWagePLN,baseSalary,delta,requiresTopUp,totalDays,eurPln});
    setActiveTab("result");
  };

  const hasTacho=tachoData&&tachoData.days&&tachoData.days.length>0;
  const extracted=hasTacho?extractDelegationFromTacho(tachoData):null;

  const inp={width:"100%",border:"1px solid #e2e8f0",borderRadius:10,padding:"8px 12px",fontSize:13,fontFamily:"Inter",outline:"none",boxSizing:"border-box"};
  const tabBtn=(id)=>({display:"flex",flex:1,alignItems:"center",justifyContent:"center",gap:6,padding:"8px 12px",borderRadius:10,border:"none",cursor:"pointer",fontSize:13,fontWeight:600,fontFamily:"Inter",transition:"all .15s",background:activeTab===id?"linear-gradient(135deg,#2563eb,#4f46e5)":"transparent",color:activeTab===id?"#fff":"#64748b"});

  return(
    <div style={{display:"flex",flexDirection:"column",gap:16}}>
      {hasTacho&&(
        <div style={{padding:16,borderRadius:16,border:"1px solid #bfdbfe",background:"linear-gradient(135deg,#eff6ff,#eef2ff)",display:"flex",alignItems:"center",gap:12,flexWrap:"wrap"}}>
          <div style={{width:36,height:36,borderRadius:10,background:"#2563eb",display:"flex",alignItems:"center",justifyContent:"center",color:"#fff",fontSize:16,flexShrink:0}}>📡</div>
          <div style={{flex:1,minWidth:0}}>
            <div style={{fontWeight:700,color:"#1e40af",fontSize:14}}>Tachograf wczytany: {tachoData.driver||"Nieznany kierowca"}</div>
            <div style={{fontSize:12,color:"#3b82f6"}}>{tachoData.days.length} dni danych · {tachoData.demo?"DEMO — załaduj plik .ddd aby użyć rzeczywistych danych":"plik rzeczywisty"}</div>
          </div>
          {extracted&&(
            <button onClick={doImportFromTacho} style={{padding:"8px 16px",background:"#2563eb",color:"#fff",border:"none",borderRadius:10,fontSize:13,fontWeight:700,cursor:"pointer",fontFamily:"Inter",display:"flex",alignItems:"center",gap:6}}>
              🔗 Importuj do delegacji
            </button>
          )}
        </div>
      )}
      {importNotice&&(
        <div style={{padding:12,borderRadius:10,border:"1px solid #a7f3d0",background:"#d1fae5",color:"#065f46",fontSize:13,fontWeight:600,display:"flex",alignItems:"center",gap:8}}>
          ✅ {importNotice}
        </div>
      )}

      <div style={{display:"flex",gap:4,background:"#fff",padding:4,borderRadius:14,border:"1px solid #e2e8f0",boxShadow:"0 1px 4px rgba(0,0,0,0.05)"}}>
        {DEL_TABS.map(t=>(<button key={t.id} onClick={()=>setActiveTab(t.id)} style={tabBtn(t.id)}><span>{t.icon}</span><span>{t.label}</span></button>))}
      </div>

      {activeTab==="driver"&&(
        <Card>
          <SectionTitle icon="👤" title="Dane kierowcy" subtitle="Wczytaj plik CSV/JSON lub wpisz ręcznie"/>
          <div style={{display:"flex",gap:8,marginBottom:20}}>
            {["manual","file"].map(m=>(<button key={m} onClick={()=>setDriverMode(m)} style={{padding:"6px 16px",borderRadius:8,fontSize:13,fontWeight:600,border:"1px solid "+(driverMode===m?"#2563eb":"#e2e8f0"),background:driverMode===m?"#2563eb":"#fff",color:driverMode===m?"#fff":"#64748b",cursor:"pointer",fontFamily:"Inter"}}>{m==="manual"?"✏️ Ręcznie":"📂 Z pliku"}</button>))}
          </div>
          {driverMode==="file"?(
            <div style={{display:"flex",flexDirection:"column",gap:12}}>
              <div style={{border:"2px dashed #93c5fd",borderRadius:12,padding:24,textAlign:"center",background:"#eff6ff",cursor:"pointer"}} onClick={()=>fileRef.current?.click()}>
                <div style={{fontSize:32,marginBottom:8}}>📁</div>
                <div style={{fontWeight:600,color:"#1d4ed8"}}>Kliknij aby wczytać plik CSV lub JSON</div>
                <div style={{fontSize:12,color:"#60a5fa",marginTop:4}}>Kolumny: imie, nazwisko, pesel, nr_prawa_jazdy, kategoria, wynagrodzenie_podstawowe</div>
                <input ref={fileRef} type="file" accept=".csv,.json" style={{display:"none"}} onChange={handleFile}/>
              </div>
              <details style={{fontSize:12,color:"#64748b"}}><summary style={{cursor:"pointer",fontWeight:600,color:"#475569"}}>📋 Przykładowy format CSV</summary><pre style={{marginTop:8,padding:12,background:"#f8fafc",borderRadius:8,overflowX:"auto",color:"#334155"}}>{SAMPLE_CSV}</pre></details>
              {drivers.length>0&&(<div><label style={{display:"block",fontSize:12,fontWeight:600,color:"#64748b",marginBottom:6,textTransform:"uppercase",letterSpacing:"0.05em"}}>Wybierz kierowcę ({drivers.length} wczytanych)</label><select style={inp} value={drivers.indexOf(selectedDriver)} onChange={e=>setSelectedDriver(drivers[Number(e.target.value)])}>{drivers.map((d,i)=><option key={i} value={i}>{d.imie} {d.nazwisko} – {d.pesel}</option>)}</select></div>)}
            </div>
          ):(
            <div style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:12}}>
              {[["imie","Imię","text"],["nazwisko","Nazwisko","text"],["pesel","PESEL","text"],["nr_prawa_jazdy","Nr prawa jazdy","text"],["kategoria","Kategoria","text"],["data_zatrudnienia","Data zatrudnienia","date"],["wynagrodzenie_podstawowe","Wynagrodzenie podstawowe (PLN)","number"]].map(([field,label,type])=>(<div key={field} style={field==="wynagrodzenie_podstawowe"?{gridColumn:"1/-1"}:{}}>
                <label style={{display:"block",fontSize:11,fontWeight:600,color:"#94a3b8",marginBottom:4,textTransform:"uppercase",letterSpacing:"0.05em"}}>{label}</label>
                <input type={type} value={manualDriver[field]} onChange={e=>setManualDriver(d=>({...d,[field]:e.target.value}))} style={inp}/>
              </div>))}
            </div>
          )}
          {activeDriver&&(activeDriver.imie||activeDriver.nazwisko)&&(
            <div style={{marginTop:20,padding:16,background:"#f0fdf4",border:"1px solid #86efac",borderRadius:12,display:"flex",alignItems:"center",gap:12,flexWrap:"wrap"}}>
              <div style={{width:44,height:44,borderRadius:"50%",background:"#16a34a",color:"#fff",display:"flex",alignItems:"center",justifyContent:"center",fontWeight:700,fontSize:18,flexShrink:0}}>{(activeDriver.imie?.[0]||"?")}{(activeDriver.nazwisko?.[0]||"")}</div>
              <div><div style={{fontWeight:700,color:"#1e293b",fontSize:15}}>{activeDriver.imie} {activeDriver.nazwisko}</div><div style={{fontSize:12,color:"#64748b"}}>kat. {activeDriver.kategoria} | {activeDriver.nr_prawa_jazdy} | {Number(activeDriver.wynagrodzenie_podstawowe||0).toLocaleString("pl-PL")} PLN/mies.</div></div>
              <Badge color="green">✓ Gotowy</Badge>
            </div>
          )}
          <button onClick={()=>setActiveTab("trip")} style={{marginTop:20,width:"100%",padding:"10px",background:"linear-gradient(135deg,#2563eb,#4f46e5)",color:"#fff",border:"none",borderRadius:12,fontSize:14,fontWeight:700,cursor:"pointer",fontFamily:"Inter"}}>Dalej → Trasa</button>
        </Card>
      )}

      {activeTab==="trip"&&(
        <Card>
          <SectionTitle icon="🗺️" title="Dane trasy i delegacji" subtitle="Uzupełnij szczegóły wyjazdu"/>
          <div style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:12,marginBottom:20}}>
            {[["nr_delegacji","Nr delegacji","text"],["data_wyjazdu","Data wyjazdu","date"],["data_powrotu","Data powrotu","date"],["nr_rejestracyjny","Nr rejestracyjny pojazdu","text"],["cel_podrozy","Cel podróży","text"]].map(([field,label,type])=>(<div key={field} style={field==="cel_podrozy"?{gridColumn:"1/-1"}:{}}>
              <label style={{display:"block",fontSize:11,fontWeight:600,color:"#94a3b8",marginBottom:4,textTransform:"uppercase",letterSpacing:"0.05em"}}>{label}</label>
              <input type={type} value={trip[field]} onChange={e=>setTrip(t=>({...t,[field]:e.target.value}))} style={inp}/>
            </div>))}
          </div>
          <div style={{display:"flex",alignItems:"center",justifyContent:"space-between",marginBottom:12}}>
            <div style={{fontWeight:700,color:"#334155"}}>📍 Odcinki trasy</div>
            <button onClick={addLeg} style={{fontSize:13,background:"#eff6ff",color:"#2563eb",border:"1px solid #bfdbfe",borderRadius:8,padding:"6px 12px",fontWeight:600,cursor:"pointer",fontFamily:"Inter"}}>+ Dodaj kraj</button>
          </div>
          <div style={{display:"flex",flexDirection:"column",gap:10}}>
            {trip.trasa.map((leg,i)=>{const c=countries.find(c=>c.code===leg.country);return(
              <div key={i} style={{border:"1px solid #e2e8f0",borderRadius:12,padding:16,background:"#f8fafc"}}>
                <div style={{display:"flex",alignItems:"center",gap:8,marginBottom:12}}>
                  <span style={{fontSize:20}}>{c?.flag}</span>
                  <span style={{fontWeight:600,color:"#334155",fontSize:14}}>{c?.name}</span>
                  <span style={{marginLeft:"auto",fontSize:12,color:"#94a3b8"}}>Odcinek {i+1}</span>
                  {trip.trasa.length>1&&<button onClick={()=>removeLeg(i)} style={{color:"#ef4444",background:"none",border:"none",fontSize:13,fontWeight:600,cursor:"pointer",fontFamily:"Inter"}}>✕ Usuń</button>}
                </div>
                <div style={{display:"grid",gridTemplateColumns:"1fr 1fr 1fr 1fr",gap:10}}>
                  <div><label style={{display:"block",fontSize:11,color:"#94a3b8",marginBottom:4}}>Kraj</label>
                    <select value={leg.country} onChange={e=>updateLeg(i,"country",e.target.value)} style={inp}>
                      {countries.map(c=><option key={c.code} value={c.code}>{c.flag} {c.name}</option>)}
                    </select>
                  </div>
                  <div><label style={{display:"block",fontSize:11,color:"#94a3b8",marginBottom:4}}>Dni</label><input type="number" min="0" value={leg.days} onChange={e=>updateLeg(i,"days",Number(e.target.value))} style={inp}/></div>
                  <div><label style={{display:"block",fontSize:11,color:"#94a3b8",marginBottom:4}}>Godz./dzień</label><input type="number" min="0" max="24" value={leg.hours} onChange={e=>updateLeg(i,"hours",Number(e.target.value))} style={inp}/></div>
                  <div><label style={{display:"block",fontSize:11,color:"#94a3b8",marginBottom:4}}>Typ operacji</label>
                    <select value={leg.operationType} onChange={e=>updateLeg(i,"operationType",e.target.value)} style={inp}>
                      {Object.entries(MOBILITY_PACKAGE_INFO).map(([k,v])=><option key={k} value={k}>{v.label}</option>)}
                    </select>
                  </div>
                </div>
                <div style={{marginTop:8,fontSize:12,color:"#94a3b8"}}>Stawka diety: <strong>{c?.dietRate} EUR/dzień</strong> · Min. wynagrodzenie: <strong>{c?.minWageEUR} EUR/h</strong></div>
              </div>
            );})}
          </div>
          <div style={{display:"flex",gap:10,marginTop:20}}>
            <button onClick={()=>setActiveTab("rates")} style={{flex:1,padding:"10px",background:"#fff",color:"#2563eb",border:"1px solid #bfdbfe",borderRadius:12,fontSize:14,fontWeight:600,cursor:"pointer",fontFamily:"Inter"}}>⚙️ Stawki</button>
            <button onClick={calculate} style={{flex:1,padding:"10px",background:"linear-gradient(135deg,#2563eb,#4f46e5)",color:"#fff",border:"none",borderRadius:12,fontSize:14,fontWeight:700,cursor:"pointer",fontFamily:"Inter"}}>🧮 Oblicz delegację</button>
          </div>
        </Card>
      )}

      {activeTab==="rates"&&(
        <Card>
          <SectionTitle icon="💶" title="Stawki diet i płac minimalnych" subtitle="Pakiet Mobilności UE 2022 — edytuj każdy kraj osobno"/>
          <div style={{marginBottom:16,padding:12,borderRadius:10,border:"1px solid #fde68a",background:"#fefce8",fontSize:12,color:"#92400e"}}>
            <strong>ℹ️ Pakiet Mobilności UE:</strong> Kierowcy w kabotażu i cross-trade muszą otrzymywać min. wynagrodzenie obowiązujące w danym kraju (Rozp. 2020/1054/UE).
          </div>
          <div style={{overflowX:"auto"}}>
            <table style={{width:"100%",borderCollapse:"collapse",fontSize:13}}>
              <thead><tr style={{background:"#f8fafc",borderBottom:"2px solid #e2e8f0"}}><th style={{textAlign:"left",padding:"8px 12px",fontSize:11,fontWeight:700,color:"#64748b",textTransform:"uppercase",letterSpacing:"0.05em"}}>Kraj</th><th style={{textAlign:"right",padding:"8px 12px",fontSize:11,fontWeight:700,color:"#64748b",textTransform:"uppercase",letterSpacing:"0.05em"}}>Dieta (EUR/dzień)</th><th style={{textAlign:"right",padding:"8px 12px",fontSize:11,fontWeight:700,color:"#64748b",textTransform:"uppercase",letterSpacing:"0.05em"}}>Min. płaca (EUR/h)</th></tr></thead>
              <tbody>
                {countries.map(c=>(<tr key={c.code} style={{borderBottom:"1px solid #f1f5f9"}}><td style={{padding:"8px 12px"}}><span style={{marginRight:8}}>{c.flag}</span><span style={{fontWeight:500,color:"#334155"}}>{c.name}</span></td><td style={{padding:"8px 12px",textAlign:"right"}}><input type="number" step="0.5" value={c.dietRate} onChange={e=>updateCountry(c.code,"dietRate",e.target.value)} style={{...inp,width:96,textAlign:"right"}}/></td><td style={{padding:"8px 12px",textAlign:"right"}}><input type="number" step="0.01" value={c.minWageEUR} onChange={e=>updateCountry(c.code,"minWageEUR",e.target.value)} style={{...inp,width:96,textAlign:"right"}}/></td></tr>))}
              </tbody>
            </table>
          </div>
          <button onClick={()=>setActiveTab("trip")} style={{marginTop:20,width:"100%",padding:"10px",background:"linear-gradient(135deg,#2563eb,#4f46e5)",color:"#fff",border:"none",borderRadius:12,fontSize:14,fontWeight:700,cursor:"pointer",fontFamily:"Inter"}}>← Powrót do trasy</button>
        </Card>
      )}

      {activeTab==="result"&&result&&(
        <div style={{display:"flex",flexDirection:"column",gap:16}}>
          <div style={{display:"grid",gridTemplateColumns:"repeat(4,1fr)",gap:12}}>
            {[{label:"Łączna dieta",value:`${fmtNum(result.totalDiet)} EUR`,sub:`≈ ${fmtNum(result.totalDiet*result.eurPln)} PLN`,bg:"linear-gradient(135deg,#3b82f6,#2563eb)"},{label:"Min. płaca (PM)",value:`${fmtNum(result.totalMinWage)} EUR`,sub:`≈ ${fmtNum(result.minWagePLN)} PLN`,bg:"linear-gradient(135deg,#6366f1,#4f46e5)"},{label:"Wynagrodzenie bazowe",value:`${fmtNum(result.baseSalary,0)} PLN`,sub:"miesięcznie",bg:"linear-gradient(135deg,#64748b,#475569)"},{label:result.requiresTopUp?"⚠️ Dopłata wymagana":"✅ Brak dopłaty",value:`${result.requiresTopUp?"+":""}${fmtNum(Math.abs(result.delta),0)} PLN`,sub:result.requiresTopUp?"wyrównanie do min. płacy":"wynagrodzenie wystarczające",bg:result.requiresTopUp?"linear-gradient(135deg,#f59e0b,#d97706)":"linear-gradient(135deg,#10b981,#059669)"}].map((k,i)=>(
              <div key={i} style={{background:k.bg,color:"#fff",borderRadius:16,padding:16,boxShadow:"0 4px 12px rgba(0,0,0,0.12)"}}>
                <div style={{fontSize:11,fontWeight:600,opacity:0.8,marginBottom:4}}>{k.label}</div>
                <div style={{fontSize:20,fontWeight:800,lineHeight:1.2}}>{k.value}</div>
                <div style={{fontSize:11,opacity:0.7,marginTop:4}}>{k.sub}</div>
              </div>
            ))}
          </div>

          <Card>
            <SectionTitle icon="📊" title="Zestawienie wg krajów" subtitle="Podział na odcinki · diety · minimalne wynagrodzenie"/>
            <div style={{overflowX:"auto"}}>
              <table style={{width:"100%",borderCollapse:"collapse",fontSize:13}}>
                <thead><tr style={{background:"#f8fafc",borderBottom:"2px solid #e2e8f0"}}>{["Kraj","Typ operacji","Dni","Godz. łącznie","Dieta (EUR)","Min. płaca (EUR)"].map(h=><th key={h} style={{padding:"8px 12px",textAlign:["Dni","Godz. łącznie","Dieta (EUR)","Min. płaca (EUR)"].includes(h)?"right":"left",fontSize:11,fontWeight:700,color:"#64748b",textTransform:"uppercase",letterSpacing:"0.05em"}}>{h}</th>)}</tr></thead>
                <tbody>
                  {result.breakdown.map((b,i)=>(<tr key={i} style={{borderBottom:"1px solid #f1f5f9",background:i%2===0?"#fff":"#f8fafc"}}><td style={{padding:"8px 12px",fontWeight:500}}>{b.country.flag} {b.country.name}</td><td style={{padding:"8px 12px"}}><Badge color="slate">{b.operationType}</Badge></td><td style={{padding:"8px 12px",textAlign:"right"}}>{b.leg.days}</td><td style={{padding:"8px 12px",textAlign:"right"}}>{b.leg.hours*b.leg.days}</td><td style={{padding:"8px 12px",textAlign:"right",fontWeight:700,color:"#2563eb"}}>{fmtNum(b.dietAmount)}</td><td style={{padding:"8px 12px",textAlign:"right",fontWeight:700,color:"#4f46e5"}}>{fmtNum(b.minWageAmount)}</td></tr>))}
                </tbody>
                <tfoot><tr style={{borderTop:"2px solid #cbd5e1",background:"#f1f5f9",fontWeight:700}}><td colSpan={4} style={{padding:"8px 12px"}}>SUMA</td><td style={{padding:"8px 12px",textAlign:"right",color:"#2563eb"}}>{fmtNum(result.totalDiet)} EUR</td><td style={{padding:"8px 12px",textAlign:"right",color:"#4f46e5"}}>{fmtNum(result.totalMinWage)} EUR</td></tr></tfoot>
              </table>
            </div>
          </Card>

          <Card id="delegation-doc">
            <div style={{display:"flex",alignItems:"center",justifyContent:"space-between",flexWrap:"wrap",gap:12,marginBottom:20}}>
              <SectionTitle icon="📄" title="Dokument delegacji" subtitle={`Nr: ${result.trip.nr_delegacji}`}/>
              <button onClick={()=>window.print()} style={{display:"flex",alignItems:"center",gap:8,padding:"8px 16px",background:"#1e293b",color:"#fff",border:"none",borderRadius:10,fontSize:13,fontWeight:600,cursor:"pointer",fontFamily:"Inter"}}>🖨️ Drukuj / PDF</button>
            </div>
            <div style={{border:"1px solid #e2e8f0",borderRadius:12,padding:24,display:"flex",flexDirection:"column",gap:20}}>
              <div style={{textAlign:"center",borderBottom:"1px solid #e2e8f0",paddingBottom:16}}>
                <div style={{fontSize:22,fontWeight:900,color:"#1e293b",letterSpacing:-0.5}}>POLECENIE WYJAZDU SŁUŻBOWEGO</div>
                <div style={{color:"#64748b",marginTop:4,fontSize:13}}>Nr: <strong>{result.trip.nr_delegacji}</strong></div>
              </div>
              <div><div style={{fontSize:11,fontWeight:700,color:"#2563eb",textTransform:"uppercase",letterSpacing:"0.05em",marginBottom:10}}>Dane Pracownika</div><div style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:"4px 32px"}}><Row label="Imię i nazwisko" value={`${result.driver.imie} ${result.driver.nazwisko}`}/><Row label="PESEL" value={result.driver.pesel}/><Row label="Nr prawa jazdy" value={result.driver.nr_prawa_jazdy}/><Row label="Kategoria" value={result.driver.kategoria}/></div></div>
              <div style={{borderTop:"1px solid #f1f5f9",paddingTop:16}}><div style={{fontSize:11,fontWeight:700,color:"#2563eb",textTransform:"uppercase",letterSpacing:"0.05em",marginBottom:10}}>Dane Wyjazdu</div><div style={{display:"grid",gridTemplateColumns:"1fr 1fr",gap:"4px 32px"}}><Row label="Data wyjazdu" value={result.trip.data_wyjazdu}/><Row label="Data powrotu" value={result.trip.data_powrotu}/><Row label="Nr rejestracyjny" value={result.trip.nr_rejestracyjny}/><Row label="Cel podróży" value={result.trip.cel_podrozy}/><Row label="Łączna liczba dni" value={`${result.totalDays} dni`}/></div></div>
              <div style={{borderTop:"1px solid #f1f5f9",paddingTop:16}}><div style={{fontSize:11,fontWeight:700,color:"#2563eb",textTransform:"uppercase",letterSpacing:"0.05em",marginBottom:10}}>Trasa</div><div style={{display:"flex",flexDirection:"column",gap:4}}>{result.breakdown.map((b,i)=>(<div key={i} style={{display:"flex",justifyContent:"space-between",padding:"6px 0",borderBottom:"1px solid #f8fafc",fontSize:13}}><span style={{color:"#475569"}}>{b.country.flag} {b.country.name} — {b.operationType} ({b.leg.days} dni × {b.leg.hours} h)</span><span style={{fontWeight:600,color:"#1e293b"}}>{fmtNum(b.dietAmount)} EUR</span></div>))}</div></div>
              <div style={{borderTop:"1px solid #f1f5f9",paddingTop:16}}><div style={{fontSize:11,fontWeight:700,color:"#2563eb",textTransform:"uppercase",letterSpacing:"0.05em",marginBottom:10}}>Rozliczenie Finansowe — Pakiet Mobilności</div>
                <div style={{background:"#f8fafc",borderRadius:10,padding:16,display:"flex",flexDirection:"column",gap:6}}>
                  <Row label="Łączna dieta zagraniczna" value={`${fmtNum(result.totalDiet)} EUR (≈ ${fmtNum(result.totalDiet*result.eurPln)} PLN)`}/>
                  <Row label="Min. wynagrodzenie wg Pakietu Mobilności" value={`${fmtNum(result.totalMinWage)} EUR (≈ ${fmtNum(result.minWagePLN)} PLN)`}/>
                  <Row label="Wynagrodzenie podstawowe kierowcy" value={`${fmtNum(result.baseSalary,0)} PLN`}/>
                  <div style={{borderTop:"1px solid #e2e8f0",paddingTop:10,marginTop:4,display:"flex",justifyContent:"space-between",fontWeight:700,color:result.requiresTopUp?"#d97706":"#059669",fontSize:14}}>
                    <span>{result.requiresTopUp?"⚠️ Wymagana dopłata do min. płacy:":"✅ Brak wymaganej dopłaty:"}</span>
                    <span>{fmtNum(Math.abs(result.delta),0)} PLN</span>
                  </div>
                </div>
              </div>
              <div style={{borderTop:"1px solid #e2e8f0",paddingTop:24,display:"grid",gridTemplateColumns:"1fr 1fr 1fr",gap:24,textAlign:"center"}}>
                {["Kierowca","Przełożony","Dział kadr"].map(s=>(<div key={s}><div style={{borderBottom:"1px solid #cbd5e1",paddingBottom:40,marginBottom:8}}/><span style={{fontSize:12,color:"#94a3b8"}}>{s}</span></div>))}
              </div>
              <div style={{textAlign:"center",fontSize:11,color:"#94a3b8"}}>Dokument wygenerowany automatycznie zgodnie z Pakietem Mobilności UE (Rozporządzenie 2020/1054/UE) · kurs EUR/PLN: {result.eurPln}</div>
            </div>
          </Card>
        </div>
      )}
      {activeTab==="result"&&!result&&(
        <Card style={{textAlign:"center",padding:48}}>
          <div style={{fontSize:48,marginBottom:16}}>📋</div>
          <div style={{fontSize:18,fontWeight:700,color:"#334155",marginBottom:8}}>Brak wyliczeń</div>
          <div style={{fontSize:13,color:"#64748b",marginBottom:20}}>Uzupełnij dane kierowcy i trasę, a następnie kliknij „Oblicz delegację"</div>
          <button onClick={()=>setActiveTab("driver")} style={{padding:"10px 24px",background:"#2563eb",color:"#fff",border:"none",borderRadius:10,fontSize:14,fontWeight:700,cursor:"pointer",fontFamily:"Inter"}}>Zacznij od początku</button>
        </Card>
      )}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════
// MAIN APP
// ═══════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════
// DELEGATION APP — integrated into TachoSystem layout
// ═══════════════════════════════════════════════════════════════
function DelegationApp() {
  const [tachoData, setTachoData] = React.useState(null);

  React.useEffect(() => {
    if (!TACHO_FILE_ID) return;
    const load = async () => {
      try {
        const resp = await fetch('/analysis/' + TACHO_FILE_ID + '/file');
        if (!resp.ok) return;
        const buf = await resp.arrayBuffer();
        const r = parseDDD(buf);
        if (r) setTachoData({ ...r, demo: false });
      } catch (e) {
        // ignore — delegation works without tacho data
      }
    };
    load();
  }, []);

  return (
    <div style={{ fontFamily: 'Inter,sans-serif', width: '100%' }}>
      <DelegationPanel tachoData={tachoData || { days: [], demo: true }} />
    </div>
  );
}

const _root = ReactDOM.createRoot(document.getElementById('delegation-root'));
_root.render(<DelegationApp />);
</script>
