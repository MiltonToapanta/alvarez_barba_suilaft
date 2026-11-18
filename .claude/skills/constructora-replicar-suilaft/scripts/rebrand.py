#!/usr/bin/env python3
"""
Cambia la marca DGL por la de otra constructora en una COPIA del proyecto SUILAFT DGL.

Uso (desde la raíz de la copia):
    python3 .claude/skills/constructora-replicar-suilaft/scripts/rebrand.py empresa.json            # simulación: solo informa
    python3 .claude/skills/constructora-replicar-suilaft/scripts/rebrand.py empresa.json --aplicar  # escribe los cambios

empresa.json (todos los campos obligatorios):
{
  "nombre_corto": "XYZ Constructora",                 # reemplaza «DGL Constructora»
  "razon_social": "Constructora XYZ S.A.",            # reemplaza «Constructora D.G.L. Cía. Ltda.» y variantes
  "ruc": "1790000000001",                             # reemplaza el RUC de DGL en los textos legales
  "sigla": "XYZ",                                     # «SUILAFT XYZ», prefijo de códigos XYZ-FOR-002, nombres de PDF
  "sigla_puntos": "X.Y.Z.",                           # reemplaza «D.G.L.» suelto en textos
  "logo": "logo_xyz_web.png",                         # archivo de logo que se copiará a la raíz
  "url_base": "suilaft.tech/xyz_suilaft"             # reemplaza «suilaft.tech/dgl_suilaft» (sin https://)
}

Nunca toca vendor/, .claude/, sql/, .vscode/, resu/ ni archivos binarios. No cambia nombres de archivo
(dgl.css y dgl.js se quedan: son internos). Al final lista los «DGL» que quedan para revisarlos a mano.
"""
import json, os, re, sys

EXCLUIR = {'vendor', '.claude', 'sql', '.vscode', 'resu', '.git', 'node_modules'}
EXT = {'.php', '.js', '.css', '.html'}

def reemplazos(c):
    up = lambda s: s.upper()
    return [
        ('Constructora D.G.L. Cía. Ltda.', c['razon_social']),
        ('DGL Constructora Cía. Ltda.', c['razon_social']),
        ('DGL CONSTRUCTORA CÍA. LTDA.', up(c['razon_social'])),
        ('0791737842001', c['ruc']),
        ('DGL CONSTRUCTORA', up(c['nombre_corto'])),
        ('DGL Constructora', c['nombre_corto']),
        ('SUILAFT DGL', 'SUILAFT ' + c['sigla']),
        ('D.G.L.', c['sigla_puntos']),
        ('logo_dgl_web.png', c['logo']),
        ('suilaft.tech/dgl_suilaft', c['url_base']),
    ]

def main():
    if len(sys.argv) < 2: print(__doc__); sys.exit(1)
    cfg = json.load(open(sys.argv[1], encoding='utf-8'))
    faltan = [k for k in ('nombre_corto', 'razon_social', 'ruc', 'sigla', 'sigla_puntos', 'logo', 'url_base') if not cfg.get(k)]
    if faltan: sys.exit('Faltan campos en el JSON: ' + ', '.join(faltan))
    if not re.fullmatch(r'[A-Z0-9]{2,8}', cfg['sigla']): sys.exit('sigla: 2 a 8 letras mayúsculas o números (se usa en códigos de documento).')
    aplicar = '--aplicar' in sys.argv
    pares = reemplazos(cfg); codigo = re.compile(r'\bDGL-'); total = 0; restos = []
    for raiz, dirs, archivos in os.walk('.'):
        dirs[:] = [d for d in dirs if d not in EXCLUIR]
        for a in archivos:
            if os.path.splitext(a)[1] not in EXT: continue
            ruta = os.path.join(raiz, a)
            try: txt = open(ruta, encoding='utf-8').read()
            except UnicodeDecodeError: continue
            nuevo = txt; n = 0
            for viejo, nv in pares:
                k = nuevo.count(viejo); n += k; nuevo = nuevo.replace(viejo, nv)
            nuevo, k = codigo.subn(cfg['sigla'] + '-', nuevo); n += k     # DGL-FOR-002, DGL-MRI-001, DGL-Cliente-PN.pdf…
            for i, linea in enumerate(nuevo.split('\n'), 1):
                if re.search(r'\bDGL\b', linea): restos.append('%s:%d  %s' % (ruta, i, linea.strip()[:110]))
            if n:
                total += n; print('%-48s %3d cambio(s)' % (ruta, n))
                if aplicar: open(ruta, 'w', encoding='utf-8').write(nuevo)
    print('\nTotal: %d reemplazo(s) %s.' % (total, 'APLICADOS' if aplicar else 'en SIMULACIÓN (añada --aplicar para escribir)'))
    if restos:
        print('\nQuedan %d línea(s) con «DGL» para revisar a mano (comentarios, textos sueltos):' % len(restos))
        for r in restos[:60]: print('  ' + r)
    if not os.path.exists(cfg['logo']): print('\nAVISO: copie el logo «%s» a la raíz del proyecto (PNG, fondo claro, ~400 px de ancho).' % cfg['logo'])

if __name__ == '__main__':
    main()
