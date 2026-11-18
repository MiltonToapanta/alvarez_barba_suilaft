import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import QRCode from 'qrcode';
import { api, mensajeError } from '../api.js';
import { useAuth } from '../App.jsx';
import LayoutAcceso, { Alerta, Chevron, Volver } from '../components/LayoutAcceso.jsx';
import Otp from '../components/Otp.jsx';

/** Primer acceso: registrar el Authenticator escaneando el QR. */
export default function Configurar() {
  const { setEstado, refrescar, setCodigosRecuperacion } = useAuth();
  const navigate = useNavigate();
  const [qr, setQr] = useState('');
  const [secreto, setSecreto] = useState('');
  const [codigo, setCodigo] = useState('');
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    api.get('/auth/configurar')
      .then(async ({ data }) => {
        setSecreto(data.secreto);
        setQr(await QRCode.toString(data.uri, { type: 'svg', margin: 2, errorCorrectionLevel: 'M' }));
      })
      .catch(() => refrescar());
  }, [refrescar]);

  const enviar = async (e) => {
    e.preventDefault();
    setError('');
    setEnviando(true);
    try {
      const { data } = await api.post('/auth/configurar', { codigo });
      setCodigosRecuperacion(data.codigos_recuperacion); // solo en memoria: se muestran una vez
      setEstado('autenticado');
      navigate('/login/recuperacion', { replace: true });
    } catch (err) {
      setError(mensajeError(err, 'Código incorrecto.'));
      setCodigo('');
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
    <LayoutAcceso titulo="Activar doble autenticación">
      <Volver onClick={volver} />
      <header className="au-header">
        <h1>Activa tu Authenticator</h1>
        <p className="au-subtitle">Desde ahora, además de la contraseña, el sistema te pedirá un código de 6 dígitos que genera tu móvil.</p>
      </header>

      <Alerta>{error}</Alerta>

      <ol className="au-steps">
        <li>Instala <strong>Google Authenticator</strong> o <strong>Microsoft Authenticator</strong> en tu móvil.</li>
        <li>En la aplicación, elige <em>Agregar cuenta</em> y escanea este código.</li>
        <li>Escribe abajo el código de 6 dígitos que aparece.</li>
      </ol>

      <div className="au-qr" role="img" aria-label="Código QR para registrar SUIAUTO en el Authenticator"
        dangerouslySetInnerHTML={{ __html: qr }} />

      <details className="au-details">
        <summary>¿No puedes escanear? Escribe la clave a mano <Chevron /></summary>
        <div className="au-details-body">
          <code className="au-secret">{secreto}</code>
          <span className="au-hint">Tipo de clave: basada en tiempo · Cuenta: SUIAUTO Alvarez Barba</span>
        </div>
      </details>

      <form onSubmit={enviar}>
        <Otp valor={codigo} onChange={setCodigo} />
        <button type="submit" className="au-btn-primary" disabled={codigo.length !== 6 || enviando}>
          Activar y entrar
        </button>
      </form>
    </LayoutAcceso>
  );
}
