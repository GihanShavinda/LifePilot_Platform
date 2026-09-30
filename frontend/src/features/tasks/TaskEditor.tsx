import {useState,type FormEvent} from 'react';
import {addTask} from './taskApi';
import type {LifeTask} from './types';
export function TaskEditor({onCreated}:{onCreated:(task:LifeTask)=>void}){
 const [title,setTitle]=useState(''),[description,setDescription]=useState(''),[due,setDue]=useState(''),[priority,setPriority]=useState('medium'),[labels,setLabels]=useState(''),[documentId,setDocumentId]=useState(''),[parentId,setParentId]=useState('');
 const [frequency,setFrequency]=useState(''),[interval,setInterval]=useState(1),[err,setErr]=useState(''),[busy,setBusy]=useState(false);
 async function submit(e:FormEvent){e.preventDefault();setErr('');setBusy(true);try{
  const task=await addTask({title,description,priority,labels:labels.split(',').map(s=>s.trim()).filter(Boolean),due_at:due?new Date(due).toISOString():null,...(documentId?{document_id:Number(documentId)}:{}),...(parentId?{parent_task_id:Number(parentId)}:{}),
   ...(frequency?{recurrence:{frequency,interval}}:{})});onCreated(task);setTitle('');setDue('');setDescription('');setFrequency('');
 }catch(e:any){setErr(e?.response?.data?.error?.message??e?.response?.data?.message??'Unable to create task');}finally{setBusy(false);}}
 return <form className="card" onSubmit={submit} style={{display:'grid',gap:10}}><h2>New task</h2>
  <label>Title<input required value={title} onChange={e=>setTitle(e.target.value)}/></label>
  <label>Details<textarea value={description} onChange={e=>setDescription(e.target.value)}/></label>
  <label>Due date<input type="datetime-local" value={due} onChange={e=>setDue(e.target.value)}/></label>
  <label>Priority<select value={priority} onChange={e=>setPriority(e.target.value)}>{['low','medium','high','urgent'].map(p=><option key={p}>{p}</option>)}</select></label>
  <label>Labels (comma-separated)<input value={labels} onChange={e=>setLabels(e.target.value)}/></label>
  <label>Source document ID (optional)<input type="number" min="1" value={documentId} onChange={e=>setDocumentId(e.target.value)}/></label>
  <label>Parent task ID / subtask (optional)<input type="number" min="1" value={parentId} onChange={e=>setParentId(e.target.value)}/></label>
  <label>Repeat<select value={frequency} onChange={e=>setFrequency(e.target.value)}><option value="">No recurrence</option>{['daily','weekly','monthly','yearly'].map(p=><option key={p}>{p}</option>)}</select></label>
  {frequency&&<label>Every <input type="number" min="1" max="365" value={interval} onChange={e=>setInterval(Number(e.target.value))}/> {frequency}</label>}
  {err&&<p className="error" role="alert">{err}</p>}<button disabled={busy}>{busy?'Saving...':'Create task'}</button>
 </form>;
}
