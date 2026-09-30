export type TaskStatus = 'pending'|'in_progress'|'completed'|'skipped'|'overdue';
export type TaskPriority = 'low'|'medium'|'high'|'urgent';
export type View = 'today'|'upcoming'|'overdue'|'all'|'calendar'|'kanban';
export interface ChecklistItem { id:number; title:string; is_completed:boolean; }
export interface TaskReminder { id:number; remind_at:string; snoozed_until:string|null; status:string; recommended:boolean; }
export interface TaskActivity { id:number; event:string; details:Record<string,unknown>|null; created_at:string; user?:{id:number;name:string}|null; }
export interface LifeTask {
 id:number;title:string;description:string|null;status:TaskStatus;effective_status:TaskStatus;
 priority:TaskPriority;labels:string[]|null;due_at:string|null;document_id:number|null;obligation_id:number|null;
 completion_evidence:string|null;checklist:ChecklistItem[];reminders:TaskReminder[];activities?:TaskActivity[];
 dependencies?:Pick<LifeTask,'id'|'title'|'status'>[];
 obligation?:{id:number;type:string;amount:string|null;currency:string|null}|null;
}
export interface Obligation {id:number;title:string;description:string|null;type:string;status:string;due_at:string|null;amount:string|null;currency:string|null;document_id:number|null;}
export interface Suggestion {type:string;title:string;due_at:string;amount:number|null;currency:string|null;document_id:number;document_extraction_id:number;dedupe_key:string;source_evidence:{field_id:number;field_name:string;evidence_text:string}[];reminder_days:number[];}
