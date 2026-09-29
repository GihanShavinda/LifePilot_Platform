import {useState} from 'react';
import {useForm} from 'react-hook-form';
import {Link} from 'react-router-dom';
import {api,ensureCsrfCookie} from '../../services/api';

type Form={email:string};
export function ForgotPasswordPage(){
  const {register,handleSubmit}=useForm<Form>();
  const [message,setMessage]=useState('');
  const submit=async(v:Form)=>{await ensureCsrfCookie();await api.post('/api/v1/auth/forgot-password',v);setMessage('If that address exists, a reset link has been sent.');};
  return <main className="auth-shell"><form className="card" onSubmit={handleSubmit(submit)}><h1>Reset password</h1>{message&&<div className="success">{message}</div>}<label>Email<input type="email" {...register('email',{required:true})}/></label><button>Send reset link</button><p><Link to="/login">Back to sign in</Link></p></form></main>;
}
