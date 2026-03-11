<?php
/**
 * @var array $file
 * @var int   $fileId
 */
?>
<!-- ── Back link ─────────────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <a href="/analysis" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Powrót do listy plików
  </a>
  <span class="text-muted small font-monospace"><?= htmlspecialchars($file['original_name']) ?></span>
</div>

<!-- ── Analyzer container ─────────────────────────────────────────────────── -->
<div id="tacho-analyzer-root"></div>

<!-- React 18 + Babel standalone (CDN) -->
<script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

<!-- Inject server-provided file ID -->
<script>
  const TACHO_FILE_ID = <?= (int)$fileId ?>;
</script>

<!-- ── Tachograph DDD Analyzer (adapted from repository JSX) ──────────────── -->
<script type="text/babel">
const { useState, useRef, useEffect, useMemo } = React;

const EU = { maxWeek:3360, maxDay:540, maxDayEx:600, minRest:660, maxCont:270 };
const LW=74, T1Y=32, T1H=36, T2Y=76, T2H=18, AXY=102, RH=120;
const ACT_FILL  =["#80DEEA","#9FA8DA","#FFCC80","#EF9A9A"];
const ACT_SOLID =["#00ACC1","#5C6BC0","#EF6C00","#E53935"];
const ACT_STROKE=["#00838F","#3949AB","#BF360C","#C62828"];
const ACT_TEXT  =["#006064","#1A237E","#BF360C","#B71C1C"];
const ACT_NAME  =["Odpoczynek","Dyspozycyjnosc","Praca","Jazda"];
const ACT_HFRAC =[0.30, 0.52, 0.72, 1.00];

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

const BORDER_DB={
  "PL-DE":[
    {name:"Slubice/Frankfurt(Oder)",lat:52.3483,lon:14.5533,road:"A2/E30"},
    {name:"Olszyna/Forst",lat:51.3550,lon:15.0010,road:"A18/E36"},
    {name:"Zgorzelec/Goerlitz",lat:51.1530,lon:14.9970,road:"A4/E40"},
    {name:"Kolbaskowo/Pomellen",lat:53.5330,lon:14.4200,road:"A6/E28"},
  ],
  "DE-PL":[
    {name:"Frankfurt(Oder)/Slubice",lat:52.3483,lon:14.5533,road:"A2/E30"},
    {name:"Forst/Olszyna",lat:51.3550,lon:15.0010,road:"A18/E36"},
    {name:"Goerlitz/Zgorzelec",lat:51.1530,lon:14.9970,road:"A4/E40"},
  ],
  "PL-CZ":[
    {name:"Cieszyn/Cesky Tesin",lat:49.7497,lon:18.6321,road:"E75"},
    {name:"Kudowa-Slone/Nachod",lat:50.4308,lon:16.2467,road:"E67"},
    {name:"Glucholazy/Mikulovice",lat:50.3000,lon:17.3900,road:"45"},
  ],
  "CZ-PL":[
    {name:"Cesky Tesin/Cieszyn",lat:49.7497,lon:18.6321,road:"E75"},
    {name:"Nachod/Kudowa-Slone",lat:50.4308,lon:16.2467,road:"E67"},
  ],
  "PL-SK":[
    {name:"Chyzne/Trstena",lat:49.4167,lon:19.6167,road:"E77"},
    {name:"Lysa Polana/Podspadky",lat:49.2322,lon:19.9997,road:"E77"},
    {name:"Zwardon/Makov",lat:49.5000,lon:18.9667,road:"E75"},
  ],
  "SK-PL":[
    {name:"Trstena/Chyzne",lat:49.4167,lon:19.6167,road:"E77"},
    {name:"Makov/Zwardon",lat:49.5000,lon:18.9667,road:"E75"},
  ],
  "CZ-SK":[
    {name:"Mosty u Jablunkova/Cadca",lat:49.5397,lon:18.7667,road:"E75"},
    {name:"Stary Hrozenkov/Drietoma",lat:48.9167,lon:17.8000,road:"E50"},
  ],
  "SK-CZ":[
    {name:"Cadca/Mosty u Jablunkova",lat:49.5397,lon:18.7667,road:"E75"},
    {name:"Drietoma/Stary Hrozenkov",lat:48.9167,lon:17.8000,road:"E50"},
  ],
  "SK-HU":[
    {name:"Sturovo/Esztergom",lat:47.7986,lon:18.7036,road:"E77"},
    {name:"Komarno/Komarom",lat:47.7595,lon:18.1286,road:"E575"},
  ],
  "HU-SK":[
    {name:"Esztergom/Sturovo",lat:47.7986,lon:18.7036,road:"E77"},
    {name:"Komarom/Komarno",lat:47.7595,lon:18.1286,road:"E575"},
  ],
  "DE-NL":[
    {name:"Venlo/Kaldenkirchen",lat:51.3667,lon:6.1675,road:"A61/E31"},
    {name:"Oldenzaal/Bad Bentheim",lat:52.3133,lon:6.9286,road:"A1/E30"},
    {name:"Emmerich/Elten",lat:51.8386,lon:6.2428,road:"A3/E35"},
  ],
  "NL-DE":[
    {name:"Kaldenkirchen/Venlo",lat:51.3667,lon:6.1675,road:"A61/E31"},
    {name:"Bad Bentheim/Oldenzaal",lat:52.3133,lon:6.9286,road:"A1/E30"},
    {name:"Elten/Emmerich",lat:51.8386,lon:6.2428,road:"A3/E35"},
  ],
  "DE-AT":[
    {name:"Freilassing/Salzburg",lat:47.7939,lon:12.9583,road:"A1/E60"},
    {name:"Kufstein/Kiefersfelden",lat:47.6097,lon:12.1764,road:"A93/E45"},
  ],
  "AT-DE":[
    {name:"Salzburg/Freilassing",lat:47.7939,lon:12.9583,road:"A1/E60"},
    {name:"Kiefersfelden/Kufstein",lat:47.6097,lon:12.1764,road:"A93/E45"},
  ],
};
function getCrossings(from,to){
  const key=from+"-"+to;
  return BORDER_DB[key]||[{name:from+" > "+to+" (brak danych)",lat:50.0614,lon:19.9366,road:"-"}];
}

function isoWeek(d){
  const t=new Date(Date.UTC(d.getFullYear(),d.getMonth(),d.getDate()));
  t.setUTCDate(t.getUTCDate()+4-(t.getUTCDay()||7));
  return Math.ceil((((t-new Date(Date.UTC(t.getUTCFullYear(),0,1)))/864e5)+1)/7);
}
function monDay(d){const r=new Date(d),dw=r.getDay();r.setDate(r.getDate()-(dw===0?6:dw-1));r.setHours(0,0,0,0);return r;}
function addD(d,n){const r=new Date(d);r.setDate(r.getDate()+n);return r;}
function hhmm(m){return String(Math.floor(m/60)).padStart(2,"0")+":"+String(m%60).padStart(2,"0");}
function hm(m){const h=Math.floor(m/60),mm=m%60;return mm?h+"h "+mm+"m":h+"h";}
function fmt(d){return String(d.getDate()).padStart(2,"0")+"."+String(d.getMonth()+1).padStart(2,"0")+"."+d.getFullYear();}
function clamp(v,a,b){return Math.max(a,Math.min(b,v));}

