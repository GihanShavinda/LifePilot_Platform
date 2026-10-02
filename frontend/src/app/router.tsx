import { createBrowserRouter, Navigate } from "react-router-dom";

import { ProtectedRoute } from "../features/auth/ProtectedRoute";

import { LoginPage } from "../features/auth/LoginPage";
import { RegisterPage } from "../features/auth/RegisterPage";
import { ForgotPasswordPage } from "../features/auth/ForgotPasswordPage";
import { ResetPasswordPage } from "../features/auth/ResetPasswordPage";

import { DashboardPage } from "../features/dashboard/DashboardPage";
import { SettingsPage } from "../features/settings/SettingsPage";

import { DocumentsPage } from "../features/documents/DocumentsPage";
import { TasksPage } from "../features/tasks/TasksPage";
import { FinancePage } from "../features/finance/FinancePage";

import { CalendarPage } from "../features/calendar/CalendarPage";
import { NotificationsPage } from "../features/calendar/NotificationsPage";

import { SearchPage } from "../features/search/SearchPage";
import { AssistantPage } from "../features/assistant/AssistantPage";

import { AppShell } from "../components/layout/AppShell";

export const router = createBrowserRouter([
  /*
  |--------------------------------------------------------------------------
  | Public authentication routes
  |--------------------------------------------------------------------------
  */

  {
    path: "/login",
    element: <LoginPage />,
  },

  {
    path: "/register",
    element: <RegisterPage />,
  },

  {
    path: "/forgot-password",
    element: <ForgotPasswordPage />,
  },

  {
    path: "/reset-password",
    element: <ResetPasswordPage />,
  },

  /*
  |--------------------------------------------------------------------------
  | Protected application routes
  |--------------------------------------------------------------------------
  |
  | All authenticated pages are rendered inside AppShell.
  |
  | AppShell provides:
  | - Sidebar
  | - Topbar
  | - Main page content through <Outlet />
  |
  */

  {
    element: <ProtectedRoute />,

    children: [
      {
        element: <AppShell />,

        children: [
          /*
          |--------------------------------------------------------------------------
          | Root redirect
          |--------------------------------------------------------------------------
          */

          {
            path: "/",
            element: (
              <Navigate
                to="/dashboard"
                replace
              />
            ),
          },

          /*
          |--------------------------------------------------------------------------
          | Dashboard
          |--------------------------------------------------------------------------
          */

          {
            path: "/dashboard",
            element: <DashboardPage />,
          },

          /*
          |--------------------------------------------------------------------------
          | Documents
          |--------------------------------------------------------------------------
          */

          {
            path: "/documents",
            element: <DocumentsPage />,
          },

          /*
          |--------------------------------------------------------------------------
          | Tasks & obligations
          |--------------------------------------------------------------------------
          */

          {
            path: "/tasks",
            element: <TasksPage />,
          },

          /*
          |--------------------------------------------------------------------------
          | Finance
          |--------------------------------------------------------------------------
          */

          {
            path: "/finance",
            element: <FinancePage />,
          },

          /*
          |--------------------------------------------------------------------------
          | Calendar
          |--------------------------------------------------------------------------
          */

          {
            path: "/calendar",
            element: <CalendarPage />,
          },

          /*
          |--------------------------------------------------------------------------
          | Notifications
          |--------------------------------------------------------------------------
          */

          {
            path: "/notifications",
            element: <NotificationsPage />,
          },

          /*
          |--------------------------------------------------------------------------
          | P7 - Life Action Graph / Search
          |--------------------------------------------------------------------------
          */

          {
            path: "/search",
            element: <SearchPage />,
          },

          /*
          |--------------------------------------------------------------------------
          | P8 - Grounded AI Assistant
          |--------------------------------------------------------------------------
          */

          {
            path: "/assistant",
            element: <AssistantPage />,
          },

          /*
          |--------------------------------------------------------------------------
          | Settings
          |--------------------------------------------------------------------------
          */

          {
            path: "/settings",
            element: <SettingsPage />,
          },
        ],
      },
    ],
  },

  /*
  |--------------------------------------------------------------------------
  | Fallback
  |--------------------------------------------------------------------------
  */

  {
    path: "*",
    element: (
      <Navigate
        to="/dashboard"
        replace
      />
    ),
  },
]);