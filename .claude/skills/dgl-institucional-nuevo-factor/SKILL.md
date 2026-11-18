---
name: dgl-institucional-nuevo-factor
description: Amplía y mantiene la Matriz de Riesgo Institucional de SUILAFT DGL (DGL-MRIN-001) — una sola matriz para toda DGL Constructora basada en escenarios R01–R16 (P×I, controles D/O/T, riesgo residual), versionada con borrador/aprobación. Úsala cuando el usuario entregue una nueva versión de la matriz institucional, pida "agregar un escenario/factor/riesgo institucional", cambiar pesos de dimensiones, escalas o umbrales, o describa un riesgo de la organización (productos inmobiliarios, canales de venta, zonas de proyectos, formas de pago) en vez de un sujeto individual. Para la matriz por sujeto usa matriz-nuevo-tipo.
---

# Matriz de riesgo institucional en DGL (DGL-MRIN-001)

## Estado (5-nov-2026)

**Ya existe y está en producción.** Se accede desde Prevención de riesgos → Matriz de riesgo → «Matriz institucional».

| Pieza | Archivo |
|---|---|
| Metodología: dimensiones y pesos, escalas, respuesta por nivel, 16 escenarios | `config/matriz_institucional.php` |
| Motor: cálculo, consolidación, indicadores reales, versiones y aprobación | `models/MatrizInstitucional.php` |
| Controlador (POST `crear`, `cabecera`, `escenario`, `aprobar`; `accion=imprimir`) | `institucional.php` |
| Vistas: lista, versión con mapa de calor, ficha de escenario con cálculo en vivo | `views/institucional.php` |
| Informe PDF de 8 páginas (extiende `PdfMatriz`) | `models/PdfInstitucional.php` |
| Tablas `inst_versiones` e `inst_escenarios` | `sql/08_matriz_institucional.sql` |

## Modelo (no lo cambies sin un documento nuevo)

- **Riesgo inherente:** RI = P × I.
  - P va de 1 a 3.
  - I es el **máximo** de los impactos aplicables (`fin`, `leg`, `ope`, `rep`), cada uno de 1 a 3.
- **Efectividad del control:** D, O y T valen de 0 a 2.
  - Si alguno vale 0, E = 0.
  - Si Q = D + O + T vale 3 o 4, E = 25 %.
  - Si Q vale 5 o 6, E = 50 %.
- **Riesgo residual:** Pr = max(1, P × (1 − E)) y RR = Pr × I.
- **Niveles:** Bajo ≤ 2,00 < Medio ≤ 4,00 < Alto.
- **Consolidación:**
  - GI y GR se ponderan por dimensión: contrapartes 35, proyectos 25, transacciones 20, canales 10 y jurisdicciones 10.
  - Los escenarios N/A se excluyen y se renormalizan los pesos.
  - Un escenario ND (sin calificar o sin evidencia) vuelve el resultado **no definitivo**.
  - **Prioridad** = máx(nivel de GR, nivel del mayor RR).
- **Versiones:**
  - El ciclo es `borrador` → `aprobada`. Solo puede haber un borrador a la vez, y se crea copiando la última versión aprobada.
  - Una versión aprobada queda bloqueada: el UPDATE lleva `WHERE estado='borrador'` y el controlador responde 409.
  - Al aprobar se guarda un `snapshot` de los indicadores reales.
- **Datos reales:**
  - Para R01–R05 (`perfil`), los indicadores de las evaluaciones individuales (DGL-MRI) **orientan** P, pero no la fijan.
  - `vinculos` muestra los factores MRI citados en cada escenario.
  - `todas_mri` (R12) agrega todos los tipos.

Casos de control que el motor debe reproducir siempre:
- la tabla de la pág. 11;
- GI 6,580 y GR 4,053 (pág. 14);
- el ejemplo de R09.

## Agregar o editar un escenario

```php
'R17' => array('nombre'=>'…','dim'=>'proyectos',            // clave de 'dimensiones'
 'evento'=>'…', 'vinculo'=>'…', 'control'=>'C17: …', 'frecuencia'=>'…',
 'impactos'=>array('fin','rep'),                            // impactos aplicables (I = máximo)
 'vinculos'=>array('proveedor'=>array(2,5)),               // opcional: tipo KYC => factores MRI
 // 'perfil'=>'cliente-pn'                                  // solo si P se orienta con datos de un tipo
),
```

- Texto, códigos y dimensión salen **del documento fuente**. Si faltan, se piden; no se inventan.
- La clave `R..` es estable: **nunca se renombra ni se reutiliza**, porque `inst_escenarios` guarda `escenario`.
- Un escenario nuevo aparece solo en **borradores nuevos**. Las versiones aprobadas no se tocan. El borrador abierto lo muestra como ND hasta que se califique.
- Los pesos de las dimensiones suman 100. Si cambian, sube la `version` de la metodología y muestra la suma en la respuesta.
- Al crear un tipo KYC nuevo con su matriz individual, añádelo a `vinculos` o `perfil` del escenario que le corresponda. No es automático.
- No hace falta migración: las tablas son genéricas por clave de escenario.

## Verificación (en producción, con datos `PRUEBA`)

- [ ] `php -l` en todo lo tocado. Siguiente migración libre: `09`.
- [ ] Un script temporal que use el motor real reproduce GI 6,580 y GR 4,053 con los valores de la pág. 11. Luego se borra y se comprueba el 404.
- [ ] Un borrador nuevo muestra el escenario. Una versión aprobada rechaza el POST directo con 409.
- [ ] El mapa de calor, la tabla por dimensión y el PDF incluyen el escenario y su plan de tratamiento.
- [ ] Desplegar con `despliegue-ftp-produccion`: la config va al final.
