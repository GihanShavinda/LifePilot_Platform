import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';

import {
  login as loginApi,
  logout as logoutApi,
  me as meApi,
  type LoginPayload,
} from './authApi';

interface User {
  id: number;
  name: string;
  email: string;
  timezone?: string;
  email_verified_at?: string | null;
}

interface AuthContextValue {
  user: User | null;
  loading: boolean;
  authenticated: boolean;

  login: (payload: LoginPayload) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(
  undefined
);

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({
  children,
}: AuthProviderProps) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  /**
   * Retrieve currently authenticated user.
   *
   * A 401 here is normal when the visitor has not logged in yet.
   */
  const refreshUser = async (): Promise<void> => {
    try {
      const response = await meApi();

      /**
       * Backend response:
       *
       * {
       *   success: true,
       *   data: {
       *     user: {...}
       *   }
       * }
       */

      const authenticatedUser =
        response?.data?.user ??
        response?.user ??
        null;

      setUser(authenticatedUser);
    } catch {
      setUser(null);
    }
  };

  /**
   * Login using email/password.
   */
  const login = async (
    payload: LoginPayload
  ): Promise<void> => {
    /**
     * Important:
     * Pass the complete object directly.
     *
     * {
     *   email: "...",
     *   password: "..."
     * }
     */
    const response = await loginApi(payload);

    const authenticatedUser =
      response?.data?.user ??
      response?.user ??
      null;

    if (authenticatedUser) {
      setUser(authenticatedUser);
    } else {
      /**
       * Fallback:
       * ask Laravel for the authenticated session user.
       */
      await refreshUser();
    }
  };

  /**
   * Logout current user.
   */
  const logout = async (): Promise<void> => {
    try {
      await logoutApi();
    } finally {
      setUser(null);
    }
  };

  /**
   * Check existing Laravel session when React starts.
   */
  useEffect(() => {
    const initializeAuth = async () => {
      try {
        await refreshUser();
      } finally {
        setLoading(false);
      }
    };

    void initializeAuth();
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      authenticated: user !== null,
      login,
      logout,
      refreshUser,
    }),
    [user, loading]
  );

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

/**
 * Auth context hook.
 */
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error(
      'useAuth must be used inside AuthProvider'
    );
  }

  return context;
}