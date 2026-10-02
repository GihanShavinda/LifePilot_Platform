import { useNavigate } from "react-router-dom";
// The CSS file is handled by the bundler and has no TypeScript declarations.
// @ts-expect-error TS cannot resolve side-effect CSS imports in this setup.
import "./Topbar.css";

type TopbarProps = {
  onMenuClick: () => void;
};

export function Topbar({
  onMenuClick,
}: TopbarProps) {
  const navigate = useNavigate();

  return (
    <header className="app-topbar">
      <div className="topbar-left">
        <button
          type="button"
          className="topbar-mobile-menu"
          onClick={onMenuClick}
          aria-label="Open navigation"
        >
          ☰
        </button>

        <div className="topbar-search">
          <span className="topbar-search-icon">
            ⌕
          </span>

          <input
            type="text"
            placeholder="Search your LifePilot..."
            onKeyDown={(event) => {
              if (
                event.key === "Enter" &&
                event.currentTarget.value.trim()
              ) {
                navigate(
                  `/search?q=${encodeURIComponent(
                    event.currentTarget.value.trim()
                  )}`
                );
              }
            }}
          />

          <span className="topbar-search-shortcut">
            Enter
          </span>
        </div>
      </div>

      <div className="topbar-actions">
        <button
          type="button"
          className="topbar-quick-add"
          onClick={() => navigate("/tasks")}
        >
          <span>＋</span>
          Quick add
        </button>

        <button
          type="button"
          className="topbar-icon-button"
          onClick={() => navigate("/notifications")}
          title="Notifications"
        >
          ◉
          <span className="topbar-notification-dot" />
        </button>

        <button
          type="button"
          className="topbar-profile"
        >
          <span className="topbar-profile-avatar">
            G
          </span>

          <span className="topbar-profile-copy">
            <strong>Gihan</strong>
            <small>Owner</small>
          </span>
        </button>
      </div>
    </header>
  );
}