function dayStatus(slots){
  if(!slots||!slots.length) return null;
  const drive=slots.filter(s=>s.activity===3).reduce((a,s)=>a+s.duration,0);
  const rest=slots.filter(s=>s.activity===0).reduce((a,s)=>a+s.duration,0);
  let e=false,w=false;
  if(drive>EU.maxDayEx) e=true; else if(drive>EU.maxDay) w=true;
  let cont=0,maxC=0;
  slots.forEach(s=>{if(s.activity===3){cont+=s.duration;if(cont>maxC)maxC=cont;}else if(s.activity===0&&s.duration>=15)cont=0;});
  if(maxC>EU.maxCont) w=true;
  if(rest<EU.minRest&&drive>0) w=true;
  return e?"error":w?"warn":"ok";
}

function parseDDD(buffer){
  const u8=new Uint8Array(buffer),dv=new DataView(buffer);
  const days=[];let driver=null;
  const mkSlots=pts=>{
    const v=pts.filter(p=>p.tmin<=1440);
    return v.map((p,i)=>({activity:p.act,startMin:p.tmin,endMin:i<v.length-1?v[i+1].tmin:1440}))
      .filter(s=>s.endMin>s.startMin).map(s=>({...s,duration:s.endMin-s.startMin}));
  };
  for(let i=0;i<u8.length-4;i++){
    if(u8[i]!==0x05||u8[i+1]!==0x05) continue;
    try{
      const bl=dv.getUint16(i+2,false);if(bl<4||bl>65000||i+4+bl>u8.length) continue;
      let off=i+4;const end=off+bl;off+=4;
      while(off+12<end){
        const rlen=dv.getUint16(off+2,false);if(rlen<12||rlen>3000||off+rlen>end){off+=2;continue;}
        const ts=dv.getUint32(off+4,false),date=new Date(ts*1000);
        if(date.getFullYear()<2000||date.getFullYear()>2035){off+=rlen;continue;}
        const n=Math.floor((rlen-12)/2),pts=[];
        for(let j=0;j<n;j++){const raw=dv.getUint16(off+12+j*2,false);pts.push({act:(raw>>14)&3,tmin:raw&0x1FFF});}
        const slots=mkSlots(pts);if(slots.length) days.push({date,slots,distance:dv.getUint16(off+10,false),crossings:[]});
        off+=rlen;
      }
      if(days.length) break;
    }catch(e_){}
  }
  for(let i=0;i<u8.length-4;i++){
    if(u8[i]!==0x05||u8[i+1]!==0x20) continue;
    try{
      const bl=dv.getUint16(i+2,false);if(bl<26||bl>300) continue;
      const sn=Array.from(u8.slice(i+4,i+30)).filter(b=>b>=32&&b<128).map(b=>String.fromCharCode(b)).join("").trim();
      const fn=Array.from(u8.slice(i+30,i+56)).filter(b=>b>=32&&b<128).map(b=>String.fromCharCode(b)).join("").trim();
      if(sn.length>1){driver=(fn+" "+sn).trim();break;}
    }catch(e_){}
  }
  if(!days.length) return null;
  return{driver,days:days.sort((a,b)=>a.date-b.date)};
}

function TachoSym(props){
  const {act,cx,cy,s}=props;
  const sc=s||1;
  const col=ACT_TEXT[act];
  if(act===0){
    return (
      <g transform={"translate("+cx+","+cy+") scale("+sc+")"} style={{pointerEvents:"none"}}>
        <rect x={-6} y={1} width={12} height={5} rx={1.5} fill={col} opacity={0.85}/>
        <rect x={-6} y={-2} width={5} height={3.5} rx={1.5} fill={col} opacity={0.7}/>
        <rect x={-7} y={-4} width={2} height={9} rx={1} fill={col} opacity={0.9}/>
        <rect x={5} y={-1} width={2} height={7} rx={1} fill={col} opacity={0.9}/>
      </g>
    );
  }
  if(act===3){
    const r=5.5, ri=2;
    return (
      <g transform={"translate("+cx+","+cy+") scale("+sc+")"} style={{pointerEvents:"none"}}>
        <circle r={r} fill="none" stroke={col} strokeWidth={1.8} opacity={0.9}/>
        <circle r={ri} fill={col} opacity={0.85}/>
        <line x1={0} y1={-ri} x2={0} y2={-r} stroke={col} strokeWidth={1.4}/>
        <line x1={ri*0.87} y1={ri*0.5} x2={r*0.87} y2={r*0.5} stroke={col} strokeWidth={1.4}/>
        <line x1={-ri*0.87} y1={ri*0.5} x2={-r*0.87} y2={r*0.5} stroke={col} strokeWidth={1.4}/>
      </g>
    );
  }
  if(act===2){
    return (
      <g transform={"translate("+cx+","+cy+") scale("+sc+")"} style={{pointerEvents:"none"}}>
        <line x1={-4} y1={4} x2={1} y2={-1} stroke={col} strokeWidth={1.6} strokeLinecap="round"/>
        <rect x={0} y={-5} width={5} height={3} rx={0.8} fill={col} opacity={0.9} transform="rotate(-45,2.5,-3.5)"/>
        <line x1={4} y1={4} x2={-1} y2={-1} stroke={col} strokeWidth={1.6} strokeLinecap="round"/>
        <rect x={-5} y={-5} width={5} height={3} rx={0.8} fill={col} opacity={0.9} transform="rotate(45,-2.5,-3.5)"/>
      </g>
    );
  }
  if(act===1){
    return (
      <g transform={"translate("+cx+","+cy+") scale("+sc+")"} style={{pointerEvents:"none"}}>
        <polygon points="-5,-5 5,-5 0,0" fill={col} opacity={0.6}/>
        <polygon points="-5,5 5,5 0,0" fill={col} opacity={0.85}/>
        <line x1={-5} y1={-5} x2={5} y2={-5} stroke={col} strokeWidth={1.5} strokeLinecap="round"/>
        <line x1={-5} y1={5} x2={5} y2={5} stroke={col} strokeWidth={1.5} strokeLinecap="round"/>
      </g>
    );
  }
  return null;
}

