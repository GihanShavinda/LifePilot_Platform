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
import { ActionCenterPage } from "../features/actions/ActionCenterPage";

import { AppShell } from "../components/layout/AppShell";

export const router = createBrowserRouter([
  { path: "/login", element: <LoginPage /> },
  { path: "/register", element: <RegisterPage /> },
  { path: "/forgot-password", element: <ForgotPasswordPage /> },
  { path: "/reset-password", element: <ResetPasswordPage /> },
  {
    element: <ProtectedRoute />,
    children: [
      {
        element: <AppShell />,
        children: [
          { path: "/", element: <Navigate to="/dashboard" replace /> },
          { path: "/dashboard", element: <DashboardPage /> },
          { path: "/documents", element: <DocumentsPage /> },
          { path: "/tasks", element: <TasksPage /> },
          { path: "/finance", element: <FinancePage /> },
          { path: "/calendar", element: <CalendarPage /> },
          { path: "/notifications", element: <NotificationsPage /> },
          { path: "/search", element: <SearchPage /> },
          { path: "/assistant", element: <AssistantPage /> },
          { path: "/actions", element: <ActionCenterPage /> },
          { path: "/settings", element: <SettingsPage /> },
        ],
      },
    ],
  },
  { path: "*", element: <Navigate to="/dashboard" replace /> },
]);
