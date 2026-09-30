import {api,ensureCsrfCookie} from '../../services/api';
import type {LifeTask,Obligation,Suggestion} from './types';
const path='/api/v1';
async function mutate<T>(verb:'post'|'put'|'delete',url:string,payload?:unknown):Promise<T>{
 await ensureCsrfCookie();
 const r=verb==='delete'?await api.delete(url):await api[verb](url,payload);
 return r.data.data as T;
}
export async function getTasks(view:string='all',extra:Record<string,string>={}):Promise<LifeTask[]>{
 const r=await api.get(`${path}/tasks`,{params:{view,...extra}});return r.data.data.tasks.data;
}
export async function getTask(id:number):Promise<LifeTask>{const r=await api.get(`${path}/tasks/${id}`);return r.data.data.task;}
export async function addTask(payload:Record<string,unknown>):Promise<LifeTask>{return (await mutate<{task:LifeTask}>('post',`${path}/tasks`,payload)).task;}
export async function updateTask(id:number,payload:Record<string,unknown>):Promise<LifeTask>{return (await mutate<{task:LifeTask}>('put',`${path}/tasks/${id}`,payload)).task;}
export async function deleteTask(id:number):Promise<void>{await mutate('delete',`${path}/tasks/${id}`);}
export async function addChecklist(id:number,title:string){return mutate('post',`${path}/tasks/${id}/checklist`,{title});}
export async function toggleChecklist(id:number,item:number,is_completed:boolean){return mutate('put',`${path}/tasks/${id}/checklist/${item}`,{is_completed});}
export async function deleteChecklist(id:number,item:number){return mutate('delete',`${path}/tasks/${id}/checklist/${item}`);}
export async function addDependency(id:number,depends_on_task_id:number){return mutate('post',`${path}/tasks/${id}/dependencies`,{depends_on_task_id});}
export async function removeDependency(id:number,depId:number){return mutate('delete',`${path}/tasks/${id}/dependencies/${depId}`);}
export async function addReminder(id:number,remind_at:string){return mutate('post',`${path}/tasks/${id}/reminders`,{remind_at});}
export async function snoozeReminder(id:number,reminder:number,until:string){return mutate('post',`${path}/tasks/${id}/reminders/${reminder}/snooze`,{until});}
export async function listObligations():Promise<Obligation[]>{const r=await api.get(`${path}/obligations`);return r.data.data.obligations.data;}
export async function addObligation(payload:Record<string,unknown>):Promise<Obligation>{return (await mutate<{obligation:Obligation}>('post',`${path}/obligations`,payload)).obligation;}
export async function approveObligation(id:number){return mutate('post',`${path}/obligations/${id}/approve`);}
export async function dismissObligation(id:number){return mutate('post',`${path}/obligations/${id}/dismiss`);}
export async function getSuggestions(documentId:number):Promise<Suggestion[]>{const r=await api.get(`${path}/documents/${documentId}/obligation-suggestions`);return r.data.data.suggestions;}
export async function approveSuggestion(documentId:number,dedupe_key:string){return mutate('post',`${path}/documents/${documentId}/obligation-suggestions/approve`,{dedupe_key});}

export async function getDueReminders(){const r=await api.get(`${path}/task-reminders`);return r.data.data.reminders as {id:number;task_id:number;sent_at:string;status:string}[];}
