import axios from 'axios';

// Mismo origen que Laravel (Nginx): la sesión viaja en cookie httpOnly y el
// token CSRF en la cookie XSRF-TOKEN que axios reenvía como cabecera.
export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
});

export async function csrf() {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

/** Mensaje legible de un error de la API. */
export function mensajeError(err, porDefecto = 'No se pudo completar la operación.') {
  const d = err?.response?.data;
  if (d?.message && !d?.errors) return d.message;
  if (d?.errors) return Object.values(d.errors).flat()[0];
  if (err?.response?.status === 429) return 'Demasiados intentos. Espera unos minutos.';
  return porDefecto;
}
