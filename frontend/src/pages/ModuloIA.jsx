import { useState } from 'react';

/**
 * Inteligencia Artificial: SUILAFT AI (app PHP en /ia-app) dentro del portal.
 * No tiene login propio: valida la sesión del portal (contraseña + 2FA) en cada solicitud.
 */
export default function ModuloIA() {
  const [cargando, setCargando] = useState(true);

  return (
    <div className="ia-marco">
      {cargando && <div className="ia-cargando" role="status">Abriendo el Centro de Inteligencia…</div>}
      <iframe
        src="/ia-app/index.php"
        title="Inteligencia Artificial · SUILAFT AI"
        onLoad={() => setCargando(false)}
        allow="clipboard-write"
      />
    </div>
  );
}
