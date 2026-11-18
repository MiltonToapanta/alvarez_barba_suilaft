import { Link } from 'react-router-dom';
import Icono from '../components/Icono.jsx';

/** Marcador para los módulos que todavía no se migran desde SUIAUTO. */
export default function EnConstruccion({ modulo }) {
  return (
    <section className="hero">
      <span className="badge"><Icono nombre={modulo.icon} /> {modulo.titulo.toUpperCase()}</span>
      <h1>{modulo.titulo}</h1>
      <p>{modulo.desc}</p>
      <p className="vacio">Este módulo está en construcción.</p>
      <Link to="/" className="btn-volver">Volver al inicio</Link>
    </section>
  );
}
