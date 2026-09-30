import {useEffect,useState,type FormEvent} from 'react';
import {listObligations,addObligation,approveObligation,dismissObligation,getSuggestions,approveSuggestion} from './taskApi';
import type {Obligation,Suggestion} from './types';
export function ObligationPanel({onTaskCreated,documentId}:{onTaskCreated:()=>void;documentId?:number}){
 const [rows,setRows]=useState<Obligation[]>([]),[doc,setDoc]=useState(documentId?String(documentId):''),[suggestions,setSuggestions]=useState<Suggestion[]>([]);
 const [title,setTitle]=useState(''),[type,setType]=useState('payment'),[due,setDue]=useState(''),[amount,setAmount]=useState(''),[currency,setCurrency]=useState('LKR'),[manualDoc,setManualDoc]=useState(''),[err,setErr]=useState('');
 async function load(){setRows(await listObligations());}
 useEffect(()=>{void load().catch(()=>setErr('Unable to load obligations'));if(documentId){void getSuggestions(documentId).then(setSuggestions).catch(()=>setErr('Could not load suggestions'));}},[documentId]);
 async function act(cb:()=>Promise<unknown>){setErr('');try{await cb();await load();onTaskCreated();}catch(e:any){setErr(e?.response?.data?.error?.message??'Request failed');}}
 async function add(e:FormEvent){e.preventDefault();await act(()=>addObligation({title,type,due_at:due?new Date(due).toISOString():null,...(amount?{amount:Number(amount),currency}:{}),...(manualDoc?{document_id:Number(manualDoc)}:{})}));setTitle('');}
 return <section className="card" style={{display:'grid',gap:12}}><h2>Obligations and approvals</h2><p>Document suggestions never create tasks without your approval.</p>
  {err&&<p className="error" role="alert">{err}</p>}
  <form className="grid" onSubmit={add}><label>Obligation title<input required value={title} onChange={e=>setTitle(e.target.value)}/></label>
   <label>Type<select value={type} onChange={e=>setType(e.target.value)}>{['payment','renewal','appointment','submission','collection','maintenance','cancellation','registration','follow-up','custom'].map(v=><option key={v}>{v}</option>)}</select></label>
   <label>Amount (optional)<input type="number" step="0.01" min="0" value={amount} onChange={e=>setAmount(e.target.value)}/></label><label>Currency<input maxLength={3} value={currency} onChange={e=>setCurrency(e.target.value.toUpperCase())}/></label><label>Source document ID (optional)<input type="number" min="1" value={manualDoc} onChange={e=>setManualDoc(e.target.value)}/></label>
   <label>Due<input type="datetime-local" value={due} onChange={e=>setDue(e.target.value)}/></label><button>Create obligation</button></form>
  <h3>Pending approval</h3>{rows.filter(r=>r.status==='suggested').map(r=><div key={r.id} className="card" style={{padding:12}}><b>{r.title}</b><p>{r.type} · {r.due_at?new Date(r.due_at).toLocaleString():'No due date'}</p><div className="actions"><button onClick={()=>void act(()=>approveObligation(r.id))}>Approve & create task</button><button className="secondary" onClick={()=>void act(()=>dismissObligation(r.id))}>Dismiss</button></div></div>)}
  <h3>Suggestions from reviewed documents</h3><div className="actions"><input type="number" min="1" value={doc} onChange={e=>setDoc(e.target.value)} placeholder="Source document ID"/><button onClick={()=>void act(async()=>{setSuggestions(await getSuggestions(Number(doc)));})} disabled={!doc}>Find suggestions</button></div>
  {suggestions.map(s=><div className="card" style={{padding:12}} key={s.dedupe_key}><b>{s.title}</b><p>Type: {s.type} · Due: {s.due_at} · {s.currency??''} {s.amount??''}</p><details><summary>Accepted extraction evidence</summary>{s.source_evidence.map(e=><p key={e.field_id}><b>{e.field_name}:</b> {e.evidence_text}</p>)}</details><button onClick={()=>void act(async()=>{await approveSuggestion(s.document_id,s.dedupe_key);setSuggestions(await getSuggestions(s.document_id));})}>Approve suggestion & create task</button></div>)}
 </section>;
}
