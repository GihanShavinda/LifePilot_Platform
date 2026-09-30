import {useMemo,useState} from 'react';
import type {LifeTask} from './types';
export function CalendarView({tasks,onSelect}:{tasks:LifeTask[];onSelect:(id:number)=>void}){
 const [month,setMonth]=useState(()=>{const d=new Date();return new Date(d.getFullYear(),d.getMonth(),1);});
 const cells=useMemo(()=>{
  const first=new Date(month.getFullYear(),month.getMonth(),1);
  const shift=(first.getDay()+6)%7;
  const count=new Date(month.getFullYear(),month.getMonth()+1,0).getDate();
  return [...Array(shift).fill(null),...Array.from({length:count},(_,i)=>i+1)];
 },[month]);
 const byDate=useMemo(()=>{
  const groups:Record<string,LifeTask[]>={};
  for(const task of tasks){if(!task.due_at)continue;const d=new Date(task.due_at);
   const key=`${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`;
   (groups[key]??=[]).push(task);
  }return groups;
 },[tasks]);
 const days=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
 return <section className="card"><div className="actions" style={{justifyContent:'space-between'}}>
   <button className="secondary" aria-label="Previous month" onClick={()=>setMonth(new Date(month.getFullYear(),month.getMonth()-1,1))}>‹</button>
   <h2>{month.toLocaleString(undefined,{month:'long',year:'numeric'})}</h2>
   <button className="secondary" aria-label="Next month" onClick={()=>setMonth(new Date(month.getFullYear(),month.getMonth()+1,1))}>›</button>
  </div>
  <div className="task-calendar">{days.map(day=><strong key={day} className="task-calendar-heading">{day}</strong>)}
   {cells.map((date,index)=>{const key=date?`${month.getFullYear()}-${month.getMonth()}-${date}`:'';return <div className={`task-calendar-day ${date?'':'task-calendar-empty'}`} key={index}>
    {date&&<><b>{date}</b>{(byDate[key]??[]).map(t=><button className="task-calendar-entry" key={t.id} onClick={()=>onSelect(t.id)} title={t.title}>{t.title}</button>)}</>}
   </div>;})}
  </div>
 </section>;
}
