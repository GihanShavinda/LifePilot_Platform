import {useState} from 'react';
import {useForm} from 'react-hook-form';
import {Link,useSearchParams} from 'react-router-dom';
import {api,ensureCsrfCookie} from '../../services/api';

type Form={email:string;password:string;password_confirmation:string};
export function ResetPasswordPage(){
  const [params]=useSearchParams();
  const {register,handleSubmit}=useForm<Form>({defaultValues:{email:params.get('email')??''}});
  const [message,setMessage]=useState('');const [error,setError]=useState('');
  const submit=async(v:Form)=>{try{setError('');await ensureCsrfCookie();await api.post('/api/v1/auth/reset-password',{...v,token:params.get('token')??''});setMessage('Password reset. You can sign in now.');}catch(e:any){setError(e.friendlyMessage??'Password reset failed');}};
  return <main className="auth-shell"><form className="card" onSubmit={handleSubmit(submit)}><h1>Choose a new password</h1>{message&&<div className="success">{message}</div>}{error&&<div className="error">{error}</div>}<label>Email<input type="email" {...register('email',{required:true})}/></label><label>New password<input type="password" {...register('password',{required:true,minLength:10})}/></label><label>Confirm password<input type="password" {...register('password_confirmation',{required:true})}/></label><button>Reset password</button><p><Link to="/login">Back to sign in</Link></p></form></main>;
}
