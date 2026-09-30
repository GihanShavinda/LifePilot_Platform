import {useState} from 'react';
import type {LifeTask} from './types';
import {addChecklist,toggleChecklist,deleteChecklist,addDependency,removeDependency,addReminder,snoozeReminder,updateTask,deleteTask,getTask} from './taskApi';
export function TaskDetailPanel({task,onChange,onClose}:{task:LifeTask;onChange:(task:LifeTask|null)=>void;onClose:()=>void}){
 const [editTitle,setEditTitle]=useState(task.title),[editDesc,setEditDesc]=useState(task.description??''),[editPriority,setEditPriority]=useState(task.priority),[editLabels,setEditLabels]=useState((task.labels??[]).join(', ')),[editDue,setEditDue]=useState(task.due_at?new Date(task.due_at).toISOString().slice(0,16):'');
 const [item,setItem]=useState(''),[dep,setDep]=useState(''),[remind,setRemind]=useState(''),[evidence,setEvidence]=useState(''),[err,setErr]=useState(''),[busy,setBusy]=useState(false);
 async function run(action:()=>Promise<unknown>){setBusy(true);setErr('');try{await action();onChange(await getTask(task.id));}catch(e:any){setErr(e?.response?.data?.error?.message??e?.response?.data?.message??'Action failed');}finally{setBusy(false);}}
 return <aside className="card" style={{display:'grid',gap:12,alignContent:'start'}} aria-label="Task details"><div className="actions"><h2 style={{flex:1}}>{task.title}</h2><button className="secondary" onClick={onClose}>Close</button></div>
  <p>{task.description}</p><p><b>Priority:</b> {task.priority} · <b>Status:</b> {task.effective_status}</p><p><b>Due:</b> {task.due_at?new Date(task.due_at).toLocaleString():'No due date'}</p>
  {task.document_id&&<p><a href={`/documents?document=${task.document_id}`}>Source document #{task.document_id}</a></p>}
  {err&&<p className="error" role="alert">{err}</p>}
  <div className="actions wrap">{(['pending','in_progress','completed','skipped'] as const).map(status=><button key={status} className="secondary" disabled={busy||task.status===status} onClick={()=>run(()=>updateTask(task.id,{status,...(status==='completed'?{completion_evidence:evidence}:{})}))}>{status.replace('_',' ')}</button>)}</div>
  <label>Completion evidence<textarea value={evidence} onChange={e=>setEvidence(e.target.value)} placeholder="Confirmation number, notes, receipt details…"/></label>
  <details><summary>Edit task information</summary><form style={{display:'grid',gap:8,marginTop:8}} onSubmit={e=>{e.preventDefault();void run(()=>updateTask(task.id,{title:editTitle,description:editDesc,priority:editPriority,labels:editLabels.split(',').map(x=>x.trim()).filter(Boolean),due_at:editDue?new Date(editDue).toISOString():null}));}}>
   <label>Title<input required value={editTitle} onChange={e=>setEditTitle(e.target.value)}/></label>
   <label>Description<textarea value={editDesc} onChange={e=>setEditDesc(e.target.value)}/></label>
   <label>Priority<select value={editPriority} onChange={e=>setEditPriority(e.target.value)}>{(['low','medium','high','urgent'] as const).map(p=><option key={p}>{p}</option>)}</select></label>
   <label>Labels<input value={editLabels} onChange={e=>setEditLabels(e.target.value)}/></label>
   <label>Due<input type="datetime-local" value={editDue} onChange={e=>setEditDue(e.target.value)}/></label>
   <button disabled={busy}>Save changes</button>
  </form></details>
  <h3>Checklist</h3>{task.checklist?.map(c=><div className="actions" key={c.id}><label style={{flex:1}}><input style={{width:'auto'}} type="checkbox" checked={c.is_completed} onChange={e=>run(()=>toggleChecklist(task.id,c.id,e.target.checked))}/>{c.title}</label><button className="secondary" disabled={busy} onClick={()=>run(()=>deleteChecklist(task.id,c.id))}>Remove</button></div>)}
  <form className="actions" onSubmit={e=>{e.preventDefault();void run(async()=>{await addChecklist(task.id,item);setItem('');});}}><input value={item} onChange={e=>setItem(e.target.value)} placeholder="New checklist item" required/><button disabled={busy}>Add</button></form>
  <h3>Dependencies</h3>{task.dependencies?.map(d=><div className="actions" key={d.id}><span style={{flex:1}}>#{d.id} {d.title} ({d.status})</span><button className="secondary" onClick={()=>run(()=>removeDependency(task.id,d.id))}>Remove</button></div>)}
  <form className="actions" onSubmit={e=>{e.preventDefault();void run(async()=>{await addDependency(task.id,Number(dep));setDep('');});}}><input type="number" min="1" value={dep} onChange={e=>setDep(e.target.value)} placeholder="Prerequisite task ID" required/><button disabled={busy}>Link</button></form>
  <h3>Reminders</h3>{task.reminders?.map(r=><div className="card" key={r.id} style={{padding:10}}><small>{new Date(r.remind_at).toLocaleString()} ({r.status})</small><form className="actions" onSubmit={e=>{e.preventDefault();const until=(e.currentTarget.elements.namedItem('until') as HTMLInputElement).value;void run(()=>snoozeReminder(task.id,r.id,new Date(until).toISOString()));}}><input aria-label="Snooze until" name="until" type="datetime-local" required/><button className="secondary" disabled={busy}>Snooze</button></form></div>)}
  <form className="actions" onSubmit={e=>{e.preventDefault();void run(async()=>{await addReminder(task.id,new Date(remind).toISOString());setRemind('');});}}><input type="datetime-local" value={remind} onChange={e=>setRemind(e.target.value)} required/><button disabled={busy}>Add reminder</button></form>
  <h3>Timeline</h3>{task.activities?.map(a=><p key={a.id}><small>{new Date(a.created_at).toLocaleString()} · {a.event}</small></p>)}
  <button className="danger" disabled={busy} onClick={()=>{if(window.confirm('Delete this task?')){setBusy(true);void deleteTask(task.id).then(()=>onChange(null)).catch(()=>setErr('Delete failed')).finally(()=>setBusy(false));}}}>Delete task</button>
 </aside>;
}
