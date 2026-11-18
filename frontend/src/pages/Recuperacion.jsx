import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../App.jsx';
import LayoutAcceso, { Alerta } from '../components/LayoutAcceso.jsx';

/** Se muestra UNA sola vez, justo después de activar el 2FA. En la base solo queda el hash. */
export default function Recuperacion() {
  const { codigosRecuperacion, setCodigosRecuperacion } = useAuth();
  const navigate = useNavigate();
  const [copiado, setCopiado] = useState(false);

  const copiar = async () => {
    await navigator.clipboard?.writeText('SUIAUTO Alvarez Barba - códigos de recuperación\n' + codigosRecuperacion.join('\n'));
    setCopiado(true);
  };

  const entrar = () => {
    setCodigosRecuperacion(null); // se descartan de la memoria
    navigate('/', { replace: true });
  };

  return (
    <LayoutAcceso titulo="Códigos de recuperación">
      <header className="au-header">
        <h1>Códigos de recuperación</h1>
        <p className="au-subtitle">Si pierdes o cambias el móvil, entra con uno de estos códigos. Cada uno sirve una sola vez.</p>
      </header>

      <Alerta tipo="info">Guárdalos en un lugar seguro: <strong>no se volverán a mostrar</strong>.</Alerta>

      <ul className="au-codes">
        {codigosRecuperacion.map((c) => <li key={c}>{c}</li>)}
      </ul>

      <div className="au-actions">
        <button type="button" className="au-btn-outline" onClick={() => window.print()}>Imprimir</button>
        <button type="button" className="au-btn-outline" onClick={copiar}>{copiado ? 'Copiados' : 'Copiar'}</button>
      </div>

      <button type="button" className="au-btn-primary" style={{ marginTop: '1rem' }} onClick={entrar}>
        Ya los guardé, entrar al sistema
      </button>
    </LayoutAcceso>
  );
}
