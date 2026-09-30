import {useEffect,useMemo,useState} from 'react';
import {Link,useSearchParams} from 'react-router-dom';
import {getTasks,getTask,getDueReminders,updateTask} from './taskApi';
import {TaskEditor} from './TaskEditor';
import {CalendarView} from './CalendarView';
import {TaskDetailPanel} from './TaskDetailPanel';
import {ObligationPanel} from './ObligationPanel';
import type {LifeTask,View} from './types';
const views:View[]=['today','upcoming','overdue','all','calendar','kanban'];
export function TasksPage(){
 const [params]=useSearchParams();const documentId=Number(params.get('document_id'))||undefined;
 const [notifications,setNotifications]=useState<{id:number;task_id:number;sent_at:string}[]>([]);
 const [view,setView]=useState<View>('today'),[tasks,setTasks]=useState<LifeTask[]>([]),[selected,setSelected]=useState<LifeTask|null>(null),[error,setError]=useState(''),[loading,setLoading]=useState(false),[showNew,setShowNew]=useState(false);
 async function refresh(){setLoading(true);try{setTasks(await getTasks(['calendar','kanban'].includes(view)?'all':view));setError('');}catch(e:any){setError(e?.friendlyMessage??'Unable to load tasks');}finally{setLoading(false);}}
 useEffect(()=>{void refresh();void getDueReminders().then(setNotifications).catch(()=>{});},[view]);
 const sorted=useMemo(()=>tasks.slice().sort((a,b)=>(a.due_at??'9999').localeCompare(b.due_at??'9999')),[tasks]);
 function taskCard(t:LifeTask){return <button type="button" className="task-item" draggable onDragStart={e=>e.dataTransfer.setData('task-id',String(t.id))} key={t.id} onClick={()=>void getTask(t.id).then(setSelected).catch(()=>setError('Unable to load task'))}>
  <strong>{t.title}</strong><span className={`badge ${t.effective_status}`}>{t.effective_status.replace('_',' ')}</span><small>{t.due_at?new Date(t.due_at).toLocaleString():'No deadline'} · {t.priority}</small>
  {t.document_id&&<small>Linked document #{t.document_id}</small>}
  {t.labels?.length?<small>{t.labels.join(' · ')}</small>:null}
 </button>}
 return <main className="page"><header><div><h1>LifePilot Tasks</h1><p>P4 · Document-linked obligations and personal task management</p></div><div className="actions"><Link className="button-link" to="/documents">Documents</Link><Link className="button-link secondary" to="/">Dashboard</Link></div></header>
  <div className="task-tabs" role="tablist">{views.map(v=><button key={v} role="tab" aria-selected={view===v} className={view===v?'':'secondary'} onClick={()=>setView(v)}>{v[0].toUpperCase()+v.slice(1)}</button>)}</div>
  <div className="actions" style={{margin:'1rem 0'}}><button onClick={()=>setShowNew(s=>!s)}>{showNew?'Hide form':'New task'}</button><button className="secondary" onClick={()=>void refresh()}>Refresh</button></div>
  {showNew&&<TaskEditor onCreated={()=>{setShowNew(false);void refresh();}}/>}
  {notifications.length>0&&<details className="card" style={{marginBottom:12}}><summary>{notifications.length} due reminders</summary>{notifications.map(n=><p key={n.id}>Task #{n.task_id}: reminder delivered {new Date(n.sent_at).toLocaleString()}</p>)}</details>}
  {error&&<p className="error" role="alert">{error}</p>}{loading&&<p>Loading…</p>}
  <div className="task-layout"><div>
   {view==='kanban'?<div className="task-kanban">{(['pending','in_progress','overdue','completed','skipped'] as const).map(status=><section key={status} className="card" onDragOver={e=>e.preventDefault()} onDrop={e=>{e.preventDefault();const id=Number(e.dataTransfer.getData('task-id'));if(id&&status!=='overdue')void updateTask(id,{status}).then(()=>refresh()).catch(()=>setError('Kanban move failed'));}}><h2>{status.replace('_',' ')}</h2>{sorted.filter(t=>t.effective_status===status).map(taskCard)}</section>)}</div>
   :view==='calendar'?<CalendarView tasks={sorted} onSelect={id=>void getTask(id).then(setSelected).catch(()=>setError('Unable to load task'))}/>
   :<div className="task-list">{sorted.map(taskCard)}{sorted.length===0&&!loading&&<div className="empty">No tasks in this view</div>}</div>}
  </div>{selected&&<TaskDetailPanel task={selected} onClose={()=>setSelected(null)} onChange={t=>{setSelected(t);void refresh();}}/>}</div>
  <div style={{marginTop:24}}><ObligationPanel documentId={documentId} onTaskCreated={()=>void refresh()}/></div>
 </main>;
}
