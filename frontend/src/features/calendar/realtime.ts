import {api} from '../../services/api';
export type RealtimeEvent={kind:string;data?:{title?:string;notification_id?:number;document_id?:number;task_id?:number}};
export async function listenForLifePilotUpdates(onEvent:(event:RealtimeEvent)=>void):Promise<()=>void>{
 if(!import.meta.env.VITE_REVERB_APP_KEY)return ()=>{};
 try{
  const [{default:Echo},{default:Pusher}]=await Promise.all([import('laravel-echo'),import('pusher-js')]);
  (window as unknown as {Pusher:typeof Pusher}).Pusher=Pusher;
  const me=await api.get('/api/v1/auth/me');const id=me.data.data?.user?.id??me.data.user?.id;if(!id)return ()=>{};
  const echo=new Echo({broadcaster:'reverb',key:import.meta.env.VITE_REVERB_APP_KEY,wsHost:import.meta.env.VITE_REVERB_HOST??'localhost',wsPort:Number(import.meta.env.VITE_REVERB_PORT??8080),wssPort:Number(import.meta.env.VITE_REVERB_PORT??8080),forceTLS:import.meta.env.VITE_REVERB_SCHEME==='https',enabledTransports:['ws','wss'],authorizer:(channel:{name:string})=>({authorize:(socketId:string,callback:(error:boolean,data:any)=>void)=>{api.post('/api/broadcasting/auth',{socket_id:socketId,channel_name:channel.name}).then(r=>callback(false,r.data)).catch(e=>callback(true,e));}})});
  echo.private(`user.${id}`).listen('.lifepilot.updated',onEvent);
  return ()=>echo.disconnect();
 }catch{return ()=>{};}
}
