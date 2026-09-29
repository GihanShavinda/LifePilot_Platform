import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from './AuthProvider';

type LoginForm = {
  email: string;
  password: string;
};

export function LoginPage() {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginForm>({
    defaultValues: {
      email: '',
      password: '',
    },
  });

  const { login } = useAuth();
  const navigate = useNavigate();

  const [serverError, setServerError] = useState('');

  const submit = async (values: LoginForm) => {
    setServerError('');

    console.log('LOGIN FORM VALUES:', {
      email: values.email,
      password: values.password ? '[provided]' : '[missing]',
    });

    try {
      await login({
        email: values.email.trim(),
        password: values.password,
      });

      console.log('LOGIN SUCCESS');

      navigate('/');
    } catch (error: any) {
      console.error('LOGIN ERROR OBJECT:', error);

      console.error(
        'LOGIN STATUS:',
        error?.response?.status
      );

      console.error(
        'LOGIN RESPONSE:',
        error?.response?.data
      );

      console.error(
        'LOGIN VALIDATION DETAILS:',
        error?.response?.data?.error?.details
      );

      const details =
        error?.response?.data?.error?.details;

      let message = 'Login failed.';

      if (details?.email?.[0]) {
        message = details.email[0];
      } else if (details?.password?.[0]) {
        message = details.password[0];
      } else if (
        error?.response?.data?.error?.message
      ) {
        message =
          error.response.data.error.message;
      } else if (
        error?.response?.data?.message
      ) {
        message =
          error.response.data.message;
      } else if (error?.friendlyMessage) {
        message = error.friendlyMessage;
      }

      setServerError(message);
    }
  };

  return (
    <main className="auth-shell">
      <form
        className="card"
        onSubmit={handleSubmit(submit)}
        noValidate
      >
        <h1>LifePilot AI</h1>

        <p>
          Sign in to your personal operations
          workspace.
        </p>

        {serverError && (
          <div
            className="error"
            role="alert"
          >
            {serverError}
          </div>
        )}

        <label htmlFor="email">
          Email
        </label>

        <input
          id="email"
          type="email"
          autoComplete="email"
          placeholder="you@example.com"
          {...register('email', {
            required: 'Email is required.',
            pattern: {
              value:
                /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
              message:
                'Enter a valid email address.',
            },
          })}
        />

        {errors.email && (
          <small className="error">
            {errors.email.message}
          </small>
        )}

        <label htmlFor="password">
          Password
        </label>

        <input
          id="password"
          type="password"
          autoComplete="current-password"
          placeholder="Enter your password"
          {...register('password', {
            required:
              'Password is required.',
          })}
        />

        {errors.password && (
          <small className="error">
            {errors.password.message}
          </small>
        )}

        <button
          type="submit"
          disabled={isSubmitting}
        >
          {isSubmitting
            ? 'Signing in...'
            : 'Sign in'}
        </button>

        <p>
          <Link to="/forgot-password">
            Forgot password?
          </Link>
        </p>

        <p>
          No account?{' '}
          <Link to="/register">
            Create one
          </Link>
        </p>
      </form>
    </main>
  );
}