import { useState } from "react";
import { Outlet } from "react-router-dom";
import { Sidebar } from "./Sidebar";
import { Topbar } from "./Topbar";
// The CSS file is handled by the bundler and has no TypeScript declarations.
// @ts-expect-error TS cannot resolve side-effect CSS imports in this setup.
import "./AppShell.css";

export function AppShell() {
  const [collapsed, setCollapsed] = useState(() => {
    return localStorage.getItem("lifepilot-sidebar-collapsed") === "true";
  });

  const [mobileOpen, setMobileOpen] = useState(false);

  const toggleSidebar = () => {
    setCollapsed((current) => {
      const next = !current;

      localStorage.setItem(
        "lifepilot-sidebar-collapsed",
        String(next)
      );

      return next;
    });
  };

  return (
    <div
      className={`app-shell ${
        collapsed ? "sidebar-collapsed" : ""
      }`}
    >
      <Sidebar
        collapsed={collapsed}
        mobileOpen={mobileOpen}
        onToggle={toggleSidebar}
        onCloseMobile={() => setMobileOpen(false)}
      />

      <div className="app-shell-workspace">
        <Topbar
          onMenuClick={() => setMobileOpen(true)}
        />

        <main className="app-shell-content">
          <Outlet />
        </main>
      </div>
    </div>
  );
}