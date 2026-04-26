import React from "react";
import { createRoot } from "react-dom/client";
import DashboardApp from "./dashboard/DashboardApp";

const rootElement = document.getElementById("app");

if (rootElement) {
  createRoot(rootElement).render(
    <React.StrictMode>
      <DashboardApp />
    </React.StrictMode>,
  );
}
