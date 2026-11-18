import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { api } from './api.js';
import Login from './pages/Login.jsx';
import Configurar from './pages/Configurar.jsx';
import Codigo from './pages/Codigo.jsx';
import Recuperacion from './pages/Recuperacion.jsx';
import Inicio from './pages/Inicio.jsx';
import EnConstruccion from './pages/EnConstruccion.jsx';
import ModuloIA from './pages/ModuloIA.jsx';
import AppLayout from './components/AppLayout.jsx';
import { modulosVisibles } from './modulos.js';

/**
 * estado: 'cargando' | 'anonimo' | 'configurar' | 'codigo' | 'autenticado'
 * El backend decide el estado; el frontend solo lo refleja (nunca lo "adelanta").
 */
const AuthContext = createContext(null);
export const useAuth = () => useContext(AuthContext);

const RUTA_POR_ESTADO = {
  anonimo: '/login',
  configurar: '/login/configurar',
  codigo: '/login/codigo',
  autenticado: '/',
};

export default function App() {
  const [estado, setEstado] = useState('cargando');
  const [usuario, setUsuario] = useState(null);
  const [codigosRecuperacion, setCodigosRecuperacion] = useState(null);

  const refrescar = useCallback(async () => {
    try {
      const { data } = await api.get('/auth/estado');
      setEstado(data.estado);
      setUsuario(data.usuario ?? null);
    } catch {
      setEstado('anonimo');
      setUsuario(null);
    }
  }, []);

  useEffect(() => { refrescar(); }, [refrescar]);

  if (estado === 'cargando') return null;

  // Cada pantalla solo se ve en su estado; si no, se redirige a la que toca.
  const solo = (permitido, elemento) =>
    estado === permitido ? elemento : <Navigate to={RUTA_POR_ESTADO[estado]} replace />;

  return (
    <AuthContext.Provider value={{ estado, setEstado, usuario, setUsuario, refrescar, codigosRecuperacion, setCodigosRecuperacion }}>
      <Routes>
        <Route path="/login" element={solo('anonimo', <Login />)} />
        <Route path="/login/configurar" element={solo('configurar', <Configurar />)} />
        <Route path="/login/codigo" element={solo('codigo', <Codigo />)} />
        <Route
          path="/login/recuperacion"
          element={estado === 'autenticado' && codigosRecuperacion ? <Recuperacion /> : <Navigate to={RUTA_POR_ESTADO[estado]} replace />}
        />
        <Route path="/" element={solo('autenticado', <AppLayout />)}>
          <Route index element={<Inicio />} />
          {modulosVisibles(usuario).map((m) => (
            <Route key={m.ruta} path={m.ruta} element={m.ruta === 'ia' ? <ModuloIA /> : <EnConstruccion modulo={m} />} />
          ))}
        </Route>
        <Route path="*" element={<Navigate to={RUTA_POR_ESTADO[estado]} replace />} />
      </Routes>
    </AuthContext.Provider>
  );
}