function CrossingModal(props){
  const [idx,setIdx]=useState(0);
  if(!props.crossing) return null;
  const {from,to,date,timeLabel}=props.crossing;
  const options=getCrossings(from,to);
  const safeIdx=Math.min(idx,options.length-1);
  const loc=options[safeIdx];
  const cs=ccStyle(to);
  const csF=ccStyle(from);
  return (
    <div onClick={props.onClose} style={{position:"fixed",inset:0,background:"rgba(0,0,0,0.45)",zIndex:10000,display:"flex",alignItems:"center",justifyContent:"center"}}>
      <div onClick={e=>e.stopPropagation()} style={{background:"#FFF",borderRadius:8,width:380,maxWidth:"95vw",boxShadow:"0 20px 60px rgba(0,0,0,0.3)",overflow:"hidden"}}>
        <div style={{padding:"12px 16px",background:"#F8F9FB",borderBottom:"1px solid #E0E4E8",display:"flex",alignItems:"center",gap:8}}>
          <div style={{flex:1}}>
            <div style={{fontSize:11,color:"#9AA0AA",marginBottom:3}}>Przekroczenie granicy</div>
            <div style={{display:"flex",alignItems:"center",gap:6}}>
              <div style={{padding:"3px 8px",background:csF.bg,border:"1px solid "+csF.bd,borderRadius:3,fontSize:11,color:csF.tx,fontWeight:700}}>{from}</div>
              <span style={{fontSize:12,color:"#9AA0AA"}}>wjazd do</span>
              <div style={{padding:"3px 8px",background:cs.bg,border:"1px solid "+cs.bd,borderRadius:3,fontSize:11,color:cs.tx,fontWeight:700}}>{to}</div>
            </div>
          </div>
          <button onClick={props.onClose} style={{marginLeft:"auto",background:"none",border:"none",fontSize:18,color:"#9AA0AA",cursor:"pointer",lineHeight:1,padding:"0 2px"}}>&#x2715;</button>
        </div>
        <div style={{padding:"14px 16px",display:"flex",flexDirection:"column",gap:10}}>
          <div style={{display:"flex",gap:16}}>
            <div>
              <div style={{fontSize:9,color:"#9AA0AA",fontWeight:600,marginBottom:3}}>DATA</div>
              <div style={{fontSize:14,fontWeight:600,color:"#1A2030"}}>{date||"-"}</div>
            </div>
            <div>
              <div style={{fontSize:9,color:"#9AA0AA",fontWeight:600,marginBottom:3}}>GODZINA</div>
              <div style={{fontSize:14,fontWeight:600,color:"#1A2030"}}>{timeLabel||"-"}</div>
            </div>
          </div>
          {options.length>1&&(
            <div>
              <div style={{fontSize:9,color:"#9AA0AA",fontWeight:600,marginBottom:5}}>PRZEJSCIE GRANICZNE</div>
              <div style={{display:"flex",gap:5,flexWrap:"wrap"}}>
                {options.map((opt,i)=>(
                  <button key={i} onClick={()=>setIdx(i)} style={{background:safeIdx===i?"#E3F2FD":"transparent",border:"1px solid "+(safeIdx===i?"#1E88E5":"#DDE1E6"),color:safeIdx===i?"#1E88E5":"#5A6070",padding:"3px 9px",borderRadius:4,fontSize:10,cursor:"pointer",fontWeight:safeIdx===i?600:400,fontFamily:"Inter"}}>
                    {opt.name.split("/")[0]}
                  </button>
                ))}
              </div>
            </div>
          )}
          <div style={{background:"#F8F9FB",border:"1px solid #E8EAF0",borderRadius:6,padding:"10px 14px"}}>
            <div style={{fontSize:12,fontWeight:600,color:"#1A2030",marginBottom:6}}>{loc.name}</div>
            <div style={{display:"flex",gap:20}}>
              <div>
                <div style={{fontSize:9,color:"#9AA0AA",fontWeight:600,marginBottom:2}}>SZEROKOSC</div>
                <div style={{fontSize:13,fontWeight:600,color:"#1565C0",fontFamily:"monospace"}}>{loc.lat.toFixed(5)}</div>
              </div>
              <div>
                <div style={{fontSize:9,color:"#9AA0AA",fontWeight:600,marginBottom:2}}>DLUGOSC</div>
                <div style={{fontSize:13,fontWeight:600,color:"#1565C0",fontFamily:"monospace"}}>{loc.lon.toFixed(5)}</div>
              </div>
              <div>
                <div style={{fontSize:9,color:"#9AA0AA",fontWeight:600,marginBottom:2}}>DROGA</div>
                <div style={{fontSize:12,fontWeight:600,color:"#5A6070"}}>{loc.road}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function DayModal(props){
  if(!props.day) return null;
  const {day,onClose}=props;
  const dow=["Niedziela","Poniedzialek","Wtorek","Sroda","Czwartek","Piatek","Sobota"];
  const dowd=day.date.getDay();
  const drive=day.slots.filter(s=>s.activity===3).reduce((a,s)=>a+s.duration,0);
  const work=day.slots.filter(s=>s.activity===2).reduce((a,s)=>a+s.duration,0);
  const rest=day.slots.filter(s=>s.activity===0).reduce((a,s)=>a+s.duration,0);
  const avail=day.slots.filter(s=>s.activity===1).reduce((a,s)=>a+s.duration,0);
  const st=dayStatus(day.slots);
  const stCol=st==="error"?"#E53935":st==="warn"?"#FF9800":"#43A047";
  const stLbl=st==="error"?"Naruszenie":st==="warn"?"Ostrzezenie":"Zgodny";
  return (
    <div onClick={onClose} style={{position:"fixed",inset:0,background:"rgba(0,0,0,0.5)",zIndex:10000,display:"flex",alignItems:"center",justifyContent:"center",padding:"16px"}}>
      <div onClick={e=>e.stopPropagation()} style={{background:"#FFF",borderRadius:8,width:560,maxWidth:"100%",maxHeight:"85vh",display:"flex",flexDirection:"column",boxShadow:"0 24px 64px rgba(0,0,0,0.3)",overflow:"hidden"}}>
        <div style={{padding:"14px 18px",background:"#F0F4F8",borderBottom:"1px solid #E0E4E8",display:"flex",alignItems:"center",gap:12,flexShrink:0}}>
          <div>
            <div style={{fontSize:10,color:"#9AA0AA",fontWeight:600,marginBottom:2}}>{dow[dowd].toUpperCase()}</div>
            <div style={{fontSize:18,fontWeight:700,color:"#1A2030"}}>{fmt(day.date)}</div>
          </div>
          {day.vehicle&&(
            <div style={{padding:"3px 10px",background:"#E3F2FD",border:"1px solid #BBDEFB",borderRadius:4,fontSize:11,color:"#1565C0",fontWeight:600}}>{day.vehicle}</div>
          )}
          {day.distance>0&&(
            <div style={{padding:"3px 10px",background:"#F3F4F7",border:"1px solid #DDE1E6",borderRadius:4,fontSize:11,color:"#5A6070",fontWeight:500}}>{day.distance} km</div>
          )}
          <div style={{padding:"3px 10px",background:stCol+"18",border:"1px solid "+stCol+"60",borderRadius:4,fontSize:11,color:stCol,fontWeight:600}}>{stLbl}</div>
          <button onClick={onClose} style={{marginLeft:"auto",background:"none",border:"none",fontSize:18,color:"#9AA0AA",cursor:"pointer",padding:"0 4px",lineHeight:1}}>&#x2715;</button>
        </div>
        <div style={{display:"flex",gap:0,borderBottom:"1px solid #EEF0F4",flexShrink:0}}>
          {[{act:3,val:drive},{act:2,val:work},{act:1,val:avail},{act:0,val:rest}].filter(x=>x.val>0).map(({act,val})=>(
            <div key={act} style={{flex:1,padding:"10px 14px",borderRight:"1px solid #EEF0F4",background:"#FAFBFC"}}>
              <div style={{display:"flex",alignItems:"center",gap:5,marginBottom:3}}>
                <div style={{width:8,height:8,borderRadius:2,background:ACT_SOLID[act]}}/>
                <span style={{fontSize:9,color:"#9AA0AA",fontWeight:600}}>{ACT_NAME[act].toUpperCase()}</span>
              </div>
              <div style={{fontSize:15,fontWeight:700,color:ACT_SOLID[act],fontFamily:"monospace"}}>{hhmm(val)}</div>
            </div>
          ))}
        </div>
        <div style={{overflowY:"auto",flex:1}}>
          <table style={{width:"100%",borderCollapse:"collapse",fontSize:12,fontFamily:"Inter"}}>
            <thead style={{position:"sticky",top:0,zIndex:1}}>
              <tr style={{background:"#F0F4F8"}}>
                <th style={{padding:"7px 14px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"2px solid #E0E4E8"}}>#</th>
                <th style={{padding:"7px 14px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"2px solid #E0E4E8"}}>Start</th>
                <th style={{padding:"7px 14px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"2px solid #E0E4E8"}}>Stop</th>
                <th style={{padding:"7px 14px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"2px solid #E0E4E8"}}>Czas</th>
                <th style={{padding:"7px 14px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"2px solid #E0E4E8"}}>Aktywnosc</th>
              </tr>
            </thead>
            <tbody>
              {day.slots.map((s,i)=>(
                <tr key={i} style={{background:i%2===0?"#FFF":"#F8FAFC",borderBottom:"1px solid #F0F2F5"}}>
                  <td style={{padding:"6px 14px",color:"#BFC5CC",fontSize:10}}>{i+1}</td>
                  <td style={{padding:"6px 14px",fontFamily:"monospace",fontWeight:600,color:"#1A2030"}}>{hhmm(s.startMin)}</td>
                  <td style={{padding:"6px 14px",fontFamily:"monospace",fontWeight:600,color:"#1A2030"}}>{hhmm(s.endMin)}</td>
                  <td style={{padding:"6px 14px",fontFamily:"monospace",fontWeight:700,color:ACT_SOLID[s.activity]}}>{hhmm(s.duration)}</td>
                  <td style={{padding:"6px 14px"}}>
                    <span style={{display:"inline-flex",alignItems:"center",gap:6}}>
                      <span style={{width:8,height:8,borderRadius:2,background:ACT_SOLID[s.activity],display:"inline-block",flexShrink:0}}/>
                      <span style={{fontWeight:600,color:ACT_SOLID[s.activity]}}>{ACT_NAME[s.activity]}</span>
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

function WeekRow(props){
  const {weekStart,days,cw,vs,ve,setTip,onCross,onDayClick}=props;
  const [expanded,setExpanded]=useState(false);
  const dur=ve-vs;
  const px=m=>((m-vs)/dur)*cw;
  const now=new Date();
  const todayDi=Array.from({length:7},(_,i)=>addD(weekStart,i)).findIndex(d=>d.toDateString()===now.toDateString());
  const nowAbs=todayDi>=0?todayDi*1440+now.getHours()*60+now.getMinutes():-1;
  const nowX=nowAbs>=0?px(nowAbs):-1;
  const showNow=nowAbs>=vs&&nowAbs<=ve;

  const flat=[],longRests=[],allCross=[],restStarts=[];
  days.forEach((day,di)=>{
    if(!day) return;
    day.slots.forEach(s=>{
      flat.push({absS:di*1440+s.startMin,absE:di*1440+s.endMin,act:s.activity,dur:s.duration,date:day.date});
      if(s.activity===0&&s.duration>=9*60){
        longRests.push({absS:di*1440+s.startMin,absE:di*1440+s.endMin,dur:s.duration});
        restStarts.push({absM:di*1440+s.startMin,label:hhmm(s.startMin)});
      }
    });
    (day.crossings||[]).forEach(c=>{
      allCross.push({absM:di*1440+c.atMin,from:c.from||"?",to:c.to,date:fmt(day.date),timeLabel:hhmm(c.atMin)});
    });
  });

  const driveMarkers=[];let prev=null;
  flat.forEach(s=>{
    if(s.act===3&&(!prev||prev.act!==3)) driveMarkers.push({abs:s.absS,type:"start"});
    else if(s.act!==3&&prev&&prev.act===3) driveMarkers.push({abs:prev.absE,type:"end"});
    prev=s;
  });
  if(prev&&prev.act===3) driveMarkers.push({abs:prev.absE,type:"end"});

  const weekDrive=days.reduce((s,d)=>s+(d?d.slots.filter(x=>x.activity===3).reduce((a,b)=>a+b.duration,0):0),0);
  const dCol=weekDrive>EU.maxWeek?"#E53935":weekDrive>EU.maxWeek*0.85?"#FF9800":"#43A047";
  const dayDots=days.map(d=>{const st=dayStatus(d&&d.slots);return st==="error"?"#E53935":st==="warn"?"#FF9800":st==="ok"?"#43A047":null;});
  const totals={0:0,1:0,2:0,3:0};
  const dist=days.reduce((s,d)=>s+(d?d.distance:0),0);
  days.forEach(d=>d&&d.slots.forEach(s=>{totals[s.activity]=(totals[s.activity]||0)+s.duration;}));

  return (
    <div style={{borderBottom:"1px solid #E2E4EA",background:"#FFF"}}>
      <div style={{height:3,background:"linear-gradient(90deg,#1E88E5,#42A5F5)",opacity:0.5}}/>
      <div style={{display:"flex",alignItems:"stretch"}}>
        <div style={{width:LW,flexShrink:0,background:"#F8F9FB",borderRight:"1px solid #E2E4EA",padding:"6px 10px",display:"flex",flexDirection:"column",justifyContent:"center"}}>
          <div style={{display:"flex",alignItems:"center",gap:4,marginBottom:2}}>
            <div style={{width:5,height:5,borderRadius:"50%",background:dCol}}/>
            <span style={{fontSize:13,fontWeight:700,color:"#1565C0"}}>W{String(isoWeek(weekStart)).padStart(2,"0")}</span>
          </div>
          <div style={{fontSize:9,color:"#9AA0AA",lineHeight:1.5}}>{fmt(weekStart)}</div>
          <div style={{fontSize:9,color:"#9AA0AA"}}>{fmt(addD(weekStart,6))}</div>
          <div style={{marginTop:3,fontSize:10,fontWeight:700,color:dCol}}>{hhmm(weekDrive)}</div>
        </div>
        <svg width={cw} height={RH} style={{display:"block",flexShrink:0}}>
          {[0,1,2,3,4,5,6].map(di=>{
            const x1=px(di*1440),x2=px((di+1)*1440),rx=Math.max(0,x1),rw=Math.min(cw,x2)-rx;
            if(rw<=0) return null;
            return <rect key={di} x={rx} y={0} width={rw} height={RH} fill={di%2===0?"#FFF":"#F6F7FA"}/>;
          })}
          {dayDots.map((col,di)=>{
            if(!col) return null;
            const xc=px(di*1440+720);
            if(xc<4||xc>cw-4) return null;
            return <circle key={di} cx={xc} cy={10} r={3} fill={col} opacity={0.75}/>;
          })}
          <rect x={0} y={T1Y} width={cw} height={T1H} fill="#E0F7FA" rx={2} opacity={0.3}/>
          <rect x={0} y={T1Y} width={cw} height={T1H} fill="none" stroke="#B2EBF2" strokeWidth={0.8} rx={2}/>
          {flat.filter(s=>s.absE>vs&&s.absS<ve).map((s,i)=>{
            const x1=Math.max(0,px(s.absS)),x2=Math.min(cw,px(s.absE)),bw=x2-x1;
            if(bw<0.4) return null;
            const fill=ACT_FILL[s.act];
            const border=ACT_STROKE[s.act];
            const trackCY=T1Y+T1H/2;
            const bh=Math.max(4,Math.round((T1H-2)*ACT_HFRAC[s.act]));
            const by=trackCY-bh/2;
            const cx=x1+bw/2,cy=trackCY;
            return (
              <g key={i}>
                <rect x={x1} y={by} width={bw} height={bh} fill={fill} rx={2}
                  onMouseEnter={e=>setTip({mx:e.clientX,my:e.clientY,act:s.act,absS:s.absS,absE:s.absE,dur:s.dur,date:s.date})}
                  onMouseLeave={()=>setTip(null)} style={{cursor:"default"}}/>
                <rect x={x1} y={by} width={bw} height={bh} fill="none" stroke={border} strokeWidth={0.8} rx={2} style={{pointerEvents:"none"}}/>
                {bw>14&&<TachoSym act={s.act} cx={bw>50?x1+12:cx} cy={cy} s={bw>50?0.9:0.75}/>}
                {bw>50&&<text x={cx} y={cy+4} textAnchor="middle" fill={ACT_TEXT[s.act]} fontSize={bw>80?10:8} fontFamily="Inter" fontWeight="600" style={{pointerEvents:"none"}}>{hhmm(s.dur)}</text>}
              </g>
            );
          })}
          <rect x={0} y={T2Y} width={cw} height={T2H} fill="#E3F2FD" rx={2} opacity={0.35}/>
          <rect x={0} y={T2Y} width={cw} height={T2H} fill="none" stroke="#BBDEFB" strokeWidth={0.8} rx={2}/>
          {longRests.filter(r=>r.absE>vs&&r.absS<ve).map((r,i)=>{
            const x1=Math.max(0,px(r.absS)),x2=Math.min(cw,px(r.absE)),bw=x2-x1;
            if(bw<0.4) return null;
            return (
              <g key={i}>
                <rect x={x1} y={T2Y+1} width={bw} height={T2H-2} fill="#90CAF9" rx={2} opacity={0.75}
                  onMouseEnter={e=>setTip({mx:e.clientX,my:e.clientY,act:-1,absS:r.absS,absE:r.absE,dur:r.dur})}
                  onMouseLeave={()=>setTip(null)} style={{cursor:"default"}}/>
                {bw>35&&<text x={x1+bw/2} y={T2Y+T2H/2+4} textAnchor="middle" fill="#1565C0" fontSize={8} fontFamily="Inter" fontWeight="600" style={{pointerEvents:"none"}}>{hhmm(r.dur)}</text>}
              </g>
            );
          })}
          {restStarts.filter(r=>r.absM>=vs&&r.absM<=ve).map((r,i)=>{
            const x=px(r.absM);
            if(x<0||x>cw) return null;
            return (
              <g key={i} style={{pointerEvents:"none"}}>
                <line x1={x} y1={T2Y-2} x2={x} y2={T2Y+T2H+2} stroke="#43A047" strokeWidth={2} opacity={0.9}/>
                <polygon points={x+","+(T2Y-2)+" "+(x-5)+","+(T2Y-10)+" "+(x+5)+","+(T2Y-10)} fill="#43A047"/>
                <rect x={x-16} y={T2Y-22} width={32} height={12} fill="#E8F5E9" stroke="#43A047" strokeWidth={0.8} rx={2}/>
                <text x={x} y={T2Y-13} textAnchor="middle" fill="#2E7D32" fontSize={8} fontFamily="Inter" fontWeight="700">{r.label}</text>
              </g>
            );
          })}
          {[1,2,3,4,5,6].map(di=>{
            const x=px(di*1440);
            if(x<0||x>cw) return null;
            return <line key={di} x1={x} y1={T1Y-8} x2={x} y2={T2Y+T2H+4} stroke="#66BB6A" strokeWidth={1.2} strokeDasharray="4,3" opacity={0.5}/>;
          })}
          {allCross.filter(c=>c.absM>=vs&&c.absM<=ve).map((c,i)=>{
            const x=px(c.absM);
            if(x<0||x>cw) return null;
            const cs=ccStyle(c.to);
            const label=c.from&&c.from!=="?"?c.from+">"+c.to:c.to;
            const bw=label.length*5+8;
            return (
              <g key={i} onClick={()=>onCross(c)} style={{cursor:"pointer"}}>
                <line x1={x} y1={T1Y-2} x2={x} y2={T1Y+T1H+2} stroke={cs.bd} strokeWidth={2} opacity={0.85}/>
                <polygon points={x+","+T1Y+" "+(x-4)+","+(T1Y-7)+" "+(x+4)+","+(T1Y-7)} fill={cs.bd}/>
                <rect x={x-bw/2} y={T1Y-21} width={bw} height={13} fill={cs.bg} stroke={cs.bd} strokeWidth={1} rx={2}/>
                <text x={x} y={T1Y-11} textAnchor="middle" fill={cs.tx} fontSize={7} fontFamily="Inter" fontWeight="700">{label}</text>
              </g>
            );
          })}
          {driveMarkers.map((m,i)=>{
            const x=px(m.abs);
            if(x<-20||x>cw+20) return null;
            return (
              <g key={i} style={{pointerEvents:"none"}}>
                <line x1={x} y1={T1Y} x2={x} y2={T1Y+T1H} stroke="#37474F" strokeWidth={1.2}/>
                {m.type==="start"
                  ?<polygon points={x+","+T1Y+" "+(x-3)+","+(T1Y-5)+" "+(x+3)+","+(T1Y-5)} fill="#37474F"/>
                  :<circle cx={x} cy={T1Y-3} r={2.5} fill="#37474F"/>}
              </g>
            );
          })}
          {[0,1,2,3,4,5,6].map(di=>{
            const xm=px(di*1440+720);
            if(xm<22||xm>cw-22) return null;
            const d=addD(weekStart,di);
            return (
              <g key={di} onClick={()=>onDayClick&&onDayClick(di)} style={{cursor:"pointer"}}>
                <rect x={xm-30} y={AXY+2} width={60} height={14} fill="transparent"/>
                <text x={xm} y={AXY+13} textAnchor="middle" fill={di>=5?"#9AA0AA":"#1565C0"} fontSize={10} fontFamily="Inter" fontWeight={di>=5?400:600} textDecoration="underline">{fmt(d)}</text>
              </g>
            );
          })}
          {showNow&&(
            <g style={{pointerEvents:"none"}}>
              <line x1={nowX} y1={T1Y-8} x2={nowX} y2={T2Y+T2H+4} stroke="#F44336" strokeWidth={1.5} strokeDasharray="3,2" opacity={0.7}/>
              <rect x={nowX-13} y={T1Y-20} width={26} height={12} fill="#F44336" rx={2}/>
              <text x={nowX} y={T1Y-11} textAnchor="middle" fill="#fff" fontSize={8} fontFamily="Inter" fontWeight="600">{hhmm(now.getHours()*60+now.getMinutes())}</text>
            </g>
          )}
          <line x1={0} y1={AXY} x2={cw} y2={AXY} stroke="#E0E2E8" strokeWidth={1}/>
        </svg>
      </div>
      <div style={{display:"flex",alignItems:"stretch",background:"#F8F9FB",borderTop:"1px solid #EEF0F4"}}>
        <div style={{width:LW,flexShrink:0,borderRight:"1px solid #E2E4EA",padding:"4px 8px",display:"flex",alignItems:"center"}}>
          {dist>0&&<span style={{fontSize:9,color:"#9AA0AA",fontWeight:500}}>{dist} km</span>}
        </div>
        <div style={{flex:1,display:"flex",alignItems:"center",flexWrap:"wrap"}}>
          {[3,2,1,0].map(k=>{
            const val=totals[k]||0;
            if(!val) return null;
            return (
              <div key={k} style={{display:"flex",alignItems:"center",gap:5,padding:"4px 12px",borderRight:"1px solid #EEF0F4"}}>
                <div style={{width:8,height:8,borderRadius:2,background:ACT_SOLID[k],flexShrink:0}}/>
                <span style={{fontSize:9,color:"#6A7080",whiteSpace:"nowrap"}}>
                  <span style={{fontWeight:600,color:ACT_SOLID[k]}}>{ACT_NAME[k]}</span> {hm(val)}
                </span>
              </div>
            );
          })}
          <button onClick={()=>setExpanded(v=>!v)} style={{marginLeft:"auto",background:"none",border:"none",fontSize:10,color:"#1E88E5",cursor:"pointer",padding:"4px 14px",fontFamily:"Inter",fontWeight:600,display:"flex",alignItems:"center",gap:4}}>
            <span style={{fontSize:12,lineHeight:1}}>{expanded?"v":">"}</span>
            {expanded?"Ukryj liste":"Szczegolowa lista dzialan"}
          </button>
        </div>
      </div>
      {expanded&&(
        <div style={{borderTop:"1px solid #EEF0F4",overflowX:"auto"}}>
          <table style={{width:"100%",borderCollapse:"collapse",fontSize:11,fontFamily:"Inter"}}>
            <thead>
              <tr style={{background:"#F0F4F8"}}>
                <th style={{padding:"5px 10px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"1px solid #E0E4E8",whiteSpace:"nowrap"}}>Data</th>
                <th style={{padding:"5px 10px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"1px solid #E0E4E8",whiteSpace:"nowrap"}}>Start</th>
                <th style={{padding:"5px 10px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"1px solid #E0E4E8",whiteSpace:"nowrap"}}>Stop</th>
                <th style={{padding:"5px 10px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"1px solid #E0E4E8",whiteSpace:"nowrap"}}>Czas</th>
                <th style={{padding:"5px 10px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"1px solid #E0E4E8",whiteSpace:"nowrap"}}>Aktywnosc</th>
                <th style={{padding:"5px 10px",textAlign:"left",fontWeight:700,color:"#5A6070",fontSize:10,borderBottom:"1px solid #E0E4E8",whiteSpace:"nowrap"}}>Pojazd</th>
              </tr>
            </thead>
            <tbody>
              {days.map((day,di)=>{
                if(!day||!day.slots) return null;
                return day.slots.map((s,si)=>{
                  const even=(di*100+si)%2===0;
                  return (
                    <tr key={di+"-"+si} style={{background:even?"#FFF":"#F8FAFC"}}>
                      <td style={{padding:"4px 10px",color:"#5A6070",borderBottom:"1px solid #F0F2F5",whiteSpace:"nowrap"}}>{si===0?fmt(day.date):""}</td>
                      <td style={{padding:"4px 10px",fontFamily:"monospace",color:"#1A2030",borderBottom:"1px solid #F0F2F5",whiteSpace:"nowrap"}}>{hhmm(s.startMin)}</td>
                      <td style={{padding:"4px 10px",fontFamily:"monospace",color:"#1A2030",borderBottom:"1px solid #F0F2F5",whiteSpace:"nowrap"}}>{hhmm(s.endMin)}</td>
                      <td style={{padding:"4px 10px",fontFamily:"monospace",fontWeight:600,color:"#1A2030",borderBottom:"1px solid #F0F2F5",whiteSpace:"nowrap"}}>{hhmm(s.duration)}</td>
                      <td style={{padding:"4px 10px",borderBottom:"1px solid #F0F2F5",whiteSpace:"nowrap"}}>
                        <span style={{display:"inline-flex",alignItems:"center",gap:5}}>
                          <span style={{display:"inline-block",width:8,height:8,borderRadius:2,background:ACT_SOLID[s.activity],flexShrink:0}}/>
                          <span style={{color:ACT_SOLID[s.activity],fontWeight:600}}>{ACT_NAME[s.activity]}</span>
                        </span>
                      </td>
                      <td style={{padding:"4px 10px",color:"#5A6070",borderBottom:"1px solid #F0F2F5",whiteSpace:"nowrap"}}>{day.vehicle||"-"}</td>
                    </tr>
                  );
                });
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function App(){
  const [data,setData]=useState(null);
  const [loading,setLoading]=useState(true);
  const [err,setErr]=useState(null);
  const [numWeeks,setNumWeeks]=useState(5);
  const [startWk,setStartWk]=useState(()=>addD(monDay(new Date()),-4*7));
  const [vs,setVs]=useState(0);
  const [ve,setVe]=useState(7*1440);
  const [tip,setTip]=useState(null);
  const [mode,setMode]=useState("select");
  const [crossModal,setCrossModal]=useState(null);
  const [dayModal,setDayModal]=useState(null);
  const [panStart,setPanStart]=useState(null);
  const [selStart,setSelStart]=useState(null);
  const [selEnd,setSelEnd]=useState(null);

  const rootRef=useRef(null);
  const chartRef=useRef(null);
  const [cw,setCw]=useState(900);

  // Fetch binary DDD file from server and parse client-side
  useEffect(()=>{
    const load=async()=>{
      try{
        const resp=await fetch("/analysis/"+TACHO_FILE_ID+"/file");
        if(!resp.ok) throw new Error("HTTP "+resp.status);
        const buf=await resp.arrayBuffer();
        const r=parseDDD(buf);
        if(r){
          setData({...r,demo:false});
          setStartWk(addD(monDay(r.days[r.days.length-1].date),-(numWeeks-1)*7));
        } else {
          setErr("Nie wykryto danych aktywnosci w pliku. Format nieobslugiwany (.ddd, .v1b).");
        }
      }catch(ex){
        setErr("Blad wczytywania pliku: "+ex.message);
      }finally{
        setLoading(false);
      }
    };
    load();
  },[]);

  useEffect(()=>{
    if(!rootRef.current) return;
    const obs=new ResizeObserver(es=>{if(es[0])setCw(Math.floor(es[0].contentRect.width));});
    obs.observe(rootRef.current);
    return()=>obs.disconnect();
  },[loading]);

  const chartWidth=Math.max(400,cw-LW-2);
  const dur=ve-vs;

  useEffect(()=>{
    const el=chartRef.current;if(!el) return;
    const fn=e=>{
      e.preventDefault();
      const rect=el.getBoundingClientRect();
      const mx=e.clientX-rect.left-LW;
      if(mx<0||mx>chartWidth) return;
      const mMin=vs+(mx/chartWidth)*dur;
      const fac=e.deltaY>0?1.3:0.77;
      let nd=clamp(dur*fac,360,7*1440);
      let ns=mMin-(mx/chartWidth)*nd,ne=ns+nd;
      if(ns<0){ns=0;ne=nd;}if(ne>7*1440){ne=7*1440;ns=7*1440-nd;}
      setVs(ns);setVe(ne);
    };
    el.addEventListener("wheel",fn,{passive:false});
    return()=>el.removeEventListener("wheel",fn);
  },[vs,ve,dur,chartWidth]);

  const onMouseDown=e=>{
    if(e.button!==0) return;
    e.preventDefault();
    const rect=chartRef.current.getBoundingClientRect();
    const mx=e.clientX-rect.left-LW;
    if(mx<0||mx>chartWidth) return;
    if(mode==="pan") setPanStart({clientX:e.clientX,vs,ve});
    else {setSelStart(mx);setSelEnd(mx);}
  };
  const onMouseMove=e=>{
    if(!chartRef.current) return;
    const rect=chartRef.current.getBoundingClientRect();
    const mx=clamp(e.clientX-rect.left-LW,0,chartWidth);
    if(mode==="pan"&&panStart){
      const dx=e.clientX-panStart.clientX;
      const shift=(dx/chartWidth)*dur*-1;
      let ns=panStart.vs+shift,ne=panStart.ve+shift;
      if(ns<0){ns=0;ne=ne-ns;}if(ne>7*1440){ne=7*1440;ns=ns-(ne-7*1440);}
      setVs(clamp(ns,0,7*1440));setVe(clamp(ne,0,7*1440));
    } else if(mode==="select"&&selStart!==null){
      setSelEnd(mx);
    }
  };
  const onMouseUp=()=>{
    if(mode==="pan"){setPanStart(null);}
    else if(selStart!==null){
      const a=Math.min(selStart,selEnd||selStart);
      const b=Math.max(selStart,selEnd||selStart);
      if(b-a>10){
        const ns=vs+(a/chartWidth)*dur;
        const ne=vs+(b/chartWidth)*dur;
        setVs(ns);setVe(ne);
      }
      setSelStart(null);setSelEnd(null);
    }
  };
  const onMouseLeave=()=>{setPanStart(null);setSelStart(null);setSelEnd(null);};

  const selX=selStart!==null&&selEnd!==null?Math.min(selStart,selEnd):null;
  const selW=selStart!==null&&selEnd!==null?Math.abs(selEnd-selStart):0;

  const allWeeks=useMemo(()=>{
    if(!data) return [];
    return Array.from({length:numWeeks},(_,i)=>{
      const ws=addD(startWk,i*7);
      const days=Array.from({length:7},(_,di)=>{
        const d=addD(ws,di);
        return data.days.find(x=>x.date.toDateString()===d.toDateString())||null;
      });
      return{start:ws,days};
    });
  },[data,startWk,numWeeks]);

  const availWeeks=useMemo(()=>{
    if(!data) return [];
    const s=new Set();
    data.days.forEach(d=>s.add(monDay(d.date).toDateString()));
    return[...s].map(x=>new Date(x)).sort((a,b)=>a-b);
  },[data]);

  const totalDrive=allWeeks.reduce((s,w)=>s+w.days.reduce((s2,d)=>s2+(d?d.slots.filter(x=>x.activity===3).reduce((a,b)=>a+b.duration,0):0),0),0);

  const NB={background:"transparent",border:"none",borderRight:"1px solid #E0E4E8",color:"#5A6070",padding:"0 12px",fontSize:13,cursor:"pointer",fontFamily:"Inter",minHeight:40};
  const ZB={background:"#FFF",border:"1px solid #DDE1E6",color:"#5A6070",padding:"4px 10px",borderRadius:4,fontSize:10,fontFamily:"Inter",cursor:"pointer"};

  if(loading){
    return (
      <div ref={rootRef} style={{width:"100%",overflow:"hidden",fontFamily:"Inter"}}>
        <div style={{padding:"40px 20px",textAlign:"center",color:"#5A6070"}}>
          <div style={{fontSize:14,color:"#1E88E5",fontWeight:600}}>Wczytywanie pliku DDD...</div>
        </div>
      </div>
    );
  }

  if(err&&!data){
    return (
      <div ref={rootRef} style={{width:"100%",overflow:"hidden",fontFamily:"Inter"}}>
        <div style={{padding:"20px"}}>
          <div style={{padding:"12px 16px",background:"#FFEBEE",border:"1px solid #FFCDD2",borderRadius:4,color:"#C62828",fontSize:13}}>{err}</div>
        </div>
      </div>
    );
  }

  return (
    <div ref={rootRef} style={{background:"transparent",fontFamily:"Inter",width:"100%",overflow:"hidden"}}>

      <div style={{display:"flex",alignItems:"center",gap:10,marginBottom:12,flexWrap:"wrap"}}>
        <div style={{display:"flex",alignItems:"center",gap:8}}>
          <div style={{background:"#1E88E5",color:"#fff",padding:"5px 10px",borderRadius:4,fontSize:12,fontWeight:700,letterSpacing:1}}>TACHO</div>
          <span style={{fontSize:16,fontWeight:600,color:"#1A2030"}}>Analyzer</span>
          <span style={{fontSize:10,color:"#9AA0AA",border:"1px solid #DDE1E6",padding:"2px 7px",borderRadius:3}}>EU 561/2006</span>
        </div>
        {data&&data.driver&&(
          <div style={{display:"flex",alignItems:"center",gap:6,padding:"4px 12px",background:"#FFF",border:"1px solid #E0E4E8",borderRadius:4}}>
            <span style={{fontSize:11,color:"#9AA0AA"}}>Kierowca:</span>
            <span style={{fontSize:13,fontWeight:600,color:"#1A2030"}}>{data.driver}</span>
            <span style={{fontSize:10,color:"#BFC5CC",marginLeft:4}}>{data&&data.days?data.days.length:0} dni</span>
          </div>
        )}
        <div style={{marginLeft:"auto",padding:"4px 12px",background:"#FFF",border:"1px solid #E0E4E8",borderRadius:4,fontSize:11,color:"#5A6070"}}>
          Jazda ({numWeeks} tyg.): <strong style={{color:"#1A2030"}}>{hhmm(totalDrive)}</strong>
        </div>
      </div>

      {err&&<div style={{marginBottom:10,padding:"8px 12px",background:"#FFF8E1",border:"1px solid #FFE082",borderRadius:4,color:"#E65100",fontSize:12}}>{err}</div>}

      <div style={{display:"flex",alignItems:"stretch",marginBottom:10,background:"#FFF",border:"1px solid #E0E4E8",borderRadius:6,overflow:"hidden",flexWrap:"wrap"}}>
        <button onClick={()=>setStartWk(d=>addD(d,-numWeeks*7))} style={NB}>&lt;&lt;</button>
        <button onClick={()=>setStartWk(d=>addD(d,-7))} style={NB}>&lt;</button>
        <button onClick={()=>setStartWk(addD(monDay(new Date()),-(numWeeks-1)*7))} style={{...NB,color:"#1E88E5",fontWeight:600}}>Dzis</button>
        <button onClick={()=>setStartWk(d=>addD(d,7))} style={NB}>&gt;</button>
        <button onClick={()=>setStartWk(d=>addD(d,numWeeks*7))} style={{...NB,borderRight:"1px solid #E0E4E8"}}>&gt;&gt;</button>
        <div style={{display:"flex",alignItems:"center",gap:6,padding:"0 14px",borderRight:"1px solid #E0E4E8"}}>
          <span style={{fontSize:11,color:"#9AA0AA"}}>Tygodni:</span>
          {[3,4,5,6,8].map(n=>(
            <button key={n} onClick={()=>setNumWeeks(n)} style={{background:numWeeks===n?"#E3F2FD":"transparent",border:"1px solid "+(numWeeks===n?"#1E88E5":"#DDE1E6"),color:numWeeks===n?"#1E88E5":"#9AA0AA",padding:"3px 9px",borderRadius:3,fontSize:10,cursor:"pointer",fontWeight:numWeeks===n?600:400}}>{n}</button>
          ))}
        </div>
        <div style={{display:"flex",alignItems:"center",padding:"0 14px",fontSize:12,color:"#5A6070",borderRight:"1px solid #E0E4E8"}}>{fmt(startWk)} - {fmt(addD(startWk,numWeeks*7-1))}</div>
        <div style={{display:"flex",alignItems:"center",gap:3,padding:"5px 10px",marginLeft:"auto",flexWrap:"wrap"}}>
          {availWeeks.slice(-14).map((aw,i)=>{
            const inV=aw>=startWk&&aw<addD(startWk,numWeeks*7);
            return <button key={i} onClick={()=>setStartWk(addD(aw,-(numWeeks-1)*7))} style={{background:inV?"#E3F2FD":"transparent",border:"1px solid "+(inV?"#1E88E5":"#DDE1E6"),color:inV?"#1E88E5":"#9AA0AA",padding:"2px 7px",borderRadius:3,fontSize:9,cursor:"pointer",fontWeight:inV?600:400}}>W{isoWeek(aw)}</button>;
          })}
        </div>
      </div>

      <div style={{background:"#FFF",border:"1px solid #E0E4E8",borderRadius:6,overflow:"hidden",boxShadow:"0 1px 4px rgba(0,0,0,0.06)"}}>
        <div style={{display:"flex",alignItems:"center",gap:10,flexWrap:"wrap",padding:"8px 12px",background:"#F8F9FB",borderBottom:"1px solid #E0E2E8"}}>
          <span style={{fontSize:10,color:"#9AA0AA",fontWeight:600}}>LEGENDA</span>
          {[
            {fill:"#80DEEA",bd:"#00838F",lbl:"Odpoczynek"},
            {fill:"#EF9A9A",bd:"#C62828",lbl:"Jazda"},
            {fill:"#FFCC80",bd:"#BF360C",lbl:"Praca"},
            {fill:"#9FA8DA",bd:"#3949AB",lbl:"Dyspozycyjnosc"},
            {fill:"#90CAF9",bd:"#1E88E5",lbl:"Odpoczynek dobowy"},
          ].map((it,i)=>(
            <div key={i} style={{display:"flex",alignItems:"center",gap:5}}>
              <div style={{width:20,height:10,background:it.fill,border:"1px solid "+it.bd+"80",borderRadius:2}}/>
              <span style={{fontSize:10,color:"#5A6070"}}>{it.lbl}</span>
            </div>
          ))}
          <span style={{fontSize:9,color:"#2E7D32",fontWeight:600}}>v Start odpoczynku dobowego</span>
          <div style={{marginLeft:"auto",display:"flex",gap:8,fontSize:10,color:"#9AA0AA"}}>
            {[["#43A047","Zgodny"],["#FF9800","Ostrzezenie"],["#E53935","Naruszenie"]].map(([c,l])=>(
              <span key={l}><span style={{color:c,fontSize:12}}>o</span> {l}</span>
            ))}
          </div>
        </div>

        <div style={{display:"flex",alignItems:"center",gap:8,padding:"7px 12px",background:"#F3F4F7",borderBottom:"1px solid #E0E2E8",flexWrap:"wrap"}}>
          <span style={{fontSize:10,fontWeight:600,color:"#9AA0AA"}}>ZOOM</span>
          <button onClick={()=>{setVs(0);setVe(7*1440);}} style={ZB}>7 dni</button>
          <button onClick={()=>{setVs(0);setVe(5*1440);}} style={ZB}>5 dni</button>
          <button onClick={()=>{setVs(0);setVe(3*1440);}} style={ZB}>3 dni</button>
          <button onClick={()=>{setVs(0);setVe(1440);}} style={ZB}>1 dzien</button>
          <button onClick={()=>{const c=(vs+ve)/2,d=(ve-vs)/4;setVs(clamp(c-d,0,7*1440-360));setVe(clamp(c+d,360,7*1440));}} style={ZB}>+ Zbliz</button>
          <button onClick={()=>{const c=(vs+ve)/2,nd=clamp((ve-vs)*1.6,ve-vs,7*1440);let ns=c-nd/2,ne=c+nd/2;if(ns<0){ns=0;ne=nd;}if(ne>7*1440){ne=7*1440;ns=7*1440-nd;}setVs(clamp(ns,0,7*1440));setVe(clamp(ne,0,7*1440));}} style={ZB}>- Oddal</button>
          <div style={{width:1,height:16,background:"#DDE1E6"}}/>
          <button onClick={()=>setMode("select")} style={{...ZB,background:mode==="select"?"#E3F2FD":"#FFF",border:"1px solid "+(mode==="select"?"#1E88E5":"#DDE1E6"),color:mode==="select"?"#1E88E5":"#5A6070",fontWeight:mode==="select"?600:400}}>[ ] Zaznacz</button>
          <button onClick={()=>setMode("pan")} style={{...ZB,background:mode==="pan"?"#E3F2FD":"#FFF",border:"1px solid "+(mode==="pan"?"#1E88E5":"#DDE1E6"),color:mode==="pan"?"#1E88E5":"#5A6070",fontWeight:mode==="pan"?600:400}}>&lt;-&gt; Przesuwaj</button>
          <span style={{fontSize:10,color:"#C0C4CC"}}>Scroll=zoom</span>
          <span style={{marginLeft:"auto",fontSize:10,color:"#9AA0AA"}}>{Math.round((ve-vs)/144)/10} dni</span>
        </div>

        <div style={{display:"flex",background:"#F0F4F8",borderBottom:"1px solid #E0E2E8"}}>
          <div style={{width:LW,flexShrink:0,padding:"5px 10px",fontSize:9,fontWeight:700,color:"#9AA0AA",letterSpacing:1,borderRight:"1px solid #E2E4EA"}}>TYDZIEN</div>
          <div style={{flex:1,padding:"5px 12px",fontSize:9,fontWeight:700,color:"#9AA0AA",letterSpacing:1}}>OS CZASU 7 DNI - kliknij date aby zobaczyc dzien - kliknij kod granicy aby zobaczyc przejscie</div>
        </div>

        <div ref={chartRef}
          style={{position:"relative",cursor:mode==="pan"?(panStart?"grabbing":"grab"):"crosshair",userSelect:"none",WebkitUserSelect:"none"}}
          onMouseDown={onMouseDown} onMouseMove={onMouseMove} onMouseUp={onMouseUp} onMouseLeave={onMouseLeave}>
          {allWeeks.map((w,i)=>(
            <div key={i} style={{marginBottom:10,borderRadius:4,overflow:"hidden",boxShadow:"0 1px 3px rgba(0,0,0,0.07)"}}>
              <WeekRow weekStart={w.start} days={w.days} cw={chartWidth} vs={vs} ve={ve} setTip={setTip}
                onCross={c=>{setTip(null);setCrossModal(c);}}
                onDayClick={di=>{
                  const d=w.days[di];
                  if(d) setDayModal(d);
                }}
              />
            </div>
          ))}
          {mode==="select"&&selX!==null&&selW>4&&(
            <div style={{position:"absolute",top:0,left:LW+selX,width:selW,height:"100%",background:"rgba(30,136,229,0.1)",border:"1px solid #1E88E5",borderRadius:2,pointerEvents:"none",zIndex:10}}>
              <div style={{position:"absolute",top:4,left:"50%",transform:"translateX(-50%)",background:"#1E88E5",color:"#fff",fontSize:9,padding:"2px 7px",borderRadius:2,whiteSpace:"nowrap",fontFamily:"Inter",fontWeight:600}}>
                {hm(Math.round((selW/chartWidth)*dur))}
              </div>
            </div>
          )}
        </div>
      </div>

      <div style={{display:"flex",justifyContent:"space-between",flexWrap:"wrap",gap:4,marginTop:10}}>
        <span style={{fontSize:10,color:"#BFC5CC"}}>TACHO ANALYZER - EC 3821/85 - (WE) 561/2006 - (UE) 165/2014</span>
        <span style={{fontSize:10,color:"#BFC5CC"}}>Dane przetwarzane lokalnie</span>
      </div>

      {tip&&(
        <div style={{position:"fixed",left:tip.mx+16,top:tip.my-50,background:"#FFF",border:"1px solid "+(tip.act>=0?ACT_STROKE[tip.act]+"60":"#1E88E540"),borderLeft:"3px solid "+(tip.act>=0?ACT_STROKE[tip.act]:"#1E88E5"),padding:"9px 13px",borderRadius:4,pointerEvents:"none",zIndex:9999,fontFamily:"Inter",fontSize:12,boxShadow:"0 6px 24px rgba(0,0,0,.12)",minWidth:155}}>
          <div style={{fontWeight:700,fontSize:13,marginBottom:6,color:tip.act>=0?ACT_TEXT[tip.act]:"#1565C0"}}>
            {tip.act>=0?ACT_NAME[tip.act]:"Odpoczynek dobowy"}
          </div>
          {[["Od",hhmm(tip.absS%1440)],["Do",hhmm(tip.absE%1440)],["Czas",hm(tip.dur)]].map(([k,v])=>(
            <div key={k} style={{display:"flex",justifyContent:"space-between",gap:14,marginBottom:2}}>
              <span style={{color:"#9AA0AA",fontSize:10}}>{k}</span>
              <span style={{color:"#333",fontWeight:500}}>{v}</span>
            </div>
          ))}
          {tip.date&&<div style={{marginTop:5,fontSize:9,color:"#BFC5CC"}}>{fmt(tip.date)}</div>}
        </div>
      )}

      <CrossingModal crossing={crossModal} onClose={()=>setCrossModal(null)}/>
      <DayModal day={dayModal} onClose={()=>setDayModal(null)}/>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("tacho-analyzer-root")).render(<App/>);
</script>
