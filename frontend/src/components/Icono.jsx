/** Íconos de línea de SUIAUTO (app/core/icons.php), como componente React. */
const P = {
  home: <><path d="M3 11.5 12 4l9 7.5" /><path d="M5 10v9a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-9" /></>,
  users: <><circle cx="9" cy="8" r="3" /><path d="M2 20c0-3.3 3.1-6 7-6s7 2.7 7 6" /><circle cx="17" cy="9" r="2.5" /><path d="M15.5 14.2c2.7.4 4.5 2.4 4.5 5.8" /></>,
  id: <><rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="8.5" cy="11" r="2" /><path d="M5.5 16c0-1.7 1.3-3 3-3s3 1.3 3 3" /><path d="M14 9.5h5M14 13h5M14 16h3" /></>,
  car: <><path d="M4 16V12l2-4h12l2 4v4" /><path d="M4 16h16v2a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-1H7v1a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-2Z" /><circle cx="7.5" cy="16" r="1.5" /><circle cx="16.5" cy="16" r="1.5" /></>,
  check: <><rect x="4" y="3" width="16" height="18" rx="2" /><path d="M8 9h8M8 13h5" /><path d="m8.5 17 2 2 4-4" /></>,
  chart: <path d="M4 20V10M10 20V4M16 20v-7M20 20H4" />,
  bank: <><path d="M3 10 12 4l9 6" /><path d="M5 10v9M9 10v9M15 10v9M19 10v9" /><path d="M3 19h18" /></>,
  bell: <><path d="M6 10a6 6 0 0 1 12 0c0 4 1.5 5.5 1.5 5.5H4.5S6 14 6 10Z" /><path d="M9.5 18a2.5 2.5 0 0 0 5 0" /></>,
  brain: <><path d="M9 4a3 3 0 0 0-3 3 3 3 0 0 0-1 5.8A3 3 0 0 0 8 17h1" /><path d="M15 4a3 3 0 0 1 3 3 3 3 0 0 1 1 5.8A3 3 0 0 1 16 17h-1" /><path d="M9 4v13M15 4v13" /></>,
  report: <><path d="M7 3h7l4 4v14a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" /><path d="M14 3v4h4" /><path d="M9 13h6M9 16h6M9 10h2" /></>,
  audit: <><rect x="5" y="3" width="14" height="18" rx="2" /><path d="M9 3v3h6V3" /><path d="M9 12h6M9 16h4" /><circle cx="9" cy="9" r="0.8" fill="currentColor" stroke="none" /></>,
  catalog: <path d="M4 6a2 2 0 0 1 2-2h3l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6Z" />,
  menu: <path d="M4 6h16M4 12h16M4 18h16" />,
  logout: <><path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3" /><path d="M14 16l4-4-4-4" /><path d="M18 12H9" /></>,
  shield: <path d="M12 3l7 3v5c0 4.5-3 7.7-7 9-4-1.3-7-4.5-7-9V6l7-3Z" />,
  map: <><path d="M4 6.5c0-1 .8-1.8 1.8-1.9l3.2-.4c.8-.1 1.6.1 2.3.5l1.4.8c.7.4 1.5.6 2.3.5l2.7-.3c1-.1 1.9.6 1.9 1.6v10.2c0 1-.8 1.8-1.8 1.9l-2.8.3c-.8.1-1.6-.1-2.3-.5l-1.4-.8c-.7-.4-1.5-.6-2.3-.5l-3.2.4C4.9 18.5 4 17.8 4 16.8V6.5Z" /><path d="M9 5.1v13.3M15 5.6v13.3" /></>,
  folder: <path d="M3 7a2 2 0 0 1 2-2h4.5l1.8 2H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" />,
};

export default function Icono({ nombre }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {P[nombre] ?? P.catalog}
    </svg>
  );
}
