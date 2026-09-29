import api, { ensureCsrfCookie } from '../../services/api';

export interface LoginPayload {
  email: string;
  password: string;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  timezone: string;
}

export interface ResetPasswordPayload {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

export async function me() {
  const response = await api.get('/api/v1/auth/me');
  return response.data;
}

export async function login(payload: LoginPayload) {
  await ensureCsrfCookie();

  const response = await api.post(
    '/api/v1/auth/login',
    payload
  );

  return response.data;
}

export async function register(payload: RegisterPayload) {
  await ensureCsrfCookie();

  const response = await api.post(
    '/api/v1/auth/register',
    payload
  );

  return response.data;
}

export async function logout() {
  await ensureCsrfCookie();

  const response = await api.post(
    '/api/v1/auth/logout'
  );

  return response.data;
}

export async function forgotPassword(email: string) {
  await ensureCsrfCookie();

  const response = await api.post(
    '/api/v1/auth/forgot-password',
    {
      email,
    }
  );

  return response.data;
}

export async function resetPassword(
  payload: ResetPasswordPayload
) {
  await ensureCsrfCookie();

  const response = await api.post(
    '/api/v1/auth/reset-password',
    payload
  );

  return response.data;
}

export async function resendVerificationEmail() {
  await ensureCsrfCookie();

  const response = await api.post(
    '/api/v1/auth/email/verification-notification'
  );

  return response.data;
}