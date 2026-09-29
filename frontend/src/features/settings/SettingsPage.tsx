import {useEffect,useState} from 'react';
import {useForm} from 'react-hook-form';
import {Link} from 'react-router-dom';
import {api} from '../../services/api';
import type {ApiSuccess,NotificationPreferences,Profile} from '../../types/api';

type ProfileForm={first_name:string;last_name:string;phone:string;date_of_birth:string;locale:string;timezone:string};
export function SettingsPage(){
 const profileForm=useForm<ProfileForm>();
 const prefForm=useForm<NotificationPreferences>();
 const [message,setMessage]=useState('');
 useEffect(()=>{(async()=>{const p=await api.get<ApiSuccess<{profile:Profile;timezone:string}>>('/api/v1/profile');profileForm.reset({...p.data.data.profile,timezone:p.data.data.timezone,first_name:p.data.data.profile.first_name??'',last_name:p.data.data.profile.last_name??'',phone:p.data.data.profile.phone??'',date_of_birth:p.data.data.profile.date_of_birth??''});const n=await api.get<ApiSuccess<{preferences:NotificationPreferences}>>('/api/v1/notification-preferences');prefForm.reset(n.data.data.preferences);})().catch(()=>setMessage('Could not load settings.'));},[]);
 const saveProfile=async(v:ProfileForm)=>{await api.put('/api/v1/profile',v);setMessage('Profile saved.');};
 const savePrefs=async(v:NotificationPreferences)=>{await api.put('/api/v1/notification-preferences',v);setMessage('Notification preferences saved.');};
 return <main className="page"><header><div><h1>Settings</h1><p>Profile, timezone and notification preferences</p></div><Link className="button-link" to="/">Dashboard</Link></header>{message&&<div className="success">{message}</div>}<section className="grid"><form className="card" onSubmit={profileForm.handleSubmit(saveProfile)}><h2>Profile</h2><label>First name<input {...profileForm.register('first_name')}/></label><label>Last name<input {...profileForm.register('last_name')}/></label><label>Phone<input {...profileForm.register('phone')}/></label><label>Date of birth<input type="date" {...profileForm.register('date_of_birth')}/></label><label>Locale<input {...profileForm.register('locale')}/></label><label>Timezone<input {...profileForm.register('timezone',{required:true})}/></label><button>Save profile</button></form><form className="card" onSubmit={prefForm.handleSubmit(savePrefs)}><h2>Notifications</h2><label className="check"><input type="checkbox" {...prefForm.register('email_enabled')}/> Email notifications</label><label className="check"><input type="checkbox" {...prefForm.register('push_enabled')}/> Push notifications</label><label className="check"><input type="checkbox" {...prefForm.register('reminder_enabled')}/> Reminder notifications</label><label className="check"><input type="checkbox" {...prefForm.register('digest_enabled')}/> Digest notifications</label><button>Save preferences</button></form></section></main>;
}
