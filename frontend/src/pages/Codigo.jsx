import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, mensajeError } from '../api.js';
import { useAuth } from '../App.jsx';
import LayoutAcceso, { Alerta, Chevron, Volver } from '../components/LayoutAcceso.jsx';
import Otp from '../components/Otp.jsx';

export default function Codigo() {
  const { refrescar } = useAuth();
  const navigate = useNavigate();
  const [codigo, setCodigo] = useState('');
  const [recuperacion, setRecuperacion] = useState('');
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  const verificar = async (valor) => {
    setError('');
    setEnviando(true);
    try {
      await api.post('/auth/codigo', { codigo: valor });
      await refrescar();
      navigate('/', { replace: true });
    } catch (err) {
      setError(mensajeError(err, 'Código incorrecto.'));
      setCodigo('');
      setRecuperacion('');
      if (err?.response?.status === 401) refrescar();
    } finally {
      setEnviando(false);
    }
  };

  const volver = async () => {
    await api.post('/auth/logout').catch(() => {});
    refrescar();
  };

  return (
    <LayoutAcceso titulo="Código de verificación">
      <Volver onClick={volver} />
      <header className="au-header">
        <h1>Código de verificación</h1>
        <p className="au-subtitle">Introduce el código de 6 dígitos que genera tu aplicación de autenticación.</p>
      </header>

      <Alerta>{error}</Alerta>

      <form onSubmit={(e) => { e.preventDefault(); verificar(codigo); }}>
        <Otp valor={codigo} onChange={setCodigo} />
        <button type="submit" className="au-btn-primary" disabled={codigo.length !== 6 || enviando}>
          Verificar código
        </button>
      </form>

      <hr className="au-separador" />

      <details className="au-details au-recuperacion">
        <summary>¿Perdiste el móvil? Usa un código de recuperación <Chevron /></summary>
        <div className="au-details-body">
          Cada código sirve una sola vez. Si tampoco los tienes, pide a un administrador que reinicie tu doble autenticación.
          <form onSubmit={(e) => { e.preventDefault(); verificar(recuperacion); }}>
            <input className="au-input" type="text" placeholder="XXXX-XXXX" maxLength={9} autoComplete="off"
              aria-label="Código de recuperación" required value={recuperacion}
              onChange={(e) => setRecuperacion(e.target.value.toUpperCase())} />
            <button type="submit" className="au-btn-outline au-btn-outline--full" disabled={enviando}>
              Entrar con código de recuperación
            </button>
          </form>
        </div>
      </details>
    </LayoutAcceso>
  );
}
