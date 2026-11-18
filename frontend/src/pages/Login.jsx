import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, csrf, mensajeError } from '../api.js';
import { useAuth } from '../App.jsx';
import LayoutAcceso, { Alerta } from '../components/LayoutAcceso.jsx';

export default function Login() {
  const { setEstado } = useAuth();
  const navigate = useNavigate();
  const [usuario, setUsuario] = useState('');
  const [clave, setClave] = useState('');
  const [verClave, setVerClave] = useState(false);
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  const enviar = async (e) => {
    e.preventDefault();
    setError('');
    setEnviando(true);
    try {
      await csrf();
      const { data } = await api.post('/auth/login', { usuario, clave });
      setEstado(data.estado); // 'configurar' o 'codigo': aún NO autenticado
      navigate(data.estado === 'configurar' ? '/login/configurar' : '/login/codigo', { replace: true });
    } catch (err) {
      setError(mensajeError(err, 'Usuario o contraseña incorrectos.'));
      setClave('');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <LayoutAcceso titulo="Acceso">
      <header className="au-header">
        <h1>Bienvenido/a</h1>
        <p className="au-subtitle">Inicia sesión con tu cuenta.</p>
      </header>

      <Alerta>{error}</Alerta>

      <form onSubmit={enviar}>
        <div className="au-field">
          <label htmlFor="usuario">Usuario</label>
          <div className="au-input-wrap">
            <svg className="au-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></svg>
            <input className="au-input" type="text" id="usuario" placeholder="Tu usuario" autoComplete="username"
              required autoFocus value={usuario} onChange={(e) => setUsuario(e.target.value)} />
          </div>
        </div>

        <div className="au-field">
          <label htmlFor="clave">Contraseña</label>
          <div className="au-input-wrap">
            <svg className="au-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>
            <input className="au-input" type={verClave ? 'text' : 'password'} id="clave" placeholder="••••••••••"
              autoComplete="current-password" required value={clave} onChange={(e) => setClave(e.target.value)} />
            <button type="button" className="au-btn-icon toggle-pass" onClick={() => setVerClave((v) => !v)}
              title={verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'} aria-label={verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
            </button>
          </div>
        </div>

        <button type="submit" className="au-btn-primary" disabled={enviando}>
          {enviando ? 'Verificando…' : 'Iniciar sesión'}
        </button>
      </form>
    </LayoutAcceso>
  );
}
