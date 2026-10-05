import { NavLink, useNavigate } from "react-router-dom";
import { useAuth } from "../../features/auth/AuthProvider";

// The CSS file is handled by the bundler and has no TypeScript declarations.
// @ts-expect-error TS cannot resolve side-effect CSS imports in this setup.
import "./Sidebar.css";

type SidebarProps = {
  collapsed: boolean;
  mobileOpen: boolean;
  onToggle: () => void;
  onCloseMobile: () => void;
};

type NavigationItem = {
  label: string;
  path: string;
  icon: string;
};

const overviewItems: NavigationItem[] = [
  { label: "Dashboard", path: "/dashboard", icon: "▦" },
  { label: "Household", path: "/household", icon: "⌂" },
];

const organizeItems: NavigationItem[] = [
  { label: "Documents", path: "/documents", icon: "▤" },
  { label: "Tasks & Obligations", path: "/tasks", icon: "✓" },
  { label: "Finance", path: "/finance", icon: "$" },
  { label: "Calendar", path: "/calendar", icon: "□" },
];

const intelligenceItems: NavigationItem[] = [
  { label: "Life Search", path: "/search", icon: "⌕" },
  { label: "AI Assistant", path: "/assistant", icon: "✦" },
  { label: "Action Center", path: "/actions", icon: "⚡" },
  { label: "Analytics & Insights", path: "/analytics", icon: "◫" },
  { label: "Evaluation & Reports", path: "/evaluation", icon: "▥" },
];

const activityItems: NavigationItem[] = [
  { label: "Notifications", path: "/notifications", icon: "◉" },
  { label: "Settings", path: "/settings", icon: "⚙" },
];

function SidebarSection({
  title,
  items,
  collapsed,
  onNavigate,
}: {
  title: string;
  items: NavigationItem[];
  collapsed: boolean;
  onNavigate: () => void;
}) {
  return (
    <div className="sidebar-section">
      {!collapsed && (
        <div className="sidebar-section-title">{title}</div>
      )}

      <div className="sidebar-navigation-list">
        {items.map((item) => (
          <NavLink
            key={item.path}
            to={item.path}
            onClick={onNavigate}
            className={({ isActive }) =>
              `sidebar-navigation-item ${isActive ? "active" : ""}`
            }
            title={collapsed ? item.label : undefined}
          >
            <span className="sidebar-navigation-icon">{item.icon}</span>

            {!collapsed && (
              <span className="sidebar-navigation-label">
                {item.label}
              </span>
            )}
          </NavLink>
        ))}
      </div>
    </div>
  );
}

function getInitial(name?: string | null, email?: string | null) {
  const value = name?.trim() || email?.trim() || "U";

  return value.charAt(0).toUpperCase();
}

export function Sidebar({
  collapsed,
  mobileOpen,
  onToggle,
  onCloseMobile,
}: SidebarProps) {
  const navigate = useNavigate();
  const { user, logout } = useAuth();

  const displayName =
    user?.name ||
    user?.email ||
    "LifePilot User";

  async function handleLogout() {
    const confirmed = window.confirm(
      "Are you sure you want to log out of LifePilot?",
    );

    if (!confirmed) {
      return;
    }

    try {
      await logout();

      onCloseMobile();

      navigate("/login", {
        replace: true,
      });
    } catch (error) {
      console.error("Logout failed:", error);

      window.alert(
        "Unable to log out. Please try again.",
      );
    }
  }

  return (
    <>
      {mobileOpen && (
        <button
          type="button"
          className="sidebar-mobile-overlay"
          onClick={onCloseMobile}
          aria-label="Close navigation"
        />
      )}

      <aside
        className={[
          "app-sidebar",
          collapsed ? "collapsed" : "",
          mobileOpen ? "mobile-open" : "",
        ].join(" ")}
      >
        <div className="sidebar-header">
          <button
            type="button"
            className="sidebar-brand"
            onClick={() => navigate("/dashboard")}
          >
            <span className="sidebar-brand-mark">L</span>

            {!collapsed && (
              <div className="sidebar-brand-copy">
                <strong>LifePilot</strong>
                <span>Personal Intelligence</span>
              </div>
            )}
          </button>

          <button
            type="button"
            className="sidebar-collapse-button"
            onClick={onToggle}
            aria-label="Toggle sidebar"
          >
            {collapsed ? "›" : "‹"}
          </button>
        </div>

        <div className="sidebar-scroll">
          <SidebarSection
            title="OVERVIEW"
            items={overviewItems}
            collapsed={collapsed}
            onNavigate={onCloseMobile}
          />

          <SidebarSection
            title="ORGANIZE"
            items={organizeItems}
            collapsed={collapsed}
            onNavigate={onCloseMobile}
          />

          <SidebarSection
            title="INTELLIGENCE"
            items={intelligenceItems}
            collapsed={collapsed}
            onNavigate={onCloseMobile}
          />

          <SidebarSection
            title="ACTIVITY"
            items={activityItems}
            collapsed={collapsed}
            onNavigate={onCloseMobile}
          />
        </div>

        <div className="sidebar-footer">
          <div
            className={`sidebar-profile ${
              collapsed ? "collapsed" : ""
            }`}
          >
            <button
              type="button"
              className="sidebar-profile-main"
              onClick={() => navigate("/settings")}
              title={collapsed ? displayName : undefined}
            >
              <div className="sidebar-profile-avatar">
                {getInitial(user?.name, user?.email)}
              </div>

              {!collapsed && (
                <div className="sidebar-profile-copy">
                  <strong>{displayName}</strong>
                  <span>Account</span>
                </div>
              )}
            </button>

            <button
              type="button"
              className="sidebar-logout-button"
              onClick={handleLogout}
              aria-label="Logout"
              title="Logout"
            >
              <span className="sidebar-logout-icon">↪</span>

              {!collapsed && (
                <span className="sidebar-logout-label">
                  Logout
                </span>
              )}
            </button>
          </div>
        </div>
      </aside>
    </>
  );
}