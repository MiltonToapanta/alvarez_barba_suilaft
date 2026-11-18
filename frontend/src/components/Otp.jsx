import { useRef } from 'react';

/** Seis casillas de un dígito: avanza al escribir, retrocede al borrar y reparte un código pegado. */
export default function Otp({ valor, onChange }) {
  const cajas = useRef([]);
  const digitos = valor.padEnd(6, ' ').slice(0, 6).split('');

  const fijar = (arr) => onChange(arr.join('').replace(/\s/g, ''));

  const repartir = (desde, texto) => {
    const nuevos = [...digitos];
    const entrada = texto.replace(/\D/g, '').split('');
    let i = desde;
    while (i < 6 && entrada.length) nuevos[i++] = entrada.shift();
    fijar(nuevos);
    cajas.current[Math.min(i, 5)]?.focus();
  };

  const alEscribir = (i, v) => {
    if (v.length > 1) return repartir(i, v);
    const d = v.replace(/\D/g, '');
    const nuevos = [...digitos];
    nuevos[i] = d || ' ';
    fijar(nuevos);
    if (d && i < 5) cajas.current[i + 1]?.focus();
  };

  const alTeclear = (i, e) => {
    if (e.key === 'Backspace' && digitos[i] === ' ' && i > 0) {
      e.preventDefault();
      const nuevos = [...digitos];
      nuevos[i - 1] = ' ';
      fijar(nuevos);
      cajas.current[i - 1]?.focus();
    } else if (e.key === 'ArrowLeft' && i > 0) cajas.current[i - 1]?.focus();
    else if (e.key === 'ArrowRight' && i < 5) cajas.current[i + 1]?.focus();
  };

  return (
    <div className="au-field">
      <label id="au-otp-label">Código de 6 dígitos</label>
      <div className="au-otp" role="group" aria-labelledby="au-otp-label">
        {digitos.map((d, i) => (
          <input
            key={i}
            ref={(el) => (cajas.current[i] = el)}
            className="au-otp-box"
            type="text"
            inputMode="numeric"
            maxLength={6}
            aria-label={`Dígito ${i + 1} de 6`}
            autoComplete={i === 0 ? 'one-time-code' : 'off'}
            autoFocus={i === 0}
            value={d.trim()}
            onChange={(e) => alEscribir(i, e.target.value)}
            onKeyDown={(e) => alTeclear(i, e)}
            onPaste={(e) => { e.preventDefault(); repartir(i, e.clipboardData.getData('text')); }}
            onFocus={(e) => e.target.select()}
          />
        ))}
      </div>
    </div>
  );
}
