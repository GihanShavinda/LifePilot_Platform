import api,{ensureCsrfCookie} from '../../services/api';
import type {Expense,Subscription,Asset,Dashboard,ExpenseCategory} from './types';
const base='/api/v1/finance';
async function send<T>(method:'post'|'put'|'delete',url:string,data?:unknown):Promise<T>{await ensureCsrfCookie();const res=await api.request({method,url,data});return res.data.data;}
export async function dashboard(month?:string):Promise<Dashboard>{return (await api.get(`${base}/dashboard`,{params:{month}})).data.data;}
export async function expenses(params:Record<string,string>={}):Promise<Expense[]>{return (await api.get(`${base}/expenses`,{params})).data.data.expenses.data;}
export async function createExpense(d:Record<string,unknown>){return send<{expense:Expense}>('post',`${base}/expenses`,d);}
export async function updateExpense(id:number,d:Record<string,unknown>){return send<{expense:Expense}>('put',`${base}/expenses/${id}`,d);}
export async function deleteExpense(id:number){return send('delete',`${base}/expenses/${id}`);}
export async function getCategories():Promise<{categories:ExpenseCategory[];merchants:{id:number;name:string}[]}>{return (await api.get(`${base}/categories`)).data.data;}
export async function saveCategory(name:string,monthly_budget?:number){return send('post',`${base}/categories`,{name,monthly_budget});}
export async function receiptSuggestion(id:number){return (await api.get(`/api/v1/documents/${id}/receipt-expense-suggestion`)).data.data.suggestion as {eligible:boolean;reason?:string;amount?:string;currency?:string;expense_date?:string;merchant_name?:string;document_id?:number;};}
export async function acceptReceipt(id:number){return send('post',`/api/v1/documents/${id}/receipt-expense-suggestion/accept`);}
export async function importCsv(file:File){await ensureCsrfCookie();const data=new FormData();data.append('file',file);const res=await api.post(`${base}/expenses/import`,data,{headers:{'Content-Type':undefined}});return res.data.data as {created:number;skipped_duplicates:number};}
export async function exportCsv(){const res=await api.get(`${base}/expenses.csv`,{responseType:'blob'});const url=URL.createObjectURL(res.data);const a=document.createElement('a');a.href=url;a.download='lifepilot-expenses.csv';a.click();URL.revokeObjectURL(url);}
export async function recurringPreview(id:number){return (await api.get(`${base}/expenses/${id}/recurrence`)).data.data.suggestion as {eligible:boolean;next_date?:string;amount?:string;currency?:string};}
export async function confirmRecurring(id:number){return send('post',`${base}/expenses/${id}/recurrence/confirm`);}
export async function subscriptions():Promise<Subscription[]>{return (await api.get(`${base}/subscriptions`)).data.data.subscriptions.data;}
export async function createSubscription(d:Record<string,unknown>){return send('post',`${base}/subscriptions`,d);}
export async function updateSubscription(id:number,d:Record<string,unknown>){return send('put',`${base}/subscriptions/${id}`,d);}
export async function cancelTracking(id:number){return send('delete',`${base}/subscriptions/${id}`);}
export async function recordPayment(id:number,d:Record<string,unknown>){return send('post',`${base}/subscriptions/${id}/payments`,d);}
export async function subscriptionInsights(){return (await api.get(`${base}/subscription-insights`)).data.data as {upcoming_renewals:Subscription[];price_changes:Subscription[];recurring_charge_candidates:{merchant?:{name:string};occurrence_count:number}[]};}
export async function assets():Promise<{assets:Asset[];categories:{id:number;name:string}[]}>{const r=await api.get(`${base}/assets`);return {assets:r.data.data.assets.data,categories:r.data.data.categories};}
export async function addAsset(d:Record<string,unknown>){return send('post',`${base}/assets`,d);}
export async function editAsset(id:number,d:Record<string,unknown>){return send('put',`${base}/assets/${id}`,d);}
export async function deleteAsset(id:number){return send('delete',`${base}/assets/${id}`);}
export async function addAssetCategory(name:string){return send('post',`${base}/asset-categories`,{name});}
export async function addWarranty(id:number,d:Record<string,unknown>){return send('post',`${base}/assets/${id}/warranties`,d);}
export async function addMaintenance(id:number,d:Record<string,unknown>){return send('post',`${base}/assets/${id}/maintenance`,d);}
