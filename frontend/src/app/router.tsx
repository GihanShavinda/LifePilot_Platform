import { createBrowserRouter } from "react-router-dom";

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

export const router = createBrowserRouter([
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

  {
    element: <ProtectedRoute />,
    children: [
      {
        path: "/",
        element: <DashboardPage />,
      },
      {
        path: "/settings",
        element: <SettingsPage />,
      },
      {
        path: "/documents",
        element: <DocumentsPage />,
      },
      {
        path: "/tasks",
        element: <TasksPage />,
      },
      {
        path: "/finance",
        element: <FinancePage />,
      },

      // P6 - Scheduling
      {
        path: "/calendar",
        element: <CalendarPage />,
      },

      // P6 - Notifications
      {
        path: "/notifications",
        element: <NotificationsPage />,
      },
    ],
  },
]);