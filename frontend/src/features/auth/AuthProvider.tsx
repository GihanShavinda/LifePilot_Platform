import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

import {
  login as loginApi,
  logout as logoutApi,
  me as meApi,
  register as registerApi,
  type LoginPayload,
  type RegisterPayload,
} from "./authApi";

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
  register: (payload: RegisterPayload) => Promise<void>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

interface AuthProviderProps {
  children: ReactNode;
}

export function AuthProvider({ children }: AuthProviderProps) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  /**
   * Retrieve currently authenticated user.
   *
   * 401 is normal when no authenticated session exists.
   */
  const refreshUser = async (): Promise<void> => {
    try {
      const response = await meApi();

      const authenticatedUser = response?.data?.user ?? response?.user ?? null;

      setUser(authenticatedUser);
    } catch {
      setUser(null);
    }
  };

  /**
   * Register a new LifePilot account.
   *
   * registerApi() already performs:
   *
   * GET /sanctum/csrf-cookie
   * POST /api/v1/auth/register
   */
  const register = async (payload: RegisterPayload): Promise<void> => {
    const response = await registerApi(payload);

    const authenticatedUser = response?.data?.user ?? response?.user ?? null;

    if (authenticatedUser) {
      setUser(authenticatedUser);
    } else {
      /**
       * Fallback in case the backend creates the authenticated
       * session but does not return the user directly.
       */
      await refreshUser();
    }
  };

  /**
   * Login using email and password.
   */
  const login = async (payload: LoginPayload): Promise<void> => {
    const response = await loginApi(payload);

    const authenticatedUser = response?.data?.user ?? response?.user ?? null;

    if (authenticatedUser) {
      setUser(authenticatedUser);
    } else {
      await refreshUser();
    }
  };

  /**
   * Logout current authenticated user.
   */
  const logout = async (): Promise<void> => {
    try {
      await logoutApi();
    } finally {
      setUser(null);
    }
  };

  /**
   * Restore an existing Laravel/Sanctum session
   * when React starts.
   */
  useEffect(() => {
    let active = true;

    const initializeAuth = async () => {
      try {
        if (active) {
          await refreshUser();
        }
      } finally {
        if (active) {
          setLoading(false);
        }
      }
    };

    void initializeAuth();

    return () => {
      active = false;
    };
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      authenticated: user !== null,
      login,
      register,
      logout,
      refreshUser,
    }),
    [user, loading],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

/**
 * Auth context hook.
 */
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used inside AuthProvider");
  }

  return context;
}
