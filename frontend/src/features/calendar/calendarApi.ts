import {api,ensureCsrfCookie} from '../../services/api';
import type {CalendarEvent,CalendarConflict,LifeNotification} from './types';
const root='/api/v1';
export async function events(from:string,to:string):Promise<CalendarEvent[]>{return (await api.get(`${root}/calendar/events`,{params:{from,to}})).data.data.events;}
export async function addEvent(body:Record<string,unknown>):Promise<CalendarEvent>{await ensureCsrfCookie();return (await api.post(`${root}/calendar/events`,body)).data.data.event;}
export async function editEvent(id:number,body:Record<string,unknown>):Promise<CalendarEvent>{await ensureCsrfCookie();return (await api.put(`${root}/calendar/events/${id}`,body)).data.data.event;}
export async function deleteEvent(id:number){await ensureCsrfCookie();await api.delete(`${root}/calendar/events/${id}`);}
export async function conflicts(body:Record<string,unknown>):Promise<CalendarConflict[]>{return (await api.get(`${root}/calendar/conflicts`,{params:body})).data.data.conflicts;}
export async function fromTask(id:number,timeZone:string,duration_minutes:number,confirm_conflicts=false){await ensureCsrfCookie();return (await api.post(`${root}/calendar/tasks/${id}/event`,{timezone:timeZone,duration_minutes,confirm_conflicts})).data.data.event as CalendarEvent;}
export async function fromDocument(id:number,body:Record<string,unknown>){await ensureCsrfCookie();return (await api.post(`${root}/calendar/documents/${id}/appointment`,body)).data.data.event as CalendarEvent;}
export async function notices(unread=false){return (await api.get(`${root}/notifications`,{params:{unread}})).data.data.notifications.data as LifeNotification[];}
export async function markRead(id:number){await ensureCsrfCookie();await api.put(`${root}/notifications/${id}/read`);}
export interface NotificationSettings {quiet_start:string|null;quiet_end:string|null;timezone:string;browser_enabled:boolean;in_app_enabled:boolean;escalation_enabled:boolean}
export async function getNotificationSettings():Promise<NotificationSettings>{return (await api.get(`${root}/notifications/settings`)).data.data.settings;}
export async function saveNotificationSettings(value:NotificationSettings):Promise<NotificationSettings>{await ensureCsrfCookie();return (await api.put(`${root}/notifications/settings`,value)).data.data.settings;}
export async function googleStatus():Promise<{connection?:{status:string;authorized_at?:string}|null}>{return (await api.get(`${root}/calendar/google`)).data.data;}
export async function googleConnect(){await ensureCsrfCookie();const r=await api.post(`${root}/calendar/google/connect`);window.location.assign(r.data.data.authorization_url as string);}
export async function googleDisconnect(){await ensureCsrfCookie();await api.delete(`${root}/calendar/google`);}
export async function googleExport(id:number){await ensureCsrfCookie();return (await api.post(`${root}/calendar/events/${id}/sync-google`)).data.data;}
